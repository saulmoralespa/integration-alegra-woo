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