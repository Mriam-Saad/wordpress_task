<?php
if (!defined('ABSPATH')) exit;

class PB_API_Rate {
    public static function increment_and_check($key_id, $limit = 100) {
        global $wpdb;
        $table = $wpdb->prefix . 'pb_api_rate';
        $period_start = date('Y-m-d H:00:00'); // hourly bucket

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE key_id=%s AND period_start=%s LIMIT 1", $key_id, $period_start), ARRAY_A);

        if (!$row) {
            $wpdb->insert($table, [
                'key_id' => $key_id,
                'period_start' => $period_start,
                'count' => 1
            ], ['%s','%s','%d']);
            return ['allowed' => true, 'remaining' => $limit - 1];
        } else {
            $new_count = $row['count'] + 1;
            if ($new_count > $limit) {
                return ['allowed' => false, 'remaining' => 0];
            } else {
                $wpdb->update($table, ['count' => $new_count], ['id' => $row['id']], ['%d'], ['%d']);
                return ['allowed' => true, 'remaining' => $limit - $new_count];
            }
        }
    }
}
