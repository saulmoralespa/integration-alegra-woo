<?php

use Saulmoralespa\Alegra\Client;

class Integration_Alegra_WC extends WC_Alegra_Integration
{

    const MAX_INVOICES_TO_STAMP = 10;

    public ?Client $alegra = null;

    public function __construct()
    {
        parent::__construct();

        if($this->user && $this->token){
            $this->alegra = new Client($this->user, $this->token);
        }
    }

    public static function test_auth($user, $token): bool
    {
        try{
            $alegra = new Client($user, $token);
            $alegra->getInvoice(2);
        }catch(Exception $exception){
            if($exception->getCode() === 401) return false;
        }

        return true;
    }

    public static function sync_products(array $ids): void
    {
        $self = new self();
        if (!$self->alegra) return;

        foreach ( $ids as $post_id ) {
            $product = wc_get_product($post_id);
            if(!$product->get_sku() || $product->meta_exists('sync_alegra') ) continue;

            try{
                $body = [
                    //"porcentaje_iva" => "12",
                    "price" => $product->get_sale_price() ?: $product->get_price(),
                    "name" => $product->get_name(),
                    "type" => $product->is_virtual() || $product->is_downloadable() ? 'service' : 'product',
                    "reference" => $product->get_sku(),
                    "description" => substr($product->get_description(), 0,50),
                    //"category" => ["id" => 4356],
                    "inventory" => [
                        "unit" => $product->is_virtual() || $product->is_downloadable() ? 'service' : 'centimeter' //get_option( 'woocommerce_dimension_unit' )
                    ]

                ];

                $query = [
                    "reference" => $product->get_sku()
                ];

                $response = $self->alegra->getItems($query);
                $item_id = $response[0]['id'] ?? null;

                if(isset($item_id)){
                    $self->alegra->editItem($item_id, $body);
                }else {
                    $self->alegra->createItem($body);
                }

                $product->add_meta_data('sync_alegra', true);
                $product->save_meta_data();
            }catch (Exception $exception){
                integration_alegra_wc_smp()->log($exception->getMessage());
            }

        }
    }

