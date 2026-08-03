<?php
/**
 * Plugin Name: CSLM Dispatch
 * Description: Carrier, driver, load, and invoice operations for CSLM LLC.
 * Version: 0.2.0
 * Author: CSLM LLC
 * Requires at least: 6.4
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) { exit; }

define('CSLM_DISPATCH_VERSION', '0.2.0');
define('CSLM_DISPATCH_FILE', __FILE__);

final class CSLM_Dispatch {
    private const TABLES = ['carriers', 'drivers', 'loads', 'invoices', 'applications'];

    public static function boot(): void {
        register_activation_hook(CSLM_DISPATCH_FILE, [self::class, 'activate']);
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
        add_action('rest_api_init', [self::class, 'routes']);
        add_action('admin_init', [self::class, 'maybe_upgrade']);
        add_action('admin_post_nopriv_cslm_driver_application', [self::class, 'submit_application']);
        add_action('admin_post_cslm_driver_application', [self::class, 'submit_application']);
        add_shortcode('cslm_dispatch_portal', [self::class, 'portal']);
        add_filter('template_include', [self::class, 'portal_template']);
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
        dbDelta("CREATE TABLE {$prefix}applications (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            company_name varchar(190) NOT NULL DEFAULT '',
            contact_name varchar(190) NOT NULL,
            phone varchar(40) NOT NULL,
            email varchar(190) NOT NULL,
            mc_number varchar(40) NOT NULL DEFAULT '',
            equipment varchar(100) NOT NULL DEFAULT '',
            preferred_lanes text NULL,
            availability varchar(190) NOT NULL DEFAULT '',
            notes text NULL,
            consent tinyint(1) NOT NULL DEFAULT 0,
            status varchar(30) NOT NULL DEFAULT 'new',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id), KEY status (status), KEY email (email)
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
        update_option('cslm_dispatch_db_version', CSLM_DISPATCH_VERSION);
        self::ensure_portal_page();
    }

    public static function maybe_upgrade(): void {
        if (get_option('cslm_dispatch_db_version') !== CSLM_DISPATCH_VERSION) { self::activate(); }
    }

    private static function ensure_portal_page(): int {
        $existing = absint(get_option('cslm_dispatch_portal_page'));
        if ($existing && get_post_status($existing)) { return $existing; }
        $page = get_page_by_path('dispatch-services');
        $id = $page ? (int) $page->ID : wp_insert_post([
            'post_title' => 'Dispatch Services',
            'post_name' => 'dispatch-services',
            'post_content' => '[cslm_dispatch_portal]',
            'post_status' => 'publish',
            'post_type' => 'page',
        ]);
        if ($id && !is_wp_error($id)) { update_option('cslm_dispatch_portal_page', $id); return (int) $id; }
        return 0;
    }

    public static function portal_template(string $template): string {
        $page_id = absint(get_option('cslm_dispatch_portal_page'));
        if ($page_id && is_page($page_id)) { return plugin_dir_path(CSLM_DISPATCH_FILE) . 'templates/portal.php'; }
        return $template;
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

    public static function portal(): string {
        $base = plugin_dir_url(CSLM_DISPATCH_FILE) . 'assets/';
        wp_enqueue_style('cslm-dispatch-public', $base . 'public.css', [], CSLM_DISPATCH_VERSION);
        wp_enqueue_script('cslm-dispatch-public', $base . 'public.js', [], CSLM_DISPATCH_VERSION, true);
        $status = isset($_GET['application']) ? sanitize_key(wp_unslash($_GET['application'])) : '';
        ob_start();
        ?>
        <div class="cslm-public">
            <header class="cslm-public-nav"><a class="cslm-public-logo" href="#top">CSLM <span>Dispatch</span></a><nav><a href="#services">Services</a><a href="#process">How it works</a><a href="#agreement">Agreements</a><a href="#apply">Apply</a></nav></header>
            <main id="top">
                <section class="cslm-hero"><div><p class="eyebrow">Florida-based truck dispatching</p><h1>Keep driving.<br><em>We handle the load.</em></h1><p class="lead">CSLM LLC helps independent carriers search for loads, organize broker paperwork, manage trip details, and stay focused on the road.</p><div class="hero-actions"><a class="button primary" href="#apply">Start with a free load</a><a class="button secondary" href="#agreement">Review agreements</a></div><ul><li>Carrier approves every load</li><li>Broker pays carrier directly</li><li>No fee on the first completed load</li></ul></div><aside><div class="route-card"><span>DISPATCH SUPPORT</span><strong>From pickup<br>to delivery.</strong><div class="route-line"><i></i><b></b><i></i></div><small>Load search · paperwork · trip coordination</small></div></aside></section>
                <section class="trust"><p>Built for owner-operators and small carriers</p><div><span>Dry Van</span><span>Box Truck</span><span>Power Only</span><span>Flatbed</span></div></section>
                <section id="services" class="section"><p class="eyebrow">Dispatch services</p><h2>Practical support without unnecessary overhead.</h2><div class="service-grid"><article><b>01</b><h3>Load assistance</h3><p>Search and organize potential loads based on your equipment, availability, lanes, and minimum rate.</p></article><article><b>02</b><h3>Rate support</h3><p>Prepare negotiation points and confirm that you approve the rate and terms before a load is booked.</p></article><article><b>03</b><h3>Broker paperwork</h3><p>Help organize carrier packets, rate confirmations, PODs, detention requests, and delivery documents.</p></article><article><b>04</b><h3>Trip coordination</h3><p>Keep pickup, delivery, broker, driver, and appointment information in one operational dashboard.</p></article></div></section>
                <section id="process" class="section dark"><p class="eyebrow">Simple process</p><h2>Your first load with CSLM.</h2><div class="steps"><article><span>1</span><h3>Submit your information</h3><p>Tell us about your authority, equipment, preferred lanes, and availability.</p></article><article><span>2</span><h3>Review and authorize</h3><p>Review the service agreement and limited dispatch authorization before work begins.</p></article><article><span>3</span><h3>Approve the load</h3><p>You make the final decision. CSLM never commits your truck without your approval.</p></article><article><span>4</span><h3>Complete delivery</h3><p>Your first completed load has no dispatch fee. Promotional pricing applies afterward for a limited time.</p></article></div></section>
                <section class="section pricing"><div><p class="eyebrow">Introductory offer</p><h2>First completed load: <em>free.</em></h2><p>After the free load, CSLM’s temporary promotional dispatch fee is <strong>0.1%</strong>. The standard service fee is <strong>5%</strong>. Final pricing and covered services appear in your signed agreement.</p></div><div class="price-card"><small>FIRST LOAD</small><strong>$0</strong><span>dispatch fee</span><hr><p>Carrier approval required</p><p>Broker pays carrier directly</p><p>No upfront dispatch charge</p></div></section>
                <section id="agreement" class="section agreement"><p class="eyebrow">Review before onboarding</p><h2>Service documents</h2><p class="intro">These previews explain the working relationship. They are review copies, not signed agreements and not legal advice. Your final documents will contain the complete terms presented for acceptance.</p><div class="agreement-grid"><details><summary><span>Carrier–Dispatcher Service Agreement</span><b>Review terms</b></summary><div><h3>Service relationship</h3><p>CSLM acts as the carrier’s dispatch service and authorized representative for agreed administrative tasks. CSLM is not the motor carrier, freight broker, shipper, employer, or insurer.</p><h3>Carrier control</h3><p>The carrier retains control of its equipment, drivers, operations, safety, routes, and final acceptance or rejection of every load.</p><h3>Compensation</h3><p>The first completed load is free. Promotional and standard fees are documented before paid service begins. Brokers and factoring companies pay freight proceeds directly to the carrier.</p><h3>Carrier responsibilities</h3><p>The carrier maintains active authority, insurance, licenses, equipment, safety compliance, and accurate documentation.</p><h3>Cancellation</h3><p>Either party may end dispatch services according to the notice and outstanding-payment provisions in the final agreement.</p></div></details><details><summary><span>Limited Dispatch Authorization</span><b>Review terms</b></summary><div><h3>Limited purpose</h3><p>The carrier may authorize CSLM to communicate with brokers, discuss available loads, complete carrier packets, and receive operational documents on the carrier’s behalf.</p><h3>No automatic load acceptance</h3><p>The authorization does not permit CSLM to accept a load without the carrier’s approval or to receive freight proceeds belonging to the carrier.</p><h3>Revocation</h3><p>The carrier may revoke authorization according to the written notice provisions in the final authorization.</p><h3>Document checklist</h3><p>Typical onboarding documents include W-9, certificate of insurance, active MC/USDOT information, factoring Notice of Assignment when applicable, and equipment details.</p></div></details></div></section>
                <section id="apply" class="section apply"><div><p class="eyebrow">Carrier and driver intake</p><h2>Tell us about your operation.</h2><p>Complete this secure form. Submission does not create a contract, guarantee a load, or authorize CSLM to book freight.</p><div class="contact-card"><span>Questions?</span><button type="button" class="reveal-email" data-user="cslmllc.support" data-domain="gmail.com">Click to show email</button></div></div><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="application-form"><input type="hidden" name="action" value="cslm_driver_application"><?php wp_nonce_field('cslm_driver_application', 'cslm_application_nonce'); ?><label>Carrier/company name<input name="company_name" autocomplete="organization"></label><label>Contact or driver name *<input name="contact_name" autocomplete="name" required></label><div class="form-row"><label>Phone *<input name="phone" type="tel" autocomplete="tel" required></label><label>Email *<input name="email" type="email" autocomplete="email" required></label></div><div class="form-row"><label>MC number<input name="mc_number" inputmode="numeric"></label><label>Equipment *<select name="equipment" required><option value="">Select equipment</option><option>Dry Van</option><option>Box Truck</option><option>Power Only</option><option>Flatbed</option><option>Reefer</option><option>Other</option></select></label></div><label>Preferred lanes<textarea name="preferred_lanes" placeholder="Example: Florida to Southeast"></textarea></label><label>Current location and availability<input name="availability" placeholder="Example: Orlando, available Monday"></label><label>Notes<textarea name="notes" placeholder="Authority age, truck details, questions, or restrictions"></textarea></label><label class="consent"><input type="checkbox" name="consent" value="1" required><span>I confirm that the information is accurate and consent to CSLM LLC contacting me about dispatch services. I understand this submission is not a signed service agreement.</span></label><label class="trap" aria-hidden="true">Leave blank<input name="website" tabindex="-1" autocomplete="off"></label><button type="submit" class="button primary">Submit application</button><?php if ($status === 'received') : ?><p class="form-success" role="status">Thank you. Your information was received.</p><?php elseif ($status === 'error') : ?><p class="form-error" role="alert">The form could not be submitted. Check the required fields and try again.</p><?php endif; ?></form></section>
            </main>
            <footer><div><b>CSLM LLC</b><span>Winter Garden, Florida</span></div><p>Dispatch services for authorized motor carriers. CSLM LLC does not represent itself as a freight broker or motor carrier.</p><p>© <?php echo esc_html(wp_date('Y')); ?> CSLM LLC</p></footer>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public static function submit_application(): void {
        $return = wp_get_referer() ?: home_url('/');
        if (!isset($_POST['cslm_application_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cslm_application_nonce'])), 'cslm_driver_application')) { wp_safe_redirect(add_query_arg('application', 'error', $return) . '#apply'); exit; }
        if (!empty($_POST['website'])) { wp_safe_redirect(add_query_arg('application', 'received', $return) . '#apply'); exit; }
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
        $rate_key = 'cslm_apply_' . md5($ip);
        if (get_transient($rate_key)) { wp_safe_redirect(add_query_arg('application', 'received', $return) . '#apply'); exit; }
        $data = self::sanitize('applications', wp_unslash($_POST));
        if (empty($data['contact_name']) || empty($data['phone']) || empty($data['email']) || empty($data['equipment']) || empty($data['consent'])) { wp_safe_redirect(add_query_arg('application', 'error', $return) . '#apply'); exit; }
        global $wpdb;
        $data['created_at'] = current_time('mysql');
        $wpdb->insert(self::table('applications'), $data);
        set_transient($rate_key, 1, MINUTE_IN_SECONDS);
        wp_safe_redirect(add_query_arg('application', $wpdb->insert_id ? 'received' : 'error', $return) . '#apply');
        exit;
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
        $required = ['carriers' => 'company_name', 'drivers' => 'name', 'loads' => 'load_number', 'invoices' => 'invoice_number', 'applications' => 'contact_name'][$resource];
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
            'applications' => ['company_name','contact_name','phone','email','mc_number','equipment','preferred_lanes','availability','notes','consent','status'],
        ];
        $numeric = ['carrier_id','driver_id','load_id','minimum_rate','default_fee_percent','gross_pay','fee_percent','amount'];
        $boolean = ['first_load_free','consent'];
        $dates = ['available_at','pickup_at','delivery_at','due_at'];
        $clean = [];
        foreach ($definitions[$resource] as $field) {
            if (!array_key_exists($field, $input)) { continue; }
            if (in_array($field, $numeric, true)) { $clean[$field] = (float) $input[$field]; }
            elseif (in_array($field, $boolean, true)) { $clean[$field] = empty($input[$field]) ? 0 : 1; }
            elseif (in_array($field, $dates, true)) { $clean[$field] = $input[$field] ? sanitize_text_field(str_replace('T', ' ', $input[$field])) : null; }
            elseif ($field === 'email') { $clean[$field] = sanitize_email($input[$field]); }
            elseif (in_array($field, ['preferred_lanes','notes'], true)) { $clean[$field] = sanitize_textarea_field($input[$field]); }
            else { $clean[$field] = sanitize_text_field($input[$field]); }
        }
        return $clean;
    }
}

CSLM_Dispatch::boot();
