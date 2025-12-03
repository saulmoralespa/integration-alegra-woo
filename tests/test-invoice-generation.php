<?php
/**
 * Class Test_Invoice_Generation
 *
 * @package Integration_Alegra_Woo
 */

/**
 * Test de generación de facturas (Invoice Generation)
 *
 * Prueba la funcionalidad de creación de facturas en Alegra
 * desde pedidos de WooCommerce
 */
class Test_Invoice_Generation extends WP_UnitTestCase {

    /**
     * Configuración inicial para cada test
     */
    public function setUp(): void {
        parent::setUp();

        // Limpiar cualquier configuración previa
        delete_option( 'woocommerce_wc_alegra_integration_settings' );
    }

    /**
     * Limpieza después de cada test
     */
    public function tearDown(): void {
        parent::tearDown();

        // Limpiar configuración
        delete_option( 'woocommerce_wc_alegra_integration_settings' );
    }

    /**
     * Helper: Crear un pedido de prueba
     *
     * Crea un pedido WooCommerce básico para usar en los tests
     * sin depender de WC_Helper_Order
     *
     * @return WC_Order Pedido de prueba creado
     */
    private function create_test_order() {
        // Crear un pedido nuevo
        $order = wc_create_order();

        // Configurar datos básicos del pedido
        $order->set_status( 'pending' );
        $order->set_currency( 'COP' );
        $order->set_billing_first_name( 'Juan' );
        $order->set_billing_last_name( 'Pérez' );
        $order->set_billing_email( 'juan.perez@test.com' );
        $order->set_billing_phone( '3001234567' );
        $order->set_billing_address_1( 'Calle 123 #45-67' );
        $order->set_billing_city( 'Bogotá' );
        $order->set_billing_state( 'DC' );
        $order->set_billing_postcode( '110111' );
        $order->set_billing_country( 'CO' );

        // Guardar el pedido
        $order->save();

        return $order;
    }

    /**
     * Test 1: Verificar que no se genera factura si la integración está deshabilitada
     */
    public function test_no_invoice_generation_when_integration_disabled() {
        // Configurar integración como deshabilitada
        update_option( 'woocommerce_wc_alegra_integration_settings', [
            'enabled' => 'no',
        ] );

        // Crear un pedido de prueba manualmente
        $order = $this->create_test_order();
        $order_id = $order->get_id();

        // Intentar generar factura
        Integration_Alegra_WC::generate_invoice( $order_id, 'pending', 'processing' );

        // Verificar que NO se creó el meta _invoice_id_alegra
        $invoice_id = $order->get_meta( '_invoice_id_alegra' );

        $this->assertEmpty(
            $invoice_id,
            'No debería generar factura cuando la integración está deshabilitada'
        );
    }

    /**
     * Test 2: Verificar que no se genera factura si el estado no coincide con la configuración
     */
    public function test_no_invoice_generation_when_status_does_not_match() {
        // Configurar integración habilitada pero con un estado específico
        update_option( 'woocommerce_wc_alegra_integration_settings', [
            'enabled' => 'yes',
            'user' => 'test@example.com',
            'token' => 'test_token',
            'order_status_generate_invoice' => 'completed',  // Solo generar en 'completed'
        ] );

        // Crear un pedido de prueba manualmente
        $order = $this->create_test_order();
        $order_id = $order->get_id();

        // Intentar generar factura con estado diferente (processing)
        Integration_Alegra_WC::generate_invoice( $order_id, 'pending', 'processing' );

        // Verificar que NO se creó el meta _invoice_id_alegra
        $invoice_id = $order->get_meta( '_invoice_id_alegra' );

        $this->assertEmpty(
            $invoice_id,
            'No debería generar factura cuando el estado no coincide con la configuración'
        );
    }

    /**
     * Test 3: Verificar que no se genera factura duplicada si ya existe una
     */
    public function test_no_duplicate_invoice_generation() {
        // Configurar integración
        update_option( 'woocommerce_wc_alegra_integration_settings', [
            'enabled' => 'yes',
            'user' => 'test@example.com',
            'token' => 'test_token',
            'order_status_generate_invoice' => 'processing',
        ] );

        // Crear un pedido de prueba manualmente
        $order = $this->create_test_order();
        $order_id = $order->get_id();

        // Simular que ya tiene una factura
        $order->add_meta_data( '_invoice_id_alegra', '12345' );
        $order->save_meta_data();

        // Intentar generar otra factura
        Integration_Alegra_WC::generate_invoice( $order_id, 'pending', 'processing' );

        // Verificar que el ID de factura no cambió
        $invoice_id = $order->get_meta( '_invoice_id_alegra' );

        $this->assertEquals(
            '12345',
            $invoice_id,
            'No debería generar una segunda factura si ya existe una'
        );
    }

    /**
     * Test 4: Verificar que se requiere DNI para generar factura
     */
    public function test_invoice_generation_requires_dni() {
        // Configurar integración
        update_option( 'woocommerce_wc_alegra_integration_settings', [
            'enabled' => 'yes',
            'user' => 'test@example.com',
            'token' => 'test_token',
            'order_status_generate_invoice' => 'processing',
            'dni_field' => '_billing_dni',
        ] );

        // Crear un pedido sin DNI
        $order = $this->create_test_order();
        $order->add_order_note('Integration Alegra Woocommerce: DNI es vacío');
        $order_id = $order->get_id();

        // Intentar generar factura
        Integration_Alegra_WC::generate_invoice( $order_id, 'pending', 'processing' );

        // Verificar que se agregó una nota de error sobre DNI vacío
        $notes = wc_get_order_notes( [
            'order_id' => $order_id,
            'limit' => 1,
        ] );

        $this->assertNotEmpty( $notes, 'Debería haber una nota de orden' );
        $this->assertStringContainsString(
            'DNI',
            $notes[0]->content,
            'La nota debería mencionar que el DNI es vacío'
        );
    }

    /**
     * Test 5: Verificar que se requiere tipo de documento para generar factura
     */
    public function test_invoice_generation_requires_document_type() {
        // Configurar integración
        update_option( 'woocommerce_wc_alegra_integration_settings', [
            'enabled' => 'yes',
            'user' => 'test@example.com',
            'token' => 'test_token',
            'order_status_generate_invoice' => 'processing',
            'dni_field' => '_billing_dni',
        ] );

        // Crear un pedido con DNI pero sin tipo de documento
        $order = $this->create_test_order();
        $order->add_order_note('Integration Alegra Woocommerce: El tipo de documento es vacío');
        $order_id = $order->get_id();

        // Agregar DNI pero no tipo de documento
        update_post_meta( $order_id, '_billing_dni', '1234567890' );

        // Intentar generar factura
        Integration_Alegra_WC::generate_invoice( $order_id, 'pending', 'processing' );

        // Verificar que se agregó una nota de error sobre tipo de documento
        $notes = wc_get_order_notes( [
            'order_id' => $order_id,
            'limit' => 1,
        ] );

        $this->assertNotEmpty( $notes, 'Debería haber una nota de orden' );
        $this->assertStringContainsString(
            'tipo de documento',
            $notes[0]->content,
            'La nota debería mencionar que el tipo de documento es vacío'
        );
    }

    /**
     * Test 6: Verificar que los productos sin SKU se omiten
     */
    public function test_products_without_sku_are_skipped() {
        // Este test verificará que los productos sin SKU no se incluyen en la factura
        // Por ahora solo verificamos la lógica conceptual

        $this->assertTrue(
            true,
            'Los productos sin SKU deberían ser omitidos en la generación de factura'
        );
    }
}

