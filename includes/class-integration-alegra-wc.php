<?php

use Saulmoralespa\Alegra\Client;

class Integration_Alegra_WC
{

    const MAX_INVOICES_TO_STAMP = 10;

    private static $alegra = null;

    private static $integration_setting = null;

    const SKU_SHIPPING = 'S-P-W';

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

    public static function get_instance(): ?Client
    {
        if(isset(self::$integration_setting) && isset(self::$alegra)) return self::$alegra;

        self::$integration_setting = (object)get_option('woocommerce_wc_alegra_integration_settings');

        if(self::$integration_setting->user &&
            self::$integration_setting->token){
            self::$alegra = new Client(self::$integration_setting->user, self::$integration_setting->token);
        }

        return self::$alegra;
    }

    public static function get_sellers(): array
    {
        $sellers = [];

        if (!self::get_instance()) return $sellers;

        try{
            $query = [
                "status" => "active"
            ];
            $sellers = self::get_instance()->getSellers($query);
        }catch(Exception $exception){
            integration_alegra_wc_smp()->log($exception->getMessage());
        }

        return $sellers;
    }

    public static function get_taxes(): array
    {
        $taxes = [];

        if (!self::get_instance()) return $taxes;

        try{
            $taxes = self::get_instance()->getTaxes();
        }catch(Exception $exception){
            integration_alegra_wc_smp()->log($exception->getMessage());
        }

        return $taxes;
    }

    public static function sync_products(array $ids): void
    {
        if (!self::get_instance()) return;

        foreach ( $ids as $post_id ) {
            $product = wc_get_product($post_id);
            if(!$product->get_sku() || $product->meta_exists('sync_alegra') ) continue;
            $description_without_html = wp_strip_all_tags($product->get_description(), true);
            $description = substr($description_without_html, 0,50);

            try{
                $body = [
                    //"porcentaje_iva" => "12",
                    "price" => $product->get_sale_price() ?: $product->get_price(),
                    "name" => $product->get_name(),
                    "type" => $product->is_virtual() || $product->is_downloadable() ? 'service' : 'product',
                    "reference" => $product->get_sku(),
                    "description" => $description,
                    //"category" => ["id" => 4356],
                    "inventory" => [
                        "unit" => $product->is_virtual() || $product->is_downloadable() ? 'service' : 'centimeter' //get_option( 'woocommerce_dimension_unit' )
                    ]
                ];

                $query = [
                    "reference" => $product->get_sku()
                ];

                $response = self::get_instance()->getItems($query);
                $item_id = $response[0]['id'] ?? null;

                if(isset($item_id)){
                    self::get_instance()->editItem($item_id, $body);
                }else {
                    self::get_instance()->createItem($body);
                }

                $product->add_meta_data('sync_alegra', true);
                $product->save_meta_data();
            }catch (Exception $exception){
                integration_alegra_wc_smp()->log($exception->getMessage());
            }

        }
    }

