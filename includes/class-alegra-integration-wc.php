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

    public function generate_payment_mappings_table_html($key, $data): string
    {
        $defaults = [
            'title' => '',
            'description' => '',
            'payment_methods' => [],
            'bank_accounts' => [],
            'active_gateways' => [],
            'all_gateways' => [],
        ];

        $data = wp_parse_args($data, $defaults);
        $field_key = $this->get_field_key($key);
        $saved_mappings = $this->sanitize_payment_gateways_mapping_input($this->get_option($key, []));
        $rows = $this->get_payment_mapping_rows($data['active_gateways'], $data['all_gateways'], $saved_mappings);

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label><?php echo esc_html($data['title']); ?></label>
            </th>
            <td class="forminp">
                <?php if ( empty($rows) ): ?>
                    <p><?php echo esc_html__('No hay métodos de pago activos disponibles en WooCommerce para mapear.', 'integration-alegra-woo'); ?></p>
                <?php else: ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Gateway WooCommerce', 'integration-alegra-woo'); ?></th>
                                <th><?php echo esc_html__('Método de pago en Alegra', 'integration-alegra-woo'); ?></th>
                                <th><?php echo esc_html__('Cuenta bancaria en Alegra', 'integration-alegra-woo'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $gateway_id => $gateway_data): ?>
                            <?php
                            $saved_method = $saved_mappings[$gateway_id]['payment_method'] ?? '';
                            $saved_account = $saved_mappings[$gateway_id]['account_id'] ?? '';
                            $gateway_label = $gateway_data['label'];
                            if (!$gateway_data['active']) {
                                $gateway_label .= ' (' . __('inactivo', 'integration-alegra-woo') . ')';
                            }
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($gateway_label); ?></strong>
                                    <br/>
                                    <small><?php echo esc_html($gateway_id); ?></small>
                                </td>
                                <td>
                                    <select name="<?php echo esc_attr(sprintf('%s[%s][payment_method]', $field_key, $gateway_id)); ?>">
                                        <option value=""><?php echo esc_html__('Seleccionar método...', 'integration-alegra-woo'); ?></option>
                                        <?php foreach ($data['payment_methods'] as $method_key => $method_label): ?>
                                            <option value="<?php echo esc_attr($method_key); ?>" <?php selected($saved_method, $method_key); ?>>
                                                <?php echo esc_html($method_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="<?php echo esc_attr(sprintf('%s[%s][account_id]', $field_key, $gateway_id)); ?>">
                                        <option value=""><?php echo esc_html__('Seleccionar cuenta...', 'integration-alegra-woo'); ?></option>
                                        <?php foreach ($data['bank_accounts'] as $account_id => $account_label): ?>
                                            <option value="<?php echo esc_attr($account_id); ?>" <?php selected($saved_account, (string) $account_id); ?>>
                                                <?php echo esc_html($account_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php if (!empty($data['description'])): ?>
                    <p class="description"><?php echo wp_kses_post($data['description']); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }

    public function validate_payment_mappings_table_field($key, $value): array
    {
        $existing_value = $this->get_option($key, []);

        if (!is_array($existing_value)) {
            $existing_value = [];
        }

        $sanitized_mapping = $this->sanitize_payment_gateways_mapping_input($value);
        $active_gateways = Integration_Alegra_WC::get_wc_payment_gateways(true);
        $bank_accounts = Integration_Alegra_WC::get_bank_accounts();
        $available_bank_accounts = [];

        foreach ($bank_accounts as $bank_account) {
            if (($bank_account['status'] ?? '') !== 'active') {
                continue;
            }

            $account_id = (string) ($bank_account['id'] ?? '');

            if (!$account_id) {
                continue;
            }

            $available_bank_accounts[$account_id] = true;
        }

        if (empty($available_bank_accounts)) {
            WC_Admin_Settings::add_error(
                __('Integration Alegra Woocommerce: No se encontraron cuentas bancarias activas en Alegra para guardar el mapeo de pagos.', 'integration-alegra-woo')
            );
            return $existing_value;
        }

        $has_errors = false;
        $validated_mapping = [];

        foreach ($active_gateways as $gateway_id => $gateway_title) {
            $gateway_map = $sanitized_mapping[$gateway_id] ?? [
                'payment_method' => '',
                'account_id' => '',
            ];

            if (!$gateway_map['payment_method'] || !$gateway_map['account_id']) {
                WC_Admin_Settings::add_error(
                    sprintf(
                        __('Integration Alegra Woocommerce: Debe configurar método de pago y cuenta bancaria para el gateway activo "%s".', 'integration-alegra-woo'),
                        $gateway_title
                    )
                );
                $has_errors = true;
            }
        }

        foreach ($sanitized_mapping as $gateway_id => $gateway_map) {
            $payment_method = $gateway_map['payment_method'];
            $account_id = $gateway_map['account_id'];

            if (($payment_method && !$account_id) || (!$payment_method && $account_id)) {
                WC_Admin_Settings::add_error(
                    sprintf(
                        __('Integration Alegra Woocommerce: El gateway "%s" tiene configuración incompleta. Debe seleccionar método y cuenta.', 'integration-alegra-woo'),
                        $gateway_id
                    )
                );
                $has_errors = true;
                continue;
            }

            if (!$payment_method && !$account_id) {
                continue;
            }

            if (!isset(Integration_Alegra_WC::PAYMENTS_METHODS[$payment_method])) {
                WC_Admin_Settings::add_error(
                    sprintf(
                        __('Integration Alegra Woocommerce: El método de pago "%s" no es válido para el gateway "%s".', 'integration-alegra-woo'),
                        $payment_method,
                        $gateway_id
                    )
                );
                $has_errors = true;
                continue;
            }

            if (!isset($available_bank_accounts[$account_id])) {
                WC_Admin_Settings::add_error(
                    sprintf(
                        __('Integration Alegra Woocommerce: La cuenta bancaria "%s" no es válida para el gateway "%s".', 'integration-alegra-woo'),
                        $account_id,
                        $gateway_id
                    )
                );
                $has_errors = true;
                continue;
            }

            $validated_mapping[$gateway_id] = [
                'payment_method' => $payment_method,
                'account_id' => $account_id,
            ];
        }

        if ($has_errors) {
            return $existing_value;
        }

        return $validated_mapping;
    }

    private function sanitize_payment_gateways_mapping_input($value): array
    {
        $sanitized = [];

        if (!is_array($value)) {
            return $sanitized;
        }

        foreach ($value as $gateway_id => $gateway_map) {
            if (!is_array($gateway_map)) {
                continue;
            }

            $sanitized_gateway_id = sanitize_text_field((string) $gateway_id);

            if (!$sanitized_gateway_id) {
                continue;
            }

            $sanitized[$sanitized_gateway_id] = [
                'payment_method' => sanitize_text_field((string) ($gateway_map['payment_method'] ?? '')),
                'account_id' => sanitize_text_field((string) ($gateway_map['account_id'] ?? '')),
            ];
        }

        return $sanitized;
    }

    private function get_payment_mapping_rows(array $active_gateways, array $all_gateways, array $saved_mappings): array
    {
        $rows = [];

        foreach ($active_gateways as $gateway_id => $gateway_title) {
            $rows[$gateway_id] = [
                'label' => $gateway_title,
                'active' => true,
            ];
        }

        foreach ($saved_mappings as $gateway_id => $mapping) {
            if (isset($rows[$gateway_id])) {
                continue;
            }

            $rows[$gateway_id] = [
                'label' => $all_gateways[$gateway_id] ?? $gateway_id,
                'active' => false,
            ];
        }

        return $rows;
    }
}