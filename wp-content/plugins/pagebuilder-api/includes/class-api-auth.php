<?php


if (!defined('ABSPATH')) exit;

class PB_API_Auth
{

    /**
     * Authenticate request.
     * Returns true on success, WP_Error on failure.
     */
    public static function authenticate(WP_REST_Request $request)
    {
        // --- Retrieve credentials ---
        $api_key = $request->get_header('x-api-key');
        $secret  = $request->get_header('x-api-secret');

        // Fallback for environments that lowercase headers
        if (!$api_key && isset($_SERVER['HTTP_X_API_KEY']))  $api_key = $_SERVER['HTTP_X_API_KEY'];
        if (!$secret && isset($_SERVER['HTTP_X_API_SECRET'])) $secret  = $_SERVER['HTTP_X_API_SECRET'];

        // --- Check credentials presence ---
        if (empty($api_key) || empty($secret)) {
            if (class_exists('PB_API_Logger')) {
                PB_API_Logger::log_request([
                    'key_id' => null,
                    'success' => false,
                    'reason' => 'Missing API credentials',
                    'route' => $request->get_route(),
                    'data' => $request->get_json_params(),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            }
            return new WP_Error('unauthorized', 'Missing API credentials.', ['status' => 401]);
        }

        // --- Validate key and secret ---
        $is_valid = PB_API_Key_Manager::validate_key($api_key, $secret);
        if (!$is_valid) {
            if (class_exists('PB_API_Logger')) {
                PB_API_Logger::log_request([
                    'key_id' => $api_key,
                    'success' => false,
                    'reason' => 'Invalid or expired API credentials',
                    'route' => $request->get_route(),
                    'data' => $request->get_json_params(),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            }
            return new WP_Error('unauthorized', 'Invalid or expired API credentials.', ['status' => 401]);
        }

        // --- Apply rate limiting ---
        if (class_exists('PB_API_Rate')) {
            $rate = PB_API_Rate::increment_and_check($api_key, 100); // default: 100/hour
            if (!$rate['allowed']) {
                if (class_exists('PB_API_Logger')) {
                    PB_API_Logger::log_request([
                        'key_id' => $api_key,
                        'success' => false,
                        'reason' => 'Rate limit exceeded',
                        'route' => $request->get_route(),
                        'data' => $request->get_json_params(),
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                }
                return new WP_Error('rate_limited', 'Rate limit exceeded.', ['status' => 429]);
            }
        }

        // --- Success case ---
        if (class_exists('PB_API_Logger')) {
            PB_API_Logger::log_request([
                'key_id' => $api_key,
                'success' => true,
                'reason' => 'Authenticated successfully',
                'route' => $request->get_route(),
                'data' => $request->get_json_params(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        }

        return true;
    }




    // Inside PB_API_Auth or wherever your API keys are created
    public static function generate_credentials() {
        $api_key = bin2hex(random_bytes(16));
        $api_secret = bin2hex(random_bytes(32));

        update_option('pb_api_key', $api_key);
        update_option('pb_api_secret', $api_secret);


        self::create_api_page();

        return [
            'api_key' => $api_key,
            'api_secret' => $api_secret,
        ];
    }

    private static function create_api_page() {
        // Check if it already exists
        $existing = get_page_by_title('API Page');
        if ($existing) {
            return $existing->ID;
        }

        // Create new page
        $page_id = wp_insert_post([
            'post_type'    => 'page',
            'post_title'   => 'API Page',
            'post_content' => '<p>This page was automatically created after generating PageBuilder API credentials.</p>',
            'post_status'  => 'publish',
            'post_author'  => 1,
        ]);

        // Optional logging
        if (!is_wp_error($page_id)) {
            PB_API_Logger::log('system', '/internal/create-api-page', 'success', 'API Page auto-created', ['page_id' => $page_id]);
        }

        return $page_id;
    }

}
