<?php
/**
 * Class Test_Client_Management
 *
 * @package Integration_Alegra_Woo
 */

/**
 * Test de gestión de clientes (Client Management)
 * 
 * Prueba la funcionalidad de búsqueda y creación de clientes en Alegra
 */
class Test_Client_Management extends WP_UnitTestCase {

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
     * @param array $custom_data Datos personalizados opcionales
     * @return WC_Order Pedido de prueba creado
     */
    private function create_test_order( array $custom_data = [] ): WC_Order {
        $order = wc_create_order();
        
        // Configurar datos básicos del pedido
        $order->set_status( 'pending' );
        $order->set_currency( 'COP' );
        $order->set_billing_first_name( $custom_data['billing_first_name'] ?? 'Juan' );
        $order->set_billing_last_name( $custom_data['billing_last_name'] ?? 'Pérez' );
        $order->set_billing_email( $custom_data['billing_email'] ?? 'juan.perez@test.com' );
        $order->set_billing_phone( $custom_data['billing_phone'] ?? '3001234567' );
        $order->set_billing_address_1( $custom_data['billing_address_1'] ?? 'Calle 123 #45-67' );
        $order->set_billing_city( $custom_data['billing_city'] ?? 'Bogotá' );
        $order->set_billing_state( $custom_data['billing_state'] ?? 'DC' );
        $order->set_billing_postcode( $custom_data['billing_postcode'] ?? '110111' );
        $order->set_billing_country( $custom_data['billing_country'] ?? 'CO' );
        
        // Guardar el pedido
        $order->save();
        
        return $order;
    }

    /**
     * Test 1: Verificar extracción de DV cuando viene en el DNI
     */
    public function test_extract_dv_from_dni_with_dash() {
        // DNI con formato: NIT-DV
        $dni = '890903938-8';
        $dni_copy = $dni;
        
        // Usar reflexión para acceder al método privado
        $reflection = new ReflectionClass( 'Integration_Alegra_WC' );
        $method = $reflection->getMethod( 'extract_dv_from_dni' );
        $method->setAccessible( true );
        
        // Ejecutar el método (nota: se pasa por referencia)
        $dv = $method->invokeArgs( null, [ &$dni_copy ] );
        
        // Verificar que el DNI se separó correctamente
        $this->assertEquals(
            '890903938',
            $dni_copy,
            'El DNI debería estar sin el DV'
        );
        
        // Verificar que el DV se extrajo correctamente
        $this->assertEquals(
            '8',
            $dv,
            'El DV extraído debería ser 8'
        );
    }

    /**
     * Test 2: Verificar que retorna null cuando no hay DV en el DNI
     */
    public function test_extract_dv_from_dni_without_dash() {
        // DNI sin DV
        $dni = '890903938';
        $dni_copy = $dni;
        
        // Usar reflexión para acceder al método privado
        $reflection = new ReflectionClass( 'Integration_Alegra_WC' );
        $method = $reflection->getMethod( 'extract_dv_from_dni' );
        $method->setAccessible( true );
        
        // Ejecutar el método
        $dv = $method->invokeArgs( null, [ &$dni_copy ] );
        
        // Verificar que el DNI no cambió
        $this->assertEquals(
            '890903938',
            $dni_copy,
            'El DNI no debería cambiar si no tiene DV'
        );
        
        // Verificar que el DV es null
        $this->assertNull(
            $dv,
            'El DV debería ser null cuando no hay guión en el DNI'
        );
    }

    /**
     * Test 3: Verificar construcción de datos de contacto para persona natural
     */
    public function test_build_contact_data_for_person() {
        $order = $this->create_test_order();
        $dni = '1234567890';
        $type_document = 'CC'; // Cédula de Ciudadanía
        $dv_nit = null;
        
        // Usar reflexión para acceder al método privado
        $reflection = new ReflectionClass( 'Integration_Alegra_WC' );
        $method = $reflection->getMethod( 'build_contact_data' );
        $method->setAccessible( true );
        
        // Ejecutar el método
        $contact_data = $method->invokeArgs( null, [ $order, $dni, $type_document, $dv_nit ] );
        
        // Verificar estructura básica
        $this->assertIsArray( $contact_data, 'Los datos del contacto deberían ser un array' );
        
        // Verificar campos requeridos
        $this->assertArrayHasKey( 'name', $contact_data, 'Debe tener campo name' );
        $this->assertArrayHasKey( 'nameObject', $contact_data, 'Debe tener campo nameObject' );
        $this->assertArrayHasKey( 'identificationObject', $contact_data, 'Debe tener campo identificationObject' );
        $this->assertArrayHasKey( 'kindOfPerson', $contact_data, 'Debe tener campo kindOfPerson' );
        $this->assertArrayHasKey( 'regime', $contact_data, 'Debe tener campo regime' );
        
        // Verificar valores específicos para persona natural
        $this->assertEquals(
            'PERSON_ENTITY',
            $contact_data['kindOfPerson'],
            'Una CC debe ser PERSON_ENTITY'
        );
        
        $this->assertEquals(
            'SIMPLIFIED_REGIME',
            $contact_data['regime'],
            'Una persona natural debe tener SIMPLIFIED_REGIME'
        );
        
        // Verificar identificación
        $this->assertEquals(
            $dni,
            $contact_data['identificationObject']['number'],
            'El número de identificación debe coincidir'
        );
        
        $this->assertEquals(
            $type_document,
            $contact_data['identificationObject']['type'],
            'El tipo de documento debe coincidir'
        );
        
        // Verificar que NO tiene DV (solo para NIT)
        $this->assertArrayNotHasKey(
            'dv',
            $contact_data['identificationObject'],
            'Una CC no debería tener DV'
        );
    }

