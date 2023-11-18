<?php

namespace VicoDIn\Inc;

defined( 'ABSPATH' ) || exit;
class Partial_Order extends \WC_Order {

	function get_type(){
		return VICODIN_CONST['order_type'];
	}

}