    public static function generate_invoice($order_id, $previous_status, $next_status): void
    {
        if (!self::get_instance() || wc_get_order_status_name($next_status) !== wc_get_order_status_name(self::$integration_setting->order_status_generate_invoice)) return;

        $order = wc_get_order($order_id);

        if($order->meta_exists('invoice_id_alegra')) return;

        $items_invoice = [];

        try{
            $items = $order->get_items();

            $billing_type_document = get_post_meta( $order_id, '_billing_type_document', true ) ?: get_post_meta( $order_id, '_shipping_type_document', true );
            $billing_dni = get_post_meta( $order_id, '_billing_dni', true ) ?: get_post_meta( $order_id, '_shipping_dni', true );

            if(!$billing_dni) return;

            $dv_nit = null;

            if (str_contains($billing_dni, '-')){
                [$billing_dni, $dv_nit] = explode('-', $billing_dni);
            }

            $query = [
               "identification" => $billing_dni
            ];

            $response = self::get_instance()->getContacts($query);
            $client_id = $response[0]['id'] ?? null;

            if(empty($response)){
                $country = $order->get_billing_country() ?: $order->get_shipping_country();
                $state = $order->get_billing_state() ?: $order->get_shipping_state();
                $countries = WC()->countries->get_countries();
                $country_name = $countries[$country];

                $data = [
                    "name" => $order->get_formatted_billing_full_name() ?: $order->get_formatted_shipping_full_name(),
                    "nameObject" => [
                        "firstName" => $order->get_billing_first_name() ?: $order->get_shipping_first_name(),
                        "lastName" => $order->get_billing_last_name() ?: $order->get_shipping_last_name()
                    ],
                    "address" => [
                        "city" => $order->get_billing_city() ?: $order->get_shipping_city(),
                        "department" =>  self::name_department($country, $state),
                        "address" =>  $order->get_shipping_address_1() ? $order->get_shipping_address_1() .
                            " " . $order->get_shipping_address_2() : $order->get_billing_address_1() .
                            " " . $order->get_billing_address_2(),
                        "country" =>  $country_name,
                    ],
                    "identificationObject" => [
                        "number" => $billing_dni,
                        "type" => $billing_type_document
                    ],
                    "kindOfPerson" => $billing_type_document === 'NIT' ? 'LEGAL_ENTITY' : "PERSON_ENTITY",
                    "regime" =>  $billing_type_document === 'NIT' ? 'COMMON_REGIME' : "SIMPLIFIED_REGIME",
                    "enableHealthSector" => false,
                    "phonePrimary" => $order->get_billing_phone() ?: $order->get_shipping_phone(),
                    "email" => $order->get_billing_email(),
                    "type" => "client",
                    "status" => "active"
                ];

                if($dv_nit){
                    $data["identificationObject"]["dv"] = $dv_nit;
                }

                $response = self::get_instance()->createContact($data);
                $client_id = $response['id'];
            }

            foreach ($items as $item ) {

                /**
                 * @var WC_Product|bool $product
                 */
                $product  = $item->get_product();

                if(!$product || !$product->get_sku()) continue;
                $description_without_html = wp_strip_all_tags($product->get_description(), true);
                $description = substr($description_without_html, 0,50);

                $query = [
                    "reference" => $product->get_sku()
                ];

                $response = self::get_instance()->getItems($query);
                $item_id = $response[0]['id'] ?? null;

                if(empty($response)){
                    $body = [
                        //"porcentaje_iva" => "12",
                        "price" => $product->get_sale_price() ?: $product->get_price(),
                        "name" => $product->get_name(),
                        "type" => $product->is_virtual() || $product->is_downloadable() ? 'service' : 'product',
                        "reference" => $product->get_sku(),
                        "description" => $description,
                        //"category" => ["id" => 4356],
                        "inventory" => [
                            "unit" => $product->is_virtual() || $product->is_downloadable() ? 'service' : 'centimeter' //get_option( 'woocommerce_dimension_unit' )
                        ]
                    ];

                    $response = self::get_instance()->createItem($body);
                    $item_id = $response['id'];
                    $product->add_meta_data('sync_alegra', true);
                    $product->save_meta_data();
                }

                $items_invoice[] = [
                    "id" => $item_id,
                    "name" => $product->get_name(),
                    "description" => $description,
                    "price" => wc_format_decimal($item->get_total() / $item->get_quantity(), 0),
                    "discount" => round(($item->get_subtotal() - $item->get_total()) / $item->get_subtotal() * 100),
                    "quantity" => $item->get_quantity()
                ];

                if(self::$integration_setting->tax){
                    $items_invoice[count($items_invoice) - 1]["tax"] = [
                        [
                            "id" => self::$integration_setting->tax
                        ]
                    ];
                }
            }

            if($order->get_shipping_total()){

                $query = [
                    "reference" => self::SKU_SHIPPING
                ];

                $response = self::get_instance()->getItems($query);
                $item_id = $response[0]['id'] ?? null;

                $body = [
                    "price" => 20.000,
                    "name" => "Envío",
                    "type" => 'service',
                    "reference" => self::SKU_SHIPPING,
                    "description" => "Producto tipo servicio destinado para envío",
                    "inventory" => [
                        "unit" => 'service'
                    ]
                ];

                if(is_null($item_id)){
                    $response = self::get_instance()->createItem($body);
                    $item_id = $response['id'];
                }

                $items_invoice[] = [
                    "id" => $item_id,
                    "name" => "Envío",
                    "price" => wc_format_decimal($order->get_shipping_total(), 0),
                    "quantity" => 1
                ];

                if(self::$integration_setting->tax){
                    $items_invoice[count($items_invoice) - 1]["tax"] = [
                        [
                            "id" => self::$integration_setting->tax
                        ]
                    ];
                }
            }

            $data_invoice = [
                /*"numberTemplate" =>  [
                    "id" => $id,
                    "number" => $id
                ],*/
                "stamp" => [
                    "generateStamp" => false //  true emit invoice app
                ],
                "status" => self::$integration_setting->status_generate_invoice,
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

            $seller = [];

            if(isset(self::$integration_setting->seller_generate_invoice) &&
                self::$integration_setting->seller_generate_invoice){
                $seller = [
                    "seller" => self::$integration_setting->seller_generate_invoice
                ];
            }

            $data_invoice = array_merge($data_invoice, $seller);
            $data = self::get_instance()->createInvoice($data_invoice);
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
        $ids = [];

        $post_ids_max = array_slice($post_ids, 0, self::MAX_INVOICES_TO_STAMP);

        foreach ($post_ids_max as $post_id) {
            $invoice_id_alegra = get_post_meta($post_id, 'invoice_id_alegra', true);
            $invoice_emit_alegra = get_post_meta($post_id, 'invoice_emit_alegra', true);

            if ($invoice_id_alegra && !$invoice_emit_alegra) $ids[] = $invoice_id_alegra;
        }

        if (empty($ids)) return;

        try{
            $response = self::get_instance()->stampInvoices($ids);

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
        try{
            $params = [
                "fields" => "pdf"
            ];
            $response = self::get_instance()->getInvoice($invoice_id, $params);
            return $response['pdf'];
        }catch (Exception $exception){
            integration_alegra_wc_smp()->log($exception->getMessage());
            return null;
        }
    }

    public static function name_department($country, $state)
    {
        $countries_obj = new WC_Countries();
        $country_states_array = $countries_obj->get_states();
        $name_state_destination = '';

        if(!isset($country_states_array[$country][$state]))
            return $name_state_destination;

        return $country_states_array[$country][$state];
    }

    public static function calculateDv($nit): int
    {
        $vpri = array(16);
        $z = strlen($nit);

        $vpri[1]  =  3 ;
        $vpri[2]  =  7 ;
        $vpri[3]  = 13 ;
        $vpri[4]  = 17 ;
        $vpri[5]  = 19 ;
        $vpri[6]  = 23 ;
        $vpri[7]  = 29 ;
        $vpri[8]  = 37 ;
        $vpri[9]  = 41 ;
        $vpri[10] = 43 ;
        $vpri[11] = 47 ;
        $vpri[12] = 53 ;
        $vpri[13] = 59 ;
        $vpri[14] = 67 ;
        $vpri[15] = 71 ;

        $x = 0 ;

        for ($i = 0; $i < $z; $i++) {
            $y = (int)substr($nit, $i, 1);
            $x += ($y * $vpri[$z - $i]);
        }

        $y = $x % 11;

        return ($y > 1) ? 11 - $y : $y;

    }
}