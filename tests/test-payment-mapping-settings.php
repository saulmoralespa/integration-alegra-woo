<?php
/**
 * Class Test_Payment_Mapping_Settings
 *
 * @package Integration_Alegra_Woo
 */

/**
 * Tests para configuración de mapeo de métodos de pago
 */
if ( ! class_exists( 'WC_Alegra_Integration_Test_Double' ) ) {
    class WC_Alegra_Integration_Test_Double extends WC_Alegra_Integration {
        public function init_form_fields(): void {
            $this->form_fields = [];
        }
    }
}

class Test_Payment_Mapping_Settings extends WP_UnitTestCase {

    /**
     * Crea una instancia de prueba sin cargar settings.php para evitar deprecaciones de wc_enqueue_js
     *
     * @return WC_Alegra_Integration
     */
    private function create_integration_test_double(): WC_Alegra_Integration {
        return new WC_Alegra_Integration_Test_Double();
    }

    /**
     * Configuración inicial para cada test
     */
    public function setUp(): void {
        parent::setUp();

        delete_option( 'woocommerce_wc_alegra_integration_settings' );
    }

    /**
     * Limpieza después de cada test
     */
    public function tearDown(): void {
        parent::tearDown();

        delete_option( 'woocommerce_wc_alegra_integration_settings' );
    }

    /**
     * Test 1: Verificar filas de tabla con gateways activos e inactivos mapeados
     */
    public function test_get_payment_mapping_rows_includes_active_and_inactive_mapped_gateways() {
        $integration = $this->create_integration_test_double();

        $reflection = new ReflectionClass( 'WC_Alegra_Integration' );
        $method = $reflection->getMethod( 'get_payment_mapping_rows' );
        $method->setAccessible( true );

        $active_gateways = [
            'cod' => 'Contra entrega',
            'bacs' => 'Transferencia bancaria',
        ];

        $all_gateways = [
            'cod' => 'Contra entrega',
            'bacs' => 'Transferencia bancaria',
            'legacy_gateway' => 'Gateway legado',
        ];

        $saved_mappings = [
            'legacy_gateway' => [
                'payment_method' => 'cash',
                'account_id' => '1',
            ],
        ];

        $rows = $method->invokeArgs( $integration, [ $active_gateways, $all_gateways, $saved_mappings ] );

        $this->assertArrayHasKey( 'cod', $rows );
        $this->assertArrayHasKey( 'bacs', $rows );
        $this->assertArrayHasKey( 'legacy_gateway', $rows );
        $this->assertTrue( $rows['cod']['active'] );
        $this->assertFalse( $rows['legacy_gateway']['active'] );
        $this->assertSame( 'Gateway legado', $rows['legacy_gateway']['label'] );
    }

    /**
     * Test 2: Verificar sanitización de la entrada del mapeo
     */
    public function test_sanitize_payment_gateways_mapping_input() {
        $integration = $this->create_integration_test_double();

        $reflection = new ReflectionClass( 'WC_Alegra_Integration' );
        $method = $reflection->getMethod( 'sanitize_payment_gateways_mapping_input' );
        $method->setAccessible( true );

        $raw_value = [
            'cod' => [
                'payment_method' => 'cash',
                'account_id' => '1',
            ],
            ' <script>alert(1)</script> ' => [
                'payment_method' => 'transfer',
                'account_id' => '3',
            ],
            'invalid' => 'not_array',
        ];

        $result = $method->invokeArgs( $integration, [ $raw_value ] );

        $this->assertArrayHasKey( 'cod', $result );
        $this->assertArrayNotHasKey( 'invalid', $result );
        $this->assertCount( 1, $result );
        $this->assertSame( 'cash', $result['cod']['payment_method'] );
        $this->assertSame( '1', $result['cod']['account_id'] );
    }

    /**
     * Test 3: Verificar que no guarda cambios si no hay cuentas activas en Alegra
     */
    public function test_validate_payment_mappings_returns_existing_value_when_no_bank_accounts() {
        $existing_mapping = [
            'cod' => [
                'payment_method' => 'cash',
                'account_id' => '1',
            ],
        ];

        update_option( 'woocommerce_wc_alegra_integration_settings', [
            'enabled' => 'no',
            'payment_gateways_mapping' => $existing_mapping,
        ] );

        $integration = $this->create_integration_test_double();

        $value = [
            'cod' => [
                'payment_method' => 'transfer',
                'account_id' => '3',
            ],
        ];

        $result = $integration->validate_payment_mappings_table_field( 'payment_gateways_mapping', $value );

        $this->assertSame(
            $existing_mapping,
            $result,
            'Debe conservar el valor previo cuando no existen cuentas bancarias activas en Alegra.'
        );
    }

