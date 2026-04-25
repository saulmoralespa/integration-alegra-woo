<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Integration_Alegra_WC_Plugin
{
    /**
     * Absolute plugin path.
     *
     * @var string
     */
    public string $plugin_path;
    /**
     * Absolute plugin URL.
     *
     * @var string
     */
    public string $plugin_url;
    /**
     * assets plugin.
     *
     * @var string
     */
    public string $assets;
    /**
     * Absolute path to plugin includes dir.
     *
     * @var string
     */
    public string $includes_path;
    /**
     * Absolute path to plugin lib dir
     *
     * @var string
     */
    public string $lib_path;
    /**
     * @var bool
     */
    private bool $bootstrapped = false;

    /**
     * Logger instance.
     *
     * @var WC_Logger|null
     */
    private ?WC_Logger $logger = null;

    public function __construct(
        protected $file,
        public $version
    )
    {
        $this->plugin_path = trailingslashit(plugin_dir_path($this->file));
        $this->plugin_url    = trailingslashit( plugin_dir_url( $this->file ) );
        $this->assets = $this->plugin_url . trailingslashit('assets');
        $this->includes_path = $this->plugin_path . trailingslashit('includes');
        $this->lib_path = $this->plugin_path . trailingslashit('lib');
    }

    public function run_alegra(): void
    {
        try {
            if ($this->bootstrapped) {
                throw new Exception('Integration Alegra Woocommerce can only be called once');
            }
            $this->run();
            $this->bootstrapped = true;
        } catch (Exception $e) {
            if (is_admin() && !defined('DOING_AJAX')) {
                add_action('admin_notices', function () use ($e) {
                    integration_alegra_wc_smp_notices($e->getMessage());
                });
            }
        }
    }

    private function run(): void
    {
        if (!class_exists('\Saulmoralespa\Alegra\Client'))
            require_once($this->lib_path . 'vendor/autoload.php');
        require_once ($this->lib_path . 'plugin-update-checker/plugin-update-checker.php');

        if (!class_exists('WC_Alegra_Integration')) {
            require_once($this->includes_path . 'class-alegra-integration-wc.php');
            add_filter('woocommerce_integrations', array($this, 'add_integration'));
        }

        if (!class_exists('Integration_Alegra_WC')) {
            require_once($this->includes_path . 'class-integration-alegra-wc.php');
        }

        $myUpdateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/saulmoralespa/integration-alegra-woo',
            $this->file
        );

        $myUpdateChecker->setBranch('main');
        $myUpdateChecker->getVcsApi()->enableReleaseAssets();

        add_filter( 'plugin_action_links_' . plugin_basename($this->file), array($this, 'plugin_action_links'));
        add_filter( 'bulk_actions-edit-product', array($this, 'sync_bulk_actions'), 20 );
        add_filter( 'bulk_actions-edit-shop_order', array($this, 'emit_invoices_bulk_actions'), 20 );
        add_filter( 'bulk_actions-woocommerce_page_wc-orders', array($this, 'emit_invoices_bulk_actions'), 20);
        add_filter( 'handle_bulk_actions-edit-product', array($this, 'sync_bulk_action_edit_product'), 10, 3 );
        add_filter( 'handle_bulk_actions-edit-shop_order', array($this, 'emit_invoices_bulk_action_edit_shop_order'), 10, 3 );
        add_action( 'handle_bulk_actions-woocommerce_page_wc-orders', array($this, 'emit_invoices_bulk_action_edit_shop_order'), 20, 3 );
        add_filter( 'manage_shop_order_posts_columns', array( $this, 'alegra_print_invoice' ), 99 );
        add_filter( 'manage_woocommerce_page_wc-orders_columns', array($this, 'alegra_print_invoice'), 99);
        add_filter('woocommerce_default_address_fields', array($this, 'document_woocommerce_fields'));
        add_action('woocommerce_checkout_update_order_meta', array($this, 'document_woocommerce_fields_update_order_meta'));
        add_action( 'woocommerce_checkout_process', array($this, 'very_nit_validation'));
        //add_action( 'woocommerce_checkout_update_order_meta', array($this, 'custom_checkout_fields_update_order_meta'));
        add_action('woocommerce_init', array($this, 'register_additional_checkout_fields'));

        add_action( 'woocommerce_order_status_changed', array( 'Integration_Alegra_WC', 'generate_invoice' ), 10, 3 );
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', array($this, 'content_column_alegra_print_invoice'), 99, 2 );
        add_action( 'admin_enqueue_scripts', array($this, 'enqueue_scripts_admin') );
        add_action( 'wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action( 'wp_ajax_integration_alegra_print_invoice', array($this, 'ajax_integration_alegra_print_invoice'));
        add_action( 'admin_notices', array($this, 'premium_survey_admin_notice'));
        add_action( 'wp_ajax_integration_alegra_send_premium_survey', array($this, 'ajax_integration_alegra_send_premium_survey'));
        add_action( 'wp_ajax_integration_alegra_dismiss_premium_survey_notice', array($this, 'ajax_integration_alegra_dismiss_premium_survey_notice'));
        add_action('woocommerce_admin_order_data_after_order_details',  array($this, 'display_custom_editable_field_on_admin_orders'), 10);
        add_action('woocommerce_process_shop_order_meta', array($this, 'save_order_custom_field_meta'), 10);

        add_action(
            'woocommerce_set_additional_field_value',
            function ( $key, $value, $group, $wc_object ) {

                if ('document/dni' !== $key ) {
                    return;
                }

                $type_document_key = "_wc_$group/document/type_document";
                $dni_key = "_wc_$group/document/dni";
                $type_document = $wc_object->get_meta($type_document_key);
                $dni = $wc_object->get_meta($dni_key);

                if($type_document === 'NIT'){
                    $dv = Integration_Alegra_WC::calculate_dv($dni);
                    $dni = "$dni-$dv";
                }

                $wc_object->update_meta_data($dni_key, $dni, true);
                $wc_object->save();
            },
            10,
            4
        );
    }

    public function add_integration($integrations)
    {
        $integrations[] = 'WC_Alegra_Integration';
        return $integrations;
    }

    public function plugin_action_links($links)
    {
        $survey_url = admin_url('admin.php?page=wc-settings&tab=integration&section=wc_alegra_integration&open_premium_survey=1');
        $links[] = '<a href="' . admin_url('admin.php?page=wc-settings&tab=integration&section=wc_alegra_integration') . '">' . 'Configuraciones' . '</a>';
        $links[] = '<a target="_blank" href="https://shop.saulmoralespa.com/integration-alegra-woocommerce/">' . 'Documentación' . '</a>';
        $links[] = '<a href="' . esc_url($survey_url) . '">' . 'Encuesta Premium' . '</a>';
        return $links;
    }

    /**
     * Check if Alegra integration is properly configured and enabled.
     *
     * @return bool True if integration is enabled and configured, false otherwise.
     */
    private function is_alegra_integration_enabled(): bool
    {
        $settings = get_option('woocommerce_wc_alegra_integration_settings');

        return !empty($settings['user']) &&
               !empty($settings['token']) &&
               $settings['enabled'] === 'yes';
    }

    public function sync_bulk_actions($bulk_actions): array
    {
        if ($this->is_alegra_integration_enabled()) {
            $bulk_actions['integration_alegra_sync'] = 'Sincronizar productos Alegra';
        }
        return $bulk_actions;
    }

    public function emit_invoices_bulk_actions(array $bulk_actions) : array
    {
        if ($this->is_alegra_integration_enabled()) {
            $bulk_actions['integration_alegra_emit_invoices'] = 'Emitir facturas Alegra';
        }
        return $bulk_actions;
    }

    public function sync_bulk_action_edit_product($redirect_to, $action, array $post_ids) :string
    {
        if ($action !== 'integration_alegra_sync') return $redirect_to;

        Integration_Alegra_WC::sync_products($post_ids);

        return $redirect_to;
    }

    public function emit_invoices_bulk_action_edit_shop_order($redirect_to, $action, array $post_ids) :string
    {
        if ($action !== 'integration_alegra_emit_invoices') return $redirect_to;

        Integration_Alegra_WC::emit_invoices($post_ids);

        return $redirect_to;
    }

    public function alegra_print_invoice(array $columns) : array
    {
        if ($this->is_alegra_integration_enabled()) {
            $columns['integration_alegra_print_invoice'] = 'Factura';
        }

        return $columns;
    }

    public function register_additional_checkout_fields(): void
    {
        woocommerce_register_additional_checkout_field(
            array(
                'id'       => 'document/type_document',
                'label'    => 'Tipo de documento',
                'location' => 'address',
                'type'     => 'select',
                'required' => true,
                'options'  => [
                    [
                        'value' => 'CC',
                        'label' => 'Cédula de ciudadanía'
                    ],
                    [
                        'value' => 'NIT',
                        'label' => '(NIT) Número de indentificación tributaria'
                    ]
                ]
            )
        );
        woocommerce_register_additional_checkout_field(
            array(
                'id'            => 'document/dni',
                'label'         => 'Número de documento (sin DV)',
                'optionalLabel' => '1055666777',
                'location'      => 'address',
                'required'      => true,
                'attributes'    => array(
                    'autocomplete'     => 'billing_dni',
                    'aria-describedby' => 'some-element',
                    'aria-label'       => 'Número de documento (sin DV)',
                    'pattern'          => '[0-9]{5,12}'
                )
            ),
        );
    }

    public function document_woocommerce_fields($fields): array
    {
        $fields['type_document'] = array(
            'label'       => __('Tipo de documento'),
            'placeholder' => _x('', 'placeholder'),
            'required'    => true,
            'clear'       => false,
            'type'        => 'select',
            'default' => 'CC',
            'options'     => array(
                'CC' => __('Cédula de ciudadanía' ),
                'NIT' => __('(NIT) Número de indentificación tributaria'),
                'CE' => __('Cédula de extranjería'),
                'DIE' => __('Documento de identificación extranjero'),
                'TE' => __('Tarjeta de extranjería'),
                'PP' => __('Pasaporte'),
                'TI' => __('Tarjeta de identidad'),
                'RC' => __('Registro civil'),
                'FOREIGN_NIT' => __('NIT de otro país')
            ),
            'class' => array('class_field_type_document')
        );

        $fields['dni'] = array(
            'label' => __('Número de documento (sin DV)'),
            'placeholder' => _x('', 'placeholder'),
            'required' => true,
            'clear' => false,
            'type' => 'number',
            'custom_attributes' => array(
                'minlength' => 5
            ),
            'class' => array('class_field_dni')
        );

        return $fields;
    }

    public function document_woocommerce_fields_update_order_meta($order_id): void
    {
        $this->updated_address('billing', $order_id);

        if(!empty($_POST['ship_to_different_address'])) {
            $this->updated_address('shipping', $order_id);
        }
    }

    private function updated_address(string $prefix, $order_id): void
    {
        if (!empty($_POST[ "{$prefix}_type_document" ])) {
            $type_document = sanitize_text_field($_POST[ "{$prefix}_type_document" ]);
            update_post_meta($order_id, "_{$prefix}_type_document", $type_document);
        }

        if (!empty($_POST[ "{$prefix}_dni" ])) {
            $dni = sanitize_text_field($_POST[ "{$prefix}_dni" ]);
            update_post_meta($order_id, "_{$prefix}_dni", $dni);
        }

        if(isset($dni) &&
            isset($type_document) &&
            $type_document === 'NIT'){
            $dv = Integration_Alegra_WC::calculate_dv($dni);
            $dni = "$dni-$dv";
            update_post_meta($order_id, "_{$prefix}_dni", $dni);
        }
    }

    public function very_nit_validation(): void
    {
        $billing_type_document = sanitize_text_field($_POST['billing_type_document']);
        $billing_dni = sanitize_text_field($_POST['billing_dni']);
        $shipping_type_document = sanitize_text_field($_POST['shipping_type_document']);
        $shipping_dni = sanitize_text_field($_POST['shipping_dni']);

        if(($billing_type_document === 'NIT' && $billing_dni && strlen($billing_dni) !== 9) ||
            ($shipping_type_document === 'NIT' && $shipping_dni && strlen($shipping_dni) !== 9)){
            wc_add_notice( __( '<p>Ingrese un NIT válido sin el DV</p>' ), 'error' );
        }
    }

    public function custom_checkout_fields_update_order_meta($order_id): void
    {

        $billing_type_document = sanitize_text_field($_POST['billing_type_document']);
        $billing_dni = sanitize_text_field($_POST['billing_dni']);
        $shipping_type_document = sanitize_text_field($_POST['shipping_type_document']);
        $shipping_dni = sanitize_text_field($_POST['shipping_dni']);
        $key_field_dni = $billing_dni ? '_billing_dni' :  '_shipping_dni';
        $key_field_type_document = $billing_type_document ? '_billing_type_document' :  '_shipping_type_document';
        $type_document = $billing_type_document ?: $shipping_type_document;
        $dni = $billing_dni ?: $shipping_dni;

        if($type_document === 'NIT'){
            $dv = Integration_Alegra_WC::calculate_dv($dni);
            $dni = "$dni-$dv";
        }

        update_post_meta( $order_id, $key_field_type_document, $type_document );
        update_post_meta( $order_id, $key_field_dni, $dni );
    }

    public function content_column_alegra_print_invoice(string $column, $order): void
    {

        $invoice_id_alegra = $order->get_meta('_invoice_id_alegra');
        $dian_state = $order->get_meta('_invoice_emit_alegra');
        $class_dian_state = $dian_state  ? 'order-status status-processing' : 'order-status status-on-hold';

        if(!empty($invoice_id_alegra) && $column == 'integration_alegra_print_invoice'){
            echo "<button class='button tips $class_dian_state integration-alegra-print-invoice' data-invoice-id='$invoice_id_alegra' data-nonce='".wp_create_nonce( "integration_alegra_print_invoice") ."'>Ver Factura</button>";
        }
    }

    public function enqueue_scripts_admin($hook): void
    {
        $load_for_orders = $hook === 'woocommerce_page_wc-orders';
        $load_for_survey = $this->should_enqueue_premium_survey_assets_on_admin($hook);

        if ($load_for_orders || $load_for_survey) {
            wp_enqueue_script( 'integration-alegra', $this->assets. 'js/integration-alegra.js', array( 'jquery' ), $this->version, true );
            wp_enqueue_script( 'integration-alegra-sweet-alert', $this->assets. 'js/sweetalert2.min.js', array( 'jquery' ), $this->version, true );
        }
    }

    public function enqueue_scripts(): void
    {
        if ( is_checkout() ) {
            wp_enqueue_script( 'integration-alegra-field-dni', $this->plugin_url . 'assets/js/field-dni-checkout.js', array( 'jquery' ), $this->version, true );
        }
    }

    public function ajax_integration_alegra_print_invoice(): void
    {
        if ( ! wp_verify_nonce(  $_REQUEST['nonce'], 'integration_alegra_print_invoice' ) )
            return;

        $invoice_id = (int)$_POST['invoice_id'];

        $pdf_url = Integration_Alegra_WC::view_invoice($invoice_id);

        if (!$pdf_url)
            wp_send_json(['status' => false, 'message' => 'Ha surgido un error interno al intentar generar el enlace del PDF']);

        wp_send_json(['status' => true, 'url' => $pdf_url]);

    }

    /**
     * Log a message to the WooCommerce logger.
     *
     * @param mixed  $message The message to log (string, array, or object).
     * @param string $level   The log level (debug, info, notice, warning, error, critical, alert, emergency).
     * @return void
     */
    public function log( mixed $message, string $level = 'info' ): void {
        if ( ! $this->logger ) {
            $this->logger = wc_get_logger();
        }

        // Convert arrays and objects to JSON format for better readability.
        if ( is_array( $message ) || is_object( $message ) ) {
            $message = wp_json_encode( $message, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        }

        $this->logger->log(
            $level,
            $message,
            array( 'source' => 'integration-alegra' )
        );
    }

    public function display_custom_editable_field_on_admin_orders(WC_Order $order ): void
    {
        ?>
        <div class="data_wrapper">
            <?php
            woocommerce_wp_select(array(
                'id' => '_billing_type_document',
                'value' => get_post_meta($order->get_id(), '_billing_type_document', true),
                'label' => __('Tipo de documento:'),
                'options' => [
                    'CC' => 'Cédula de ciudadanía',
                    'NIT' => '(NIT) Número de identificación tributaria'
                ],
                'wrapper_class' => 'wc-enhanced-select'
            ));

            woocommerce_wp_text_input( array(
                'id' => '_billing_dni',
                'value' => get_post_meta($order->get_id(), '_billing_dni', true),
                'label' => __('Número de documento:'),
                'wrapper_class' => 'form-field-wide'
            ) );
            ?>
        </div>
        <?php
    }

    public function save_order_custom_field_meta( $order_id ): void
    {

        if ( isset($_POST['_billing_type_document']) ){
            update_post_meta($order_id, '_billing_type_document', sanitize_text_field($_POST['_billing_type_document']));
        }
        if ( isset($_POST['_billing_dni']) ){
            update_post_meta($order_id, '_billing_dni', sanitize_text_field($_POST['_billing_dni']));
        }

    }

    // =========================================================================
    // Premium Survey
    // =========================================================================

    private function has_survey_eligible_store(): bool
    {
        return $this->is_alegra_integration_enabled();
    }

    private function is_woocommerce_admin_screen(): bool
    {
        if ( ! is_admin() || ! function_exists('get_current_screen') ) {
            return false;
        }

        $screen = get_current_screen();

        if ( ! $screen ) {
            return false;
        }

        if ( str_starts_with($screen->id, 'woocommerce_page_') ) {
            return true;
        }

        if ( ! function_exists('wc_get_screen_ids') ) {
            return false;
        }

        return in_array($screen->id, wc_get_screen_ids(), true);
    }

    private function is_alegra_integration_settings_screen(): bool
    {
        if ( ! function_exists('get_current_screen') ) {
            return false;
        }

        $screen = get_current_screen();

        if ( ! $screen || $screen->id !== 'woocommerce_page_wc-settings' ) {
            return false;
        }

        $tab     = isset($_GET['tab'])     ? sanitize_key(wp_unslash($_GET['tab']))     : '';
        $section = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : '';

        return $tab === 'integration' && $section === 'wc_alegra_integration';
    }

    private function is_alegra_integration_settings_page_request(string $hook): bool
    {
        if ( $hook !== 'woocommerce_page_wc-settings' ) {
            return false;
        }

        $tab     = isset($_GET['tab'])     ? sanitize_key(wp_unslash($_GET['tab']))     : '';
        $section = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : '';

        return $tab === 'integration' && $section === 'wc_alegra_integration';
    }

    private function has_user_dismissed_premium_survey_notice(): bool
    {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        $dismissed = get_user_meta(get_current_user_id(), 'integration_alegra_premium_survey_notice_dismissed', true);

        return $dismissed === 'yes';
    }

    private function should_expose_survey_on_admin(): bool
    {
        if ( ! current_user_can('manage_woocommerce') ) {
            return false;
        }

        if ( ! $this->has_survey_eligible_store() ) {
            return false;
        }

        if ( $this->has_user_dismissed_premium_survey_notice() ) {
            return false;
        }

        return $this->is_woocommerce_admin_screen();
    }

    private function should_enqueue_premium_survey_assets_on_admin(string $hook): bool
    {
        if ( $this->is_alegra_integration_settings_page_request($hook) ) {
            return true;
        }

        return $this->should_expose_survey_on_admin();
    }

    /**
     * Validates and sanitizes the premium survey payload.
     *
     * @param array $payload Raw survey data (keys match form field names).
     * @return array{valid: bool, data?: array, error?: string}
     */
    private function validate_survey_payload(array $payload): array
    {
        $q1_score        = isset($payload['q1_score'])        ? (int) $payload['q1_score']                                            : 0;
        $q1_motivo       = isset($payload['q1_motivo'])       ? substr(sanitize_text_field(wp_unslash((string) $payload['q1_motivo'])), 0, 500) : '';
        $q2_pain_point   = isset($payload['q2_pain_point'])   ? sanitize_text_field(wp_unslash((string) $payload['q2_pain_point']))   : '';
        $q3_time_loss    = isset($payload['q3_time_loss'])    ? sanitize_text_field(wp_unslash((string) $payload['q3_time_loss']))    : '';
        $q5_other_feature= isset($payload['q5_other_feature'])? sanitize_text_field(wp_unslash((string) $payload['q5_other_feature'])): '';
        $q6_billing_model= isset($payload['q6_billing_model'])? sanitize_text_field(wp_unslash((string) $payload['q6_billing_model'])): '';
        $q7_price_range  = isset($payload['q7_price_range'])  ? sanitize_text_field(wp_unslash((string) $payload['q7_price_range']))  : '';
        $q8_open_feedback= isset($payload['q8_open_feedback'])? sanitize_textarea_field(wp_unslash((string) $payload['q8_open_feedback'])): '';
        $consent_yes_no  = isset($payload['consent_yes_no']) && sanitize_text_field(wp_unslash((string) $payload['consent_yes_no'])) === 'yes' ? 'yes' : 'no';

        // Normalise q4_top_features: accept array, JSON string, or CSV string.
        $q4_raw = isset($payload['q4_top_features']) ? $payload['q4_top_features'] : array();

        if ( is_string($q4_raw) ) {
            $decoded = json_decode($q4_raw, true);
            if ( JSON_ERROR_NONE === json_last_error() && is_array($decoded) ) {
                $q4_raw = $decoded;
            } else {
                $q4_raw = array_filter(array_map('trim', explode(',', $q4_raw)));
            }
        }

        if ( ! is_array($q4_raw) ) {
            $q4_raw = array();
        }

        $q4_top_features = array_slice(
            array_values(
                array_unique(
                    array_filter(
                        array_map(
                            static fn( $v ): string => sanitize_text_field((string) $v),
                            $q4_raw
                        )
                    )
                )
            ),
            0,
            3
        );

        // --- Validation rules ---

        if ( $q1_score < 1 || $q1_score > 10 ) {
            return array(
                'valid' => false,
                'error' => __('La satisfacción debe ser un valor entre 1 y 10.'),
            );
        }

        if ( $q1_score < 8 && '' === $q1_motivo ) {
            return array(
                'valid' => false,
                'error' => __('Por favor explica el motivo de tu baja satisfacción.'),
            );
        }

        if ( empty($q4_top_features) ) {
            return array(
                'valid' => false,
                'error' => __('Selecciona al menos una funcionalidad premium.'),
            );
        }

        if ( '' === $q7_price_range ) {
            return array(
                'valid' => false,
                'error' => __('Selecciona un rango de precio.'),
            );
        }

        return array(
            'valid' => true,
            'data'  => array(
                'q1_score'         => $q1_score,
                'q1_motivo'        => $q1_motivo,
                'q2_pain_point'    => $q2_pain_point,
                'q3_time_loss'     => $q3_time_loss,
                'q4_top_features'  => $q4_top_features,
                'q5_other_feature' => $q5_other_feature,
                'q6_billing_model' => $q6_billing_model,
                'q7_price_range'   => $q7_price_range,
                'q8_open_feedback' => $q8_open_feedback,
                'consent_yes_no'   => $consent_yes_no,
            ),
        );
    }

    public function premium_survey_admin_notice(): void
    {
        if ( ! $this->should_expose_survey_on_admin() || $this->is_alegra_integration_settings_screen() ) {
            return;
        }

        $settings_url            = admin_url('admin.php?page=wc-settings&tab=integration&section=wc_alegra_integration');
        $settings_with_survey_url = add_query_arg('open_premium_survey', '1', $settings_url);
        ?>
        <div class="notice notice-info is-dismissible alegra-premium-survey-notice" data-dismiss-nonce="<?php echo esc_attr(wp_create_nonce('integration_alegra_dismiss_premium_survey_notice')); ?>">
            <p>
                <strong>Integration Alegra Woocommerce: ayúdanos a priorizar la versión premium.</strong>
                Esta encuesta toma menos de 3 minutos y nos ayuda a mejorar este plugin para tu tienda.
            </p>
            <p>
                <button type="button" class="button button-primary alegra-send-premium-survey" data-nonce="<?php echo esc_attr(wp_create_nonce('integration_alegra_send_premium_survey')); ?>">
                    Responder encuesta premium
                </button>
                <a class="button button-secondary" href="<?php echo esc_url($settings_with_survey_url); ?>">Abrir desde configuraciones</a>
            </p>
        </div>
        <?php
    }

    public function ajax_integration_alegra_dismiss_premium_survey_notice(): void
    {
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';

        if ( ! wp_verify_nonce($nonce, 'integration_alegra_dismiss_premium_survey_notice') ) {
            wp_send_json(array(
                'status'  => false,
                'message' => __('No se pudo validar la solicitud.'),
            ));
        }

        if ( ! is_user_logged_in() || ! current_user_can('manage_woocommerce') ) {
            wp_send_json(array(
                'status'  => false,
                'message' => __('No tienes permisos para esta acción.'),
            ));
        }

        update_user_meta(get_current_user_id(), 'integration_alegra_premium_survey_notice_dismissed', 'yes');

        wp_send_json(array('status' => true));
    }

    public function ajax_integration_alegra_send_premium_survey(): void
    {
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';

        if ( ! wp_verify_nonce($nonce, 'integration_alegra_send_premium_survey') ) {
            wp_send_json(array(
                'status'  => false,
                'message' => __('No se pudo validar la solicitud. Recarga la página e intenta de nuevo.'),
            ));
        }

        if ( ! is_user_logged_in() || ! current_user_can('manage_woocommerce') ) {
            wp_send_json(array(
                'status'  => false,
                'message' => __('No tienes permisos para esta acción.'),
            ));
        }

        $validation = $this->validate_survey_payload($_POST);

        if ( ! $validation['valid'] ) {
            wp_send_json(array(
                'status'  => false,
                'message' => $validation['error'],
            ));
        }

        $data = $validation['data'];

        $response_id  = wp_generate_uuid4();
        $date_time     = wp_date('Y-m-d H:i:s');
        $date_time_iso = wp_date('c');
        $site_name     = get_bloginfo('name');
        $site_url      = home_url();

        $default_country = get_option('woocommerce_default_country', '');
        $country  = is_string($default_country) ? explode(':', $default_country)[0] : '';
        $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : get_option('woocommerce_currency', 'N/A');

        $recipient = apply_filters('integration_alegra_premium_survey_email_recipient', 'moralespachecopablo@gmail.com');
        $recipient = is_array($recipient)
            ? array_filter(array_map('sanitize_email', $recipient))
            : sanitize_email((string) $recipient);

        if ( empty($recipient) ) {
            $this->log('No premium survey email recipient configured', 'error');
            wp_send_json(array(
                'status'  => false,
                'message' => __('No hay correo de destino configurado para la encuesta premium.'),
            ));
        }

        $admin_email = sanitize_email((string) get_option('admin_email'));
        $headers     = array('Content-Type: text/plain; charset=UTF-8');

        if ( ! empty($admin_email) ) {
            $headers[] = sprintf('Reply-To: %s', $admin_email);
        }

        $subject = sprintf(
            '[Alegra Woo] Respuesta encuesta premium | %s | %s',
            $site_url,
            $date_time
        );

        $message_lines = array(
            sprintf('Response ID: %s',                     $response_id),
            sprintf('Fecha envio: %s',                     $date_time_iso),
            sprintf('Sitio: %s',                           $site_name),
            sprintf('URL tienda: %s',                      $site_url),
            sprintf('Pais/moneda: %s / %s',                $country ?: 'N/A', $currency ?: 'N/A'),
            sprintf('Version plugin: %s',                  (string) $this->version),
            sprintf('Version WP/WC: %s / %s',             get_bloginfo('version'), defined('WC_VERSION') ? WC_VERSION : 'N/A'),
            sprintf('Satisfaccion actual (1-10): %d',      $data['q1_score']),
            sprintf('Motivo baja satisfaccion: %s',        $data['q1_motivo']        ?: 'N/A'),
            sprintf('Dolor principal actual: %s',          $data['q2_pain_point']    ?: 'N/A'),
            sprintf('Tiempo semanal perdido: %s',          $data['q3_time_loss']     ?: 'N/A'),
            sprintf('Top 3 funcionalidades premium: %s',   implode(', ', $data['q4_top_features'])),
            sprintf('Otras funcionalidades: %s',           $data['q5_other_feature'] ?: 'N/A'),
            sprintf('Modelo de cobro preferido: %s',       $data['q6_billing_model'] ?: 'N/A'),
            sprintf('Rango de precio mensual: %s',         $data['q7_price_range']),
            sprintf('Comentario abierto: %s',              $data['q8_open_feedback'] ?: 'N/A'),
            sprintf('Consentimiento contacto: %s',         $data['consent_yes_no']),
        );

        $mail_sent = wp_mail($recipient, $subject, implode("\n", $message_lines), $headers);

        if ( ! $mail_sent ) {
            $this->log(
                array(
                    'event'       => 'premium_survey_send_failed',
                    'response_id' => $response_id,
                    'site_url'    => $site_url,
                ),
                'error'
            );

            wp_send_json(array(
                'status'  => false,
                'message' => __('No fue posible enviar tu respuesta por email. Intenta nuevamente.'),
            ));
        }

        wp_send_json(array(
            'status'  => true,
            'message' => __('Gracias. Tu respuesta fue enviada correctamente.'),
        ));
    }
}