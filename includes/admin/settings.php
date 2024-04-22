<?php

wc_enqueue_js( "
    jQuery( function( $ ) {
    if($( '#woocommerce_wc_alegra_integration_token' ).val()){
        $( '#woocommerce_wc_alegra_integration_seller_generate_invoice, #woocommerce_wc_alegra_integration_seller_generate_invoice + p' ).show();
        $( '#woocommerce_wc_alegra_integration_seller_generate_invoice' ).closest( 'tr' ).show();
    }else{
        $( '#woocommerce_wc_alegra_integration_seller_generate_invoice, #woocommerce_wc_alegra_integration_seller_generate_invoice + p' ).hide();
		$( '#woocommerce_wc_alegra_integration_seller_generate_invoice' ).closest( 'tr' ).hide();
    }
});
");

$docs = "<p><a target='_blank' href='https://mi.alegra.com/integrations/api'>Ver datos de integraciones</a></p>";

$sellers = Integration_Alegra_WC::get_sellers();
$sellers = array_reduce($sellers, function($new_seller, $seller){
    $new_seller[$seller["id"]] = $seller["name"];
    return $new_seller;
}, []);

return apply_filters('integration_alegra_settings', [
    'enabled' => array(
        'title' => __('Activar/Desactivar'),
        'type' => 'checkbox',
        'label' => __('Activar  Alegra'),
        'default' => 'no'
    ),
    'debug' => array(
        'title'       => __( 'Depurador' ),
        'label'       => __( 'Habilitar el modo de desarrollador' ),
        'type'        => 'checkbox',
        'default'     => 'yes',
        'description' => __( 'Enable debug mode to show debugging information in woocommerce - status' ),
        'desc_tip' => true
    ),
    'api'  => array(
        'title' => __( 'Credenciales API' ),
        'type'  => 'title',
        'description' => $docs
    ),
    'user' => array(
        'title' => __( 'Usuario' ),
        'type'  => 'email',
        'description' => __( 'El email de la cuenta de Alegra' ),
        'desc_tip' => true
    ),
    'token' => array(
        'title' => __( 'Token' ),
        'type'  => 'password',
        'description' => __( 'Token provisto por Alegra' ),
        'desc_tip' => true
    ),
    'invoice'  => array(
        'title' => __( 'Facturas de ventas' ),
        'type'  => 'title'
    ),
    'order_status_generate_invoice' => array(
        'title' => __( 'Estado del pedido' ),
        'type' => 'select',
        'options'  => wc_get_order_statuses(),
        'default' => 'wc-processing',
        'description' => __( 'El estado del pedido en el que se genera la factura' ),
        'desc_tip' => false
    ),
    'status_generate_invoice' => array(
        'title' => __( 'Estado de la factura' ),
        'type' => 'select',
        'options'  => array(
            'draft' => 'Borrador',
            'open' => 'Abierto'
        ),
        'default' => 'open',
        'description' => __( 'El estado de la factura en la que queda creada' ),
        'desc_tip' => false
    ),
    'seller_generate_invoice' => array(
        'title' => __( 'Vendedor' ),
        'type' => 'select',
        'options'  => $sellers,
        'default' => '',
        'description' => __( 'Vendedor asociado a la factura' ),
        'desc_tip' => false
    )
]);