    /**
     * Test 4: Verificar construcción de datos de contacto para empresa (NIT)
     */
    public function test_build_contact_data_for_company() {
        $order = $this->create_test_order([
            'billing_first_name' => 'Empresa',
            'billing_last_name' => 'Test S.A.S.',
        ]);
        $dni = '890903938';
        $type_document = 'NIT';
        $dv_nit = '8';
        
        // Usar reflexión para acceder al método privado
        $reflection = new ReflectionClass( 'Integration_Alegra_WC' );
        $method = $reflection->getMethod( 'build_contact_data' );
        $method->setAccessible( true );
        
        // Ejecutar el método
        $contact_data = $method->invokeArgs( null, [ $order, $dni, $type_document, $dv_nit ] );
        
        // Verificar valores específicos para empresa
        $this->assertEquals(
            'LEGAL_ENTITY',
            $contact_data['kindOfPerson'],
            'Un NIT debe ser LEGAL_ENTITY'
        );
        
        $this->assertEquals(
            'COMMON_REGIME',
            $contact_data['regime'],
            'Una empresa debe tener COMMON_REGIME'
        );
        
        // Verificar que SÍ tiene DV
        $this->assertArrayHasKey(
            'dv',
            $contact_data['identificationObject'],
            'Un NIT debería tener DV'
        );
        
        $this->assertEquals(
            $dv_nit,
            $contact_data['identificationObject']['dv'],
            'El DV debe coincidir'
        );
    }

    /**
     * Test 5: Verificar que se incluyen todos los datos del pedido
     */
    public function test_build_contact_data_includes_order_details() {
        $custom_data = [
            'billing_first_name' => 'María',
            'billing_last_name' => 'García',
            'billing_email' => 'maria.garcia@example.com',
            'billing_phone' => '3109876543',
            'billing_address_1' => 'Carrera 10 #20-30',
            'billing_city' => 'Medellín',
            'billing_state' => 'ANT',
            'billing_country' => 'CO',
        ];
        
        $order = $this->create_test_order( $custom_data );
        $dni = '9876543210';
        $type_document = 'CC';
        $dv_nit = null;
        
        // Usar reflexión
        $reflection = new ReflectionClass( 'Integration_Alegra_WC' );
        $method = $reflection->getMethod( 'build_contact_data' );
        $method->setAccessible( true );
        
        // Ejecutar el método
        $contact_data = $method->invokeArgs( null, [ $order, $dni, $type_document, $dv_nit ] );
        
        // Verificar datos del pedido
        $this->assertStringContainsString(
            'María',
            $contact_data['nameObject']['firstName'],
            'El nombre debe coincidir'
        );
        
        $this->assertStringContainsString(
            'García',
            $contact_data['nameObject']['lastName'],
            'El apellido debe coincidir'
        );
        
        $this->assertEquals(
            'maria.garcia@example.com',
            $contact_data['email'],
            'El email debe coincidir'
        );
        
        $this->assertEquals(
            '3109876543',
            $contact_data['phonePrimary'],
            'El teléfono debe coincidir'
        );
        
        // Verificar dirección
        $this->assertArrayHasKey( 'address', $contact_data, 'Debe tener campo address' );
        $this->assertEquals(
            'Medellín',
            $contact_data['address']['city'],
            'La ciudad debe coincidir'
        );
    }

