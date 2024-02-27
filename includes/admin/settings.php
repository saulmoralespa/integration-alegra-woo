<?php

$docs = "<p><a target='_blank' href='https://mi.alegra.com/integrations/api'>Ver datos de integraciones</a></p>";

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
    )
]);