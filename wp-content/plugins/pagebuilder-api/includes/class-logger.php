<?php
if (!defined('ABSPATH')) exit;

class PB_API_Logger {
    public static function log($key_id, $route, $status, $message = '', $payload = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'pb_api_logs';
        $wpdb->insert($table, [
            'key_id' => $key_id,
            'route' => $route,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'status' => $status,
            'message' => $message,
            'payload' => $payload ? maybe_serialize($payload) : null,
            'created_at' => current_time('mysql')
        ], ['%s','%s','%s','%s','%s','%s','%s','%s']);
    }



    public static function log_request(array $data) {
        if (!($request instanceof WP_REST_Request)) {
            return; // fail silently if not a request object
        }

        $route = method_exists($request, 'get_route') ? $request->get_route() : 'unknown';
        $params = $request->get_json_params() ?? [];

        $log = sprintf(
            "[%s] API Key: %s | Route: %s | Data: %s\n",
            current_time('mysql'),
            $api_key ?: 'N/A',
            $route,
            json_encode($params)
        );

        $log_file = WP_CONTENT_DIR . '/pb-api-log.txt';

        // Ensure the directory exists and is writable
        if (is_writable(WP_CONTENT_DIR)) {
            file_put_contents($log_file, $log, FILE_APPEND);
        }
    }

}
