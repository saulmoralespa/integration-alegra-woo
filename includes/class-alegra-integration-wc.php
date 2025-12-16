<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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

        add_filter( 'woocommerce_settings_api_form_fields_' .  $this->id, array( $this, 'add_additional_settings' ) );
        add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );
    }

    public function init_form_fields(): void
    {
        $this->form_fields = include(dirname(__FILE__) . '/admin/settings.php');
    }

    public function add_additional_settings(array $settings): array
    {
        $additional_settings = [];

        if ( empty( $this->settings ) ) {
            return $settings;
        }

        $user = $this->get_option('user');
        $token = $this->get_option('token');

        if ($user && $token) {
            $additional_settings = include(dirname(__FILE__) . '/admin/other_settings.php');
        }

        $settings = array_merge($settings, $additional_settings);

        return apply_filters('integration_alegra_settings', $settings);
    }

    public function is_available(): bool
    {
        return $this->enabled === 'yes' &&
            !empty($this->user) &&
            !empty($this->token);
    }

    /**
     * Validate password field (token).
     *
     * @param string $key   Field key.
     * @param string $value Field value.
     * @return string Validated value.
     */
    public function validate_password_field( $key, $value ): string {
        // Sanitize the input value.
        $value = sanitize_text_field( $value );

        // Only validate token when user is already configured.
        if ( 'token' !== $key ) {
            return $value;
        }

        // If token is empty, return it (will be caught by required validation).
        if ( empty( $value ) ) {
            return $value;
        }

        $user = $this->get_option( 'user' );

        // User must be set before validating token.
        if ( empty( $user ) ) {
            WC_Admin_Settings::add_error(
                __( 'Por favor, configure el usuario antes de ingresar el token.', 'integration-alegra-woo' )
            );
            return '';
        }

        // Test authentication with Alegra API.
        $is_valid = Integration_Alegra_WC::test_auth( $user, $value );

        if ( ! $is_valid ) {
            WC_Admin_Settings::add_error(
                __( 'Las credenciales proporcionadas son inválidas. Por favor, verifique el usuario y el token.', 'integration-alegra-woo' )
            );
            return '';
        }

        return $value;
    }

    public function get_data_options(string $method, callable $callback)
    {
        $data = isset($_GET['section']) && $_GET['section'] === $this->id ? $method() : [];
        return array_reduce($data, $callback, []);
    }
}