    /**
     * Test 4: Verificar que sanitize_payment_gateways_mapping_input conserva payment_type
     */
    public function test_sanitize_payment_gateways_mapping_input_includes_payment_type() {
        $integration = $this->create_integration_test_double();

        $reflection = new ReflectionClass( 'WC_Alegra_Integration' );
        $method = $reflection->getMethod( 'sanitize_payment_gateways_mapping_input' );
        $method->setAccessible( true );

        $raw_value = [
            'cod' => [
                'payment_method' => 'cash',
                'account_id' => '1',
                'payment_type' => 'CASH',
            ],
            'stripe' => [
                'payment_method' => 'credit-card',
                'account_id' => '2',
                'payment_type' => 'CREDIT',
            ],
            'injected' => [
                'payment_method' => 'transfer',
                'account_id' => '3',
                'payment_type' => '<script>CREDIT</script>', // script content is stripped entirely
            ],
        ];

        $result = $method->invokeArgs( $integration, [ $raw_value ] );

        $this->assertSame( 'CASH',   $result['cod']['payment_type'],      'payment_type CASH debe conservarse' );
        $this->assertSame( 'CREDIT', $result['stripe']['payment_type'],   'payment_type CREDIT debe conservarse' );
        $this->assertSame( '',       $result['injected']['payment_type'],  'script tag con contenido debe vaciarse por seguridad' );
    }

    /**
     * Test 5: Verificar que get_payment_mapping_rows incluye payment_type desde el mapeo guardado
     */
    public function test_get_payment_mapping_rows_includes_payment_type_from_saved_mapping() {
        $integration = $this->create_integration_test_double();

        $reflection = new ReflectionClass( 'WC_Alegra_Integration' );
        $method = $reflection->getMethod( 'get_payment_mapping_rows' );
        $method->setAccessible( true );

        $active_gateways = [
            'bacs' => 'Transferencia bancaria',
        ];

        $all_gateways = [
            'bacs' => 'Transferencia bancaria',
        ];

        $saved_mappings = [
            'bacs' => [
                'payment_method' => 'transfer',
                'account_id' => '3',
                'payment_type' => 'CASH',
            ],
        ];

        $rows = $method->invokeArgs( $integration, [ $active_gateways, $all_gateways, $saved_mappings ] );

        $this->assertArrayHasKey( 'bacs', $rows );
        $this->assertTrue( $rows['bacs']['active'] );
    }

    /**
     * Test 6: Verificar que sanitize pasa códigos UBL tal cual (sin transformar)
     */
    public function test_sanitize_payment_gateways_mapping_passes_through_ubl_codes() {
        $integration = $this->create_integration_test_double();

        $reflection = new ReflectionClass( 'WC_Alegra_Integration' );
        $method = $reflection->getMethod( 'sanitize_payment_gateways_mapping_input' );
        $method->setAccessible( true );

        $raw_value = [
            'stripe' => [
                'payment_method' => 'CREDIT_CARD',
                'account_id' => '2',
                'payment_type' => 'CREDIT',
            ],
            'bacs' => [
                'payment_method' => 'CREDIT_TRANSFER_BANK',
                'account_id' => '3',
                'payment_type' => 'CASH',
            ],
        ];

        $result = $method->invokeArgs( $integration, [ $raw_value ] );

        $this->assertSame( 'CREDIT_CARD',         $result['stripe']['payment_method'], 'CREDIT_CARD debe pasar sin transformar' );
        $this->assertSame( 'CREDIT',              $result['stripe']['payment_type'],   'payment_type CREDIT debe conservarse' );
        $this->assertSame( 'CREDIT_TRANSFER_BANK', $result['bacs']['payment_method'],  'CREDIT_TRANSFER_BANK debe pasar sin transformar' );
        $this->assertSame( 'CASH',                $result['bacs']['payment_type'],     'payment_type CASH debe conservarse' );
    }

    /**
     * Test 7: Verificar que PAYMENT_METHODS_UI tiene exactamente los 6 métodos UBL del README
     */
    public function test_payment_methods_ui_has_exactly_six_ubl_entries() {
        $expected_keys = [ 'CASH', 'CHECK', 'CREDIT_TRANSFER_BANK', 'BANK_DEPOSIT', 'CREDIT_CARD', 'DEBIT_CARD' ];

        $this->assertSame(
            $expected_keys,
            array_keys( Integration_Alegra_WC::PAYMENT_METHODS_UI ),
            'PAYMENT_METHODS_UI debe contener exactamente los 6 códigos UBL en orden correcto'
        );
    }
}
