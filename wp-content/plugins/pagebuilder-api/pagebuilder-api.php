<?php
/**
 * Plugin Name: PageBuilder API Task
 * Description: Secure REST endpoint for creating pages + API key management, rate limiting, logging, and webhooks.
 * Version: 1.0.0
 * Author: Mariam Saad
 */

if (!defined('ABSPATH')) exit;

define('PB_API_PATH', plugin_dir_path(__FILE__));
define('PB_API_URL', plugin_dir_url(__FILE__));

require_once PB_API_PATH . 'includes/class-api-key-manager.php';
require_once PB_API_PATH . 'includes/class-api-auth.php';
//require_once PB_API_PATH . 'includes/class-api-logger.php';
require_once PB_API_PATH . 'includes/class-rate.php';
require_once PB_API_PATH . 'includes/class-endpoints.php';
require_once PB_API_PATH . 'admin/class-admin.php';
require_once PB_API_PATH . 'includes/class-logger.php';


// Activation hook: create custom tables and schedule cron (if needed)
register_activation_hook(__FILE__, 'pb_api_activate');
function pb_api_activate()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $keys_table = $wpdb->prefix . 'pb_api_keys';
    $logs_table = $wpdb->prefix . 'pb_api_logs';
    $rate_table = $wpdb->prefix . 'pb_api_rate';

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $sql1 = "CREATE TABLE {$keys_table} (
      id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
      key_id varchar(64) NOT NULL,
      secret_hash varchar(255) NOT NULL,
      name varchar(191) DEFAULT '' NOT NULL,
      status enum('active','revoked') NOT NULL DEFAULT 'active',
      permissions text NULL,
      created_at datetime NOT NULL,
      expires_at datetime NULL,
      last_used datetime NULL,
      request_count bigint(20) unsigned NOT NULL DEFAULT 0,
      PRIMARY KEY(id),
      UNIQUE KEY key_id (key_id)
    ) {$charset_collate};";

    $sql2 = "CREATE TABLE {$logs_table} (
      id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
      key_id varchar(64) NULL,
      route varchar(191) NOT NULL,
      ip varchar(45) NULL,
      user_agent text NULL,
      status varchar(50) NOT NULL,
      message text NULL,
      payload longtext NULL,
      created_at datetime NOT NULL,
      PRIMARY KEY(id)
    ) {$charset_collate};";

    $sql3 = "CREATE TABLE {$rate_table} (
      id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
      key_id varchar(64) NOT NULL,
      period_start datetime NOT NULL,
      count bigint(20) unsigned NOT NULL DEFAULT 0,
      PRIMARY KEY(id),
      KEY key_period (key_id, period_start)
    ) {$charset_collate};";

    dbDelta($sql1);
    dbDelta($sql2);
    dbDelta($sql3);

    // Ensure webhook cron hook exists (no schedule used here automatically)
}

// Deactivation: clear any scheduled events we might have used
register_deactivation_hook(__FILE__, function () {
    // nothing scheduled by default, but clear any pb_api_send_webhook if present
    wp_clear_scheduled_hook('pb_api_send_webhook');
});




// Register REST routes on init of rest api
add_action('rest_api_init', ['PB_API_Endpoints', 'register_routes']);

// Hook webhook send action
add_action('pb_api_send_webhook', function ($post_id, $payload) {
    $url = get_option('pb_webhook_url');
    if (empty($url)) return;
    $body = [
        'page_id' => $post_id,
        'payload' => $payload,
    ];
    wp_remote_post($url, [
        'body' => wp_json_encode($body),
        'headers' => ['Content-Type' => 'application/json'],
        'timeout' => 5,
    ]);
});