    /**
     * Test 6: Verificar que el tipo de contacto es "client"
     */
    public function test_build_contact_data_sets_type_as_client() {
        $order = $this->create_test_order();
        $dni = '1234567890';
        $type_document = 'CC';
        $dv_nit = null;
        
        // Usar reflexión
        $reflection = new ReflectionClass( 'Integration_Alegra_WC' );
        $method = $reflection->getMethod( 'build_contact_data' );
        $method->setAccessible( true );
        
        // Ejecutar el método
        $contact_data = $method->invokeArgs( null, [ $order, $dni, $type_document, $dv_nit ] );
        
        // Verificar tipo y estado
        $this->assertEquals(
            'client',
            $contact_data['type'],
            'El tipo debe ser "client"'
        );
        
        $this->assertEquals(
            'active',
            $contact_data['status'],
            'El estado debe ser "active"'
        );
        
        $this->assertFalse(
            $contact_data['enableHealthSector'],
            'enableHealthSector debe ser false por defecto'
        );
    }

    /**
     * Test 7: Verificar cálculo automático de DV para NIT
     */
    public function test_nit_calculates_dv_automatically_when_not_provided() {
        // Crear un NIT sin DV
        $nit = '890903938';
        
        // Calcular el DV esperado usando el método calculate_dv
        $expected_dv = Integration_Alegra_WC::calculate_dv( $nit );
        
        // Verificar que el cálculo es correcto
        $this->assertEquals(
            8,
            $expected_dv,
            'El DV calculado para 890903938 debe ser 8'
        );
    }

    /**
     * Test 8: Verificar que se manejan correctamente los datos faltantes
     */
    public function test_build_contact_data_handles_missing_shipping_data() {
        $order = $this->create_test_order();
        
        // No establecer datos de shipping (usar solo billing)
        $order->set_shipping_first_name( '' );
        $order->set_shipping_last_name( '' );
        $order->set_shipping_address_1( '' );
        $order->save();
        
        $dni = '1234567890';
        $type_document = 'CC';
        $dv_nit = null;
        
        // Usar reflexión
        $reflection = new ReflectionClass( 'Integration_Alegra_WC' );
        $method = $reflection->getMethod( 'build_contact_data' );
        $method->setAccessible( true );
        
        // Ejecutar el método - no debería lanzar error
        $contact_data = $method->invokeArgs( null, [ $order, $dni, $type_document, $dv_nit ] );
        
        // Verificar que se usaron los datos de billing
        $this->assertNotEmpty(
            $contact_data['name'],
            'El nombre no debería estar vacío'
        );
        
        $this->assertNotEmpty(
            $contact_data['address']['address'],
            'La dirección no debería estar vacía'
        );
    }

    /**
     * Test 9: Verificar formato del nombre completo
     */
    public function test_build_contact_data_formats_full_name_correctly() {
        $order = $this->create_test_order([
            'billing_first_name' => 'Pedro',
            'billing_last_name' => 'Martínez López',
        ]);
        
        $dni = '1234567890';
        $type_document = 'CC';
        $dv_nit = null;
        
        // Usar reflexión
        $reflection = new ReflectionClass( 'Integration_Alegra_WC' );
        $method = $reflection->getMethod( 'build_contact_data' );
        $method->setAccessible( true );
        
        // Ejecutar el método
        $contact_data = $method->invokeArgs( null, [ $order, $dni, $type_document, $dv_nit ] );
        
        // Verificar que el nombre completo contiene ambos nombres
        $this->assertStringContainsString(
            'Pedro',
            $contact_data['name'],
            'El nombre completo debe contener el primer nombre'
        );
        
        $this->assertStringContainsString(
            'Martínez López',
            $contact_data['name'],
            'El nombre completo debe contener el apellido'
        );
    }

    /**
     * Test 10: Verificar que el país se obtiene correctamente
     */
    public function test_build_contact_data_gets_country_name() {
        $order = $this->create_test_order([
            'billing_country' => 'CO',
        ]);
        
        $dni = '1234567890';
        $type_document = 'CC';
        $dv_nit = null;
        
        // Usar reflexión
        $reflection = new ReflectionClass( 'Integration_Alegra_WC' );
        $method = $reflection->getMethod( 'build_contact_data' );
        $method->setAccessible( true );
        
        // Ejecutar el método
        $contact_data = $method->invokeArgs( null, [ $order, $dni, $type_document, $dv_nit ] );
        
        // Verificar que el país se convirtió a nombre
        $this->assertArrayHasKey(
            'country',
            $contact_data['address'],
            'Debe tener el campo country en address'
        );
        
        // El nombre del país debería ser "Colombia" o similar
        $this->assertNotEmpty(
            $contact_data['address']['country'],
            'El nombre del país no debería estar vacío'
        );
    }
}

