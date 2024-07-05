<?php

class WC_Alegra_Integration extends WC_Integration
{
    public string $debug;

    public string $user;

    public string $token;

    public string $status_generate_invoice;

    public function __construct()
    {
        $this->id = 'wc_alegra_integration';
        $this->method_title = __( 'Integration Alegra Woocommerce');
        $this->method_description = __( 'Integration Alegra for Woocommerce');

        $this->init_form_fields();
        $this->init_settings();

        $this->debug = $this->get_option( 'debug' );
        $this->user = $this->get_option( 'user' );
        $this->token = $this->get_option( 'token' );
        $this->status_generate_invoice = $this->get_option( 'order_status_generate_invoice' );

        add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );
    }

    public function init_form_fields(): void
    {
        $this->form_fields = include(dirname(__FILE__) . '/admin/settings.php');
    }

    public function is_available(): bool
    {
        return $this->enabled === 'yes' &&
            !empty($this->user) &&
            !empty($this->token);
    }

    public function validate_password_field($key, $value) :string
    {
        if($this->get_option('user') !== '' && $key === 'token'){
            $status = Integration_Alegra_WC::test_auth($this->get_option('user'), $value);
            if(!$status){
                WC_Admin_Settings::add_error("Credenciales inválidas");
                $value = '';
            }
        }

        return $value;
    }

    public function get_data_options(string $method, callable $callback)
    {
        $data = isset($_GET['section']) && $_GET['section'] === $this->id ? $method() : [];
        return array_reduce($data, $callback, []);
    }
}