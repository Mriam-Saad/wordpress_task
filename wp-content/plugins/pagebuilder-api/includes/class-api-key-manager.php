<?php
if (!defined('ABSPATH')) exit;

class PB_API_Key_Manager
{

    public static function register_post_type() {
        register_post_type('pb_api_key', [
            'labels' => [
                'name'          => 'API Keys',
                'singular_name' => 'API Key',
                'add_new'       => 'Add New',
                'add_new_item'  => 'Add New API Key',
                'edit_item'     => 'Edit API Key',
                'new_item'      => 'New API Key',
                'view_item'     => 'View API Key',
                'search_items'  => 'Search API Keys',
                'not_found'     => 'No API Keys found',
                'menu_name'     => 'API Keys'
            ],
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => true,
            'menu_position' => 25,
            'menu_icon'     => 'dashicons-admin-network',
            'supports'      => ['title'],
            'capability_type' => 'post',
        ]);
    }



    public static function generate_key_pair() {
        $api_key = wp_generate_password(24, false);
        $secret  = wp_generate_password(48, false);

        return ['api_key' => $api_key, 'secret' => $secret];
    }




    public static function auto_generate_keys($post_id, $post, $update) {
        // Only run on initial creation, not updates
        if ($update) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

        $pair = self::generate_key_pair();

        // Update post title with API key (if empty)
        if (empty($post->post_title) || $post->post_title === 'Auto Draft') {
            wp_update_post([
                'ID'         => $post_id,
                'post_title' => $pair['api_key'],
            ]);
        }

        // Save secret key and status
        update_post_meta($post_id, '_secret', $pair['secret']);
        update_post_meta($post_id, '_revoked', false);
    }


    public static function add_meta_boxes() {
        add_meta_box(
            'pb_api_key_details',
            'API Key Details',
            ['PB_API_Key_Manager', 'render_meta_box'],
            'pb_api_key',
            'normal',
            'high'
        );
    }




    public static function render_meta_box($post) {
        $api_key = $post->post_title;
        $secret  = get_post_meta($post->ID, '_secret', true);
        $revoked = get_post_meta($post->ID, '_revoked', true);

        echo '<p><strong>API Key:</strong><br><input type="text" readonly value="' . esc_attr($api_key) . '" style="width:100%;"></p>';
        echo '<p><strong>Secret Key:</strong><br><input type="text" readonly value="' . esc_attr($secret) . '" style="width:100%;"></p>';
        echo '<p><strong>Status:</strong> ' . ($revoked ? '<span style="color:red;">Revoked</span>' : '<span style="color:green;">Active</span>') . '</p>';
    }



    public static function create_key($name = '', $expires_at = null, $permissions = 'create_pages')
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pb_api_keys';

        $key_id = bin2hex(random_bytes(16)); // 32 chars
        $secret = bin2hex(random_bytes(32));  // 64 chars
        $secret_hash = password_hash($secret, PASSWORD_DEFAULT);

        $wpdb->insert($table, [
            'key_id' => $key_id,
            'secret_hash' => $secret_hash,
            'name' => $name,
            'status' => 'active',
            'permissions' => maybe_serialize($permissions),
            'created_at' => current_time('mysql'),
            'expires_at' => $expires_at ? date('Y-m-d H:i:s', strtotime($expires_at)) : null
        ], ['%s', '%s', '%s', '%s', '%s', '%s', '%s']);

        return ['key_id' => $key_id, 'secret' => $secret];
    }

    public static function revoke_key($key_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pb_api_keys';
        return $wpdb->update($table, ['status' => 'revoked'], ['key_id' => $key_id], ['%s'], ['%s']);
    }

    public static function regenerate_secret($key_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pb_api_keys';
        $new_secret = bin2hex(random_bytes(32));
        $hash = password_hash($new_secret, PASSWORD_DEFAULT);
        $wpdb->update($table, ['secret_hash' => $hash], ['key_id' => $key_id], ['%s'], ['%s']);
        return $new_secret;
    }



    public static function validate_key($api_key, $secret)
    {
        global $wpdb;

        // First, try the custom table method
        $table = $wpdb->prefix . 'pb_api_keys';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));

        if ($table_exists) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE key_id = %s LIMIT 1", $api_key), ARRAY_A);
            if (!$row) return false;
            if ($row['status'] !== 'active') return false;
            if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time()) return false;
            if (empty($row['secret_hash'])) return false;

            if (!password_verify($secret, $row['secret_hash'])) return false;


            $wpdb->update(
                $table,
                [
                    'last_used' => current_time('mysql'),
                    'request_count' => $row['request_count'] + 1
                ],
                ['key_id' => $api_key],
                ['%s', '%d'],
                ['%s']
            );

            return true;
        }

        // If no table, fall back to post/meta method
        $post = get_page_by_title($api_key, OBJECT, 'pb_api_key');
        if (!$post) return false;

        $stored_secret = get_post_meta($post->ID, '_secret', true);
        $revoked = get_post_meta($post->ID, '_revoked', true);
        $expires = get_post_meta($post->ID, '_expires', true);

        if (empty($stored_secret)) return false;
        if ($revoked) return false;
        if ($expires && strtotime($expires) < time()) return false;

        return hash_equals($stored_secret, $secret);
    }












    public static function get_key_row($key_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pb_api_keys';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE key_id = %s LIMIT 1", $key_id), ARRAY_A);
    }

    // Admin listing helper
    public static function list_keys($limit = 100)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'pb_api_keys';
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit), ARRAY_A);
    }
}

add_action('init', ['PB_API_Key_Manager', 'register_post_type']);
add_action('save_post_pb_api_key', ['PB_API_Key_Manager', 'auto_generate_keys'], 10, 3);
add_action('add_meta_boxes_pb_api_key', ['PB_API_Key_Manager', 'add_meta_boxes']);

