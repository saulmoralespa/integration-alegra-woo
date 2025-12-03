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

return [
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
        'description' => __( 'Habilitar el modo de depuración para mostrar información de depuración en WooCommerce - estado' ),
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
        'desc_tip' => true,
        'custom_attributes' => array(
            'required' => 'required'
        )
    ),
    'token' => array(
        'title' => __( 'Token' ),
        'type'  => 'password',
        'description' => __( 'Token provisto por Alegra' ),
        'desc_tip' => true,
        'custom_attributes' => array(
            'required' => 'required'
        )
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
        'description' => __( 'Estado en el que se crea la factura' ),
        'desc_tip' => false
    ),
];