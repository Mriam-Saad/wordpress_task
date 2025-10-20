<?php
if (!defined('ABSPATH')) exit;

class PB_API_Endpoints
{
    /**
     * Register REST API routes
     */
    public static function register_routes()
    {
        // Main endpoint for creating pages
        register_rest_route('pagebuilder/v1', '/create-pages', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_create_page'],
            'permission_callback' => [__CLASS__, 'perm_cb'],
            'args'                => [
                'title'   => ['required' => true],
                'content' => ['required' => false],
            ],
        ]);

        // Secondary endpoint (if needed for another variant)
        register_rest_route('pagebuilder/v1', '/create-pages-sec', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_create_page_sec'],
            'permission_callback' => [__CLASS__, 'auth_callback'],
            'args'                => [
                'title'   => ['required' => true],
                'content' => ['required' => false],
            ],
        ]);
    }

    public static function perm_cb($request) {
        $auth = PB_API_Auth::authenticate($request);
        return $auth === true ? true : $auth;
    }

    /**
     * Permission (Auth) check
     */
    public static function auth_callback(WP_REST_Request $request)
    {
        // If your auth system is strict, it must return true to pass.
        // If it returns WP_Error, WordPress auto-sends the correct 401/403 JSON.
        $auth = PB_API_Auth::authenticate($request);
        return $auth === true ? true : $auth;
    }

    /**
     * Main route handler
     */
    public static function handle_create_page(WP_REST_Request $request)
    {
        return self::insert_page($request, '/pagebuilder/v1/create-pages');
    }

    /**
     * Secondary route handler
     */
    public static function handle_create_page_sec(WP_REST_Request $request)
    {
        return self::insert_page($request, '/pagebuilder/v1/create-pages-sec');
    }


    private static function insert_page(WP_REST_Request $request, $route)
    {
        $params  = $request->get_json_params();
        $title   = sanitize_text_field($params['title'] ?? 'Untitled');
        $content = wp_kses_post($params['content'] ?? '');

        // --- Create the page ---
        $post_id = wp_insert_post([
            'post_type'   => 'page',
            'post_title'  => $title,
            'post_content'=> $content,
            'post_status' => 'publish',
            'post_author' => get_current_user_id() ?: 1, // fallback to admin ID 1
        ]);

        $api_key = $request->get_header('x-api-key') ?? ($_SERVER['HTTP_X_API_KEY'] ?? null);

        if (is_wp_error($post_id) || !$post_id) {
            PB_API_Logger::log($api_key, $route, 'fail', 'Page creation failed', $params);
            return new WP_Error('page_creation_failed', 'Failed to create page', ['status' => 500]);
        }

        // --- Schedule webhook if configured ---
        $webhook = get_option('pb_webhook_url');
        if (!empty($webhook)) {
            wp_schedule_single_event(time() + 5, 'pb_api_send_webhook', [$post_id, $params]);
        }

        // --- Log success ---
        PB_API_Logger::log($api_key, $route, 'success', 'Page created', [
            'post_id' => $post_id,
            'payload' => $params,
        ]);

        // --- Return REST response ---
        return rest_ensure_response([
            'success'  => true,
            'message'  => 'Page created successfully.',
            'page_id'  => $post_id,
            'page_url' => get_permalink($post_id),
        ]);
    }
}


add_action('rest_api_init', ['PB_API_Endpoints', 'register_routes']);
