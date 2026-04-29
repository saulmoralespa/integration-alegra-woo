/**
 * Admin Payment Mappings — Integration Alegra WooCommerce
 *
 * Resalta el campo "Forma de pago" cuando el "Tipo de pago" es CASH y el campo
 * está vacío. Deshabilita el botón "Guardar cambios" mientras exista al menos
 * una fila con esa combinación inválida (aplica a gateways activos e inactivos
 * que aparezcan en la tabla).
 */
(function ($) {
    'use strict';

    var TABLE    = '#alegra-payment-mappings-table';
    var SAVE_BTN = 'button.woocommerce-save-button';
    var CASH_TYPE = (typeof alegraPaymentMappings !== 'undefined') ? alegraPaymentMappings.cashType : 'CASH';

    // ── Estilos ──────────────────────────────────────────────────────────────

    $('<style>').text(
        '#alegra-mapping-errors{' +
            'background:#fcf0f1;border-left:4px solid #d63638;' +
            'padding:10px 14px;margin:0 0 10px;border-radius:2px' +
        '}' +
        '#alegra-mapping-errors p{margin:4px 0;color:#d63638;font-weight:600}' +
        '#alegra-mapping-errors ul{margin:4px 0 0 18px;list-style:disc;color:#50575e}' +
        'select.alegra-field-error{' +
            'outline:2px solid #d63638 !important;' +
            'border-color:#d63638 !important' +
        '}'
    ).appendTo('head');

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Escapa HTML para usar en literales de innerHTML.
     */
    function escHtml(str) {
        return $('<div>').text(str).html();
    }

    /**
     * Recorre todas las filas de la tabla y devuelve un array con información
     * de las filas inválidas (CASH + Forma de pago vacía).
     * Como efecto secundario, elimina el marcado de error de las filas válidas.
     *
     * @returns {Array<{id: string, label: string, $methodSelect: jQuery}>}
     */
    function collectInvalidRows() {
        var errors = [];

        $(TABLE + ' tbody tr[data-alegra-gateway-id]').each(function () {
            var $row          = $(this);
            var $typeSelect   = $row.find('select[data-alegra-field="payment_type"]');
            var $methodSelect = $row.find('select[data-alegra-field="payment_method"]');

            var isCash  = $typeSelect.val() === CASH_TYPE;
            var isEmpty = $methodSelect.val() === '';

            if (isCash && isEmpty) {
                var label = $row.data('alegra-gateway-label') || $row.data('alegra-gateway-id');
                errors.push({ id: $row.data('alegra-gateway-id'), label: label, $methodSelect: $methodSelect });
            } else {
                // Fila válida: limpiar marcado de error previo.
                $methodSelect.removeClass('alegra-field-error').removeAttr('aria-invalid');
            }
        });

        return errors;
    }

    /**
     * Ejecuta validación completa:
     * - Resalta selects inválidos con clase de error y aria-invalid.
     * - Muestra/oculta el bloque de mensaje detallado.
     * - Habilita/deshabilita el botón Guardar cambios.
     */
    function validate() {
        var errors   = collectInvalidRows();
        var $errorBox = $('#alegra-mapping-errors');

        if (errors.length > 0) {
            // Marcar cada select inválido.
            $.each(errors, function (i, e) {
                e.$methodSelect.addClass('alegra-field-error').attr('aria-invalid', 'true');
            });

            // Construir mensaje detallado listando gateways con error.
            var items = $.map(errors, function (e) {
                return '<li>' +
                    '<strong>' + escHtml(e.label) + '</strong>' +
                    ': la Forma de pago es obligatoria cuando el Tipo de pago es Contado (CASH).' +
                    '</li>';
            }).join('');

            var html = '<p>No se pueden guardar los cambios. Completa los campos requeridos:</p>' +
                       '<ul>' + items + '</ul>';

            if ($errorBox.length === 0) {
                $errorBox = $('<div id="alegra-mapping-errors">').insertBefore(TABLE);
            }
            $errorBox.html(html);

            $(SAVE_BTN).prop('disabled', true);

        } else {
            // Sin errores: limpiar todo y habilitar guardado.
            $errorBox.remove();
            $(SAVE_BTN).prop('disabled', false);
        }
    }

    // ── Inicialización ────────────────────────────────────────────────────────

    $(function () {
        if ($(TABLE).length === 0) {
            return; // Tabla no presente en esta pantalla.
        }

        // Validación inicial: cubre el caso de datos ya guardados en estado inválido.
        validate();

        // Re-validar en cualquier cambio de tipo o forma de pago en la tabla.
        $(document).on(
            'change',
            TABLE + ' select[data-alegra-field="payment_type"],' +
            TABLE + ' select[data-alegra-field="payment_method"]',
            validate
        );
    });

})(jQuery);