    public static function integration_alegra_generate_invoice($order_id, $previous_status, $next_status): void
    {
        $self = new self();
        if (!$self->alegra || wc_get_order_status_name($next_status) !== wc_get_order_status_name($self->status_generate_invoice)) return;

        $order = wc_get_order($order_id);

        if($order->meta_exists('invoice_id_alegra')) return;

        $items_invoice = [];

        try{
            $items = $order->get_items();

            $billing_type_document = get_post_meta( $order_id, '_billing_type_document', true ) ?: get_post_meta( $order_id, '_shipping_type_document', true );
            $billing_dni = get_post_meta( $order_id, '_billing_dni', true ) ?: get_post_meta( $order_id, '_shipping_dni', true );

            if(!$billing_dni) return;

            $query = [
               "identification" =>  $billing_dni
            ];

            $response = $self->alegra->getContacts($query);
            $client_id = $response[0]['id'] ?? null;

            if(empty($response)){
                $data = [
                    "name" => $order->get_formatted_billing_full_name() ?: $order->get_formatted_shipping_full_name(),
                    "nameObject" => [
                        "firstName" => $order->get_billing_first_name() ?: $order->get_shipping_first_name(),
                        "lastName" => $order->get_billing_last_name() ?: $order->get_shipping_last_name()
                    ],
                    "identificationObject" => [
                        "number" => $billing_dni,
                        "type" => $billing_type_document,
                    ],
                    "kindOfPerson" => $billing_type_document === 'NIT' ? 'LEGAL_ENTITY' : "PERSON_ENTITY",
                    "regime" =>  $billing_type_document === 'NIT' ? 'COMMON_REGIME' : "SIMPLIFIED_REGIME",
                    "enableHealthSector" => false,
                    "phonePrimary" => $order->get_billing_phone() ?: $order->get_shipping_phone(),
                    "email" => $order->get_billing_email(),
                    "type" => "client",
                    "status" => "active"
                ];

                $response = $self->alegra->createContact($data);
                $client_id = $response['id'];
            }

            foreach ($items as $item ) {

                /**
                 * @var WC_Product|bool $product
                 */
                $product  = $item->get_product();

                if(!$product || !$product->get_sku()) continue;

                $query = [
                    "reference" => $product->get_sku()
                ];

                $response = $self->alegra->getItems($query);
                $item_id = $response[0]['id'] ?? null;

                if(empty($response)){
                    $body = [
                        //"porcentaje_iva" => "12",
                        "price" => $product->get_sale_price() ?: $product->get_price(),
                        "name" => $product->get_name(),
                        "type" => $product->is_virtual() || $product->is_downloadable() ? 'service' : 'product',
                        "reference" => $product->get_sku(),
                        "description" => substr($product->get_description(), 0,50),
                        //"category" => ["id" => 4356],
                        "inventory" => [
                            "unit" => $product->is_virtual() || $product->is_downloadable() ? 'service' : 'centimeter' //get_option( 'woocommerce_dimension_unit' )
                        ]
                    ];

                    $response = $self->alegra->createItem($body);
                    $item_id = $response['id'];
                    $product->add_meta_data('sync_alegra', true);
                    $product->save_meta_data();
                }

                $items_invoice[] = [
                    "id" => $item_id,
                    "name" => $product->get_name(),
                    "description" => substr($product->get_description(), 0,50),
                    "price" => wc_format_decimal( $order->get_line_total( $item ), 2 ),
                    "quantity" => $item->get_quantity()
                ];

            }

            $data_invoice = [
                /*"numberTemplate" =>  [
                    "id" => $id,
                    "number" => $id
                ],*/
                "status" => "open",
                "dueDate" =>  wp_date('Y-m-d'),
                "date" => wp_date('Y-m-d'),
                "client" => [
                    "id" => $client_id
                ],
                "items" => $items_invoice,
                "paymentMethod" => "CASH", //or CREDIT
                "paymentForm" =>  "CASH", //or CREDIT
                "type" => "NATIONAL", //EXPORT
                "purchaseOrderNumber" => (string)$order_id,
                /*"healthSector" => [

                ],*/
                /*"payments" => [
                    "date" => $order->get_date_paid(),
                    "account" => [
                      "id" => 3
                    ],
                    "amount" => (int)$order->get_total(),
                    "paymentMethod" => "credit-card",
                    "currency" => [
                        "code" => $order->get_currency()
                    ]
                ]*/
            ];
            $data = $self->alegra->createInvoice($data_invoice);
            $invoice_id = $data['id'];
            $order->add_order_note( sprintf( __( 'Factura de venta %s.' ), $invoice_id ) );
            $order->add_meta_data('invoice_id_alegra', $invoice_id);
            $order->save_meta_data();
        }catch (Exception $exception){
            integration_alegra_wc_smp()->log($exception->getMessage());
        }
    }

    public static function emit_invoices(array $post_ids): void
    {
        $self = new self();
        $ids = [];

        $post_ids_max = array_slice($post_ids, 0, self::MAX_INVOICES_TO_STAMP);

        foreach ($post_ids_max as $post_id) {
            $invoice_id_alegra = get_post_meta($post_id, 'invoice_id_alegra', true);
            $invoice_emit_alegra = get_post_meta($post_id, 'invoice_emit_alegra', true);

            if ($invoice_id_alegra && !$invoice_emit_alegra) $ids[$invoice_id_alegra] = $post_id;
        }

        if (empty($ids)) return;

        $ids_str = implode(',', array_keys($ids));

        try{
            $idsArr = [$ids_str];
            $response = $self->alegra->stampInvoices($idsArr);

            foreach ($response['data'] as $invoice){
                if ($invoice['success'] && isset($ids[$invoice['id']])){
                    update_post_meta($ids[$invoice['id']], 'invoice_emit_alegra', true);
                }
            }
        }catch (Exception $exception){
            integration_alegra_wc_smp()->log($exception->getMessage());
        }
    }

    public static function view_invoice(int $invoice_id) :string|null
    {
        $self = new self();

        try{
            $params = [
                "fields" => "pdf"
            ];
            $response = $self->alegra->getInvoice($invoice_id, $params);
            return $response['pdf'];
        }catch (Exception $exception){
            integration_alegra_wc_smp()->log($exception->getMessage());
            return null;
        }
    }

}