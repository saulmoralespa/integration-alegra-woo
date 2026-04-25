<?php
/**
 * Class Test_Premium_Survey
 *
 * @package Integration_Alegra_Woo
 */

/**
 * Tests de encuesta premium (Premium Survey)
 *
 * Prueba la lógica de visibilidad, dismiss, validación de payload y envío de
 * la encuesta premium para Integration Alegra WooCommerce.
 * Todos los métodos bajo test son privados/internos de Integration_Alegra_WC_Plugin;
 * se acceden vía ReflectionClass cuando sea necesario.
 */
class Test_Premium_Survey extends WP_UnitTestCase {

    /** @var Integration_Alegra_WC_Plugin */
    private Integration_Alegra_WC_Plugin $plugin;

    /** @var ReflectionClass */
    private ReflectionClass $reflection;

    /**
     * Configuración inicial para cada test.
     */
    public function setUp(): void {
        parent::setUp();

        delete_option( 'woocommerce_wc_alegra_integration_settings' );

        // Limpiar user_meta del usuario de prueba
        $user_id = get_current_user_id();
        if ( $user_id ) {
            delete_user_meta( $user_id, 'integration_alegra_premium_survey_notice_dismissed' );
        }

        $this->plugin     = integration_alegra_wc_smp();
        $this->reflection = new ReflectionClass( Integration_Alegra_WC_Plugin::class );
    }

    /**
     * Limpieza después de cada test.
     */
    public function tearDown(): void {
        parent::tearDown();

        delete_option( 'woocommerce_wc_alegra_integration_settings' );

        $user_id = get_current_user_id();
        if ( $user_id ) {
            delete_user_meta( $user_id, 'integration_alegra_premium_survey_notice_dismissed' );
        }
    }

    // -------------------------------------------------------------------------
    // Helpers de reflexión
    // -------------------------------------------------------------------------

    private function call_private( string $method, array $args = [] ): mixed {
        $m = $this->reflection->getMethod( $method );
        $m->setAccessible( true );
        return $m->invokeArgs( $this->plugin, $args );
    }

    // -------------------------------------------------------------------------
    // Grupo 1: has_survey_eligible_store
    // -------------------------------------------------------------------------

    /**
     * Test 1: Tienda sin credenciales → no elegible.
     */
    public function test_has_survey_eligible_store_returns_false_without_credentials() {
        update_option( 'woocommerce_wc_alegra_integration_settings', [
            'enabled' => 'yes',
            'user'    => '',
            'token'   => '',
        ] );

        $result = $this->call_private( 'has_survey_eligible_store' );

        $this->assertFalse(
            $result,
            'Sin credenciales la tienda no debería ser elegible para la encuesta'
        );
    }

    /**
     * Test 2: Integración deshabilitada → no elegible.
     */
    public function test_has_survey_eligible_store_returns_false_when_disabled() {
        update_option( 'woocommerce_wc_alegra_integration_settings', [
            'enabled' => 'no',
            'user'    => 'admin@test.com',
            'token'   => 'abc123',
        ] );

        $result = $this->call_private( 'has_survey_eligible_store' );

        $this->assertFalse(
            $result,
            'Con integración deshabilitada la tienda no debería ser elegible para la encuesta'
        );
    }

    /**
     * Test 3: Integración habilitada con credenciales → elegible.
     */
    public function test_has_survey_eligible_store_returns_true_with_credentials_and_enabled() {
        update_option( 'woocommerce_wc_alegra_integration_settings', [
            'enabled' => 'yes',
            'user'    => 'admin@test.com',
            'token'   => 'abc123',
        ] );

        $result = $this->call_private( 'has_survey_eligible_store' );

        $this->assertTrue(
            $result,
            'Con integración habilitada y credenciales la tienda debería ser elegible'
        );
    }

    // -------------------------------------------------------------------------
    // Grupo 2: has_user_dismissed_premium_survey_notice
    // -------------------------------------------------------------------------

