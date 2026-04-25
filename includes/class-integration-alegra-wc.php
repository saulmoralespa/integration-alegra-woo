<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Saulmoralespa\Alegra\Client;
use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;

class Integration_Alegra_WC
{

    const MAX_INVOICES_TO_STAMP = 10;

    private static ?Client $alegra = null;

    private static ?stdClass $integration_settings = null;

    const SKU_SHIPPING = 'S-P-W';

    const PAYMENTS_METHODS = [
        'INSTRUMENT_NOT_DEFINED'                     => 'Instrumento no definido',
        'CASH'                                       => 'Efectivo',
        'DEBIT_TRANSFER'                             => 'Débito ACH',
        'BANK_DEPOSIT'                               => 'Consignación bancaria',
        'ACH_CREDIT'                                 => 'Crédito ACH',
        'ACH_DEBIT'                                  => 'Débito ACH',
        'REVERSION_ACH_DEBIT'                        => 'Reversión débito de demanda ACH',
        'REVERSION_ACH_CREDIT'                       => 'Reversión crédito de demanda ACH',
        'CREDIT_ACH_DEMAND'                          => 'Crédito de demanda ACH',
        'DEBIT_ACH_DEMAND'                           => 'Débito de demanda ACH',
        'NATIONAL_CLEARING'                          => 'Clearing nacional o regional',
        'REVERSION_CREDIT_SAVING'                    => 'Reversión crédito ahorro',
        'REVERSION_DEBIT_SAVING'                     => 'Reversión débito ahorro',
        'CREDIT_SAVING'                              => 'Crédito ahorro',
        'DEBIT_SAVING'                               => 'Débito ahorro',
        'BOOKENTRY_CREDIT'                           => 'Bookentry crédito',
        'BOOKENTRY_DEBIT'                            => 'Bookentry débito',
        'CTP_CREDIT'                                 => 'Crédito pago negocio corporativo (CTP)',
        'CHECK'                                      => 'Cheque',
        'BANK_PROYECT'                               => 'Proyecto bancario',
        'BANK_PROYECT_CERTIFIED'                     => 'Proyecto bancario certificado',
        'EXCHANGE_RATE_PENDING_ACEPT'                => 'Nota cambiaria esperando aceptación',
        'CERTIFIED_CHECK'                            => 'Cheque certificado',
        'LOCAL_CHECK'                                => 'Cheque local',
        'CTP_DEBIT'                                  => 'Débito Pago Negocio Corporativo (CTP)',
        'CTX_CREDIT'                                 => 'Crédito Negocio Intercambio Corporativo (CTX)',
        'CTX_DEBIT'                                  => 'Débito Negocio Intercambio Corporativo (CTX)',
        'CREDIT_TRANSFER'                            => 'Transferecia crédito',
        'PPD_PAY'                                    => 'Pago y depósito pre acordado (PPD)',
        'CTP_CREDIT_SAVING'                          => 'Pago negocio corporativo ahorros crédito (CTP)',
        'CTP_DEBIT_SAVING'                           => 'Pago negocio corporativo ahorros débito (CTP)',
        'EXCHANGE_RATE'                              => 'Nota cambiaria',
        'CREDIT_TRANSFER_BANK'                       => 'Transferencia crédito bancario',
        'DEBIT_TRANSFER_INTERBANK'                   => 'Transferencia débito interbancario',
        'DEBIT_TRANSFER_BANK'                        => 'Transferencia débito bancaria',
        'CREDIT_CARD'                                => 'Tarjeta crédito',
        'DEBIT_CARD'                                 => 'Tarjeta débito',
        'POSTTURN'                                   => 'Postgiro',
        'URGENT_BUSINESS_PAYMENT'                    => 'Pago comercial urgente',
        'URGENT_CASH_PAYMENT'                        => 'Pago tesorería urgente',
        'PROMISING_NOTE'                             => 'Nota promisoria',
        'PROMISING_NOTE_SIGNED_PROVIDER'             => 'Nota promisoria firmada por el acreedor',
        'PROMISING_NOTE_SIGNED_PROVIDER_BANK'        => 'Nota promisoria firmada por el acreedor, avalada por el banco',
        'PROMISING_NOTE_SIGNED_PROVIDER_THIRD'       => 'Nota promisoria firmada por el acreedor, avalada por un tercero',
        'PROMISING_NOTE_SIGNED_BANK'                 => 'Nota promisoria firmada por el bancos',
        'PROMISING_NOTE_SIGNED_BANK_ENDORSED_BANK'   => 'Nota promisoria firmada por un banco avalada por otro banco',
        'PROMISING_NOTE_SIGNED'                      => 'Nota promisoria firmada',
        'PROMISING_NOTE_SIGNED_THIRD'                => 'Nota promisoria firmada por un tercero avalada por un banco',
        'WITHDRAWAL_NOTE_CREDITOR'                   => 'Retiro de nota por el acreedor',
        'BONDS'                                      => 'Bonos',
        'VOUCHERS'                                   => 'Vales',
        'WITHDRAWAL_NOTE_CREDITOR_BANK'              => 'Retiro de nota por el acreedor sobre un banco',
        'WITHDRAWAL_NOTE_CREDITOR_ENDORSED_BANK'     => 'Retiro de nota por el acreedor, avalada por otro banco',
        'WITHDRAWAL_NOTE_CREDITOR_BANK_ENDORSED_THIRD' => 'Retiro de nota por el acreedor, sobre un banco avalada por un tercero',
        'WITHDRAWAL_NOTE_CREDITOR_ENDORSED_THIRD'    => 'Retiro de una nota por el acreedor sobre un tercero',
        'WITHDRAWAL_NOTE_CREDITOR_THIRD_PARTY'       => 'Retiro de una nota por el acreedor sobre un tercero avalada por un banco',
        'TRANSFERABLE_BANK_NOTE'                     => 'Nota bancaria transferible',
        'TRANSFERABLE_LOCAL_CHECK'                   => 'Cheque local transferible',
        'REFERENCED_TURN'                            => 'Giro referenciado',
        'URGENT_TURN'                                => 'Giro urgente',
        'OPEN_FORMAT_TWIST'                          => 'Giro formato abierto',
        'PAYMENT_METHOD_REQUESTED_NOT_USED'          => 'Método de pago solicitado no usado',
        'CLEARING_PARTNERS'                          => 'Clearing entre partners',
        'CCD_CREDIT'                                 => 'Desembolso Crédito (CCD)s',
        'CCD_DEBIT'                                  => 'Desembolso (CCD) débito',
        'BANK_CHECK'                                 => 'Cheque bancario de gerencia',
        'CDD_CREDIT'                                 => 'Desembolso Crédito plus (CCD+)s',
        'CDD_CASH'                                   => 'Desembolso Débito plus (CCD+)',
        'CCD_CASH_CREDIT'                            => 'Desembolso Crédito (CCD)',
        'CCD_CASH_DEBIT'                             => 'Desembolso Débito (CCD)',
        'CTX_CREDIT_DEAL'                            => 'Crédito Intercambio Corporativo (CTX)',
        'CTX_DEBIT_DEAL'                             => 'Débito Intercambio Corporativo (CTX)',
        'CCD_CREDIT_PLUS'                            => 'Desembolso Crédito plus (CCD+)',
        'CCD_DEBIT_PLUS'                             => 'Desembolso Débito plus (CCD+)',
        'FRENCH_BANKING_STANDARD_TELEX'              => 'Telex estándar bancario',
        'MUTUAL_AGREEMENT'                           => 'Otro',
    ];

