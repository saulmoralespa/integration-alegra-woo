(function($){
    $('button.integration-alegra-print-invoice').click(function (e) {

        e.preventDefault();

        $.ajax({
            data: {
                action: 'integration_alegra_print_invoice',
                nonce: $(this).data("nonce"),
                invoice_id: $(this).data("invoice-id")
            },
            type: 'POST',
            url: ajaxurl,
            dataType: "json",
            beforeSend : () => {
                Swal.fire({
                    title: 'Generando enlace del PDF',
                    didOpen: () => {
                        Swal.showLoading()
                    },
                    allowOutsideClick: false
                });
            },
            success: (r) => {
                if (r.status){
                    Swal.fire({
                        icon: 'success',
                        html: `<a target="_blank" href="${r.url}">Abrir PDF</a>`,
                        allowOutsideClick: false,
                        showCloseButton: true,
                        showConfirmButton: false
                    })
                }else{
                    Swal.fire(
                        'Error',
                        r.message,
                        'error'
                    );
                }
            }
        });
    });
})(jQuery);

// =============================================================================
// Premium Survey Modal — Integration Alegra WooCommerce
// =============================================================================
(function ($) {

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------
    const premiumSurveyButton              = 'button.alegra-send-premium-survey';
    const actionSendPremiumSurvey          = 'integration_alegra_send_premium_survey';
    const actionDismissPremiumSurveyNotice = 'integration_alegra_dismiss_premium_survey_notice';

    // -------------------------------------------------------------------------
    // Question options
    // -------------------------------------------------------------------------
    const premiumSurveyOptions = {
        q2: [
            { value: '', label: '— Selecciona —' },
            { value: 'facturacion',        label: 'Problemas con la facturación automática' },
            { value: 'sincronizacion',     label: 'Sincronización lenta o incompleta de productos/pedidos' },
            { value: 'configuracion',      label: 'Configuración compleja o poco intuitiva' },
            { value: 'soporte',            label: 'Falta de soporte técnico oportuno' },
            { value: 'funcionalidades',    label: 'Faltan funcionalidades que necesito' },
            { value: 'otro',               label: 'Otro' },
        ],
        q3: [
            { value: '', label: '— Selecciona —' },
            { value: 'menos_1h',  label: 'Menos de 1 hora' },
            { value: '1_3h',      label: 'Entre 1 y 3 horas' },
            { value: '3_5h',      label: 'Entre 3 y 5 horas' },
            { value: 'mas_5h',    label: 'Más de 5 horas' },
        ],
        q4: [
            { value: 'webhooks',       label: 'Webhooks: sincronización automática entre Alegra y WooCommerce' },
            { value: 'notas_credito',  label: 'Notas de crédito automáticas' },
            { value: 'cotizaciones',   label: 'Cotizaciones desde WooCommerce' },
            { value: 'reportes',       label: 'Reportes y alertas inteligentes' },
            { value: 'otras',          label: 'Otras funcionalidades (especificar abajo)' },
        ],
        q6: [
            { value: '', label: '— Selecciona —' },
            { value: 'mensual',    label: 'Pago mensual recurrente' },
            { value: 'anual',      label: 'Pago anual (descuento)' },
            { value: 'pago_unico', label: 'Pago único (licencia permanente)' },
        ],
        q7: [
            { value: '', label: '— Selecciona —' },
            { value: '30000_49000',    label: '$30.000 – $49.000 COP/mes' },
            { value: '50000_99000',    label: '$50.000 – $99.000 COP/mes' },
            { value: '100000_199000',  label: '$100.000 – $199.000 COP/mes' },
            { value: '200000_mas',     label: '$200.000 o más COP/mes' },
        ],
    };

    // -------------------------------------------------------------------------
    // Build HTML
    // -------------------------------------------------------------------------
    function buildPremiumSurveyHtml() {
        const select = (id, opts, required = false) =>
            `<select id="${id}" style="width:100%;margin-top:4px" ${required ? 'required' : ''}>
                ${opts.map(o => `<option value="${o.value}">${o.label}</option>`).join('')}
             </select>`;

        const checkboxes = (opts) =>
            opts.map(o =>
                `<label style="display:block;margin:4px 0">
                    <input type="checkbox" class="swal2-checkbox" name="q4_top_features[]" value="${o.value}"> ${o.label}
                 </label>`
            ).join('');

        return `
<div style="text-align:left;font-size:14px">

  <p style="margin-bottom:16px">Tus respuestas nos ayudan a construir la versión premium que realmente necesitas. 🙏</p>

  <!-- Q1: Satisfacción -->
  <div class="swal2-input-wrapper" style="margin-bottom:12px">
    <label for="q1_score"><strong>1. ¿Qué tan satisfecho estás con el plugin actual? (1 = muy insatisfecho, 10 = muy satisfecho) *</strong></label>
    ${select('q1_score', Array.from({length:10}, (_,i) => ({value: String(i+1), label: String(i+1)})))}
  </div>

  <!-- Q1-motivo: solo visible si score < 8 -->
  <div id="q1_motivo_wrapper" style="margin-bottom:12px;display:none">
    <label for="q1_motivo"><strong>¿Por qué diste esa puntuación? (requerido) *</strong></label>
    <textarea id="q1_motivo" style="width:100%;margin-top:4px" rows="3" maxlength="500" placeholder="Cuéntanos qué podemos mejorar..."></textarea>
  </div>

  <!-- Q2: Pain point -->
  <div class="swal2-input-wrapper" style="margin-bottom:12px">
    <label for="q2_pain_point"><strong>2. ¿Cuál es tu principal dolor con la integración actual?</strong></label>
    ${select('q2_pain_point', premiumSurveyOptions.q2)}
  </div>

  <!-- Q3: Time loss -->
  <div class="swal2-input-wrapper" style="margin-bottom:12px">
    <label for="q3_time_loss"><strong>3. ¿Cuánto tiempo semanal pierdes en tareas manuales de facturación/contabilidad?</strong></label>
    ${select('q3_time_loss', premiumSurveyOptions.q3)}
  </div>

  <!-- Q4: Top features (checkboxes, max 3) -->
  <div style="margin-bottom:12px">
    <label><strong>4. ¿Cuáles de estas funcionalidades valorarías más en la versión premium? (máximo 3) *</strong></label>
    <div id="q4_top_features" style="margin-top:6px">
      ${checkboxes(premiumSurveyOptions.q4)}
    </div>
  </div>

  <!-- Q5: Other feature -->
  <div class="swal2-input-wrapper" style="margin-bottom:12px">
    <label for="q5_other_feature"><strong>5. Si seleccionaste "Otras", ¿cuál funcionalidad necesitas?</strong></label>
    <input id="q5_other_feature" type="text" style="width:100%;margin-top:4px" maxlength="200" placeholder="Describe la funcionalidad...">
  </div>

  <!-- Q6: Billing model -->
  <div class="swal2-input-wrapper" style="margin-bottom:12px">
    <label for="q6_billing_model"><strong>6. ¿Qué modelo de cobro prefieres para la versión premium?</strong></label>
    ${select('q6_billing_model', premiumSurveyOptions.q6)}
  </div>

  <!-- Q7: Price range -->
  <div class="swal2-input-wrapper" style="margin-bottom:12px">
    <label for="q7_price_range"><strong>7. ¿Cuánto estarías dispuesto a pagar mensualmente? *</strong></label>
    ${select('q7_price_range', premiumSurveyOptions.q7, true)}
  </div>

  <!-- Q8: Open feedback -->
  <div class="swal2-input-wrapper" style="margin-bottom:12px">
    <label for="q8_open_feedback"><strong>8. ¿Algo más que quieras compartirnos?</strong></label>
    <textarea id="q8_open_feedback" style="width:100%;margin-top:4px" rows="3" maxlength="1000" placeholder="Comentarios adicionales..."></textarea>
  </div>

  <!-- Consent -->
  <div style="margin-bottom:12px">
    <label>
      <input type="checkbox" id="consent_yes_no" class="swal2-checkbox">
      Autorizo que el equipo de Alegra WooCommerce me contacte para profundizar en mis respuestas.
    </label>
  </div>

  <p style="font-size:12px;color:#666">* Campos obligatorios</p>
</div>`;
    }

    // -------------------------------------------------------------------------
    // Collect and validate
    // -------------------------------------------------------------------------
    function collectPremiumSurveyData() {
        const q1Score = parseInt($('#q1_score').val(), 10);

        if (isNaN(q1Score) || q1Score < 1 || q1Score > 10) {
            Swal.showValidationMessage('La satisfacción debe ser un valor entre 1 y 10.');
            return false;
        }

        if (q1Score < 8) {
            const motivo = $('#q1_motivo').val().trim();
            if (!motivo) {
                Swal.showValidationMessage('Por favor explica el motivo de tu baja satisfacción.');
                return false;
            }
        }

        const selectedFeatures = [];
        $('#q4_top_features input[type="checkbox"]:checked').each(function () {
            selectedFeatures.push($(this).val());
        });

        if (selectedFeatures.length === 0) {
            Swal.showValidationMessage('Selecciona al menos una funcionalidad premium.');
            return false;
        }

        if (selectedFeatures.length > 3) {
            Swal.showValidationMessage('Selecciona máximo 3 funcionalidades.');
            return false;
        }

        const q7PriceRange = $('#q7_price_range').val();
        if (!q7PriceRange) {
            Swal.showValidationMessage('Selecciona un rango de precio.');
            return false;
        }

        return {
            q1_score:         q1Score,
            q1_motivo:        $('#q1_motivo').val().trim(),
            q2_pain_point:    $('#q2_pain_point').val(),
            q3_time_loss:     $('#q3_time_loss').val(),
            q4_top_features:  JSON.stringify(selectedFeatures),
            q5_other_feature: $('#q5_other_feature').val().trim(),
            q6_billing_model: $('#q6_billing_model').val(),
            q7_price_range:   q7PriceRange,
            q8_open_feedback: $('#q8_open_feedback').val().trim(),
            consent_yes_no:   $('#consent_yes_no').is(':checked') ? 'yes' : 'no',
        };
    }

    // -------------------------------------------------------------------------
    // Send survey via AJAX
    // -------------------------------------------------------------------------
    function sendPremiumSurveyResponse(nonce, surveyData) {
        return $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: Object.assign({}, surveyData, {
                action: actionSendPremiumSurvey,
                nonce,
            }),
        });
    }

    // -------------------------------------------------------------------------
    // Open modal
    // -------------------------------------------------------------------------
    function openPremiumSurveyModal(nonce) {
        Swal.fire({
            title: 'Encuesta Premium — Integration Alegra',
            html: buildPremiumSurveyHtml(),
            width: '680px',
            showCancelButton: true,
            confirmButtonText: 'Enviar respuesta',
            cancelButtonText: 'Cancelar',
            allowOutsideClick: false,
            showLoaderOnConfirm: true,
            didOpen: () => {
                // Show/hide q1_motivo based on q1_score
                $('#q1_score').on('change', function () {
                    const score = parseInt($(this).val(), 10);
                    if (score < 8) {
                        $('#q1_motivo_wrapper').show();
                    } else {
                        $('#q1_motivo_wrapper').hide();
                        $('#q1_motivo').val('');
                    }
                });

                // Enforce max 3 checkboxes
                $(document).on('change', '#q4_top_features input[type="checkbox"]', function () {
                    const checked = $('#q4_top_features input[type="checkbox"]:checked');
                    if (checked.length > 3) {
                        $(this).prop('checked', false);
                        Swal.showValidationMessage('Solo puedes seleccionar un máximo de 3 funcionalidades.');
                    }
                });
            },
            preConfirm: () => {
                return collectPremiumSurveyData();
            },
        }).then((result) => {
            if (!result.isConfirmed || !result.value) return;

            Swal.fire({
                title: 'Enviando respuesta…',
                didOpen: () => Swal.showLoading(),
                allowOutsideClick: false,
            });

            sendPremiumSurveyResponse(nonce, result.value)
                .done((r) => {
                    if (r.status) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Gracias!',
                            text: r.message || 'Tu respuesta fue enviada correctamente.',
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: r.message || 'No fue posible enviar la respuesta.',
                        });
                    }
                })
                .fail(() => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de conexión',
                        text: 'No fue posible conectar con el servidor. Intenta nuevamente.',
                    });
                });
        });
    }

    // -------------------------------------------------------------------------
    // Auto-open from URL (?open_premium_survey=1)
    // -------------------------------------------------------------------------
    function maybeOpenPremiumSurveyFromUrl() {
        const params = new URLSearchParams(window.location.search);
        if (params.get('open_premium_survey') !== '1') return;

        const $btn = $(premiumSurveyButton).first();
        if (!$btn.length) return;

        openPremiumSurveyModal($btn.data('nonce'));
    }

    // -------------------------------------------------------------------------
    // Dismiss notice via AJAX on WP dismiss button click
    // -------------------------------------------------------------------------
    function persistPremiumSurveyNoticeDismiss() {
        $(document).on('click', '.alegra-premium-survey-notice .notice-dismiss', function () {
            const nonce = $(this).closest('.alegra-premium-survey-notice').data('dismiss-nonce');
            if (!nonce) return;

            $.post(ajaxurl, {
                action: actionDismissPremiumSurveyNotice,
                nonce,
            });
        });
    }

    // -------------------------------------------------------------------------
    // Event listeners
    // -------------------------------------------------------------------------
    $(document).on('click', premiumSurveyButton, function (e) {
        e.preventDefault();
        openPremiumSurveyModal($(this).data('nonce'));
    });

    $(document).ready(function () {
        maybeOpenPremiumSurveyFromUrl();
    });
    persistPremiumSurveyNoticeDismiss();

})(jQuery);
