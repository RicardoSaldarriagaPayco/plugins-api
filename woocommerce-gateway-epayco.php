<?php
/**
 * Plugin Name: WooCommerce ePayco Gateway
 * Plugin URI: https://wordpress.org/plugins/woocommerce-gateway-epayco/
 * Description: Take credit card payments on your store using ePayco.
 * Author: ePayco
 * Author URI: https://epayco.co/
 * Version: 5.0.0
 * Requires at least: 4.4
 * Tested up to: 5.6
 * WC requires at least: 3.0
 * WC tested up to: 5.0
 * Text Domain: woocommerce-gateway-epayco
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums and constants
 */
define( 'WC_EPAYCO_VERSION', '5.0.0' );
require_once(dirname(__FILE__) . '/lib/EpaycoOrder.php');
require_once(dirname(__FILE__) . '/lib/EpaycoRules.php');
function woocommerce_gateway_epayco() {
    static $plugin;
    if (!isset($plugin)){

            class WC_Epayco extends WC_Payment_Gateway {
            /**
             * Filepath of main plugin file.
             *
             * @var string
             */
            public $file;
            /**
             * Plugin version.
             *
             * @var string
             */
            public $version;
            /**
             * Absolute plugin path.
             *
             * @var string
             */
            public $plugin_path;
            /**
             * Absolute plugin URL.
             *
             * @var string
             */
            public $plugin_url;
            /**
             * Absolute path to plugin includes dir.
             *
             * @var string
             */
            public $includes_path;
            /**
             * @var WC_Logger
             */
            public $logger;
            /**
             * @var bool
             */
            private $_bootstrapped = false;

            public function __construct()
            {
                $this->id = 'epayco';
                $this->file = __FILE__;
                $this->plugin_url    = trailingslashit( plugin_dir_url( $this->file ) );
                $this->icon = $this->plugin_url . 'assets/images/logo.png';
                $this->method_description = __( 'ePayco works by adding payment fields on the checkout and then sending the details to ePayco for verification.', 'woocommerce-gateway-epayco' );
                $this->has_fields         = true;
                $this->supports           = [
                    'products'
                ];
                // Load the form fields.
                $this->init_form_fields();

                // Load the settings.
                $this->init_settings();

                $this->title                = $this->get_option( 'title' );
                $this->description          = $this->get_option( 'description' );
                $this->epayco_customerid    = $this->get_option('epayco_customerid');
                $this->epayco_secretkey     = $this->get_option('epayco_secretkey');
                $this->epayco_publickey     = $this->get_option('epayco_publickey');
                $this->epayco_privatekey    = $this->get_option('epayco_privatekey');
                
                //$this->enabled              = $this->get_option( 'enabled' );
                $this->testmode             = $this->get_option( 'testmode' );


                $this->version = WC_EPAYCO_VERSION;
                $this->name = 'epayco';
                // Path.
                $this->plugin_path   = trailingslashit( plugin_dir_path( $this->file ) );
               
                $this->includes_path = $this->plugin_path . trailingslashit( 'includes' );


                $this->plugin_path   = trailingslashit( plugin_dir_path( $this->file ) );
                $this->includes_path = $this->plugin_path;
                $this->lib_path = $this->plugin_path . trailingslashit( 'lib' );
                load_plugin_textdomain('woocommerce-gateway-epayco', FALSE, dirname(plugin_basename(__FILE__)) . '/languages');
                //$this->lib_path = $this->plugin_path . trailingslashit( 'lib' );
                $this->init();
            }

            /**
            * Init the plugin after plugins_loaded so environment variables are set.
            *
            * @since 1.0.0
            * @version 5.0.0
            */
            public function init() {
                add_filter( 'woocommerce_payment_gateways', array($this, 'add_gateways'));
                add_filter( 'woocommerce_checkout_process', array($this, 'process_custom_payment'));
                add_filter( 'plugin_action_links_' . plugin_basename( $this->file), array( $this, 'plugin_action_links' ) );
                add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_ePayco_response' ) );
                add_action('ePayco_init', array( $this, 'ePayco_successful_request'));
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
                add_action('wp_ajax_nopriv_returndata',array($this,'datareturnepayco_ajax'));
                add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
                add_filter( 'woocommerce_checkout_fields', array($this, 'custom_woocommerce_billing_fields'));

                
                if ($this->testmode == "yes") {
                    if (class_exists('WC_Logger')) {
                        $this->log = new WC_Logger();
                    } else {
                        $this->log = WC_ePayco::woocommerce_instance()->logger();
                    }
                }
                if (!class_exists('Epayco\Epayco'))
                    require_once ($this->lib_path . 'vendor/autoload.php');
                //require_once (plugin_dir_url(__FILE__) . 'lib/vendor/autoload.php');
                
                require_once (dirname( __FILE__ ) . '/includes/class-wc-epayco.php');
            }

            public function add_gateways( $methods ) {
                $methods[] = 'WC_Epayco';
                return $methods;
            }
            public function enqueue_scripts()
            {
                $gateways = WC()->payment_gateways->get_available_payment_gateways();
                
                if($gateways['epayco']->enabled === 'yes' && is_checkout()){

                    wp_enqueue_script( 'fontawesome', 'https://kit.fontawesome.com/fc569eac4d.js', array(  ), $this->version, true );
                    wp_enqueue_script( 'imask', 'https://cdnjs.cloudflare.com/ajax/libs/imask/3.4.0/imask.min.js', array(  ), $this->version, true );
                    wp_enqueue_script( 'subscription-epayco', plugin_dir_url( __FILE__ ) . 'assets/js/subscription-epayco.js', array( 'jquery' ), $this->version, true );
                    wp_enqueue_script( 'subscription-epayco-card', plugin_dir_url( __FILE__ ) . 'assets/js/card.js', array( 'jquery' ), $this->version, true );
                    //////////////////////////////////////////////////////////

                    wp_enqueue_style('style-woocommerce-gateway-epayco', plugin_dir_url(__FILE__). 'assets/css/style.css', array(), $this->version, null);
                    wp_enqueue_style('general-woocommerce-gateway-epayco', plugin_dir_url(__FILE__). 'assets/css/general.css', array(), $this->version, null);
                    wp_enqueue_style('card-js-woocommerce-gateway-epayco', plugin_dir_url(__FILE__). 'assets/css/card-js.min.css', array(), $this->version, null);

                }
            }

            public function custom_woocommerce_billing_fields($fields)
            {
                $fields['billing']['billing_type_document'] = array(
                    'label'       => __('Tipo de documento', 'woocommerce-gateway-epayco'),
                    'placeholder' => _x('', 'placeholder', 'woocommerce-gateway-epayco'),
                    'required'    => true,
                    'clear'       => false,
                    'type'        => 'select',
                    'default' => 'CC',
                    'options'     => array(
                        'CC' => __('Cédula de ciudadanía' ),
                        'CE' => __('Cédula de extranjería'),
                        'PPN' => __('Pasaporte'),
                        'SSN' => __('Número de seguridad social'),
                        'LIC' => __('Licencia de conducción'),
                        'NIT' => __('(NIT) Número de indentificación tributaria'),
                        'TI' => __('Tarjeta de identidad'),
                        'DNI' => __('Documento nacional de identificación')
                    )
                );                                                                        
                $fields['billing']['billing_dni'] = array(
                    'label' => __('DNI', 'woocommerce-gateway-epayco'),
                    'placeholder' => _x('Your DNI here....', 'placeholder', 'woocommerce-gateway-epayco'),
                    'required' => true,
                    'clear' => false,
                    'type' => 'number',
                    'class' => array('my-css')
                );
        
        
                $fields['shipping']['shipping_type_document'] = array(
                    'label'       => __('Tipo de documento', 'woocommerce-gateway-epayco'),
                    'placeholder' => _x('', 'placeholder', 'woocommerce-gateway-epayco'),
                    'required'    => true,
                    'clear'       => false,
                    'type'        => 'select',
                    'default' => 'CC',
                    'options'     => array(
                        'CC' => __('Cédula de ciudadanía' ),
                        'CE' => __('Cédula de extranjería'),
                        'PPN' => __('Pasaporte'),
                        'SSN' => __('Número de seguridad social'),
                        'LIC' => __('Licencia de conducción'),
                        'NIT' => __('(NIT) Número de indentificación tributaria'),
                        'TI' => __('Tarjeta de identidad'),
                        'DNI' => __('Documento nacional de identificación')
                    )
                );
        
                $fields['shipping']['shipping_dni'] = array(
                    'label' => __('DNI', 'woocommerce-gateway-epayco'),
                    'placeholder' => _x('Your DNI here....', 'placeholder', 'woocommerce-gateway-epayco'),
                    'required' => true,
                    'clear' => false,
                    'type' => 'number',
                    'class' => array('my-css')
                );
        
                return $fields;
            }

            public function plugin_action_links($links)
            {
                $plugin_links = array();
                $plugin_links[] = '<a href="'.admin_url( 'admin.php?page=wc-settings&tab=checkout&section=epayco').'">' . esc_html__( 'Configuraciones') . '</a>';
                return array_merge( $plugin_links, $links );
            }

            public function is_valid_for_use()
            {
                return in_array(get_woocommerce_currency(), array('COP', 'USD'));
            }

            /**
             * Initialise Gateway Settings Form Fields
             */
            public function init_form_fields() {

                 $this->form_fields = require (dirname( __FILE__ ) . '/includes/admin/epayco-settings.php');
               
            }

             /**
         * Output for the order received page.
         */
        public function thankyou_page() {
            if ( $this->instructions )
                echo wpautop( wptexturize( $this->instructions ) );
        }

            public function admin_options()
            {
                ?>
                <style>
                    tbody{
                    }
                    .epayco-table tr:not(:first-child) {
                        border-top: 1px solid #ededed;
                    }
                    .epayco-table tr th{
                            padding-left: 15px;
                            text-align: -webkit-right;
                    }
                    .epayco-table input[type="text"]{
                            padding: 8px 13px!important;
                            border-radius: 3px;
                            width: 100%!important;
                    }
                    .epayco-table .description{
                        color: #afaeae;
                    }
                    .epayco-table select{
                            padding: 8px 13px!important;
                            border-radius: 3px;
                            width: 100%!important;
                            height: 37px!important;
                    }
                    .epayco-required::before{
                        content: '* ';
                        font-size: 16px;
                        color: #F00;
                        font-weight: bold;
                    }

                </style>
                <div class="container-fluid">
                    <div class="panel panel-default" style="">
                        <img  src="https://369969691f476073508a-60bf0867add971908d4f26a64519c2aa.ssl.cf5.rackcdn.com/logos/logo_epayco_200px.png">
                        <div class="panel-heading">
                            <h3 class="panel-title"><i class="fa fa-pencil"></i>Configuración <?php _e('ePayco', 'woocommerce-gateway-epayco'); ?></h3>
                        </div>
                        <div style ="color: #31708f; background-color: #d9edf7; border-color: #bce8f1;padding: 10px;border-radius: 5px;">
                            <b>Este modulo le permite aceptar pagos seguros por la plataforma de pagos ePayco</b>
                            <br>Si el cliente decide pagar por ePayco, el estado del pedido cambiara a ePayco Esperando Pago
                            <br>Cuando el pago sea Aceptado o Rechazado ePayco envia una configuracion a la tienda para cambiar el estado del pedido.
                        </div>
                        <div class="panel-body" style="padding: 15px 0;background: #fff;margin-top: 15px;border-radius: 5px;border: 1px solid #dcdcdc;border-top: 1px solid #dcdcdc;">
                            <table class="form-table epayco-table">
                                <?php
                                    if ($this->is_valid_for_use()) :
                                        $this->generate_settings_html();
                                    else :
                                        if ( is_admin() && ! defined( 'DOING_AJAX')) {
                                            echo '<div class="error"><p><strong>' . __( 'ePayco: Requiere que la moneda sea USD O COP', 'woocommerce-gateway-epayco' ) . '</strong>: ' . sprintf(__('%s', 'woocommerce-gateway-epayco' ), '<a href="' . admin_url() . 'admin.php?page=wc-settings&tab=general#s2id_woocommerce_currency">' . __( 'Click aquí para configurar!', 'woocommerce-gateway-epayco') . '</a>' ) . '</p></div>';
                                        }
                                    endif;
                                ?>
                            </table>
                        </div>
                    </div>
                </div>
                <?php
            }

            public function payment_fields()
            {
                if ( $description = $this->get_description() )
                    echo wp_kses_post( wpautop( wptexturize( $description ) ) );
                    // global $wpdb;
                    // $table_name = $wpdb->prefix . "payco_rules";
                    //  $sql = 'SELECT * FROM `wp_payco_rules` WHERE `id_payco` = '. trim($this->epayco_customerid) ;
          
                    // $results = $wpdb->get_results($sql, OBJECT);
                   
                    // if (count($results) == 0)
                    // {
                    //    var_dump($sql);
                    // }else{
                    //     $counter = 0;
                    //     for ($i=0; $i < count($results); $i++) { 
                          
                    //        if($results[$i]->email == 'ricardo.saldarriaga7321@payco.co'){
                    //         echo $results[$i]->email .'<br>';
                    //        }else{
                    //         $counter += 1;
                    //        }
                    //     }
                    //     if($counter == count($results))
                    //     {
                    //         echo 'crear un nuevo cliente'.'<br>';
                            
                    //     }else{
                    //         echo 'no crear cliente nuevo' .'<br>';
                    //     }
                        
                    // }
                ?>


                <div class="middle-xs bg_onpage porcentbody m-0" style="margin: 0">
                    <div class="centered" id="centered">
                        <div class="onpage relative" id="web-checkout-content" style="
                                    border-radius: 5px;">
                            <div class="body-modal fix-top-safari">
                                
                                <div class="bar-option hidden-print">
                                    <div class="dropdown select-pais pointer">
                                        <br>
                                    </div>
                                </div>
                                                                
                                <div class="wc scroll-content">
                                    <div class="menu-select">

                                            <div class="input-form">
                                                <span style="
                                                    position: absolute;
                                                    padding-left: 63px;
                                                    padding-top: 58px !important;
                                                    line-height: 40px;
                                                    font-size: 5px;
                                                    ">
                                                    <i class="fas fa-user loadshield2" style="color: #158cba; font-size: 17px;" aria-hidden="true"></i>
                                                </span>
                                                <input type="text" class="binding-input inspectletIgnore"  name="card_name" id="card_name" placeholder="<?php echo __('Cardholder', 'woocommerce-gateway-epayco'); ?>"  autocomplete="off" style="
                                                    border: 1px solid #e0e0e0;
                                                " value="ricardo saldarriaga" >
                                            </div>

                                            <div class="input-form">
                                            <span style="
                                                    position: absolute;
                                                    padding-left: 63px;
                                                    padding-top: 58px !important;
                                                    line-height: 40px;
                                                    font-size: 5px;
                                                    ">
                                                    <i class="fas fa-credit-card loadshield2" style="color: #158cba; font-size: 17px;" aria-hidden="true"></i>
                                                </span>
                                                <div id="card-epayco-suscribir">
                                                    <div class='card-wrapper'></div>
                                                    <div id="form-epayco">
                                                    
                                                        <input placeholder="<?php echo __('Card number', 'woocommerce-gateway-epayco'); ?>" type="tel" name="subscriptionepayco_number" id="subscriptionepayco_number" required="" class="form-control" style="
                                                        border: 1px solid #e0e0e0;">

                                                </div>
                                      
                                            </div>

                                            <div class="select-option bordergray vencimiento" style="float:left" id="expiration">

                                                <div class="input-form full-width noborder monthcredit nomargin">         
                                                    <span class="icon-date_range color icon-select">
                                                        <i class="far fa-calendar-alt"></i>
                                                    </span>
                                                    <input class="binding-input inspectletIgnore" id="month-value" name="month-value" placeholder="MM" maxlength="2" autocomplete="off" data-epayco="card[exp_month]"  inputmode="numeric"  pattern="[0-9]*" required value="03">
                                                </div>
                                            
                                                <div class="" style="
                                                                float:left;
                                                                width:10%;
                                                                margin-top:-3px;
                                                                text-align:center;
                                                                line-height: 40px;
                                                                height: 38px;
                                                                background-color: white;
                                                                color:#a3a3a3;
                                                                ">
                                                                /
                                                </div>

                                                <div class="input-form full-width normalinput noborder yearcredit nomargin">                
                                                    <input class="binding-input inspectletIgnore" name="year-value" id="year-value" placeholder="AAAA" maxlength="4" autocomplete="off" data-epayco="card[exp_year]" pattern="[0-9]*" inputmode="numeric" required value="2023">
                                                </div>

                                            </div>

                                            <div class="input-form normalinput cvv_style"       id="cvc_">
                                                <input type="password" placeholder="CVC" class="nomargin  binding-input" name="cvc" id="card_cvc" autocomplete="off" maxlength="4" data-epayco="card[cvc]" style="width: 85% !important;border: 1px solid #e0e0e0 !important;" value="1234">
                                                <i class="fa color fa-question-circle pointer" aria-hidden="true" style="right: 16px; padding: 0;" id="look-cvv"></i>
                                            </div>

                                            <div class="select-option cuotas bordergray">
                                                <select class="select binding-select" name="dues" style="width: 100%;">
                                                    <option value="0">Cuotas</option>
                                                    <option value="01">1</option>
                                                    <option value="02">2</option>
                                                    <option value="03">3</option>
                                                    <option value="04">4</option>
                                                    <option value="05">5</option>
                                                    <option value="06">6</option>
                                                    <option value="07">7</option>
                                                    <option value="08">8</option>
                                                    <option value="09">9</option>
                                                    <option value="10">10</option>
                                                    <option value="11">11</option>
                                                    <option value="12">12</option>
                                                    <option value="13">13</option>
                                                    <option value="14">14</option>
                                                    <option value="15">15</option>
                                                    <option value="16">16</option>
                                                    <option value="17">17</option>
                                                    <option value="18">18</option>
                                                    <option value="19">19</option>
                                                    <option value="20">20</option>
                                                    <option value="21">21</option>
                                                    <option value="22">22</option>
                                                    <option value="23">23</option>
                                                    <option value="24">24</option>
                                                    <option value="25">25</option>
                                                    <option value="26">26</option>
                                                    <option value="27">27</option>
                                                    <option value="28">28</option>
                                                    <option value="29">29</option>
                                                    <option value="30">30</option>
                                                    <option value="31">31</option>
                                                    <option value="32">32</option>
                                                    <option value="33">33</option>
                                                    <option value="34">34</option>
                                                    <option value="35">35</option>
                                                    <option value="36">36</option>
                                                </select>
                                            </div>
                                            
                                            <div class="clearfix" style="padding: 10px;"></div>
                                            
                                    </div>
                                </div>

                            </div>
                        
                        </div>
                                
                    </div>

                <div> 
   

                <?php
            }
            
            public function process_payment($order_id)
            {
                $order = wc_get_order( $order_id );
                $params = $_POST;
                $params['id_order'] = $order_id;
                $charge = new Epayco_SE();
                $data = $charge->charge_epayco($params);

                if($data['status']){

                    wc_reduce_stock_levels($order_id);
                    WC()->cart->empty_cart();
                    return [
                        'result' => 'success',
                        'redirect' => $this->get_return_url( $order )
                    ];
                }else{
                    
                    wc_add_notice($data['message'], 'error' );
                }
        
            }

            function process_custom_payment(){

                if($_POST['payment_method'] != 'epayco')
                    return;
            
                if( !isset($_POST['card_name']) || empty($_POST['card_name']) )
                {
                    wc_add_notice( 'Please add your credit card name', 'error' );
                }

                if( !isset($_POST['subscriptionepayco_number']) || empty($_POST['subscriptionepayco_number']) )
                {
                    wc_add_notice( 'Please add your credit card number', 'error' );
                }

                if( !isset($_POST['month-value']) || empty($_POST['month-value']) )
                {
                    wc_add_notice(  'Please add your credit card expitarion month', 'error' );
                }

                if( !isset($_POST['year-value']) || empty($_POST['year-value']) )
                {
                    wc_add_notice(  'Please add your credit card expitarion year', 'error' );
                }

                if( !isset($_POST['dues']) || empty($_POST['dues']) )
                {
                    wc_add_notice(  'Please add your dues', 'error' );
                }

                if( !isset($_POST['cvc']) || empty($_POST['cvc']) )
                {
                    wc_add_notice(  'Please add your credit card cvc', 'error' );
                }

                
        
            }

            function check_ePayco_response(){
                @ob_clean();
                if ( ! empty( $_REQUEST ) ) {
                    header( 'HTTP/1.1 200 OK' );
                    do_action( "ePayco_init", $_REQUEST );
                } else {
                    wp_die( __("ePayco Request Failure", 'woocommerce-gateway-epayco') );
                }
            }

            function ePaycoSignature( $data ){
                $signature = hash('sha256',
                        trim($this->epayco_customerid).'^'
                        .trim($this->epayco_secretkey).'^'
                        .trim($data['x_ref_payco']).'^'
                        .trim($data['x_transaction_id']).'^'
                        .trim($data['x_amount']).'^'
                        .trim($data['x_currency_code'])
                    );
                return $signature;
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
                    // echo $qty . '  ' .  $operation;
                    // echo '<br>';
                }

              
            }

            function ePayco_successful_request($validationData){

                global $woocommerce;
                    $order_id="";
                    $ref_payco="";
                    $signature="";

                    $x_signature_     =  wp_kses_post( $_REQUEST['x_signature'] );
                    $x_signature__    =  esc_html($_REQUEST['x_signature']);

                    $order_id = sanitize_text_field($_GET['order_id']);
                    $ref_payco = sanitize_text_field($_REQUEST['x_ref_payco']) ? sanitize_text_field($_REQUEST['x_ref_payco']) : sanitize_text_field($_REQUEST['ref_payco']);

                  if($x_signature_ || $x_signature__ ){
                    $x_cod_transaction_state = $_REQUEST['x_cod_transaction_state'];
                        //Validamos la firma
                       
                        if ($order_id!="" && $ref_payco!="") {
                            $order = new WC_Order($order_id);
                            $signature = $this->ePaycoSignature($_REQUEST);
                            
                        }
                        
                    }
                    else{
                        
                        if ( !$ref_payco ) {
                            $explode=explode('=',$order_id);
                            $ref_payco=$explode[1];
                        }
    
                        if ( !$order_id ) {
                            $explode2 = explode('?', $order_id );
                            $order_id=$explode2[0];
                        }
    
                        $message = __('Esperando respuesta por parte del servidor.','payco-woocommerce');
                        $js = $this->block($message);
                        $url = 'https://secure.epayco.co/validation/v1/reference/'.$ref_payco;
                        $response = wp_remote_get(  $url );
                        $body = wp_remote_retrieve_body( $response ); 
                        $jsonData = @json_decode($body, true);
                        $validationData = $jsonData['data'];
                        $ref_payco = $validationData['x_ref_payco'];
                        $x_signature_ = $validationData['x_signature'];
                        $x_cod_transaction_state = $validationData['x_cod_transaction_state'];
                        if ($order_id!="" && $ref_payco!="") {
                            $order = new WC_Order($order_id);
                            $signature = $this->ePaycoSignature($validationData);
                        }
                    }

                    $message = '';
                    $messageClass = '';
                    $current_state = $order->get_status();

                    if($signature == trim($x_signature_)){  
                       
                        switch ((int)trim($x_cod_transaction_state)) {
                            case 1:{
                                //Busca si ya se descontó el stock
                                if (!EpaycoOrder::ifStockDiscount($order_id)) {
                                    //se descuenta el stock
                                    if (EpaycoOrder::updateStockDiscount($order_id,1)) {
                                        $this->restore_order_stock($order_id,'decrease');
                                    }
                                }
                                $message = 'Pago exitoso';
                                $messageClass = 'woocommerce-message';
                                $order->payment_complete($ref_payco);
                                $order->update_status('completed');
                                $order->add_order_note('Pago exitoso');
                            }break;
                            case 2: {
                                {
                                    if( 
                                    $current_state=="failed" || 
                                    $current_state == "processing" || 
                                    $current_state == "completed"){
                                    }else{
                                    $message = 'Pago rechazado';
                                    $messageClass = 'woocommerce-error';
                                    $order->update_status('failed');
                                    $order->add_order_note('Pago fallido');
                                    $this->restore_order_stock($order->id);
                                    }
                                }
                            }break;
                            case 3:{
                                //Busca si ya se restauro el stock y si se configuro reducir el stock en transacciones pendientes  
                               
                                if (!EpaycoOrder::ifStockDiscount($order_id)) {
                                   
                                    //reducir el stock
                                    if (EpaycoOrder::updateStockDiscount($order_id,1)) {
                                        $this->restore_order_stock($order_id,'decrease');
                                    }
                                }
                                $message = 'Pago pendiente de aprobación';
                                $messageClass = 'woocommerce-info';
                                $order->update_status('pending');
                                $order->add_order_note('Pago pendiente');
                            }break;
                            case 4:{
                                $message = 'Pago fallido';
                                $messageClass = 'woocommerce-error';
                                $order->update_status('failed');
                                $order->add_order_note('Pago fallido');
                                //$this->restore_order_stock($order->id);
                            }break;
                            default:{
                                $message = 'Pago '.$_REQUEST['x_transaction_state'];
                                $messageClass = 'woocommerce-error';
                                $order->update_status('failed');
                                $order->add_order_note($message);
                               // $this->restore_order_stock($order->id);
                            }break;
                        }
                        
                        //validar si la transaccion esta pendiente y pasa a rechazada y ya habia descontado el stock
                        if($current_state == 'pending' && ((int)$validationData['x_cod_transaction_state'] == 2 || (int)$validationData['x_cod_transaction_state'] == 4) && EpaycoOrder::ifStockDiscount($order_id)){
                            //si no se restauro el stock restaurarlo inmediatamente
                            $this->restore_order_stock($order_id);
                        };
                    }else {
                        $message = 'Firma no valida';
                        $messageClass = 'error';
                        $order->update_status('failed');
                        $order->add_order_note('Failed');
                        //$this->restore_order_stock($order_id);
                    }

                    if (isset($_REQUEST['confirmation'])) {
                            echo "ok";
                            die();
                    }
                    // $redirect_url = $order->get_checkout_order_received_url();
                    // $arguments=array();
                    // foreach ($validationData as $key => $value) {
                    //     $arguments[$key]=$value;
                    // }
                    // unset($arguments["wc-api"]);
                    // $arguments['msg']=urlencode($message);
                    // $arguments['type']=$messageClass;
                    // $woocommerce->cart->empty_cart();
                    // wp_redirect($redirect_url);
                    die();
            }

        }
        $plugin = new  WC_Epayco();
    }
    return $plugin;
}