    const PAYMENT_TYPE_CASH   = 'CASH';
    const PAYMENT_TYPE_CREDIT = 'CREDIT';

    /** UBL payment methods that map to CREDIT paymentForm; all others default to CASH. */
    const CREDIT_PAYMENT_METHODS = [ 'CREDIT_CARD', 'DEBIT_CARD' ];

    /**
     * The six UBL payment method options shown in the admin settings UI.
     * Keys are UBL codes stored directly; values are the Spanish labels displayed.
     */
    const PAYMENT_METHODS_UI = [
        'CASH'                 => 'Efectivo',
        'CHECK'                => 'Cheque',
        'CREDIT_TRANSFER_BANK' => 'Transferencia bancaria',
        'BANK_DEPOSIT'         => 'Depósito bancario',
        'CREDIT_CARD'          => 'Tarjeta de crédito',
        'DEBIT_CARD'           => 'Tarjeta de débito',
    ];

    /**
     * Maps UBL payment method codes to legacy values required by the Alegra API
     * for the payments[].paymentMethod child field.
     */
    const UBL_TO_LEGACY_PAYMENT_METHOD = [
        'CASH'                 => 'cash',
        'CHECK'                => 'check',
        'CREDIT_TRANSFER_BANK' => 'transfer',
        'BANK_DEPOSIT'         => 'deposit',
        'CREDIT_CARD'          => 'credit-card',
        'DEBIT_CARD'           => 'debit-card',
    ];

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
        $settings = get_option( 'woocommerce_wc_alegra_integration_settings', [] );

        if ( ! is_array( $settings ) ) {
            $settings = [];
        }

        self::$integration_settings = (object) $settings;

        if ( ( self::$integration_settings->enabled ?? 'no' ) === 'no' ) {
            return null;
        }

        if ( ! empty( self::$integration_settings->user ) &&
            ! empty( self::$integration_settings->token ) ) {
            self::$alegra = new Client( self::$integration_settings->user, self::$integration_settings->token );
        }

