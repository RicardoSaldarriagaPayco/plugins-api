<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
    'enabled' => array(
        'title' => __('Habilitar/Deshabilitar', 'woocommerce-gateway-epayco'),
        'type' => 'checkbox',
        'label' => __('Habilitar ePayco Checkout', 'woocommerce-gateway-epayco'),
        'default' => 'yes'
    ),
    'title' => array(
        'title' => __('<span class="epayco-required">Título</span>', 'woocommerce-gateway-epayco'),
        'type' => 'text',
        'description' => __('Corresponde al titulo que el usuario ve durante el checkout.', 'woocommerce-gateway-epayco'),
        'default' => __('Checkout ePayco Tarjetas de crédito', 'woocommerce-gateway-epayco'),
    ),
    'description' => array(
        'title' => __('<span class="epayco-required">Descripción</span>', 'woocommerce-gateway-epayco'),
        'type' => 'textarea',
        'description' => __('Corresponde a la descripción que verá el usuaro durante el checkout', 'woocommerce-gateway-epayco'),
        'default' => __('Checkout ePayco (Tarjetas de crédito)', 'woocommerce-gateway-epayco'),
        //'desc_tip' => true,
    ),

    'epayco_customerid' => array(
        'title' => __('<span class="epayco-required">P_CUST_ID_CLIENTE</span>', 'woocommerce-gateway-epayco'),
        'type' => 'text',
        'description' => __('ID de cliente que lo identifica en ePayco. Lo puede encontrar en su panel de clientes en la opción configuración.', 'woocommerce-gateway-epayco'),
        'default' => '',
        'placeholder' => '',
    ),
    'epayco_secretkey' => array(
        'title' => __('<span class="epayco-required">P_KEY</span>', 'woocommerce-gateway-epayco'),
        'type' => 'text',
        'description' => __('LLave para firmar la información enviada y recibida de ePayco. Lo puede encontrar en su panel de clientes en la opción configuración.', 'woocommerce-gateway-epayco'),
        'default' => '',
        'placeholder' => ''
    ),
    'epayco_publickey' => array(
        'title' => __('<span class="epayco-required">PUBLIC_KEY</span>', 'woocommerce-gateway-epayco'),
        'type' => 'text',
        'description' => __('LLave para autenticar y consumir los servicios de ePayco, Proporcionado en su panel de clientes en la opción configuración.', 'woocommerce-gateway-epayco'),
        'default' => '',
        'placeholder' => ''
    ),
    'epayco_privatekey' => array(
        'title' => __('<span class="epayco-required">PRIVATE_KEY</span>', 'woocommerce-gateway-epayco'),
        'type' => 'text',
        'description' => __('LLave para autenticar y consumir los servicios de ePayco, Proporcionado en su panel de clientes en la opción configuración.', 'woocommerce-gateway-epayco'),
        'default' => '',
        'placeholder' => ''
    ),
    'testmode' => array(
        'title' => __('Sitio en pruebas', 'woocommerce-gateway-epayco'),
        'type' => 'checkbox',
        'label' => __('Habilitar el modo de pruebas', 'woocommerce-gateway-epayco'),
        'description' => __('Habilite para realizar pruebas', 'woocommerce-gateway-epayco'),
        'default' => 'no',
    ),
    'epayco_endorder_state' => array(
        'title' => __('Estado Final del Pedido', 'epayco_woocommerce'),
        'type' => 'select',
        'css' =>'line-height: inherit',
        'description' => __('Seleccione el estado del pedido que se aplicará a la hora de aceptar y confirmar el pago de la orden', 'woocommerce-gateway-epayco'),
        'options' => array(
            'epayco-processing'=>"ePayco Procesando Pago",
            "epayco-completed"=>"ePayco Pago Completado",
            'processing'=>"Procesando",
            "completed"=>"Completado"
        ),
    ),
);