global $epayco_db_version;
$epayco_db_version = '1.0';

global $epayco_r_db_version;
$epayco_r_db_version = '1.0';
//Verificar si la version de la base de datos esta actualizada 

function epayco_update_db_check()
{
    global $epayco_db_version;
    global $epayco_r_db_version;
    $installed_ver = get_option('epayco_db_version');
        EpaycoOrder::setup();
        EpaycoRules::setup();
        update_option('epayco_db_version', $epayco_db_version);
        update_option('epayco_r_db_version', $epayco_r_db_version);
    
}


add_action('plugins_loaded', 'epayco_update_db_check');

add_action( 'woocommerce_admin_order_data_after_billing_address', 'custom_checkout_field_display_', 10, 1 );

function custom_checkout_field_display_($order){
    $method = get_post_meta( $order->id, '_payment_method', true );
    if($method != 'epayco')
        return;

    $ref_payco = get_post_meta( $order->id, 'ref_payco', true );

    echo '<p><strong>'.__( 'ref_payco' ).':</strong> ' . $ref_payco . '</p>';

}


add_action('plugins_loaded','woocommerce_gateway_epayco_init');
function woocommerce_gateway_epayco_init() {
	load_plugin_textdomain( 'woocommerce-gateway-epayco', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
	// if ( ! class_exists( 'WooCommerce' ) ) {
	// 	add_action( 'admin_notices', 'woocommerce_stripe_missing_wc_notice' );
	// 	return;
	// }
	// if ( version_compare( WC_VERSION, WC_STRIPE_MIN_WC_VER, '<' ) ) {
	// 	add_action( 'admin_notices', 'woocommerce_stripe_wc_not_supported' );
	// 	return;
	// }
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
	woocommerce_gateway_epayco();
}
?>