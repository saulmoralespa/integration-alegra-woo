<?php

$sellers = $this->get_data_options('Integration_Alegra_WC::get_sellers', function($new_seller, $seller){
    if($seller["status"] !== 'active') return $new_seller;
    $new_seller[$seller["id"]] = $seller["name"];
    return $new_seller;
});

$taxes = $this->get_data_options('Integration_Alegra_WC::get_taxes', function($new_tax, $tax){
    if($tax["status"] !== 'active') return $new_tax;
    $new_tax[$tax["id"]] = "{$tax["name"]} - {$tax["percentage"]}%";
    return $new_tax;
});

// Agregar opción por defecto sin impuesto
$taxes = array_merge(
    array('' => __('Sin impuesto')),
    $taxes
);

return [
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
    'seller_generate_invoice' => array(
        'title' => __( 'Vendedor' ),
        'type' => 'select',
        'options'  => $sellers,
        'default' => '',
        'description' => __( 'Vendedor asociado a la factura' ),
        'desc_tip' => false
    ),
    'client'  => array(
        'title' => __( 'Clientes' ),
        'type'  => 'title'
    ),
    'allow_create_clients' => array(
        'title'       => __( 'Crear clientes automáticamente' ),
        'label'       => __( 'Habilitar creación automática de clientes en Alegra' ),
        'type'        => 'checkbox',
        'default'     => 'yes',
        'description' => __( 'Permitir la creación automática de clientes en Alegra cuando se genere una factura desde WooCommerce si el cliente no existe.' ),
        'desc_tip' => false
    ),
    'dni_field' => array(
        'title' => __( 'Meta clave campo DNI' ),
        'type'  => 'text',
        'placeholder' => 'dni',
        'description' => __( 'El campo que hace referencia al documento del cliente. <br/><b>Por defecto usa el predefinido de Woocommerce.</b>' ),
        'desc_tip' => false
    ),
    'product'  => array(
        'title' => __( 'Productos' ),
        'type'  => 'title'
    ),
    'allow_create_products' => array(
        'title'       => __( 'Crear productos automáticamente' ),
        'label'       => __( 'Habilitar creación automática de productos en Alegra' ),
        'type'        => 'checkbox',
        'default'     => 'yes',
        'description' => __( 'Permitir la creación automática de productos en Alegra cuando se genere una factura desde WooCommerce si el producto no existe.' ),
        'desc_tip' => false
    ),
    'tax' => array(
        'title' => __( 'IVA a productos' ),
        'type' => 'select',
        'class' => 'wc-enhanced-select',
        'options'  => $taxes,
        'default' => '',
        'description' => __( 'El IVA que desea aplicar a los productos de la factura. <br/><b>Se recomienda incluir el IVA en los precios de los productos.</b>' ),
        'desc_tip' => false
    ),
    'shipping_tax' => array(
        'title' => __( 'IVA a envío' ),
        'type' => 'select',
        'class' => 'wc-enhanced-select',
        'options'  => $taxes,
        'default' => '',
        'description' => __( 'El IVA que desea aplicar al valor del envío. <br/><b>El envío es tratado como un item en la factuta</b>' ),
        'desc_tip' => false
    )
];