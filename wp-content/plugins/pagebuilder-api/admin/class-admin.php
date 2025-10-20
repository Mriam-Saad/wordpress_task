<?php
/**
 * Admin UI for managing PageBuilder API keys
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('PB_API_Admin')) {

    class PB_API_Admin {

        public static function init() {
            // Add main admin menu for PageBuilder API
            add_action('admin_menu', function () {
                add_menu_page(
                    'PageBuilder API',
                    'PageBuilder API',
                    'manage_options',
                    'pb-api',
                    ['PB_API_Admin', 'render_page'],
                    'dashicons-admin-network',
                    58
                );
            });

            // Handle key actions
            add_action('admin_post_pb_create_key', ['PB_API_Admin', 'handle_create']);
            add_action('admin_post_pb_revoke_key', ['PB_API_Admin', 'handle_revoke']);
            add_action('admin_post_pb_regenerate_key', ['PB_API_Admin', 'handle_regenerate']);
        }

        public static function render_page() {
            if (!current_user_can('manage_options')) {
                wp_die('Forbidden');
            }

            // Display transient-based notices
            $notice = get_transient('pb_api_notice');
            if ($notice) {
                delete_transient('pb_api_notice');
                echo '<div class="notice notice-success"><p>' . $notice . '</p></div>';
            }

            echo '<div class="wrap"><h1>PageBuilder API Keys</h1>';

            // Create Key Form
            echo '<h2>Create New Key</h2>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="pb_create_key">';
            wp_nonce_field('pb_create_key_nonce', 'pb_create_key_nonce_field');
            echo '<table class="form-table">';
            echo '<tr><th>Friendly Name</th><td><input name="name" class="regular-text"></td></tr>';
            echo '<tr><th>Expires (YYYY-mm-dd HH:MM) or empty</th><td><input name="expires" class="regular-text"></td></tr>';
            echo '</table>';
            submit_button('Create Key');
            echo '</form>';

            // List Keys
            echo '<h2>Existing Keys</h2>';
            if (!class_exists('PB_API_Key_Manager')) {
                echo '<p style="color:red;">Error: PB_API_Key_Manager class not found. Please ensure it’s included before loading admin page.</p>';
                echo '</div>';
                return;
            }

            $rows = PB_API_Key_Manager::list_keys(200);
            if (empty($rows)) {
                echo '<p>No keys found.</p>';
            } else {
                echo '<table class="widefat fixed striped">';
                echo '<thead><tr><th>Key ID</th><th>Name</th><th>Status</th><th>Created</th><th>Expires</th><th>Last Used</th><th>Requests</th><th>Actions</th></tr></thead><tbody>';
                foreach ($rows as $r) {
                    echo '<tr>';
                    echo '<td><code>' . esc_html($r['key_id']) . '</code></td>';
                    echo '<td>' . esc_html($r['name']) . '</td>';
                    echo '<td>' . esc_html($r['status']) . '</td>';
                    echo '<td>' . esc_html($r['created_at']) . '</td>';
                    echo '<td>' . esc_html($r['expires_at']) . '</td>';
                    echo '<td>' . esc_html($r['last_used']) . '</td>';
                    echo '<td>' . esc_html($r['request_count']) . '</td>';
                    echo '<td>';

                    // Revoke Button
                    echo '<form style="display:inline" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                    echo '<input type="hidden" name="action" value="pb_revoke_key">';
                    echo '<input type="hidden" name="key_id" value="' . esc_attr($r['key_id']) . '">';
                    wp_nonce_field('pb_revoke_key_nonce', 'pb_revoke_key_nonce_field');
                    submit_button('Revoke', 'small', '', false);
                    echo '</form> ';

                    // Regenerate Button
                    echo '<form style="display:inline" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                    echo '<input type="hidden" name="action" value="pb_regenerate_key">';
                    echo '<input type="hidden" name="key_id" value="' . esc_attr($r['key_id']) . '">';
                    wp_nonce_field('pb_regenerate_key_nonce', 'pb_regenerate_key_nonce_field');
                    submit_button('Regenerate Secret', 'small', '', false);
                    echo '</form>';

                    echo '</td></tr>';
                }
                echo '</tbody></table>';
            }

            echo '</div>';
        }



        public static function handle_create() {
            if (!current_user_can('manage_options')) wp_die('Forbidden');
            check_admin_referer('pb_create_key_nonce', 'pb_create_key_nonce_field');

            $name = sanitize_text_field($_POST['name'] ?? '');
            $expires = sanitize_text_field($_POST['expires'] ?? '');

            if (!class_exists('PB_API_Key_Manager')) {
                require_once plugin_dir_path(__FILE__) . '../includes/class-key-manager.php';
            }

            $pair = PB_API_Key_Manager::create_key($name, $expires);

            $page_id = wp_insert_post([
                'post_type'   => 'page',
                'post_title'  => 'API Test Page - ' . $name,
                'post_content'=> '<p>This page was automatically created after generating a new API key.</p>',
                'post_status' => 'publish',
                'post_author' => 1,
            ]);

            $page_url = $page_id ? get_permalink($page_id) : 'Page not created';

            set_transient(
                'pb_api_notice',
                '<strong>API Key created successfully!</strong><br>' .
                'Key ID: <code>' . esc_html($pair['key_id']) . '</code><br>' .
                'Secret (copy now): <code>' . esc_html($pair['secret']) . '</code><br>' .
                '<em>Save this securely — you will not see it again.</em><br>' .
                'Created Page: <a href="' . esc_url($page_url) . '" target="_blank">' . esc_html($page_url) . '</a>',
                45
            );

            wp_redirect(admin_url('admin.php?page=pb-api'));
            exit;
        }


        public static function handle_revoke() {
            if (!current_user_can('manage_options')) wp_die('Forbidden');
            check_admin_referer('pb_revoke_key_nonce', 'pb_revoke_key_nonce_field');

            $key_id = sanitize_text_field($_POST['key_id'] ?? '');
            PB_API_Key_Manager::revoke_key($key_id);
            set_transient('pb_api_notice', 'Key revoked: ' . $key_id, 10);
            wp_redirect(admin_url('admin.php?page=pb-api'));
            exit;
        }

        public static function handle_regenerate() {
            if (!current_user_can('manage_options')) wp_die('Forbidden');
            check_admin_referer('pb_regenerate_key_nonce', 'pb_regenerate_key_nonce_field');

            $key_id = sanitize_text_field($_POST['key_id'] ?? '');
            $new_secret = PB_API_Key_Manager::regenerate_secret($key_id);
            set_transient('pb_api_notice', 'Secret regenerated for ' . $key_id . ' | New secret (copy now): ' . $new_secret, 30);
            wp_redirect(admin_url('admin.php?page=pb-api'));
            exit;
        }
    }

    // Initialize only once
    add_action('init', ['PB_API_Admin', 'init']);
}
