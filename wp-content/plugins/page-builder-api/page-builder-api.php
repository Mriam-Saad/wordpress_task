<?php

/**
 * Plugin Name: PageBuilder API manager
 * Description: Admin interface for viewing PageBuilder API logs, created pages, settings, and documentation.
 * Version: 1.0
 * Author: Mariam Saad
 */

if (!defined('ABSPATH')) exit;

class PB_API_Admin_Test {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
    }

    public static function add_admin_menu() {
        add_submenu_page(
            'tools.php',
            'Page Builder API',
            'Page Builder API',
            'manage_options',
            'pb-api-admin',
            [__CLASS__, 'render_admin_page']
        );
    }

    public static function render_admin_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'logs';
        ?>
        <div class="wrap">
            <h1>Page Builder API</h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=pb-api-admin&tab=logs" class="nav-tab <?php echo $active_tab=='logs'?'nav-tab-active':'' ?>">API Activity Log</a>
                <a href="?page=pb-api-admin&tab=pages" class="nav-tab <?php echo $active_tab=='pages'?'nav-tab-active':'' ?>">Created Pages</a>
                <a href="?page=pb-api-admin&tab=settings" class="nav-tab <?php echo $active_tab=='settings'?'nav-tab-active':'' ?>">Settings</a>
                <a href="?page=pb-api-admin&tab=documentation" class="nav-tab <?php echo $active_tab=='documentation'?'nav-tab-active':'' ?>">API Documentation</a>
            </h2>
            <div class="tab-content">
                <?php
                switch($active_tab) {
                    case 'logs': self::render_logs_tab(); break;
                    case 'pages': self::render_pages_tab(); break;
                    case 'settings': self::render_settings_tab(); break;
                    case 'documentation': self::render_docs_tab(); break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /***** API Logs Tab *****/
    private static function render_logs_tab() {
        global $wpdb;
        $table = $wpdb->prefix . 'pb_api_logs';
        $logs = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 100");
        ?>
        <h2>API Activity Log</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
            <tr>
                <th>Created At</th>
                <th>API Key Used</th>
                <th>Status</th>
                <th>Endpoint</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo isset($log->created_at) ? esc_html($log->created_at) : '-'; ?></td>
                    <td><?php echo isset($log->api_key) ? esc_html($log->api_key) : '****'; ?></td>
                    <td><?php echo isset($log->status) ? esc_html($log->status) : '-'; ?></td>
                    <td><?php echo isset($log->route) ? esc_html($log->route) : '-'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /***** Created Pages Tab *****/
    private static function render_pages_tab() {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'pb_api_logs';
        $keys_table = $wpdb->prefix . 'pb_api_keys';

        $pages = $wpdb->get_results("
            SELECT l.page_name, l.page_url, l.created_at, k.name as api_key_name 
            FROM $logs_table l
            LEFT JOIN $keys_table k ON k.api_key = l.api_key
            WHERE l.page_name IS NOT NULL
            ORDER BY l.created_at DESC
            LIMIT 100
        ");
        ?>
        <h2>Pages Created via API</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
            <tr>
                <th>Page Title</th>
                <th>URL</th>
                <th>Created Date</th>
                <th>Created By (API Key)</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!empty($pages)): ?>
                <?php foreach ($pages as $page): ?>
                    <tr>
                        <td><?php echo isset($page->page_name) ? esc_html($page->page_name) : '-'; ?></td>
                        <td>
                            <?php if(isset($page->page_url)): ?>
                                <a href="<?php echo esc_url($page->page_url); ?>" target="_blank"><?php echo esc_html($page->page_url); ?></a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo isset($page->created_at) ? esc_html($page->created_at) : '-'; ?></td>
                        <td><?php echo isset($page->api_key_name) ? esc_html($page->api_key_name) : '-'; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">No pages created via API yet.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php
    }




    public static function send_webhook($pages, $api_key_name) {
        $options = get_option('pb_api_settings');
        $webhook_url = $options['webhook_url'] ?? '';
        $secret = $options['webhook_secret'] ?? '';

        if (empty($webhook_url)) return;

        $payload = [
            'event' => 'pages_created',
            'timestamp' => gmdate('c'),
            'request_id' => 'req_' . wp_generate_password(10, false),
            'api_key_name' => $api_key_name,
            'total_pages' => count($pages),
            'pages' => $pages
        ];

        $body = wp_json_encode($payload);
        $signature = hash_hmac('sha256', $body, $secret);

        $args = [
            'body' => $body,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Webhook-Signature' => $signature
            ],
            'timeout' => 10,
            'sslverify' => false // optional for localhost (MAMP)
        ];

        // Retry logic
        $success = false;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $response = wp_remote_post($webhook_url, $args);
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
                $success = true;
                break;
            }
            sleep(pow(2, $attempt)); // exponential backoff
        }

        // Log webhook
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'pb_api_logs',
            [
                'api_key' => $api_key_name,
                'route' => 'webhook_delivery',
                'status' => $success ? 'success' : 'failed',
                'response' => is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response),
                'created_at' => current_time('mysql')
            ]
        );
    }




    public static function pb_api_create_page($request) {
        $params = $request->get_json_params();
        $pages = $params['pages'];
        $created_pages = [];

        foreach ($pages as $page) {
            $page_id = wp_insert_post([
                'post_title' => sanitize_text_field($page['title']),
                'post_content' => $page['content'] ?? '',
                'post_status' => 'publish',
                'post_type' => 'page'
            ]);
            if ($page_id) {
                $created_pages[] = [
                    'id' => $page_id,
                    'title' => get_the_title($page_id),
                    'url' => get_permalink($page_id)
                ];
            }
        }

        PB_API_Admin_Test::send_webhook($created_pages, 'Production Server');

        return rest_ensure_response([
            'status' => 'success',
            'created' => $created_pages
        ]);
    }




    /***** Settings Tab *****/
    private static function render_settings_tab() {
        $options = get_option('pb_api_settings', [
            'webhook_url' => '',
            'webhook_secret' => '',
            'rate_limit' => 100,
            'global_enabled' => 1,
            'default_expiration' => 30
        ]);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer('pb_api_settings_save', 'pb_api_nonce');

            $options['webhook_url'] = sanitize_text_field($_POST['webhook_url']);
            $options['webhook_secret'] = sanitize_text_field($_POST['webhook_secret'] ?? '');
            $options['rate_limit'] = intval($_POST['rate_limit']);
            $options['global_enabled'] = isset($_POST['global_enabled']) ? 1 : 0;
            $options['default_expiration'] = intval($_POST['default_expiration']);

            update_option('pb_api_settings', $options);
            echo '<div class="updated"><p>Settings saved.</p></div>';
        }
        ?>
        <h2>API Settings</h2>
        <form method="POST">
            <?php wp_nonce_field('pb_api_settings_save', 'pb_api_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th>Webhook URL</th>
                    <td><input type="url" name="webhook_url" value="<?php echo esc_attr($options['webhook_url']); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th>Webhook Secret</th>
                    <td><input type="text" name="webhook_secret" value="<?php echo esc_attr($options['webhook_secret']); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th>Rate Limit (requests per hour per key)</th>
                    <td><input type="number" name="rate_limit" value="<?php echo esc_attr($options['rate_limit']); ?>"></td>
                </tr>
                <tr>
                    <th>Enable API Access Globally</th>
                    <td><input type="checkbox" name="global_enabled" <?php checked($options['global_enabled'],1); ?>></td>
                </tr>
                <tr>
                    <th>Default Key Expiration (days)</th>
                    <td>
                        <select name="default_expiration">
                            <option value="30" <?php selected($options['default_expiration'],30); ?>>30</option>
                            <option value="60" <?php selected($options['default_expiration'],60); ?>>60</option>
                            <option value="90" <?php selected($options['default_expiration'],90); ?>>90</option>
                            <option value="0" <?php selected($options['default_expiration'],0); ?>>Never</option>
                        </select>
                    </td>
                </tr>
            </table>
            <p><input type="submit" class="button button-primary" value="Save Settings"></p>
        </form>
        <?php
    }

    /***** API Documentation Tab *****/
    private static function render_docs_tab() {
        $api_url = site_url('/wp-json/pb-api/v1/');
        ?>
        <h2>API Documentation</h2>
        <h3>Authentication</h3>
        <p>All API requests require an API key in the header:</p>
        <pre>Authorization: Bearer YOUR_API_KEY</pre>
        <h3>Endpoints</h3>
        <ul>
            <li><code>POST <?php echo esc_html($api_url); ?>create-page</code> — Create a new page</li>
            <li><code>GET <?php echo esc_html($api_url); ?>pages</code> — List pages</li>
            <li><code>GET <?php echo esc_html($api_url); ?>logs</code> — View API logs</li>
        </ul>
        <h3>Example cURL Request</h3>
        <pre>
curl -X POST "http://localhost:8888/task/wp-json/pb-api/v1/create-pages" \
  -H "Content-Type: application/json" \
  -d '{
    "pages": [
      { "title": "Test Page 1", "content": "This is a test." },
      { "title": "Test Page 2", "content": "Another page." }
    ]
  }'

</pre>
        <h3>Response Example</h3>
        <pre>
{
    "success": true,
    "pages_created": 1,
    "page_ids": [123]
}
</pre>

      <h3>Webhook results url here:</h3>
        <pre>

        https://webhook.site/#!/view/34452fdd-2903-49d5-8c56-dd3ff6ad1e91/1c2cd996-522d-4ee7-9a58-35cd1fc49213/1
        </pre>
        <?php
    }

} // End of class


PB_API_Admin_Test::init();

add_action('rest_api_init', function () {
    register_rest_route('pb-api/v1', '/create-pages', [
        'methods' => 'POST',
        'callback' => ['PB_API_Admin_Test', 'pb_api_create_page'],
        'permission_callback' => '__return_true'
    ]);
});
