<?php
/**
 * Class Test_Integration_Alegra_WC
 *
 * @package Integration_Alegra_Woo
 */

/**
 * Test del método calculate_dv para cálculo de dígito de verificación de NITs colombianos
 */
class Test_Integration_Alegra_WC extends WP_UnitTestCase {

    /**
     * Test del cálculo del dígito de verificación (DV) para NITs colombianos
     *
     * El algoritmo de la DIAN utiliza números primos para validar NITs.
     * Este test verifica que el cálculo se realice correctamente con casos reales.
     *
     * @dataProvider provider_nit_dv_cases
     *
     * @param string $nit NIT sin dígito de verificación
     * @param int $expected_dv Dígito de verificación esperado
     */
    public function test_calculate_dv( $nit, $expected_dv ) {
        $result = Integration_Alegra_WC::calculate_dv( $nit );

        $this->assertSame(
            $expected_dv,
            $result,
            sprintf(
                'El DV calculado para el NIT %s debería ser %d, pero se obtuvo %d',
                $nit,
                $expected_dv,
                $result
            )
        );
    }

    /**
     * Proveedor de datos para test de cálculo de DV
     *
     * Casos de prueba con NITs reales colombianos y sus DV correspondientes
     * Estos han sido validados con el algoritmo oficial de la DIAN
     *
     * @return array Array de casos de prueba [descripción => [NIT, DV esperado]]
     */
    public function provider_nit_dv_cases() {
        return [
            // NITs de empresas colombianas reales
            'NIT 890903938 (DV 8)' => [ '890903938', 8 ],
            'NIT 800197268 (DV 4)' => [ '800197268', 4 ],
            'NIT 860028415 (DV 5)' => [ '860028415', 5 ],
            'NIT 900123456 (DV 8)' => [ '900123456', 8 ],
            'NIT 830037248 (DV 0)' => [ '830037248', 0 ],

            // NITs de diferentes longitudes
            'NIT corto 12345 (DV 8)'     => [ '12345', 8 ],
            'NIT medio 1234567 (DV 2)'   => [ '1234567', 2 ],
            'NIT largo 901234567890 (DV 9)' => [ '901234567890', 9 ],

            // Casos especiales
            'Todos ceros'   => [ '000000000', 0 ],
            'Todos unos'    => [ '111111111', 9 ],
            'Todos nueves'  => [ '999999999', 4 ],

            // NITs con diferentes patrones
            'NIT 890900608 (DV 9)' => [ '890900608', 9 ],
            'NIT 860034313 (DV 7)' => [ '860034313', 7 ],
            'NIT 900345678 (DV 9)' => [ '900345678', 9 ],
        ];
    }

    /**
     * Test que verifica que calculate_dv retorna un entero
     *
     * El DV debe ser siempre un número entero entre 0 y 9
     */
    public function test_calculate_dv_returns_integer() {
        $result = Integration_Alegra_WC::calculate_dv( '890903938' );

        $this->assertIsInt(
            $result,
            'calculate_dv debe retornar un entero, no una cadena o flotante'
        );
    }

    /**
     * Test que verifica que el DV está en el rango válido (0-9)
     *
     * Según el algoritmo de la DIAN, el DV siempre debe estar entre 0 y 9
     */
    public function test_calculate_dv_returns_valid_range() {
        $result = Integration_Alegra_WC::calculate_dv( '890903938' );

        $this->assertGreaterThanOrEqual(
            0,
            $result,
            'El DV debe ser mayor o igual a 0'
        );

        $this->assertLessThanOrEqual(
            9,
            $result,
            'El DV debe ser menor o igual a 9'
        );
    }

    /**
     * Test de consistencia: el mismo NIT debe retornar siempre el mismo DV
     *
     * Esta es una propiedad fundamental: el algoritmo debe ser determinístico
     */
    public function test_calculate_dv_consistency() {
        $nit = '890903938';

        $result1 = Integration_Alegra_WC::calculate_dv( $nit );
        $result2 = Integration_Alegra_WC::calculate_dv( $nit );
        $result3 = Integration_Alegra_WC::calculate_dv( $nit );

        $this->assertSame(
            $result1,
            $result2,
            'calculate_dv debe retornar el mismo resultado para el mismo NIT (comparación 1-2)'
        );

        $this->assertSame(
            $result2,
            $result3,
            'calculate_dv debe retornar el mismo resultado para el mismo NIT (comparación 2-3)'
        );
    }

