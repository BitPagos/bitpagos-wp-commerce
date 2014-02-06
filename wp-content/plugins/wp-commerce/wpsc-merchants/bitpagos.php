<?php

$nzshpcrt_gateways[$num]['name'] = __( 'BitPagos', 'wpsc' );
$nzshpcrt_gateways[$num]['internalname'] = 'bitpagos';
$nzshpcrt_gateways[$num]['function'] = 'gateway_bitpagos';
$nzshpcrt_gateways[$num]['form'] = "form_bitpagos";
$nzshpcrt_gateways[$num]['submit_function'] = "submit_bitpagos";
$nzshpcrt_gateways[$num]['payment_type'] = "bitpagos";
$nzshpcrt_gateways[$num]['display_name'] = __( 'BitPagos', 'wpsc' );
$nzshpcrt_gateways[$num]['image'] = WPSC_URL . '/images/cc.gif';

function gateway_bitpagos($separator, $sessionid) {

	$redirect = get_option('transact_url') . $separator . 'sessionid=' . $sessionid;
	unset($_SESSION['WpscGatewayErrorMessage']);
	$output = "<form id=\"bitpagos_form\" name=\"bitpagos_form\" method=\"post\" action='" . $redirect . "'>\n";
    $output .= '<script>document.getElementById("bitpagos_form").submit();</script>';
    echo $output;

}

$bitpagos_checkout_confirm_called = false;

function bitpagos_confirm_checkout( $purchased_log_id ) {
	
	global $wpdb, $bitpagos_checkout_confirm_called;

	// there is a bug in wp, this hook is called twice
	if ( !$bitpagos_checkout_confirm_called ) {		

		$bitpagos_checkout_confirm_called = true;
		$purchase_sql = "SELECT * FROM `".WPSC_TABLE_PURCHASE_LOGS."` WHERE `id`='" . $purchased_log_id . "'";		
		$purchase = $wpdb->get_results( $purchase_sql,ARRAY_A );

		$sessionid = $purchase[0]['sessionid'];
		$order_amount = $purchase[0]['totalprice'];
		
		$ipn_url = get_option('transact_url') . '&sessionid=' . $sessionid;	

		$tpl = '<div style="text-align: center">';
		$tpl .= '<form method="post" action="' . $ipn_url . '">';	
		$tpl .= '<p>Thank you for your order, please click the button below to pay with BitPagos.</p>';
		$tpl .= "<script src='https://www.bitpagos.net/public/js/partner/m.js' class='bp-partner-button' data-role='checkout' data-account-id='" . get_option('bitpagos_account_id') . "' data-reference-id='" . $sessionid . "' data-title='product description' data-amount='" . $order_amount . "' data-currency='USD' data-description='' data-ipn='" . $ipn_url . "'></script> ";
		$tpl .= '</form></div>';
		echo $tpl;

	}	

}
add_action( 'wpsc_confirm_checkout', 'bitpagos_confirm_checkout');

function bitpagos_callback() {
	
	global $wpdb;
	if ( sizeOf( $_POST ) > 0 ) {   		
		
		if ( isset( $_POST['referenceId'] ) ) {
			$reference_field = 'referenceId';
			$transaction_field = 'transactionId';
		} elseif ( isset( $_POST['reference_id'] ) ) { 
			$reference_field = 'reference_id';
			$transaction_field = 'transaction_id';
		}

		if (!isset( $_POST[$reference_field] ) || 
			!isset( $_POST[$transaction_field] ) ) {
			header("HTTP/1.1 500 BAD_PARAMETERS");
			return false;
		}

		$transaction_id = filter_var( $_POST[$transaction_field], FILTER_SANITIZE_STRING);
		$url = 'https://www.bitpagos.net/api/v1/transaction/' . $transaction_id . '/?api_key=' . get_option('bitpagos_api_key') . '&format=json';
		$cbp = curl_init( $url );
		curl_setopt($cbp, CURLOPT_RETURNTRANSFER, TRUE);
		$response_curl = curl_exec( $cbp );
		curl_close( $cbp );
		$response = json_decode( $response_curl );
		$reference_id = (int)$_POST[$reference_field];

		if ( $reference_id != $response->reference_id ) {
			die('Wrong reference id');
		}

		if ( $response->status == 'PA' || $response->status == 'CO' ) {

			$data = array(
				'processed'  => 3,
				'transactid' => $transaction_id,
				'date'       => time(),
			);

			// 'processed'  => 3 -> Accepted Payment
			$sessionid = $reference_id;
			wpsc_update_purchase_log_details( $sessionid, $data, 'sessionid' );
			
			header("HTTP/1.1 200 OK");

		}

		exit();

	}

}

function submit_bitpagos() {
	
	if( isset( $_POST['bitpagos_account_id'] ) ) {
    	update_option( 'bitpagos_account_id', $_POST['bitpagos_account_id'] );
    }

    if( isset( $_POST['bitpagos_api_key'] ) ) {
    	update_option( 'bitpagos_api_key', $_POST['bitpagos_api_key'] );
    }

	if( isset( $_POST['bitpagos_initial_order_status'] ) ) {
    	update_option( 'bitpagos_initial_order_status', $_POST['bitpagos_initial_order_status'] );
    }

	return true;

}

function form_bitpagos() {
	
	$output = "
	<tr>
		<td>" . __( 'Account ID', 'wpsc' ) . "</td>
		<td>
			<input type='text' size='40' value='" . get_option( 'bitpagos_account_id' ) . "' name='bitpagos_account_id' />
			<p class='description'>
				" . __( 'Account ID here.', 'wpsc' ) . "
			</p>
	</tr>
	<tr>
		<td>" . __( 'API KEY', 'wpsc' ) . "</td>
		<td>
			<input type='text' size='40' value='" . get_option( 'bitpagos_api_key' ) . "' name='bitpagos_api_key' />
			<p class='description'>
				" . __( 'API KEY Here', 'wpsc' ) . "
			</p>
	</tr>
	<tr>
		<td>" . __( 'Initial Order Status', 'wpsc' ) . "</td>
		<td>
			<select name='bitpagos_initial_order_status'>
				<option value='Pending'>" . __( 'Pending', 'wpsc' ) . "</option>
			</select>
			<p class='description'>
				" . __( 'Initial status for orders.', 'wpsc' ) . "
			</p>
	</tr>";

	return $output;

}

add_action('init', 'bitpagos_callback');

?>