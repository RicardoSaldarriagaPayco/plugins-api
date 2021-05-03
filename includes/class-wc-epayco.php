<?php
/**
 * WooCommerce Epayco SE Class
 *
 *
 * @since 4.0.2
 */


class Epayco_SE extends WC_Epayco
{
    public $epayco;

    public function __construct()
    {
        parent::__construct();

        $lang =  get_locale();
        $lang = explode('_', $lang);
        $lang = $lang[0];

        $this->epayco = new Epayco\Epayco(array(
            "apiKey" => trim($this->epayco_publickey),
            "privateKey" => trim($this->epayco_privatekey),
            "lenguage" => strtoupper($lang),
            "test" => $this->testmode
        ));
    }

    public function charge_epayco(array $params)
    {
        $order_id = $params['id_order'];
        $order = new WC_Order($order_id);
        if (!EpaycoOrder::ifExist($order_id)) {
            //si no se restauro el stock restaurarlo inmediatamente
            $this->restore_order_stock($order_id);
            EpaycoOrder::create($order_id,1);
        }

        $descripcionParts = array();
        foreach ($order->get_items() as $product) {
            $descripcionParts[] = $this->string_sanitize($product['name']);
        }

        $descripcion = implode(' - ', $descripcionParts);

        $tax=$order->get_total_tax();
        $tax=round($tax,2);
        if((int)$tax>0){
            $base_tax=$order->get_total()-$tax;
        }else{
            $base_tax=0;
            $tax=0;
        }

        $redirect_url =get_site_url() . "/";
        $confirm_url=get_site_url() . "/";
        $redirect_url = add_query_arg( 'wc-api', 'WC_Epayco' , $redirect_url );
        $redirect_url = add_query_arg( 'order_id', $order_id, $redirect_url );
        $confirm_url = add_query_arg( 'wc-api', 'WC_Epayco' , $confirm_url );
        $confirm_url = add_query_arg( 'order_id', $order_id, $confirm_url );
        $confirm_url = $redirect_url.'&confirmation=1';

        $orderInfo = array(
            //'bill' => $order->get_id(),
            "description" => $descripcion,
            "value" => $order->get_total(),
            "tax" => $tax,
            "tax_base" => $base_tax,
            "currency" => $order->get_currency(),
            "url_response" => $redirect_url,
            "url_confirmation" => $confirm_url,
            "use_default_card_customer" => true,
            "ip" => $this->getIP(),
            "extras"=> array(
                "extra1" => "Woocommerce",
                "extra2" => $order->get_id()
                    )
        );

        $card = $this->prepareDataCard($params);
        $token = $this->tokenCreate($card);
        if($token->status){
        //if(true){
        $customerData = $this->paramsBilling($order);
        $customerData['token_card'] = $token->id;
        //$customerData['token_card'] = '0805db51e5c2b522f111d85';
    
        $sql = EpaycoRules::ifExist(trim($this->epayco_customerid ));
        if(!$sql) {
            $customer = $this->customerCreate($customerData);
            if($customer->status){
            //if(true){
                $customerData['customer_id'] = $customer->data->customerId;
                //$customerData['customer_id'] = '0804a8f30fd0550b728194d';

                $registerCustomerToBd= EpaycoRules::create(trim($this->epayco_customerid ),$customerData['customer_id'],$customerData['token_card'],$customerData['email']);
                if(!$registerCustomerToBd){
                    $register = 'please, try again!';
                    return $response_status = [
                        'status' => false,
                        'message' => __(
                            $register,
                            'woocommerce-gateway-epayco')
                    ]; 
                }
                         
            }else{
                $response_status = [
                    'status' => false,
                    'message' => __(
                        'Cliente ya asociado ó token inexistente',
                         'woocommerce-gateway-epayco')
                ]; 
            }
        }else{

            
            $counter = 0;
           for ($i=0; $i < count($sql); $i++) { 
                         
                if($sql[$i]->email == $customerData['email']){
                    $customerAddtoken = $this->customerAddToken($sql[$i]->customer_id, $customerData['token_card']);
                        if(!$customerAddtoken->status){
                                $response_status = [
                                    'status' => false,
                                    'message' => __('Cliente ya asociado ó token inexistente', 'woocommerce-gateway-epayco')
                                ];
                                return $response_status;
                            }                
                $customer_id_ =  $sql[$i]->customer_id;
                }else{
                    $counter += 1;
                }  
                      
            }
            if($counter == count($sql))
            {
                       
                            $customer = $this->customerCreate($customerData);
                            if($customer->status)
                            {
                                $customerData['customer_id'] = $customer->data->customerId;
                                
                                $registerCustomerToBd= EpaycoRules::create(trim($this->epayco_customerid ),$customerData['customer_id'],$customerData['token_card'],$customerData['email']);
                                if(!$registerCustomerToBd){
                                    $register = 'please, try again!';
                                    return $response_status = [
                                        'status' => false,
                                        'message' => __(
                                            $register,
                                            'woocommerce-gateway-epayco')
                                    ]; 
                                }
                                         
                            }else{
                                $response_status = [
                                    'status' => false,
                                    'message' => __(
                                        'Cliente ya asociado ó token inexistente',
                                         'woocommerce-gateway-epayco')
                                ]; 
                            }
                            
            }

            
        }

        $customerData['customer_id'] = $customerData['customer_id'] ? $customerData['customer_id'] : $customer_id_;

 
                $customerData = array_merge($customerData, $card);
                $paymentCharge = array_merge($customerData, $orderInfo );
                $paymentCharge_ = $this->paymentCharge($paymentCharge);
                if($paymentCharge_->status){
                    switch ((int)$paymentCharge_->data->cod_respuesta) {
                        case 1:{
                            if (!EpaycoOrder::ifStockDiscount($order_id)) {
                                //se descuenta el stock
                                if (EpaycoOrder::updateStockDiscount($order_id,1)) {
                                    $this->restore_order_stock($order_id,'decrease');
                                }
                            }
                            $order->payment_complete();
                            $order->update_status('completed');
                            $note  = sprintf(__('Successful Payment (ref_payco: %s)', 'woocommerce-gateway-epayco'),
                            $paymentCharge_->data->ref_payco);
                            $order->add_order_note($note);
                            update_post_meta($order->get_id(), 'ref_payco', $paymentCharge_->data->ref_payco);
                        }break;
                        case 2: {
                            $order->update_status('failed');
                            $note  = sprintf(__('Rejected Payment (ref_payco: %s)', 'woocommerce-gateway-epayco'),
                            $paymentCharge_->data->ref_payco);
                            $order->add_order_note($note);
                            update_post_meta($order->get_id(), 'ref_payco', $paymentCharge_->data->ref_payco);
                        }break;
                        case 3: {
                            //Busca si ya se restauro el stock y si se configuro reducir el stock en transacciones pendientes  
                            if (!EpaycoOrder::ifStockDiscount($order_id)) {
                                //reducir el stock
                                if (EpaycoOrder::updateStockDiscount($order_id,1)) {
                                    $this->restore_order_stock($order_id,'decrease');
                                }
                            }
                            $order->update_status('pending');
                            $note  = sprintf(__('Pending Payment (ref_payco: %s)', 'woocommerce-gateway-epayco'),
                            $paymentCharge_->data->ref_payco);
                            $order->add_order_note($note);
                            update_post_meta($order->get_id(), 'ref_payco', $paymentCharge_->data->ref_payco);
                        }break;
                        case 4: {
                            $order->update_status('failed');
                            $note  = sprintf(__('Rejected Payment (ref_payco: %s)', 'woocommerce-gateway-epayco'),
                            $paymentCharge_->data->ref_payco);
                            $order->add_order_note($note);
                            update_post_meta($order->get_id(), 'ref_payco', $paymentCharge_->data->ref_payco);
                        }break;
                        default:{
                            $order->update_status('failed');
                            $note  = sprintf(__('failed Payment (ref_payco: %s)', 'woocommerce-gateway-epayco'),
                            $paymentCharge_->data->ref_payco);
                            $order->add_order_note($note);
                            update_post_meta($order->get_id(), 'ref_payco', $paymentCharge_->data->ref_payco);
                        }break;
                    }
                    $response_status = 
                        ['status' => true,
                        'message' => '',
                        'url' => $this->get_return_url( $order )
                        ];
                }else{
                    $response_status = [
                        'status' => false,
                        'message' => __(
                            $paymentCharge_->data->errors[0]->errorMessage,
                             'woocommerce-gateway-epayco')
                    ];
                }

            

        }else{
            $response_status = [
                'status' => false,
                'message' => __('Los datos son erroneos o son requeridos por favor intente nuevamente!.', 'woocommerce-gateway-epayco')
            ];
        }

        return $response_status;
    }

