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
            $this->testmode             = $this->get_option( 'testmode' );
            $this->epayco_endorder_state= $this->get_option('epayco_endorder_state');

            $this->version = WC_EPAYCO_VERSION;
            $this->name = 'epayco';
            // Path.
            $this->plugin_path   = trailingslashit( plugin_dir_path( $this->file ) );
            $this->includes_path = $this->plugin_path . trailingslashit( 'includes' );

            $this->plugin_path   = trailingslashit( plugin_dir_path( $this->file ) );
            $this->includes_path = $this->plugin_path;
            $this->lib_path = $this->plugin_path . trailingslashit( 'lib' );
            load_plugin_textdomain('woocommerce-gateway-epayco', FALSE, dirname(plugin_basename(__FILE__)) . '/languages');
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
            ?>
            <div id="myRadioGroup">
                Credit Card<input type="radio" name="cars" checked="checked" value="creditCard"  />
                PSE<input type="radio" name="cars" value="pse" />
                Cash<input type="radio" name="cars" value="cash" />
            </div>

            <div class="middle-xs bg_onpage porcentbody m-0" style="display: block;margin: 0">
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
                                <div class="menu-select" style="height: 95px;">

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

                                                <input placeholder="<?php echo __('Card number', 'woocommerce-gateway-epayco'); ?>"
                                                       type="tel" name="subscriptionepayco_number"
                                                       id="subscriptionepayco_number"
                                                       required="" class="form-control"
                                                       style="
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

                                <div class="cuotas bordergray" id="typePersonSelector" style="margin-left: 3%;
                                        margin-right: 3%;
                                        border-radius: 5px; display: none">
                                    <label style="font-size: 17px;margin-left: 10px;"> Type person:</label>
                                    <select class="select binding-select" name="typePerson" style="width: 66%;">
                                        <option value="0">Natural</option>
                                        <option value="1">Juridica</option>
                                    </select>
                                </div>
                                <br>

                                <div class="select-option cuotas bordergray" id="pseSelector" style="display: none">
                                    <select class="select binding-select" name="pse"  style="width: 100%;">
                                    <?php
                                        if($this->testmode == "yes"){
                                    ?>
                                        <option value="1077">BANKA</option>
                                        <option value="1022">BANCO UNION COLOMBIANO</option>
                                        <?php
                                            }else{
                                        ?>
                                            <option value="1059">BANCAMIA S.A.</option>
                                            <option value="1040">BANCO AGRARIO</option>
                                            <option value="1052">BANCO AV VILLAS</option>
                                            <option value="1013">BANCO BBVA COLOMBIA S.A.</option>
                                            <option value="1032">BANCO CAJA SOCIAL</option>
                                            <option value="1066">BANCO COOPERATIVO COOPCENTRAL</option>
                                            <option value="1558">BANCO CREDIFINANCIERA</option>
                                            <option value="1051">BANCO DAVIVIENDA</option>
                                            <option value="1001">BANCO DE BOGOTA</option>
                                            <option value="1023">BANCO DE OCCIDENTE</option>
                                            <option value="1062">BANCO FALABELLA</option>
                                            <option value="1012">BANCO GNB SUDAMERIS</option>
                                            <option value="1006">BANCO ITAU</option>
                                            <option value="1060">BANCO PICHINCHA S.A.</option>
                                            <option value="1002">BANCO POPULAR</option>
                                            <option value="1065">BANCO SANTANDER COLOMBIA</option>
                                            <option value="1069">BANCO SERFINANZA</option>
                                            <option value="1007">BANCOLOMBIA</option>
                                            <option value="1061">BANCOOMEVA S.A.</option>
                                            <option value="1283">CFA COOPERATIVA FINANCIERA</option>
                                            <option value="1009">CITIBANK</option>
                                            <option value="1370">COLTEFINANCIERA</option>
                                            <option value="1292">CONFIAR COOPERATIVA FINANCIERA</option>
                                            <option value="1291">COOFINEP COOPERATIVA FINANCIERA</option>
                                            <option value="1289">COTRAFA</option>
                                            <option value="1097">DALE</option>
                                            <option value="1551">DAVIPLATA</option>
                                            <option value="1303">GIROS Y FINANZAS COMPAÑIA DE FINANCIAMIENTO S.A.</option>
                                            <option value="1637">IRIS</option>
                                            <option value="1801">MOVII S.A.</option>
                                            <option value="1507">NEQUI</option>
                                            <option value="1151">RAPPIPAY</option>
                                            <option value="1019">SCOTIABANK COLPATRIA</option>
                                        <?php
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="select-option cuotas bordergray" id="cashSelector" style="display: none">
                                    <select class="select binding-select" name="cash" style="width: 100%;">
                                        <option value="efecty">efecty</option>
                                        <option value="baloto">baloto</option>
                                        <option value="gana">gana</option>
                                        <option value="redservi">redservi</option>
                                        <option value="puntored">puntored</option>
                                        <option value="sured">sured</option>
                                        <option value="apostar">apostar</option>
                                        <option value="susuerte">susuerte</option>
                                    </select>
                                </div>
                                <div id="expiration-cash-date" style="display: none; padding-left: 20px;padding-top: 20px;">
                                    <label>date expiration:
                                <input type="date" id="start" name="trip-start"
                                       min="2022-01-01" max="2034-12-31" required/>
                                    </label>
                                </div>
                                <br>
                            </div>
                    </div>
                </div>
            <div>

            <script type="text/javascript">
                jQuery( document ).ready( function( $ ) {
                    $("input[name$='cars']").click(function() {
                        var paymentMethod = $(this).val();
                        if(paymentMethod != "creditCard"){
                            $(".menu-select").hide();
                            if(paymentMethod != "pse"){
                                $("#pseSelector").hide();
                                $("#cashSelector").show();
                                $("#expiration-cash-date").show();
                                $("#typePersonSelector").show();
                                $("#typePerson").show();
                            }else{
                                $("#pseSelector").show();
                                $("#cashSelector").hide();
                                $("#expiration-cash-date").hide();
                                $("#typePersonSelector").show();
                                $("#typePerson").show();
                            }
                        }else{
                            $(".menu-select").show();
                            $("#pseSelector").hide();
                            $("#cashSelector").hide();
                            $("#expiration-cash-date").hide();
                            $("#typePersonSelector").hide();
                            $("#typePerson").hide();
                        }
                    });
                });
            </script>

            <?php
        }
            
        public function process_payment($order_id)
        {
            $order = wc_get_order( $order_id );
            $params = $_POST;
            $params['id_order'] = $order_id;
            $charge = new Epayco_SE();
            $data = $charge->charge_epayco($params);

            if($data['status']) {
                if ($data['redirect']){

                    return [
                        'result' => 'success',
                        'redirect' => $data['url']
                    ];
                }else{
                    wc_reduce_stock_levels($order_id);
                    WC()->cart->empty_cart();
                    return [
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order)
                    ];
                }
            }else{

                wc_add_notice($data['message'], 'error' );
            }

        }

        function process_custom_payment(){

            if($_POST['payment_method'] != 'epayco')
                return;

            if($_POST['cars'] != 'creditCard'){
                if($_POST['cars'] == 'cash') {
                    if( !isset($_POST['trip-start']) || empty($_POST['trip-start']) )
                    {
                        wc_add_notice( 'Please add expiration date', 'error' );
                    }else{
                        return;
                    }
                }else{
                    return;
                }
            }else{
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

        function ePayco_successful_request($validationData)
        {

            global $woocommerce;
            $order_id = "";
            $ref_payco = "";
            $signature = "";
            $isConfirmation = sanitize_text_field($_GET['confirmation']) == 1;

            $x_signature_ = wp_kses_post($_REQUEST['x_signature']);
            $x_signature__ = esc_html($_REQUEST['x_signature']);

            $order_id = sanitize_text_field($_GET['order_id']);
            $ref_payco = sanitize_text_field($_REQUEST['x_ref_payco']) ? sanitize_text_field($_REQUEST['x_ref_payco']) : sanitize_text_field($_REQUEST['ref_payco']);
            $order = new WC_Order($order_id);

            $x_cod_transaction_state = $_REQUEST['x_cod_transaction_state'];
            //Validamos la firma
            if ($order_id != "" && $ref_payco != "") {
                $order = new WC_Order($order_id);
                $signature = $this->ePaycoSignature($_REQUEST);
            }

            if (!$ref_payco) {
                $explode = explode('=', $order_id);
                $ref_payco = $explode[1];
            }

            if (!$order_id) {
                $explode2 = explode('?', $order_id);
                $order_id = $explode2[0];
            }
            if ( isset( $ref_payco ) && !empty($ref_payco) ) {
                $message = __('Esperando respuesta por parte del servidor.', 'payco-woocommerce');
                if (!isset($_REQUEST['confirmation'])) {
                    $url = 'https://secure.epayco.co/validation/v1/reference/' . $ref_payco;
                    $response = wp_remote_get($url);
                    $body = wp_remote_retrieve_body($response);
                    $jsonData = @json_decode($body, true);
                    $validationData = $jsonData['data'];
                }else{
                    $validationData = $_REQUEST;
                }

                $ref_payco = $validationData['x_ref_payco'];
                $x_signature_ = $validationData['x_signature'];
                $x_cod_transaction_state = $validationData['x_cod_transaction_state'];
                if ($order_id != "" && $ref_payco != "") {

                    $signature = $this->ePaycoSignature($validationData);
                }
            }else{
                $redirect_url = $order->get_checkout_order_received_url();
                $woocommerce->cart->empty_cart();
                wp_redirect($redirect_url);
                die();
            }
            $x_cod_transaction_state = (int)trim($validationData['x_cod_transaction_state']);
            $x_ref_payco = trim($validationData['x_ref_payco']);
            $message = '';
            $messageClass = '';
            $current_state = $order->get_status();
            $x_test_request = trim($validationData['x_test_request']);

            $isTestTransaction = $x_test_request == 'TRUE' ? "yes" : "no";
             update_option('epayco_order_status', $isTestTransaction);
            $isTestMode = $isTestTransaction == "yes" ? "yes" : "false";
            if ($signature == trim($x_signature_)) {
            $isTestPluginMode = $this->testmode;
            $x_franchise = trim($validationData['x_franchise']);
            switch ((int)$x_cod_transaction_state) {
            case 1:
                {
                    if($isTestPluginMode=="yes"){
                        $message = 'Pago exitoso Prueba';
                        switch ($this->epayco_endorder_state ){
                            case 'epayco-processing':{
                                $orderStatus ='epayco_processing';
                            }break;
                            case 'epayco-completed':{
                                $orderStatus ='epayco_completed';
                            }break;
                            case 'processing':{
                                $orderStatus ='processing_test';
                            }break;
                            case 'completed':{
                                $orderStatus ='completed_test';
                            }break;
                        }
                    }else{
                        $message = 'Pago exitoso';
                        $orderStatus = $this->epayco_endorder_state;
                    }

                    if($current_state == "epayco_failed" ||
                        $current_state == "epayco_cancelled" ||
                        $current_state == "failed" ||
                        $current_state == "epayco-cancelled" ||
                        $current_state == "epayco-failed"
                    ){
                        if (!EpaycoOrder::ifStockDiscount($order_id)){
                            //se descuenta el stock
                            EpaycoOrder::updateStockDiscount($order_id,1);
                            if($current_state != $orderStatus){
                                if($isTestMode=="yes"){
                                    $this->restore_order_stock($order->get_id(),"decrease");
                                }else{
                                    if($orderStatus == "epayco-processing" || $orderStatus == "epayco-completed"){
                                        $this->restore_order_stock($order->get_id(),"decrease");
                                    }
                                }

                                $order->payment_complete($x_ref_payco);
                                $order->update_status($orderStatus);
                                $order->add_order_note($message);
                            }
                        }

                    }else{
                        //Busca si ya se descontó el stock
                        if (!EpaycoOrder::ifStockDiscount($order_id)){
                            //se descuenta el stock
                            EpaycoOrder::updateStockDiscount($order_id,1);
                        }
                        if($current_state != $orderStatus){
                            if($isTestMode=="yes" && $current_state == "epayco_on_hold"){
                                if($orderStatus == "processing"){
                                    $this->restore_order_stock($order->get_id(),"decrease");
                                }
                                if($orderStatus == "completed"){
                                    $this->restore_order_stock($order->get_id(),"decrease");
                                }
                            }
                            if($isTestMode != "yes" && $current_state == "epayco-on-hold"){
                                if($orderStatus == "processing"){
                                    $this->restore_order_stock($order->get_id());
                                }
                                if($orderStatus == "completed"){
                                    $this->restore_order_stock($order->get_id());
                                }
                            }
                            if($current_state =="pending")
                            {
                                $this->restore_order_stock($order->get_id());
                            }
                            $order->payment_complete($x_ref_payco);
                            $order->update_status($orderStatus);
                            $order->add_order_note($message);
                        }
                    }



                    $note = sprintf(__('Successful Payment (ref_payco: %s)', 'woocommerce-gateway-epayco'),
                        $x_ref_payco);
                    $order->add_order_note($note);
                    update_post_meta($order->get_id(), 'ref_payco', $x_ref_payco);
                }
                break;
            case 2:
                {
                    if($isTestMode=="yes"){
                        if(
                            $current_state == "epayco_processing" ||
                            $current_state == "epayco_completed" ||
                            $current_state == "processing_test" ||
                            $current_state == "completed_test"
                        ){}else{
                            $message = 'Pago rechazado Prueba: ' .$x_ref_payco;
                            $messageClass = 'woocommerce-error';
                            $order->update_status('epayco_cancelled');
                            $order->add_order_note($message);
                            if($current_state =="epayco-cancelled"||
                                $current_state == "epayco_cancelled" ){
                            }else{
                                $this->restore_order_stock($order->get_id());
                            }
                        }
                    }else{
                        if(
                            $current_state == "epayco-processing" ||
                            $current_state == "epayco-completed" ||
                            $current_state == "processing-test" ||
                            $current_state == "completed-test"||
                            $current_state == "processing" ||
                            $current_state == "completed"
                        ){}else{
                            $message = 'Pago rechazado: ' .$x_ref_payco;
                            $messageClass = 'woocommerce-error';
                            $order->update_status('epayco-cancelled');
                            $order->add_order_note($message);
                            if($current_state !="epayco-cancelled"){
                                $this->restore_order_stock($order->get_id());
                            }
                        }
                    }
                    $note = sprintf(__('Rejected Payment (ref_payco: %s)', 'woocommerce-gateway-epayco'),
                        $x_ref_payco);
                    $order->add_order_note($note);
                    update_post_meta($order->get_id(), 'ref_payco', $x_ref_payco);
                    if(!$isConfirmation){
                        $woocommerce->cart->empty_cart();
                        foreach ($order->get_items() as $item) {
                            // Get an instance of corresponding the WC_Product object
                            $product_id = $item->get_product()->id;
                            $qty = $item->get_quantity(); // Get the item quantity
                            WC()->cart->add_to_cart( $product_id ,(int)$qty);
                        }
                        wp_safe_redirect( wc_get_checkout_url() );
                        exit();
                    }
                }
                break;
            case 3:
                {
                    //Busca si ya se restauro el stock y si se configuro reducir el stock en transacciones pendientes
                    if (!EpaycoOrder::ifStockDiscount($order_id)) {
                        //actualizar el stock
                        EpaycoOrder::updateStockDiscount($order_id,1);
                    }

                    if($isTestMode=="yes"){
                        $message = 'Pago pendiente de aprobación Prueba';
                        $orderStatus = "epayco_on_hold";
                    }else{
                        $message = 'Pago pendiente de aprobación';
                        $orderStatus = "epayco-on-hold";
                    }
                    if($x_franchise != "PSE"){
                        $order->update_status($orderStatus);
                        $order->add_order_note($message);
                        if($current_state == "epayco_failed" ||
                            $current_state == "epayco_cancelled" ||
                            $current_state == "failed" ||
                            $current_state == "epayco-cancelled" ||
                            $current_state == "epayco-failed"
                        ){
                            $this->restore_order_stock($order->get_id(),"decrease");
                        }
                    }
                    $note = sprintf(__('Pending Payment (ref_payco: %s)', 'woocommerce-gateway-epayco'),
                        $x_ref_payco);
                    $order->add_order_note($note);
                    update_post_meta($order->get_id(), 'ref_payco', $x_ref_payco);
                }
                break;
            case 4:
                {
                    if($isTestMode=="yes"){
                        if(
                            $current_state == "epayco_processing" ||
                            $current_state == "epayco_completed" ||
                            $current_state == "processing_test" ||
                            $current_state == "completed_test"
                        ){}else{
                            $message = 'Pago rechazado Prueba: ' .$x_ref_payco;
                            $messageClass = 'woocommerce-error';
                            $order->update_status('epayco_failed');
                            $order->add_order_note($message);
                            if($current_state =="epayco-failed"||
                                $current_state == "epayco_failed" ){
                            }else{
                                $this->restore_order_stock($order->get_id());
                            }
                        }
                    }else{
                        if(
                            $current_state == "epayco-processing" ||
                            $current_state == "epayco-completed" ||
                            $current_state == "processing-test" ||
                            $current_state == "completed-test"||
                            $current_state == "processing" ||
                            $current_state == "completed"
                        ){}else{
                            $message = 'Pago rechazado: ' .$x_ref_payco;
                            $messageClass = 'woocommerce-error';
                            $order->update_status('epayco-failed');
                            $order->add_order_note($message);
                            if($current_state !="epayco-failed"){
                                $this->restore_order_stock($order->get_id());
                            }
                        }
                    }
                    $note = sprintf(__('Rejected Payment (ref_payco: %s)', 'woocommerce-gateway-epayco'),
                        $x_ref_payco);
                    $order->add_order_note($note);
                    update_post_meta($order->get_id(), 'ref_payco', $x_ref_payco);
                    if(!$isConfirmation){
                        $woocommerce->cart->empty_cart();
                        foreach ($order->get_items() as $item) {
                            // Get an instance of corresponding the WC_Product object
                            $product_id = $item->get_product()->id;
                            $qty = $item->get_quantity(); // Get the item quantity
                            WC()->cart->add_to_cart( $product_id ,(int)$qty);
                        }
                        wp_safe_redirect( wc_get_checkout_url() );
                        exit();
                    }
                }
                break;
            default:
                {
                    if(
                        $current_state == "epayco-processing" ||
                        $current_state == "epayco-completed" ||
                        $current_state == "processing" ||
                        $current_state == "completed"){
                    } else{
                        $message = 'Pago '.$_REQUEST['x_transaction_state'] . $x_ref_payco;
                        $messageClass = 'woocommerce-error';
                        $order->update_status('epayco-failed');
                        $order->add_order_note('Pago fallido o abandonado');
                        $this->restore_order_stock($order->get_id());
                    }
                    $note = sprintf(__('failed Payment (ref_payco: %s)', 'woocommerce-gateway-epayco'),
                        $x_ref_payco);
                    $order->add_order_note($note);
                    update_post_meta($order->get_id(), 'ref_payco', $x_ref_payco);
                }
                break;
            }

                    //validar si la transaccion esta pendiente y pasa a rechazada y ya habia descontado el stock
                    if ($current_state == 'pending' && ((int)$validationData['x_cod_transaction_state'] == 2 || (int)$validationData['x_cod_transaction_state'] == 4) && EpaycoOrder::ifStockDiscount($order_id)) {
                        //si no se restauro el stock restaurarlo inmediatamente
                        $this->restore_order_stock($order_id);
                    };
                } else {
                    if($isTestMode=="yes"){
                        if($x_cod_transaction_state==1){
                            $message = 'Pago exitoso Prueba';
                            switch ($this->epayco_endorder_state ){
                                case 'epayco-processing':{
                                    $orderStatus ='epayco_processing';
                                }break;
                                case 'epayco-completed':{
                                    $orderStatus ='epayco_completed';
                                }break;
                                case 'processing':{
                                    $orderStatus ='processing_test';
                                }break;
                                case 'completed':{
                                    $orderStatus ='completed_test';
                                }break;
                            }
                        }
                        if($isTestMode == "no" && $x_cod_transaction_state == 1)
                        {
                            $this->restore_order_stock($order->get_id());
                        }
                    }else{
                        if(
                            $current_state == "epayco-processing" ||
                            $current_state == "epayco-completed" ||
                            $current_state == "processing" ||
                            $current_state == "completed"){
                        }else{
                            $message = 'Firma no valida';
                            $orderStatus = 'epayco-failed';
                            if($x_cod_transaction_state!=1){
                                $this->restore_order_stock($order->get_id());
                            }
                        }

                    }
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

function register_epayco_order_status() {
    register_post_status( 'wc-epayco-failed', array(
        'label'                     => 'ePayco Pago Fallido',
        'public'                    => true,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list'    => true,
        'exclude_from_search'       => false,
        'label_count'               => _n_noop( 'ePayco Pago Fallido <span class="count">(%s)</span>', 'ePayco Pago Fallido <span class="count">(%s)</span>' )
    ));

    register_post_status( 'wc-epayco_failed', array(
        'label'                     => 'ePayco Pago Fallido Prueba',
        'public'                    => true,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list'    => true,
        'exclude_from_search'       => false,
        'label_count'               => _n_noop( 'ePayco Pago Fallido Prueba <span class="count">(%s)</span>', 'ePayco Pago Fallido Prueba <span class="count">(%s)</span>' )
    ));

    register_post_status( 'wc-epayco-cancelled', array(
        'label'                     => 'ePayco Pago Cancelado',
        'public'                    => true,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list'    => true,
        'exclude_from_search'       => false,
        'label_count'               => _n_noop( 'ePayco Pago Cancelado <span class="count">(%s)</span>', 'ePayco Pago Cancelado <span class="count">(%s)</span>' )
    ));

    register_post_status( 'wc-epayco_cancelled', array(
        'label'                     => 'ePayco Pago Cancelado Prueba',
        'public'                    => true,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list'    => true,
        'exclude_from_search'       => false,
        'label_count'               => _n_noop( 'ePayco Pago Cancelado Prueba <span class="count">(%s)</span>', 'ePayco Pago Cancelado Prueba <span class="count">(%s)</span>' )
    ));

    register_post_status( 'wc-epayco-on-hold', array(
        'label'                     => 'ePayco Pago Pendiente',
        'public'                    => true,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list'    => true,
        'exclude_from_search'       => false,
        'label_count'               => _n_noop( 'ePayco Pago Pendiente <span class="count">(%s)</span>', 'ePayco Pago Pendiente <span class="count">(%s)</span>' )
    ));

    register_post_status( 'wc-epayco_on_hold', array(
        'label'                     => 'ePayco Pago Pendiente Prueba',
        'public'                    => true,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list'    => true,
        'exclude_from_search'       => false,
        'label_count'               => _n_noop( 'ePayco Pago Pendiente Prueba <span class="count">(%s)</span>', 'ePayco Pago Pendiente Prueba <span class="count">(%s)</span>' )
    ));

    register_post_status( 'wc-epayco-processing', array(
        'label'                     => 'ePayco Procesando Pago',
        'public'                    => true,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list'    => true,
        'exclude_from_search'       => false,
        'label_count'               => _n_noop( 'ePayco Procesando Pago <span class="count">(%s)</span>', 'ePayco Procesando Pago <span class="count">(%s)</span>' )
    ));

    register_post_status( 'wc-epayco_processing', array(
        'label'                     => 'ePayco Procesando Pago Prueba',
        'public'                    => true,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list'    => true,
        'exclude_from_search'       => false,
        'label_count'               => _n_noop( 'ePayco Procesando Pago Prueba<span class="count">(%s)</span>', 'ePayco Procesando Pago Prueba<span class="count">(%s)</span>' )
    ));

    register_post_status( 'wc-processing', array(
        'label'                     => 'Procesando',
        'public'                    => true,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list'    => true,
        'exclude_from_search'       => false,
        'label_count'               => _n_noop( 'Procesando<span class="count">(%s)</span>', 'Procesando<span class="count">(%s)</span>' )
    ));

    register_post_status( 'wc-processing_test', array(
        'label'                     => 'Procesando Prueba',
        'public'                    => true,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list'    => true,
        'exclude_from_search'       => false,
        'label_count'               => _n_noop( 'Procesando Prueba<span class="count">(%s)</span>', 'Procesando Prueba<span class="count">(%s)</span>' )
    ));

    register_post_status( 'wc-epayco-completed', array(
        'label'                     => 'ePayco Pago Completado',
        'public'                    => true,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list'    => true,
        'exclude_from_search'       => false,
        'label_count'               => _n_noop( 'ePayco Pago Completado <span class="count">(%s)</span>', 'ePayco Pago Completado <span class="count">(%s)</span>' )
    ));

    register_post_status( 'wc-epayco_completed', array(
        'label'                     => 'ePayco Pago Completado Prueba',
        'public'                    => true,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list'    => true,
        'exclude_from_search'       => false,
        'label_count'               => _n_noop( 'ePayco Pago Completado Prueba <span class="count">(%s)</span>', 'ePayco Pago Completado Prueba <span class="count">(%s)</span>' )
    ));

    register_post_status( 'wc-completed', array(
        'label'                     => 'Completado',
        'public'                    => true,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list'    => true,
        'exclude_from_search'       => false,
        'label_count'               => _n_noop( 'Completado<span class="count">(%s)</span>', 'Completado<span class="count">(%s)</span>' )
    ));

    register_post_status( 'wc-completed_test', array(
        'label'                     => 'Completado Prueba',
        'public'                    => true,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list'    => true,
        'exclude_from_search'       => false,
        'label_count'               => _n_noop( 'Completado Prueba<span class="count">(%s)</span>', 'Completado Prueba<span class="count">(%s)</span>' )
    ));
}

add_action( 'plugins_loaded', 'register_epayco_order_status' );

function add_epayco_to_order_statuses( $order_statuses ) {
    $new_order_statuses = array();
    $epayco_order = get_option('epayco_order_status');
    $testMode = $epayco_order == "yes" ? "true" : "false";
    foreach ( $order_statuses as $key => $status ) {
        $new_order_statuses[ $key ] = $status;
        if ( 'wc-cancelled' === $key ) {
            if($testMode=="true"){
                $new_order_statuses['wc-epayco_cancelled'] = 'ePayco Pago Cancelado Prueba';
            }else{
                $new_order_statuses['wc-epayco-cancelled'] = 'ePayco Pago Cancelado';
            }
        }

        if ( 'wc-failed' === $key ) {
            if($testMode=="true"){
                $new_order_statuses['wc-epayco_failed'] = 'ePayco Pago Fallido Prueba';
            }else{
                $new_order_statuses['wc-epayco-failed'] = 'ePayco Pago Fallido';
            }
        }

        if ( 'wc-on-hold' === $key ) {
            if($testMode=="true"){
                $new_order_statuses['wc-epayco_on_hold'] = 'ePayco Pago Pendiente Prueba';
            }else{
                $new_order_statuses['wc-epayco-on-hold'] = 'ePayco Pago Pendiente';
            }
        }

        if ( 'wc-processing' === $key ) {
            if($testMode=="true"){
                $new_order_statuses['wc-epayco_processing'] = 'ePayco Pago Procesando Prueba';
            }else{
                $new_order_statuses['wc-epayco-processing'] = 'ePayco Pago Procesando';
            }
        }else {
            if($testMode=="true"){
                $new_order_statuses['wc-processing_test'] = 'Procesando Prueba';
            }else{
                $new_order_statuses['wc-processing'] = 'Procesando';
            }
        }

        if ( 'wc-completed' === $key ) {
            if($testMode=="true"){
                $new_order_statuses['wc-epayco_completed'] = 'ePayco Pago Completado Prueba';
            }else{
                $new_order_statuses['wc-epayco-completed'] = 'ePayco Pago Completado';
            }
        }else{
            if($testMode=="true"){
                $new_order_statuses['wc-completed_test'] = 'Completado Prueba';
            }else{
                $new_order_statuses['wc-completed'] = 'Completado';
            }
        }
    }
    return $new_order_statuses;
}

add_filter( 'wc_order_statuses', 'add_epayco_to_order_statuses' );

    function styling_admin_order_list() {
        global $pagenow, $post;
        if( $pagenow != 'edit.php') return; // Exit
        if( get_post_type($post->ID) != 'shop_order' ) return; // Exit
        // HERE we set your custom status
        $epayco_order = get_option('epayco_order_status');
        $testMode = $epayco_order == "yes" ? "true" : "false";
        if($testMode=="true"){
            $order_status_failed = 'epayco_failed';
            $order_status_on_hold = 'epayco_on_hold';
            $order_status_processing = 'epayco_processing';
            $order_status_processing_ = 'processing_test';
            $order_status_completed = 'epayco_completed';
            $order_status_cancelled = 'epayco_cancelled';
            $order_status_completed_ = 'completed_test';

        }else{
            $order_status_failed = 'epayco-failed';
            $order_status_on_hold = 'epayco-on-hold';
            $order_status_processing = 'epayco-processing';
            $order_status_processing_ = 'processing';
            $order_status_completed = 'epayco-completed';
            $order_status_cancelled = 'epayco-cancelled';
            $order_status_completed_ = 'completed';
        }
        ?>

        <style>
            .order-status.status-<?php esc_html_e( $order_status_failed, 'text_domain' );  ?> {
                background: #eba3a3;
                color: #761919;
            }
            .order-status.status-<?php esc_html_e( $order_status_on_hold, 'text_domain' ); ?> {
                background: #f8dda7;
                color: #94660c;
            }
            .order-status.status-<?php esc_html_e( $order_status_processing, 'text_domain' ); ?> {
                background: #c8d7e1;
                color: #2e4453;
            }
            .order-status.status-<?php esc_html_e( $order_status_processing_, 'text_domain' ); ?> {
                background: #c8d7e1;
                color: #2e4453;
            }
            .order-status.status-<?php esc_html_e( $order_status_completed, 'text_domain' ); ?> {
                background: #d7f8a7;
                color: #0c942b;
            }
            .order-status.status-<?php esc_html_e( $order_status_completed_, 'text_domain' ); ?> {
                background: #d7f8a7;
                color: #0c942b;
            }
            .order-status.status-<?php esc_html_e( $order_status_cancelled, 'text_domain' ); ?> {
                background: #eba3a3;
                color: #761919;
            }
        </style>

        <?php
    }
add_action('admin_head', 'styling_admin_order_list' );


add_action('plugins_loaded','woocommerce_gateway_epayco_init');
function woocommerce_gateway_epayco_init() {
	load_plugin_textdomain( 'woocommerce-gateway-epayco', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
	woocommerce_gateway_epayco();
}
?>