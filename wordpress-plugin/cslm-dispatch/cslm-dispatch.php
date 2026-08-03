<?php
/**
 * Plugin Name: CSLM Dispatch
 * Description: Carrier, driver, load, and invoice operations for CSLM LLC.
 * Version: 0.1.0
 * Author: CSLM LLC
 * Requires at least: 6.4
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) { exit; }

define('CSLM_DISPATCH_VERSION', '0.1.0');
define('CSLM_DISPATCH_FILE', __FILE__);

final class CSLM_Dispatch {
    private const TABLES = ['carriers', 'drivers', 'loads', 'invoices'];

    public static function boot(): void {
        register_activation_hook(CSLM_DISPATCH_FILE, [self::class, 'activate']);
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
        add_action('rest_api_init', [self::class, 'routes']);
    }

    public static function activate(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'cslm_';

        dbDelta("CREATE TABLE {$prefix}carriers (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            company_name varchar(190) NOT NULL,
            contact_name varchar(190) NOT NULL DEFAULT '',
            phone varchar(40) NOT NULL DEFAULT '',
            email varchar(190) NOT NULL DEFAULT '',
            mc_number varchar(40) NOT NULL DEFAULT '',
            equipment varchar(80) NOT NULL DEFAULT 'Dry Van',
            preferred_lanes text NULL,
            minimum_rate decimal(10,2) NOT NULL DEFAULT 0,
            default_fee_percent decimal(5,2) NOT NULL DEFAULT 0.10,
            first_load_free tinyint(1) NOT NULL DEFAULT 1,
            status varchar(30) NOT NULL DEFAULT 'prospect',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id), KEY status (status), KEY mc_number (mc_number)
        ) $charset;");
        dbDelta("CREATE TABLE {$prefix}drivers (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            carrier_id bigint unsigned NULL,
            name varchar(190) NOT NULL,
            phone varchar(40) NOT NULL DEFAULT '',
            email varchar(190) NOT NULL DEFAULT '',
            equipment_type varchar(80) NOT NULL DEFAULT 'Dry Van',
            equipment_number varchar(80) NOT NULL DEFAULT '',
            current_location varchar(190) NOT NULL DEFAULT '',
            available_at datetime NULL,
            status varchar(30) NOT NULL DEFAULT 'available',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id), KEY carrier_id (carrier_id), KEY status (status)
        ) $charset;");
        dbDelta("CREATE TABLE {$prefix}loads (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            carrier_id bigint unsigned NULL,
            driver_id bigint unsigned NULL,
            load_number varchar(100) NOT NULL,
            broker varchar(190) NOT NULL DEFAULT '',
            origin varchar(190) NOT NULL,
            destination varchar(190) NOT NULL,
            pickup_at datetime NULL,
            delivery_at datetime NULL,
            gross_pay decimal(12,2) NOT NULL DEFAULT 0,
            fee_percent decimal(5,2) NOT NULL DEFAULT 0.10,
            first_load_free tinyint(1) NOT NULL DEFAULT 0,
            status varchar(30) NOT NULL DEFAULT 'planned',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id), UNIQUE KEY load_number (load_number), KEY status (status)
        ) $charset;");
        dbDelta("CREATE TABLE {$prefix}invoices (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            carrier_id bigint unsigned NULL,
            load_id bigint unsigned NULL,
            invoice_number varchar(100) NOT NULL,
            amount decimal(12,2) NOT NULL DEFAULT 0,
            status varchar(30) NOT NULL DEFAULT 'draft',
            due_at date NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id), UNIQUE KEY invoice_number (invoice_number), KEY status (status)
        ) $charset;");

        add_option('cslm_dispatch_settings', [
            'company_name' => 'CSLM LLC',
            'phone' => '4076007604',
            'email' => 'cslmllc.support@gmail.com',
            'address' => '745 Marsh Reed DR, Winter Garden, FL, 34787',
            'promotional_fee' => '0.1',
            'standard_fee' => '5',
            'first_load_free' => '1',
        ]);
    }

    public static function menu(): void {
        add_menu_page('CSLM Dispatch', 'CSLM Dispatch', 'manage_options', 'cslm-dispatch', [self::class, 'page'], 'dashicons-location-alt', 25);
    }

    public static function page(): void {
        echo '<div class="wrap"><div id="cslm-dispatch-app"><p>Loading CSLM Dispatch…</p></div></div>';
    }

    public static function assets(string $hook): void {
        if ($hook !== 'toplevel_page_cslm-dispatch') { return; }
        $base = plugin_dir_url(CSLM_DISPATCH_FILE) . 'assets/';
        wp_enqueue_style('cslm-dispatch', $base . 'admin.css', [], CSLM_DISPATCH_VERSION);
        wp_enqueue_script('cslm-dispatch', $base . 'admin.js', [], CSLM_DISPATCH_VERSION, true);
        wp_localize_script('cslm-dispatch', 'CSLMDispatch', [
            'restUrl' => esc_url_raw(rest_url('cslm-dispatch/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'settings' => get_option('cslm_dispatch_settings', []),
        ]);
    }

    public static function routes(): void {
        foreach (self::TABLES as $resource) {
            register_rest_route('cslm-dispatch/v1', '/' . $resource, [
                ['methods' => WP_REST_Server::READABLE, 'callback' => fn() => self::list($resource), 'permission_callback' => [self::class, 'allowed']],
                ['methods' => WP_REST_Server::CREATABLE, 'callback' => fn(WP_REST_Request $request) => self::create($resource, $request), 'permission_callback' => [self::class, 'allowed']],
            ]);
        }
        register_rest_route('cslm-dispatch/v1', '/loads/(?P<id>\d+)', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [self::class, 'update_load'],
            'permission_callback' => [self::class, 'allowed'],
        ]);
    }

    public static function allowed(): bool { return current_user_can('manage_options'); }

    private static function table(string $resource): string {
        global $wpdb;
        if (!in_array($resource, self::TABLES, true)) { wp_die('Invalid CSLM resource.'); }
        return $wpdb->prefix . 'cslm_' . $resource;
    }

    public static function list(string $resource): WP_REST_Response {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT * FROM ' . self::table($resource) . ' ORDER BY created_at DESC LIMIT 500', ARRAY_A);
        return new WP_REST_Response($rows ?: []);
    }

    public static function create(string $resource, WP_REST_Request $request) {
        global $wpdb;
        $data = self::sanitize($resource, $request->get_json_params() ?: []);
        $required = ['carriers' => 'company_name', 'drivers' => 'name', 'loads' => 'load_number', 'invoices' => 'invoice_number'][$resource];
        if (empty($data[$required])) { return new WP_Error('missing_field', "$required is required", ['status' => 400]); }
        if ($resource === 'loads' && (empty($data['origin']) || empty($data['destination']))) { return new WP_Error('missing_route', 'origin and destination are required', ['status' => 400]); }
        $data['created_at'] = current_time('mysql');
        $ok = $wpdb->insert(self::table($resource), $data);
        if (!$ok) { return new WP_Error('database_error', 'Could not save record.', ['status' => 500]); }
        return new WP_REST_Response(array_merge(['id' => $wpdb->insert_id], $data), 201);
    }

    public static function update_load(WP_REST_Request $request) {
        global $wpdb;
        $allowed = array_intersect_key(self::sanitize('loads', $request->get_json_params() ?: []), array_flip(['status', 'gross_pay', 'fee_percent', 'driver_id']));
        if (!$allowed) { return new WP_Error('no_changes', 'No supported fields supplied.', ['status' => 400]); }
        $wpdb->update(self::table('loads'), $allowed, ['id' => absint($request['id'])]);
        return new WP_REST_Response(['updated' => true]);
    }

    private static function sanitize(string $resource, array $input): array {
        $definitions = [
            'carriers' => ['company_name','contact_name','phone','email','mc_number','equipment','preferred_lanes','minimum_rate','default_fee_percent','first_load_free','status'],
            'drivers' => ['carrier_id','name','phone','email','equipment_type','equipment_number','current_location','available_at','status'],
            'loads' => ['carrier_id','driver_id','load_number','broker','origin','destination','pickup_at','delivery_at','gross_pay','fee_percent','first_load_free','status'],
            'invoices' => ['carrier_id','load_id','invoice_number','amount','status','due_at'],
        ];
        $numeric = ['carrier_id','driver_id','load_id','minimum_rate','default_fee_percent','gross_pay','fee_percent','amount'];
        $boolean = ['first_load_free'];
        $dates = ['available_at','pickup_at','delivery_at','due_at'];
        $clean = [];
        foreach ($definitions[$resource] as $field) {
            if (!array_key_exists($field, $input)) { continue; }
            if (in_array($field, $numeric, true)) { $clean[$field] = (float) $input[$field]; }
            elseif (in_array($field, $boolean, true)) { $clean[$field] = empty($input[$field]) ? 0 : 1; }
            elseif (in_array($field, $dates, true)) { $clean[$field] = $input[$field] ? sanitize_text_field(str_replace('T', ' ', $input[$field])) : null; }
            elseif ($field === 'email') { $clean[$field] = sanitize_email($input[$field]); }
            elseif ($field === 'preferred_lanes') { $clean[$field] = sanitize_textarea_field($input[$field]); }
            else { $clean[$field] = sanitize_text_field($input[$field]); }
        }
        return $clean;
    }
}

CSLM_Dispatch::boot();