    public function prepareDataCard(array $params)
    {
        $data = [];
        $card_number = str_replace(" ", "", $params['subscriptionepayco_number']);
        //$data['card_number'] = str_replace(' ','', $card_number);
        $data['card_number'] = $card_number;
        //$card_expire = explode('/', $params['subscriptionepayco_expiry']);
        $data['cvc'] = $params['cvc'];
        $data['card_expire_year'] = $params['year-value'];
        $data['card_expire_month'] = $params['month-value'];
        $data['card_holder_name'] = $params['card_name'];
        $data['dues'] = $params['dues'];
        return $data;
    }

    public function tokenCreate(array $data)
    {
        $token = false;

        try{
            $token = $this->epayco->token->create(
                array(
                    "card[number]" => $data['card_number'],
                    "card[exp_year]" => $data['card_expire_year'],
                    "card[exp_month]" => $data['card_expire_month'],
                    "card[cvc]" => $data['cvc']
                )
            );
        }catch (Exception $exception){
            //subscription_epayco_se()->log('tokenCreate: ' . $exception->getMessage());
            echo 'tokenCreate: ' . $exception->getMessage();
            die();
        }

        return $token;
    }

    public function paramsBilling($order)
    {
        $data = [];
        $data['name'] = $name_billing=$order->get_billing_first_name(); 
        $data['last_name'] = $name_billing=$order->get_billing_last_name();
        $data['email'] = $order->get_billing_email();
        $data['phone'] = $order->get_billing_phone();
        $data['country'] = $order->get_shipping_country() ? $order->get_shipping_country() : $order->get_billing_country();
        $data['city'] = $order->get_shipping_city() ? $order->get_shipping_city() : $order->get_billing_city();
        $data['address'] = $order->get_shipping_address_1() ? $order->get_shipping_address_1() . " " . $order->get_shipping_address_2() : $order->get_billing_address_1() . " " . $order->get_billing_address_2();
        $data['doc_number'] = get_post_meta( $order->get_id(), '_billing_dni', true );
        $data['type_document'] = get_post_meta( $order->get_id(), '_billing_type_document', true );

        return $data;
    }