    /**
     * Test 4: Sin user_meta → notice no dismissida.
     */
    public function test_has_user_dismissed_returns_false_by_default() {
        $result = $this->call_private( 'has_user_dismissed_premium_survey_notice' );

        $this->assertFalse(
            $result,
            'El usuario no debería haber dismissado el notice por defecto'
        );
    }

    /**
     * Test 5: Con user_meta = 'yes' → notice dismissida.
     */
    public function test_has_user_dismissed_returns_true_when_meta_set() {
        $user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $user_id );

        update_user_meta( $user_id, 'integration_alegra_premium_survey_notice_dismissed', 'yes' );

        $result = $this->call_private( 'has_user_dismissed_premium_survey_notice' );

        $this->assertTrue(
            $result,
            'El usuario que ha dismissado el notice debería retornar true'
        );

        wp_set_current_user( 0 );
    }

    // -------------------------------------------------------------------------
    // Grupo 3: Validación de payload del survey
    // -------------------------------------------------------------------------

    /**
     * Arma un payload válido completo para reutilizar en tests de validación.
     */
    private function valid_survey_payload( array $overrides = [] ): array {
        return array_merge( [
            'q1_score'         => 9,
            'q1_motivo'        => '',
            'q2_pain_point'    => 'facturacion',
            'q3_time_loss'     => '1_3h',
            'q4_top_features'  => [ 'webhooks', 'notas_credito', 'cotizaciones' ],
            'q5_other_feature' => '',
            'q6_billing_model' => 'mensual',
            'q7_price_range'   => '50000_99000',
            'q8_open_feedback' => '',
            'consent_yes_no'   => 'no',
        ], $overrides );
    }

    /**
     * Test 6: q1_score = 0 (por debajo del rango) → inválido.
     */
    public function test_validate_survey_payload_rejects_q1_zero() {
        $payload = $this->valid_survey_payload( [ 'q1_score' => 0 ] );

        $result = $this->call_private( 'validate_survey_payload', [ $payload ] );

        $this->assertFalse(
            $result['valid'],
            'q1_score = 0 debe ser rechazado'
        );
    }

    /**
     * Test 7: q1_score = 11 (por encima del rango) → inválido.
     */
    public function test_validate_survey_payload_rejects_q1_above_ten() {
        $payload = $this->valid_survey_payload( [ 'q1_score' => 11 ] );

        $result = $this->call_private( 'validate_survey_payload', [ $payload ] );

        $this->assertFalse(
            $result['valid'],
            'q1_score = 11 debe ser rechazado'
        );
    }

    /**
     * Test 8: q1_score < 8 y motivo vacío → inválido.
     */
    public function test_validate_survey_payload_requires_motivo_when_q1_less_than_8() {
        $payload = $this->valid_survey_payload( [
            'q1_score'  => 5,
            'q1_motivo' => '',
        ] );

        $result = $this->call_private( 'validate_survey_payload', [ $payload ] );

        $this->assertFalse(
            $result['valid'],
            'Con q1_score < 8 y motivo vacío el payload debe ser inválido'
        );
    }

    /**
     * Test 9: q1_score < 8 y motivo diligenciado → válido respecto a esa regla.
     */
    public function test_validate_survey_payload_accepts_motivo_when_q1_less_than_8() {
        $payload = $this->valid_survey_payload( [
            'q1_score'  => 6,
            'q1_motivo' => 'El plugin no sincroniza bien los productos',
        ] );

        $result = $this->call_private( 'validate_survey_payload', [ $payload ] );

        $this->assertTrue(
            $result['valid'],
            'Con q1_score < 8 y motivo diligenciado el payload debe ser válido'
        );
    }

    /**
     * Test 10: q1_score >= 8 y motivo vacío → válido (motivo no es requerido).
     */
    public function test_validate_survey_payload_does_not_require_motivo_when_q1_gte_8() {
        $payload = $this->valid_survey_payload( [
            'q1_score'  => 8,
            'q1_motivo' => '',
        ] );

        $result = $this->call_private( 'validate_survey_payload', [ $payload ] );

        $this->assertTrue(
            $result['valid'],
            'Con q1_score >= 8 el motivo no debe ser requerido'
        );
    }

    /**
     * Test 11: Sin funcionalidades seleccionadas → inválido.
     */
    public function test_validate_survey_payload_requires_at_least_one_feature() {
        $payload = $this->valid_survey_payload( [ 'q4_top_features' => [] ] );

        $result = $this->call_private( 'validate_survey_payload', [ $payload ] );

        $this->assertFalse(
            $result['valid'],
            'Sin funcionalidades seleccionadas el payload debe ser inválido'
        );
    }

    /**
     * Test 12: Más de 3 funcionalidades → se limita a 3 y el payload es válido.
     */
    public function test_validate_survey_payload_truncates_features_to_three() {
        $payload = $this->valid_survey_payload( [
            'q4_top_features' => [ 'webhooks', 'notas_credito', 'cotizaciones', 'inventario', 'soporte' ],
        ] );

        $result = $this->call_private( 'validate_survey_payload', [ $payload ] );

        $this->assertTrue(
            $result['valid'],
            'Con más de 3 funcionalidades el payload debe ser válido después de truncar'
        );
        $this->assertCount(
            3,
            $result['data']['q4_top_features'],
            'Deben quedar exactamente 3 funcionalidades'
        );
    }

    /**
     * Test 13: Sin rango de precio → inválido.
     */
    public function test_validate_survey_payload_requires_price_range() {
        $payload = $this->valid_survey_payload( [ 'q7_price_range' => '' ] );

        $result = $this->call_private( 'validate_survey_payload', [ $payload ] );

        $this->assertFalse(
            $result['valid'],
            'Sin rango de precio el payload debe ser inválido'
        );
    }

    /**
     * Test 14: Payload completamente válido → retorna valid = true con datos saneados.
     */
    public function test_validate_survey_payload_returns_valid_for_complete_payload() {
        $payload = $this->valid_survey_payload();

        $result = $this->call_private( 'validate_survey_payload', [ $payload ] );

        $this->assertTrue(
            $result['valid'],
            'Un payload completo válido debe retornar valid = true'
        );
        $this->assertArrayHasKey(
            'data',
            $result,
            'El resultado debe contener la clave data'
        );
    }

    /**
     * Test 15: q4_top_features como string JSON → se parsea correctamente.
     */
    public function test_validate_survey_payload_parses_features_from_json_string() {
        $payload = $this->valid_survey_payload( [
            'q4_top_features' => json_encode( [ 'webhooks', 'notas_credito' ] ),
        ] );

        $result = $this->call_private( 'validate_survey_payload', [ $payload ] );

        $this->assertTrue( $result['valid'], 'Debe parsear q4_top_features desde string JSON' );
        $this->assertCount( 2, $result['data']['q4_top_features'] );
    }

    /**
     * Test 16: q4_top_features como string CSV → se parsea correctamente.
     */
    public function test_validate_survey_payload_parses_features_from_csv_string() {
        $payload = $this->valid_survey_payload( [
            'q4_top_features' => 'webhooks, notas_credito',
        ] );

        $result = $this->call_private( 'validate_survey_payload', [ $payload ] );

        $this->assertTrue( $result['valid'], 'Debe parsear q4_top_features desde string CSV' );
        $this->assertCount( 2, $result['data']['q4_top_features'] );
    }

    /**
     * Test 17: q1_motivo excesivamente largo se trunca a 500 chars.
     */
    public function test_validate_survey_payload_sanitizes_motivo_length() {
        $long_motivo = str_repeat( 'x', 700 );
        $payload     = $this->valid_survey_payload( [
            'q1_score'  => 3,
            'q1_motivo' => $long_motivo,
        ] );

        $result = $this->call_private( 'validate_survey_payload', [ $payload ] );

        $this->assertTrue( $result['valid'] );
        $this->assertLessThanOrEqual(
            500,
            strlen( $result['data']['q1_motivo'] ),
            'El motivo debe ser truncado a 500 caracteres'
        );
    }
}
