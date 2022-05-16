<?php

class EpaycoRules{
	public $id;
	public $id_payco;
	public $order_id;
	public $order_stock_restore;
	public $order_stock_discount;
	public $order_status;
	

	/**
	 * Guarda el registro de una oden
	 * @param string $id_payco
	 * @param string $customer_id
	 * @param string $token_id
	 */
	public static function create($id_payco, $customer_id, $token_id, $email)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . "payco_rules";
	  	$result = $wpdb->insert( $table_name, 
		    array( 
		      'id_payco' => $id_payco, 
		      'customer_id' => $customer_id,
			  'token_id' => $token_id,
			  'email' => $email
		    )
	  	);

		if (count($result) > 0)
		{
			return true;
		}else{
			return false;
		}

	}


	/**
	 * Consultar si existe el registro de una un cliente
	 * @param int $id_payco
	 */	
	public static function ifExist($id_payco)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . "payco_rules";
		$sql = 'SELECT * FROM `wp_payco_rules` WHERE `id_payco` = '. trim($id_payco);
		$results = $wpdb->get_results($sql, OBJECT);
		if (count($results) == 0)
		{
		   return false;
		}else{
			return $results;
		}
			

	}


	/**
	 * Consultar si a una orden ya se le descconto el stock
	 * @param int $orderId
	 */	
	public static function ifStockDiscount($orderId)

	{	

		global $wpdb;
    	$table_name = $wpdb->prefix . "payco_rules";
		$sql = 'SELECT * FROM '.$table_name.' WHERE order_id ='.intval($orderId);
		$results = $wpdb->get_results($sql, OBJECT);
		

		if (count($results) == 0)
			return false;
		return intval($results[0]->order_stock_discount) != 0 ? true : false;
	}

	/**
	 * Actualizar que ya se le descontó el stock a una orden
	 * @param int $orderId
	 */	
	public static function updateStockDiscount($orderId)
	{
		global $wpdb;
		$table_name = $wpdb->prefix . "payco_rules";
	  	$result = $wpdb->update( $table_name, array('order_stock_discount'=>1), array('order_id'=>(int)$orderId) );

		return (int)$result == 1;
	}

	

	/**
	 * Crear la tabla en la base de datos.
	 * @return true or false
	 */
	public static function setup()
	{
		global $wpdb;
    	$table_name = $wpdb->prefix . "payco_rules";
	    $charset_collate = $wpdb->get_charset_collate();

	    $sql = "CREATE TABLE IF NOT  EXISTS $table_name (
            id INT NOT NULL AUTO_INCREMENT,
            id_payco TEXT NULL,
            customer_id TEXT NULL,
            token_id TEXT NULL,
            email TEXT NULL,
            PRIMARY KEY (id)
	  	) $charset_collate;";

	    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	    dbDelta($sql);
	    global $epayco_r_db_version;
	    add_option('epayco_r_db_version', $epayco_r_db_version);
	}



	/**
	 * Borra la tabla en la base de datos.
	 * @return true or false
	 */
	public static function remove(){
		$sql = array(
				'DROP TABLE IF EXISTS '._DB_PREFIX_.'payco_rules'
		);

		foreach ($sql as $query) {
		    if (Db::getInstance()->execute($query) == false) {
		        return false;
		    }
		}
	}

}