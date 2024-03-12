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
    private bool $_bootstrapped = false;

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
            if ($this->_bootstrapped) {
                throw new Exception('Integration Alegra Woocommerce can only be called once');
            }
            $this->run();
            $this->_bootstrapped = true;
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

        require_once($this->includes_path . 'class-integration-alegra-wc.php');

        $myUpdateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/saulmoralespa/integration-alegra-woo',
            $this->file
        );

        $myUpdateChecker->setBranch('main');
        $myUpdateChecker->getVcsApi()->enableReleaseAssets();

        add_filter( 'plugin_action_links_' . plugin_basename($this->file), array($this, 'plugin_action_links'));
        add_filter( 'bulk_actions-edit-product', array($this, 'sync_bulk_actions'), 20 );
        add_filter( 'bulk_actions-edit-shop_order', array($this, 'emit_invoices_bulk_actions'), 20 );
        add_filter( 'handle_bulk_actions-edit-product', array($this, 'sync_bulk_action_edit_product'), 10, 3 );
        add_filter( 'handle_bulk_actions-edit-shop_order', array($this, 'emit_invoices_bulk_action_edit_shop_order'), 10, 3 );
        add_filter( 'manage_edit-shop_order_columns', array($this, 'integration_alegra_print_invoice'), 20 );
        add_filter( 'woocommerce_checkout_fields', array($this, 'document_woocommerce_billing_fields'));

        add_action( 'woocommerce_order_status_changed', array( 'Integration_Alegra_WC', 'integration_alegra_generate_invoice' ), 10, 3 );
        add_action( 'manage_shop_order_posts_custom_column', array($this, 'content_column_alegra_print_invoice') );
        add_action( 'admin_enqueue_scripts', array($this, 'enqueue_scripts_admin') );
        add_action( 'wp_ajax_integration_alegra_print_invoice', array($this, 'ajax_integration_alegra_print_invoice'));
        add_action( 'woocommerce_admin_order_data_after_billing_address', array($this, 'document_admin_order_data_after_billing_address'), 10, 1 );
    }

    public function add_integration($integrations)
    {
        $integrations[] = 'WC_Alegra_Integration';
        return $integrations;
    }

    public function plugin_action_links($links)
    {
        $links[] = '<a href="' . admin_url('admin.php?page=wc-settings&tab=integration&section=wc_alegra_integration') . '">' . 'Configuraciones' . '</a>';
        $links[] = '<a target="_blank" href="https://shop.saulmoralespa.com/integration-alegra-woocommerce/">' . 'Documentación' . '</a>';
        return $links;
    }

    public function sync_bulk_actions($bulk_actions): array
    {
        $settings = get_option('woocommerce_wc_alegra_integration_settings');

        if(isset($settings['user']) &&
            $settings['user'] &&
            isset($settings['token']) &&
            $settings['token'] &&
            $settings['enabled'] === 'yes'
        ){
            $bulk_actions['integration_alegra_sync'] = 'Sincronizar productos Alegra';
        }
        return $bulk_actions;
    }

    public function emit_invoices_bulk_actions($bulk_actions) : array
    {
        $settings = get_option('woocommerce_wc_alegra_integration_settings');

        if(isset($settings['user']) &&
            $settings['user'] &&
            isset($settings['token']) &&
            $settings['token'] &&
            $settings['enabled'] === 'yes'
        ){
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

    public function integration_alegra_print_invoice($columns) : array
    {
        $settings = get_option('woocommerce_wc_alegra_integration_settings');
        if(isset($settings['user']) &&
            $settings['user'] &&
            isset($settings['token']) &&
            $settings['token'] &&
            $settings['enabled'] === 'yes'
        ){
            $columns['integration_alegra_print_invoice'] = 'Factura';
        }

        return $columns;
    }

    public function document_woocommerce_billing_fields($fields): array
    {
        $fields['billing']['billing_type_document'] = array(
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
            )
        );

        $fields['billing']['billing_dni'] = array(
            'label' => __('Número de documento'),
            'placeholder' => _x('', 'placeholder'),
            'required' => true,
            'clear' => false,
            'type' => 'number',
            'class' => array('my-css')
        );


        $fields['shipping']['shipping_type_document'] = array(
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
            )
        );

        $fields['shipping']['shipping_dni'] = array(
            'label' => __('Número de documento'),
            'placeholder' => _x('', 'placeholder'),
            'required' => true,
            'clear'    => false,
            'type' => 'number',
            'class' => array('my-css')
        );

        return $fields;
    }

    public function document_admin_order_data_after_billing_address($order)
    {
        ?>
        <p><strong><?= __('Tipo de documento:'); ?></strong><br/> <?= get_post_meta( $order->get_id(), '_billing_type_document', true ) ?></p>
        <p><strong><?= __('Número de documento:'); ?></strong><br/> <?= get_post_meta( $order->get_id(), '_billing_dni', true ) ?></p>
        <?php
    }

    public function content_column_alegra_print_invoice($column): void
    {
        global $post;

        $order = new WC_Order($post->ID);

        $invoice_id_alegra = get_post_meta($order->get_id(), 'invoice_id_alegra', true);
        $dian_state = get_post_meta($order->get_id(), 'invoice_emit_alegra', true);
        $class_dian_state = $dian_state  ? 'order-status status-processing' : 'order-status status-on-hold';

        if(!empty($invoice_id_alegra) && $column == 'integration_alegra_print_invoice'){
            echo "<button class='button tips $class_dian_state integration-alegra-print-invoice' data-invoice-id='$invoice_id_alegra' data-nonce='".wp_create_nonce( "integration_alegra_print_invoice") ."'>Ver Factura</button>";
        }
    }

    public function enqueue_scripts_admin($hook): void
    {
        if ($hook === 'edit.php'){
            wp_enqueue_script( 'integration_alegra', $this->assets. 'js/integration-alegra.js', array( 'jquery' ), $this->version, true );
            wp_enqueue_script( 'integration_alegra_sweet_alert', $this->assets. 'js/sweetalert2.min.js', array( 'jquery' ), $this->version, true );
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

    public function log($message): void
    {
        if (is_array($message) || is_object($message))
            $message = print_r($message, true);
        $logger = new WC_Logger();
        $logger->add('integration-alegra', $message);
    }
}