        return self::$alegra;
    }

    public static function get_cost_centers(): array
    {
        $cost_centers = [];

        if (!self::get_instance()) return $cost_centers;

        try{
            $query = [
                "status" => "active"
            ];
            $cost_centers = self::get_instance()->getCostCenters($query);
        }catch(Exception $exception){
            integration_alegra_wc_smp()->log($exception->getMessage());
        }

        return $cost_centers;
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

    public static function get_bank_accounts(): array
    {
        $bank_accounts = [];

        if ( ! self::get_instance() ) {
            return $bank_accounts;
        }

        try {
            $query = [
                'status' => 'active',
            ];
            $bank_accounts = self::get_instance()->getBankAccounts( $query );
        } catch ( Exception $exception ) {
            integration_alegra_wc_smp()->log( $exception->getMessage() );
        }

        return $bank_accounts;
    }

    public static function get_wc_payment_gateways( bool $only_active = false ): array
    {
        $gateways = [];

        if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->payment_gateways() ) {
            return $gateways;
        }

        $payment_gateways = WC()->payment_gateways()->payment_gateways();

        if ( ! is_array( $payment_gateways ) ) {
            return $gateways;
        }

        foreach ( $payment_gateways as $gateway ) {
            $gateway_id = $gateway->id ?? '';

            if ( ! $gateway_id ) {
                continue;
            }

            $is_active = isset( $gateway->enabled ) && 'yes' === $gateway->enabled;

            if ( $only_active && ! $is_active ) {
                continue;
            }

            $gateway_title = method_exists( $gateway, 'get_title' ) ? $gateway->get_title() : ( $gateway->title ?? $gateway_id );

            $gateways[ $gateway_id ] = $gateway_title ?: $gateway_id;
        }

        return $gateways;
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
        if (!self::get_instance() || wc_get_order_status_name($next_status) !== wc_get_order_status_name(self::$integration_settings->order_status_generate_invoice)) return;

        $order = wc_get_order($order_id);

        if($order->meta_exists('_invoice_id_alegra')) return;

        $items_invoice = [];
        $field_type_document = 'document/type_document';
        $field_dni = 'document/dni';
        $meta_key_dni = self::$integration_settings->dni_field ?: '_billing_dni';
        $checkout_fields = Package::container()->get( CheckoutFields::class );
        $billing_type_document = $checkout_fields->get_field_from_object( $field_type_document, $order, 'billing' );
        $shipping_type_document = $checkout_fields->get_field_from_object( $field_type_document, $order, 'shipping' );

        $classic_type_document = get_post_meta( $order_id, '_billing_type_document', true ) ?: get_post_meta( $order_id, '_shipping_type_document', true );
        $classic_dni = get_post_meta( $order_id, $meta_key_dni, true ) ?: get_post_meta( $order_id, '_shipping_dni', true );

        $billing_dni = $checkout_fields->get_field_from_object( $field_dni, $order, 'billing' );
        $shipping_dni = $checkout_fields->get_field_from_object( $field_dni, $order, 'shipping' );

        $type_document = $billing_type_document ?: $shipping_type_document ? : $classic_type_document;
        $dni = $billing_dni ?: $shipping_dni ?: $classic_dni;
        $dni = trim($dni);

        try{
            $items = $order->get_items();

            if(!$dni) throw new Exception('Integration Alegra Woocommerce: DNI es vacío');
            if(!$type_document) throw new Exception(' Integration Alegra Woocommerce: El tipo de documento es vacío');

            //TODO: validar si es NIT y calcular DV
            $dv_nit = self::extract_dv_from_dni($dni);
            $client_id = self::get_or_create_client($order, $dni, $type_document, $dv_nit);

            foreach ( $items as $item ) {
                $product = $item->get_product();

                if (!$product || !$product->get_sku() ) {
                    continue;
                }

                $item_id = self::get_or_create_product_in_alegra( $product );
                $invoice_item = self::build_invoice_item_data( $item, $product, $item_id );

                $items_invoice[] = $invoice_item;
            }

            // Agregar envío si aplica
            if ( $order->get_shipping_total() ) {
                $shipping_item = self::get_or_create_shipping_item( $order );
                $items_invoice[] = $shipping_item;
            }

            $data_invoice = [
                /*"numberTemplate" =>  [
                    "id" => $id,
                    "number" => $id
                ],*/
                "stamp" => [
                    "generateStamp" => false //  true emit invoice app
                ],
                "status" => self::$integration_settings->status_generate_invoice,
                "dueDate" =>  wp_date('Y-m-d'),
                "date" => wp_date('Y-m-d'),
                "client" => [
                    "id" => $client_id
                ],
                "items" => $items_invoice,
                "paymentMethod" => "CASH",
                "paymentForm" =>  "CASH", //or CREDIT
                "type" => "NATIONAL", //EXPORT
                "purchaseOrderNumber" => (string)$order_id,
                /*"healthSector" => [

                ],*/
            ];

            if(!self::$integration_settings->seller_generate_invoice){
                throw new Exception( 'Integration Alegra Woocommerce: El vendedor para la factura no está configurado.' );
            }

            $seller = [
                "seller" => self::$integration_settings->seller_generate_invoice
            ];

            $data_invoice = array_merge($data_invoice, $seller);

            if ( ! empty( self::$integration_settings->cost_center_generate_invoice ) ) {
                $data_invoice['costCenter'] = self::$integration_settings->cost_center_generate_invoice;
            }

            $payment_mapping = self::resolve_gateway_payment_mapping(
                (string) $order->get_payment_method(),
                self::get_payment_gateways_mapping(),
                self::get_active_wc_payment_gateway_ids()
            );

            if ( $payment_mapping ) {
                $data_invoice['paymentForm']   = $payment_mapping['payment_type']   ?? self::PAYMENT_TYPE_CASH;
                $data_invoice['paymentMethod'] = $payment_mapping['payment_method'] ?? self::PAYMENT_TYPE_CASH;
                $data_invoice['payments']      = self::build_invoice_payments_data( $order, $payment_mapping );
            } else {
                integration_alegra_wc_smp()->log(
                    sprintf(
                        'No se encontró mapeo de pago para el pedido %d (gateway: %s). Se usará CASH como tipo de pago.',
                        $order_id,
                        (string) $order->get_payment_method() ?: 'N/A'
                    ),
                    'warning'
                );
            }

            if(self::$integration_settings->debug === 'yes') {
                integration_alegra_wc_smp()->log('createInvoice: ' . print_r($data_invoice, true));
            }
            $data = self::get_instance()->createInvoice($data_invoice);
            $invoice_id = $data['id'];
            $order->add_order_note( sprintf( __( 'Factura de venta %s.' ), $invoice_id ) );
            $order->add_meta_data('_invoice_id_alegra', $invoice_id);
            $order->save_meta_data();
        }catch (Exception $exception){
            $order->add_order_note( $exception->getMessage() );
            integration_alegra_wc_smp()->log($exception->getMessage());
        }
    }

    /**
     * Emite facturas en DIAN a través de Alegra en lote
     *
     * Procesa hasta MAX_INVOICES_TO_STAMP facturas, enviándolas a la DIAN
     * para su timbrado oficial.
     *
     * @param array $post_ids IDs de los pedidos de WooCommerce
     * @return void
     */
    public static function emit_invoices( array $post_ids ): void {
        if ( ! self::get_instance() ) {
            return;
        }

        $invoice_ids_to_stamp = self::collect_invoice_ids_to_stamp( $post_ids );

        if ( empty( $invoice_ids_to_stamp ) ) {
            return;
        }

        self::stamp_invoices_in_alegra( $invoice_ids_to_stamp );
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

    public static function name_department($country, $state): string
    {
        $countries_obj = new WC_Countries();
        $country_states_array = $countries_obj->get_states();
        $name_state_destination = '';

        if(!isset($country_states_array[$country][$state]))
            return $name_state_destination;

        return $country_states_array[$country][$state];
    }

    /**
     * Calcula el dígito de verificación (DV) de un NIT colombiano
     *
     * Implementa el algoritmo oficial de la DIAN para calcular el dígito de verificación
     * basado en la multiplicación por números primos específicos.
     *
     * @param string $nit Número de identificación tributaria sin el DV
     * @return int Dígito de verificación calculado (0-9)
     */
    public static function calculate_dv( $nit ): int {
        // Array de números primos para la multiplicación según posición
        $prime_numbers = array( 3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71 );

        $nit_length = strlen( $nit );
        $sum = 0;

        // Multiplicar cada dígito del NIT por su primo correspondiente
        for ( $i = 0; $i < $nit_length; $i++ ) {
            $digit = (int) substr( $nit, $i, 1 );
            $sum += $digit * $prime_numbers[ $nit_length - $i - 1 ];
        }

        // Calcular el módulo 11
        $remainder = $sum % 11;

        // Retornar el DV según el algoritmo de la DIAN
        return ( $remainder > 1 ) ? 11 - $remainder : $remainder;
    }


    /**
     * Extrae el dígito de verificación (DV) del DNI si existe
     *
     * @param string &$dni DNI pasado por referencia que será modificado si contiene DV
     * @return string|null El dígito de verificación o null si no existe
     */
    private static function extract_dv_from_dni(string &$dni): ?string
    {
        $dv_nit = null;

        if (str_contains($dni, '-')) {
            list($dni, $dv_nit) = explode('-', $dni, 2);
        }

        return $dv_nit;
    }

    private static function get_payment_gateways_mapping(): array
    {
        if ( ! isset( self::$integration_settings ) ) {
            $settings = get_option( 'woocommerce_wc_alegra_integration_settings', [] );
            self::$integration_settings = (object) ( is_array( $settings ) ? $settings : [] );
        }

        $raw_mapping = self::$integration_settings->payment_gateways_mapping ?? [];

        return self::normalize_payment_gateways_mapping( $raw_mapping );
    }

    private static function get_active_wc_payment_gateway_ids(): array
    {
        return array_keys( self::get_wc_payment_gateways( true ) );
    }

    private static function get_payment_type_from_method( string $payment_method ): string
    {
        return in_array( $payment_method, self::CREDIT_PAYMENT_METHODS, true )
            ? self::PAYMENT_TYPE_CREDIT
            : self::PAYMENT_TYPE_CASH;
    }

    private static function get_legacy_child_payment_method( string $ubl_method ): string
    {
        if ( isset( self::UBL_TO_LEGACY_PAYMENT_METHOD[ $ubl_method ] ) ) {
            return self::UBL_TO_LEGACY_PAYMENT_METHOD[ $ubl_method ];
        }

        integration_alegra_wc_smp()->log(
            sprintf(
                'Integration Alegra Woocommerce: El método de pago UBL "%s" no tiene equivalente legacy para payments[].paymentMethod. Se usará "cash" como fallback.',
                $ubl_method
            ),
            'warning'
        );

        return 'cash';
    }

    private static function normalize_payment_gateways_mapping( $raw_mapping ): array
    {
        $normalized_mapping = [];

        if ( ! is_array( $raw_mapping ) ) {
            return $normalized_mapping;
        }

        $valid_payment_types = [ self::PAYMENT_TYPE_CASH, self::PAYMENT_TYPE_CREDIT ];

        foreach ( $raw_mapping as $gateway_id => $mapping ) {
            if ( ! is_array( $mapping ) ) {
                continue;
            }

            $sanitized_gateway_id = sanitize_text_field( (string) $gateway_id );
            $payment_method       = sanitize_text_field( (string) ( $mapping['payment_method'] ?? '' ) );
            $account_id           = sanitize_text_field( (string) ( $mapping['account_id'] ?? '' ) );

            if ( ! $sanitized_gateway_id || ! $payment_method || ! $account_id ) {
                continue;
            }

            // Reject any payment_method not present in the UBL catalog.
            if ( ! isset( self::PAYMENTS_METHODS[ $payment_method ] ) ) {
                continue;
            }

            $raw_payment_type = sanitize_text_field( (string) ( $mapping['payment_type'] ?? '' ) );
            $payment_type = in_array( $raw_payment_type, $valid_payment_types, true )
                ? $raw_payment_type
                : self::get_payment_type_from_method( $payment_method );

            $normalized_mapping[ $sanitized_gateway_id ] = [
                'payment_method' => $payment_method,
                'account_id'     => $account_id,
                'payment_type'   => $payment_type,
            ];
        }

        return $normalized_mapping;
    }

    private static function resolve_gateway_payment_mapping(string $gateway_id, array $payment_mappings, array $active_gateway_ids): ?array
    {
        $sanitized_gateway_id = trim( $gateway_id );

        if ( '' === $sanitized_gateway_id ) {
            return null;
        }
        
        if ( isset( $payment_mappings[ $sanitized_gateway_id ] ) ) {
            return $payment_mappings[ $sanitized_gateway_id ];
        }

        if ( ! in_array( $sanitized_gateway_id, $active_gateway_ids, true ) ) {
            throw new Exception(
                sprintf(
                    'Integration Alegra Woocommerce: El método de pago "%s" no tiene mapeo en Alegra. Configure el mapeo en la sección Métodos de pago.',
                    $sanitized_gateway_id
                )
            );
        }

        return null;
    }

    private static function build_invoice_payments_data( WC_Order $order, array $payment_mapping ): array
    {
        $order_created_at = $order->get_date_created();
        $payment_date = $order_created_at
            ? wc_format_datetime( $order_created_at, 'Y-m-d' )
            : wp_date( 'Y-m-d' );

        return [
            [
                'date' => $payment_date,
                'amount' => (float) $order->get_total(),
                'paymentMethod' => self::get_legacy_child_payment_method( $payment_mapping['payment_method'] ),
                'account' => [
                    'id' => $payment_mapping['account_id'],
                ],
                'currency' => [
                    'code' => $order->get_currency(),
                ],
            ],
        ];
    }

    /**
     * Busca un cliente existente en Alegra o lo crea si no existe
     *
     * @param WC_Order $order Orden de WooCommerce
     * @param string $dni Número de identificación del cliente
     * @param string $type_document Tipo de documento del cliente
     * @param string|null $dv_nit Dígito de verificación si aplica
     * @return int ID del cliente en Alegra
     */
    private static function get_or_create_client(WC_Order $order, string $dni, string $type_document, ?string $dv_nit): int
    {
        $client_id = self::find_existing_client($dni);

        if ($client_id) {
            return $client_id;
        }

        $is_alllowed_create_clients = self::$integration_settings->allow_create_clients ?? 'no';

        if ($is_alllowed_create_clients === 'no') {
            throw new Exception('Integration Alegra Woocommerce: Cliente no existe y la creación de clientes está deshabilitada.');
        }

        return self::create_new_client($order, $dni, $type_document, $dv_nit);
    }

    /**
     * Busca un cliente existente en Alegra por su número de identificación
     *
     * @param string $dni Número de identificación del cliente
     * @return int|null ID del cliente si existe, null si no existe
     */
    private static function find_existing_client(string $dni): ?int
    {
        $query = [
            "identification" => $dni
        ];

        $response = self::get_instance()->getContacts($query);

        return $response[0]['id'] ?? null;
    }

    /**
     * Crea un nuevo cliente en Alegra
     *
     * @param WC_Order $order Orden de WooCommerce
     * @param string $dni Número de identificación del cliente
     * @param string $type_document Tipo de documento del cliente
     * @param string|null $dv_nit Dígito de verificación si aplica
     * @return int ID del cliente creado
     */
    private static function create_new_client(WC_Order $order, string $dni, string $type_document, ?string $dv_nit): int
    {
        $contact_data = self::build_contact_data($order, $dni, $type_document, $dv_nit);

        $response = self::get_instance()->createContact($contact_data);

        return $response['id'];
    }

    /**
     * Construye el array de datos para crear un contacto en Alegra
     *
     * @param WC_Order $order Orden de WooCommerce
     * @param string $dni Número de identificación del cliente
     * @param string $type_document Tipo de documento del cliente
     * @param string|null $dv_nit Dígito de verificación si aplica
     * @return array Datos del contacto formateados para la API de Alegra
     */
    private static function build_contact_data(WC_Order $order, string $dni, string $type_document, ?string $dv_nit): array
    {
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
                "department" => self::name_department($country, $state),
                "address" => $order->get_shipping_address_1()
                    ? $order->get_shipping_address_1() . " " . $order->get_shipping_address_2()
                    : $order->get_billing_address_1() . " " . $order->get_billing_address_2(),
                "country" => $country_name,
            ],
            "identificationObject" => [
                "number" => $dni,
                "type" => $type_document
            ],
            "kindOfPerson" => $type_document === 'NIT' ? 'LEGAL_ENTITY' : "PERSON_ENTITY",
            "regime" => $type_document === 'NIT' ? 'COMMON_REGIME' : "SIMPLIFIED_REGIME",
            "enableHealthSector" => false,
            "phonePrimary" => $order->get_billing_phone() ?: $order->get_shipping_phone(),
            "email" => $order->get_billing_email(),
            "type" => "client",
            "status" => "active"
        ];

        if ($dv_nit) {
            $data["identificationObject"]["dv"] = $dv_nit;
        }

        return $data;
    }

    /**
     * Obtiene o crea un producto en Alegra
     *
     * Busca un producto existente por SKU. Si no existe, lo crea automáticamente.
     *
     * @param WC_Product $product Producto de WooCommerce
     * @return int ID del producto en Alegra
     */
    private static function get_or_create_product_in_alegra( WC_Product $product ): int {
        $item_id = self::find_existing_product( $product->get_sku() );

        if ( $item_id ) {
            return $item_id;
        }

        $is_allowed_create_products = self::$integration_settings->allow_create_products ?? 'no';

        if($is_allowed_create_products === 'no'){
            throw new Exception( 'Integration Alegra Woocommerce: El producto con SKU ' . $product->get_sku() . ' no existe en Alegra y la creación de productos está deshabilitada.' );
        }

        return self::create_product_in_alegra( $product );
    }

    /**
     * Busca un producto existente en Alegra por SKU
     *
     * @param string $sku SKU del producto
     * @return int|null ID del producto si existe, null si no existe
     */
    private static function find_existing_product( string $sku ): ?int {
        $query = array(
            'reference' => $sku,
        );

        $response = self::get_instance()->getItems( $query );

        return $response[0]['id'] ?? null;
    }

    /**
     * Crea un nuevo producto en Alegra
     *
     * @param WC_Product $product Producto de WooCommerce
     * @return int ID del producto creado en Alegra
     */
    private static function create_product_in_alegra( WC_Product $product ): int {

        $product_data = self::build_product_data( $product );

        $response = self::get_instance()->createItem( $product_data );
        $item_id  = $response['id'];

        // Marcar producto como sincronizado
        $product->add_meta_data( 'sync_alegra', true );
        $product->save_meta_data();

        return $item_id;
    }

    /**
     * Construye el array de datos de un producto para Alegra
     *
     * @param WC_Product $product Producto de WooCommerce
     * @return array Datos del producto formateados para la API de Alegra
     */
    private static function build_product_data( WC_Product $product ): array {
        $description = self::get_product_description( $product );
        $is_service  = $product->is_virtual() || $product->is_downloadable();

        return array(
            'price'       => $product->get_sale_price() ?: $product->get_price(),
            'name'        => $product->get_name(),
            'type'        => $is_service ? 'service' : 'product',
            'reference'   => $product->get_sku(),
            'description' => $description,
            'inventory'   => array(
                'unit' => $is_service ? 'service' : 'centimeter',
            ),
        );
    }

    /**
     * Obtiene la descripción del producto truncada a 50 caracteres
     *
     * @param WC_Product $product Producto de WooCommerce
     * @return string Descripción sin HTML y truncada
     */
    private static function get_product_description( WC_Product $product ): string {
        $description_without_html = wp_strip_all_tags( $product->get_description(), true );
        return substr( $description_without_html, 0, 50 );
    }

    /**
     * Construye los datos del item de factura
     *
     * @param WC_Order_Item $item Item de la orden
     * @param WC_Product $product Producto de WooCommerce
     * @param int $item_id ID del producto en Alegra
     * @return array Datos del item formateados para la factura
     */
    private static function build_invoice_item_data( WC_Order_Item $item, WC_Product $product, int $item_id ): array {
        $quantity    = $item->get_quantity();
        $subtotal    = $item->get_subtotal();
        $total       = $item->get_total();

        $invoice_item = array(
            'id'          => $item_id,
            'name'        => $product->get_name(),
            'price'       => wc_format_decimal( $total / $quantity, 0 ),
            'discount'    => self::calculate_item_discount_percentage( $subtotal, $total ),
            'quantity'    => $quantity,
        );

        // Agregar impuesto si está configurado
        if ( self::$integration_settings->tax ) {
            $invoice_item['tax'] = array(
                array(
                    'id' => self::$integration_settings->tax,
                ),
            );
        }

        return $invoice_item;
    }

    /**
     * Calcula el porcentaje de descuento de un item
     *
     * @param float $subtotal Subtotal del item (antes de descuento)
     * @param float $total Total del item (después de descuento)
     * @return int Porcentaje de descuento redondeado
     */
    private static function calculate_item_discount_percentage( float $subtotal, float $total ): int {
        if ( $subtotal <= 0 ) {
            return 0;
        }

        return round( ( $subtotal - $total ) / $subtotal * 100 );
    }

    /**
     * Obtiene o crea el item de envío en Alegra y construye los datos para la factura
     *
     * @param WC_Order $order Orden de WooCommerce
     * @param int|null $custom_tax_id ID de impuesto personalizado (opcional)
     * @return array Datos del item de envío formateados para la factura
     */
    private static function get_or_create_shipping_item( WC_Order $order, ?int $custom_tax_id = null ): array {
        $shipping_item_id = self::get_or_create_shipping_product_in_alegra();

        return self::build_shipping_invoice_item( $order, $shipping_item_id, $custom_tax_id );
    }

    /**
     * Obtiene o crea el producto de envío en Alegra
     *
     * @return int ID del producto de envío en Alegra
     */
    private static function get_or_create_shipping_product_in_alegra(): int {
        $item_id = self::find_existing_product( self::SKU_SHIPPING );

        if ( $item_id ) {
            return $item_id;
        }

        return self::create_shipping_product_in_alegra();
    }

    /**
     * Crea el producto de envío en Alegra
     *
     * @return int ID del producto de envío creado
     */
    private static function create_shipping_product_in_alegra(): int {
        $shipping_data = self::build_shipping_product_data();

        $response = self::get_instance()->createItem( $shipping_data );

        return $response['id'];
    }

    /**
     * Construye el array de datos del producto de envío para Alegra
     *
     * @return array Datos del producto de envío formateados para la API de Alegra
     */
    private static function build_shipping_product_data(): array {
        return array(
            'price'       => 20000,
            'name'        => 'Envío',
            'type'        => 'service',
            'reference'   => self::SKU_SHIPPING,
            'description' => 'Producto tipo servicio destinado para el envío',
            'inventory'   => array(
                'unit' => 'service',
            ),
        );
    }

    /**
     * Construye los datos del item de envío para la factura
     *
     * @param WC_Order $order Orden de WooCommerce
     * @param int $shipping_item_id ID del producto de envío en Alegra
     * @return array Datos del item de envío formateados para la factura
     */
    private static function build_shipping_invoice_item( WC_Order $order, int $shipping_item_id ): array {
        $shipping_item = array(
            'id'       => $shipping_item_id,
            'name'     => 'Envío',
            'price'    => wc_format_decimal( $order->get_shipping_total(), 0 ),
            'quantity' => 1,
        );

        // Agregar impuesto si está configurado
        if ( self::$integration_settings->shipping_tax ) {
            $shipping_item['tax'] = array(
                array(
                    'id' => self::$integration_settings->shipping_tax,
                ),
            );
        }

        return $shipping_item;
    }

    /**
     * Recolecta los IDs de facturas de Alegra que necesitan ser timbradas
     *
     * Filtra los pedidos para encontrar aquellos que tienen factura en Alegra
     * pero aún no han sido timbrados en la DIAN.
     *
     * @param array $post_ids IDs de los pedidos de WooCommerce
     * @return array Mapeo de invoice_id_alegra => post_id para facturas pendientes
     */
    private static function collect_invoice_ids_to_stamp( array $post_ids ): array {
        $invoice_ids_map = array();
        
        // Limitar a la cantidad máxima de facturas permitidas
        $post_ids_limited = array_slice( $post_ids, 0, self::MAX_INVOICES_TO_STAMP );

        foreach ( $post_ids_limited as $post_id ) {
            $invoice_data = self::get_invoice_stamp_status( $post_id );
            
            if ( self::should_stamp_invoice( $invoice_data ) ) {
                $invoice_ids_map[ $invoice_data['invoice_id'] ] = $post_id;
            }
        }

        return $invoice_ids_map;
    }

    /**
     * Obtiene el estado de timbrado de una factura desde los metadatos del pedido
     *
     * @param int $post_id ID del pedido de WooCommerce
     * @return array Array con invoice_id y estado de emisión
     */
    private static function get_invoice_stamp_status( int $post_id ): array {
        return array(
            'invoice_id' => get_post_meta( $post_id, '_invoice_id_alegra', true ),
            'is_emitted' => get_post_meta( $post_id, '_invoice_emit_alegra', true ),
        );
    }

    /**
     * Determina si una factura debe ser timbrada
     *
     * Una factura debe ser timbrada si:
     * - Tiene un ID de factura en Alegra
     * - No ha sido emitida aún
     *
     * @param array $invoice_data Datos de la factura con invoice_id e is_emitted
     * @return bool True si debe ser timbrada, false en caso contrario
     */
    private static function should_stamp_invoice( array $invoice_data ): bool {
        return ! empty( $invoice_data['invoice_id'] ) && empty( $invoice_data['is_emitted'] );
    }

    /**
     * Envía las facturas a Alegra para su timbrado en la DIAN
     *
     * @param array $invoice_ids_map Mapeo de invoice_id_alegra => post_id
     * @return void
     */
    private static function stamp_invoices_in_alegra( array $invoice_ids_map ): void {
        try {
            $invoice_ids = array_keys( $invoice_ids_map );
            $response = self::get_instance()->stampInvoices( $invoice_ids );

            self::process_stamp_response( $response, $invoice_ids_map );

        } catch ( Exception $exception ) {
            integration_alegra_wc_smp()->log( 
                sprintf( 
                    'Error al timbrar facturas: %s', 
                    $exception->getMessage() 
                ) 
            );
        }
    }

    /**
     * Procesa la respuesta del timbrado y actualiza los metadatos
     *
     * @param array $response Respuesta de la API de Alegra
     * @param array $invoice_ids_map Mapeo de invoice_id_alegra => post_id
     * @return void
     */
    private static function process_stamp_response( array $response, array $invoice_ids_map ): void {
        if ( ! isset( $response['data'] ) || ! is_array( $response['data'] ) ) {
            return;
        }

        foreach ( $response['data'] as $invoice_result ) {
            if ( self::is_stamp_successful( $invoice_result, $invoice_ids_map ) ) {
                $post_id = $invoice_ids_map[ $invoice_result['id'] ];
                self::mark_invoice_as_emitted( $post_id );
            }
        }
    }

    /**
     * Verifica si el timbrado de una factura fue exitoso
     *
     * @param array $invoice_result Resultado del timbrado de una factura
     * @param array $invoice_ids_map Mapeo de invoice_id_alegra => post_id
     * @return bool True si el timbrado fue exitoso, false en caso contrario
     */
    private static function is_stamp_successful( array $invoice_result, array $invoice_ids_map ): bool {
        return isset( $invoice_result['success'] ) 
            && $invoice_result['success'] 
            && isset( $invoice_result['id'] )
            && isset( $invoice_ids_map[ $invoice_result['id'] ] );
    }

    /**
     * Marca una factura como emitida en los metadatos del pedido
     *
     * @param int $post_id ID del pedido de WooCommerce
     * @return void
     */
    private static function mark_invoice_as_emitted( int $post_id ): void {
        update_post_meta( $post_id, '_invoice_emit_alegra', true );
        
        // Opcional: agregar nota al pedido
        $order = wc_get_order( $post_id );
        if ( $order ) {
            $order->add_order_note( __( 'Factura timbrada exitosamente en la DIAN.' ) );
        }
    }
}