    public function customerCreate(array $data)
    {
        $customer = false;

        try{
            $customer = $this->epayco->customer->create(
                [
                    "token_card" => $data['token_card'],
                    "name" => $data['card_holder_name'],
                    "email" => $data['email'],
                    "phone" => $data['phone'],
                    "cell_phone" => $data['phone'],
                    "country" =>  $data['country'],
                    "city" => $data['city'],
                    "address" => $data['address'],
                    "default" => true
                ]
            );
        }catch (Exception $exception){
            //subscription_epayco_se()->log('create client: ' . $exception->getMessage());
        }

        return $customer;
    }

    public function customerAddToken($customer_id, $token_card)
    {

        $customer = false;
        try{
            $customer = $this->epayco->customer->addNewToken(
                [
                    "token_card" => $token_card,
                    "customer_id" => $customer_id
                ]
            );
        }catch (Exception $exception){
           echo 'add token: ' . $exception->getMessage();
           die();
        }

        return $customer;
    }

    public function paymentCharge(array $data)
    {
        $payment = false;

        try{
            $payment = $this->epayco->charge->create( array(
                "token_card" => $data['token_card'],
                "customer_id" => $data['customer_id'],
                "doc_type" => $data['type_document'],
                "doc_number" => $data['doc_number'],
                "name" => $data['name'],
                "last_name" => $data['last_name'],
                "email" => $data['email'],
                //"bill" => "OR-1234",
                "description" => $data['description'],
                "value" => $data['value'],
                "tax" => $data['tax'],
                "tax_base" => $data['tax_base'],
                "currency" => $data['currency'],
                "dues" => $data['dues'],
                "address" => $data['address'],
                "phone"=> $data['address'],
                "ip" => $data['ip'],  // This is the client's IP, it is required
                "url_response" => $data['url_response'],
                "url_confirmation" => $data['url_confirmation'],
            ));
        }catch (Exception $exception){
            //subscription_epayco_se()->log('tokenCreate: ' . $exception->getMessage());
            echo 'paymentCreate: ' . $exception->getMessage();
            die();
        }
        return $payment;
    }

    public function string_sanitize($string, $force_lowercase = true, $anal = false) {
        $strip = array("~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "=", "+", "[", "{", "]",
        "}", "\\", "|", ";", ":", "\"", "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
        "â€”", "â€“", ",", "<", ".", ">", "/", "?");
        $clean = trim(str_replace($strip, "", strip_tags($string)));
        $clean = preg_replace('/\s+/', "_", $clean);
        $clean = ($anal) ? preg_replace("/[^a-zA-Z0-9]/", "", $clean) : $clean ;
        return $clean;
    }


    public function getIP()
    {
        if (getenv('HTTP_CLIENT_IP'))
            $ipaddress = getenv('HTTP_CLIENT_IP');
        else if(getenv('HTTP_X_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        else if(getenv('HTTP_X_FORWARDED'))
            $ipaddress = getenv('HTTP_X_FORWARDED');
        else if(getenv('HTTP_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        else if(getenv('HTTP_FORWARDED'))
            $ipaddress = getenv('HTTP_FORWARDED');
        else if(getenv('REMOTE_ADDR'))
            $ipaddress = getenv('REMOTE_ADDR');
        else
            $ipaddress = '127.0.0.1';

        return $ipaddress;
    }

    
    public function restore_order_stock($order_id,$operation = 'increase')
    {
        $order = wc_get_order($order_id);
        if (!get_option('woocommerce_manage_stock') == 'yes' && !sizeof($order->get_items()) > 0) {
            return;
        }
        foreach ($order->get_items() as $item) {
            // Get an instance of corresponding the WC_Product object
            $product = $item->get_product();
            $qty = $item->get_quantity(); // Get the item quantity
            wc_update_product_stock($product, $qty, $operation);
        }
    }
}