    /**
     * Test con NIT de diferentes longitudes
     *
     * El algoritmo debe funcionar correctamente con NITs de cualquier longitud válida
     *
     * @dataProvider provider_nit_different_lengths
     *
     * @param string $nit NIT de prueba
     */
    public function test_calculate_dv_different_lengths( $nit ) {
        $result = Integration_Alegra_WC::calculate_dv( $nit );

        $this->assertIsInt(
            $result,
            sprintf( 'Debe calcular DV para NIT de longitud %d', strlen( $nit ) )
        );

        $this->assertGreaterThanOrEqual( 0, $result );
        $this->assertLessThanOrEqual( 9, $result );
    }

    /**
     * Proveedor de NITs con diferentes longitudes
     *
     * @return array Array de casos de prueba con NITs de distintas longitudes
     */
    public function provider_nit_different_lengths() {
        return [
            'Longitud 5 dígitos'  => [ '12345' ],
            'Longitud 7 dígitos'  => [ '1234567' ],
            'Longitud 9 dígitos'  => [ '890903938' ],
            'Longitud 10 dígitos' => [ '9001234567' ],
            'Longitud 12 dígitos' => [ '901234567890' ],
            'Longitud 15 dígitos' => [ '123456789012345' ],
        ];
    }

    /**
     * Test con múltiples NITs para verificar que todos producen DVs válidos
     *
     * Prueba masiva con varios NITs para asegurar robustez
     */
    public function test_calculate_dv_multiple_nits_valid_range() {
        $nits = [
            '890903938',
            '800197268',
            '860028415',
            '900123456',
            '830037248',
            '890900608',
            '860034313',
            '900345678',
            '12345',
            '999999999',
        ];

        foreach ( $nits as $nit ) {
            $result = Integration_Alegra_WC::calculate_dv( $nit );

            $this->assertIsInt( $result, "DV para NIT {$nit} debe ser entero" );
            $this->assertGreaterThanOrEqual( 0, $result, "DV para NIT {$nit} debe ser >= 0" );
            $this->assertLessThanOrEqual( 9, $result, "DV para NIT {$nit} debe ser <= 9" );
        }
    }

    /**
     * Test del algoritmo con casos donde DV es 0
     *
     * El DV 0 es un caso especial que debe manejarse correctamente
     */
    public function test_calculate_dv_when_result_is_zero() {
        $nits_with_zero_dv = [
            '830037248',
            '000000000',
        ];

        foreach ( $nits_with_zero_dv as $nit ) {
            $result = Integration_Alegra_WC::calculate_dv( $nit );

            $this->assertSame(
                0,
                $result,
                "El NIT {$nit} debe tener DV 0"
            );
        }
    }

    /**
     * Test con NITs que contienen ceros a la izquierda
     *
     * Verifica que el algoritmo maneje correctamente NITs con ceros al inicio
     */
    public function test_calculate_dv_with_leading_zeros() {
        // PHP puede eliminar ceros a la izquierda si se trata como número
        // Debe mantenerse como string
        $nit = '000123456';
        $result = Integration_Alegra_WC::calculate_dv( $nit );

        $this->assertIsInt( $result );
        $this->assertGreaterThanOrEqual( 0, $result );
        $this->assertLessThanOrEqual( 9, $result );
    }

    /**
     * Test con NITs secuenciales
     *
     * Verifica que NITs secuenciales producen diferentes DVs (generalmente)
     */
    public function test_calculate_dv_sequential_nits_produce_different_dvs() {
        $dv1 = Integration_Alegra_WC::calculate_dv( '890903937' );
        $dv2 = Integration_Alegra_WC::calculate_dv( '890903938' );
        $dv3 = Integration_Alegra_WC::calculate_dv( '890903939' );

        // Los DVs no deberían ser todos iguales
        $all_same = ( $dv1 === $dv2 && $dv2 === $dv3 );

        $this->assertFalse(
            $all_same,
            'NITs secuenciales generalmente deberían producir diferentes DVs'
        );
    }
}
