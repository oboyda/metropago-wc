<?php
function mwc_define_gateway_class(){

	class MWC_Gateway extends WC_Payment_Gateway{

		protected $cc_type;
		protected $cc_num;
		protected $cc_holder;
		protected $cc_exp_m;
		protected $cc_exp_y;
		protected $cc_cvv;

		protected $auth_expires;
		protected $adjust_delay;

		protected $log_errors;
		protected $log_errors_file;

		function __construct(){

			$this->auth_expires = 20;
			$this->adjust_delay = 5;

			$this->log_errors = true;
			$this->log_errors_file = dirname(__FILE__) . '/failed_transactions';

			$this->id = 'mwc_gateway';
			$this->icon = MWC_INDEX . 'images/credit_cards.png';
			$this->has_fields = true;
			$this->method_title = __('Metropago', MWC_TXTDOM );
			$this->method_description = __('Direct payments with Metropago. User will be asked to enter credit card details on the checkout page.', MWC_TXTDOM);

			$this->init_form_fields();
			$this->init_settings();

			$this->title = $this->get_option('title');

			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		}

		function init_form_fields(){
			$this->form_fields = array(
				'enabled' => array(
					'title' => __('Enable Matropago', MWC_TXTDOM),
					'type' => 'checkbox',
					'label' => __('Enable', MWC_TXTDOM),
					'default' => 'yes'
				),
				'title' => array(
					'title' => __('Method name', MWC_TXTDOM),
					'type' => 'text',
					'default' => __('Metropago', MWC_TXTDOM),
					'desc_tip' => true
				),
				'description' => array(
					'title' => __('Method description', MWC_TXTDOM),
					'type' => 'textarea',
					'default' =>  __('Use this method to pay with your credit card securely.', MWC_TXTDOM)
				),
				'acc_code' => array(
					'title' => __('AccCode', MWC_TXTDOM),
					'type' => 'text',
					'default' => '123123',
				),
				'merchant_id' => array(
					'title' => __('Merchant', MWC_TXTDOM),
					'type' => 'text',
					'default' => 'DEMO0001',
				),
				'terminal_id' => array(
					'title' => __('Terminal', MWC_TXTDOM),
					'type' => 'text',
					'default' => 'DEMO0001',
				),
				'transtype' => array(
					'title' => __('Transaction type', MWC_TXTDOM),
					'type' => 'select',
					'default' => 'sale',
					'options' => array(
						'sale' => __('Sale', MWC_TXTDOM),
						'preauth' => __('PreAuthorization', MWC_TXTDOM)
						)
				),
				'sandbox' => array(
					'title' => __('Sandbox mode', MWC_TXTDOM),
					'type' => 'checkbox',
					'label' => __('Enable', MWC_TXTDOM),
					'default' => 'no'
				)
			);
		}

		function payment_fields(){ ?>
			<p>
				<label for="mwc_cc_type"><?php _e('Credit Card Type', MWC_TXTDOM); ?></label><br />
				<select id="mwc_cc_type" name="mwc_cc_type" autocomplete="off">
					<option value=""><?php _e('--Please Select--', MWC_TXTDOM); ?></option>
					<option value="VI">Visa</option>
					<option value="MC">MasterCard</option>
					<!--<option value="AE">American Express</option>
					<option value="DI">Discover</option>-->
				</select>
			</p><p>
				<label for="mwc_cc_num"><?php _e('Card Number', MWC_TXTDOM); ?></label><br />
				<input id="mwc_cc_num" name="mwc_cc_num" type="text" autocomplete="off" pattern="\d*" maxlength="16" />
			</p><p>
				<label for="mwc_cc_holder"><?php _e('Card Holder Name', MWC_TXTDOM); ?></label><br />
				<input id="mwc_cc_holder" name="mwc_cc_holder" type="text" autocomplete="off" maxlength="50" />
			</p><p>
				<label for="mwc_cc_exp_m"><?php _e('Expiration Date', MWC_TXTDOM); ?></label><br />
				<select id="mwc_cc_exp_m" name="mwc_cc_exp_m" autocomplete="off">
					<option value="" selected="selected"><?php _e('Month', MWC_TXTDOM); ?></option>
					<option value="1">01 - <?php _e('January', MWC_TXTDOM); ?></option>
					<option value="2">02 - <?php _e('February', MWC_TXTDOM); ?></option>
					<option value="3">03 - <?php _e('March', MWC_TXTDOM); ?></option>
					<option value="4">04 - <?php _e('April', MWC_TXTDOM); ?></option>
					<option value="5">05 - <?php _e('May', MWC_TXTDOM); ?></option>
					<option value="6">06 - <?php _e('June', MWC_TXTDOM); ?></option>
					<option value="7">07 - <?php _e('July', MWC_TXTDOM); ?></option>
					<option value="8">08 - <?php _e('August', MWC_TXTDOM); ?></option>
					<option value="9">09 - <?php _e('September', MWC_TXTDOM); ?></option>
					<option value="10">10 - <?php _e('October', MWC_TXTDOM); ?></option>
					<option value="11">11 - <?php _e('November', MWC_TXTDOM); ?></option>
					<option value="12">12 - <?php _e('December', MWC_TXTDOM); ?></option>
				</select>
				<select id="mwc_cc_exp_y" name="mwc_cc_exp_y" autocomplete="off">
					<option value="" selected="selected"><?php _e('Year', MWC_TXTDOM); ?></option>
					<?php
					$curr_year = (int)date('Y');
					for($i=$curr_year; $i<=($curr_year+10); $i++): ?>
					<option value="<?php echo $i; ?>"><?php echo $i; ?></option>
					<?php endfor; ?>
				</select>
			</p><p>
				<label for="mwc_cc_cvv"><?php _e('Card Verification Number (CVV)', MWC_TXTDOM); ?></label><br />
				<input id="mwc_cc_cvv" name="mwc_cc_cvv" type="text" autocomplete="off" pattern="\d*" maxlength="4" />
			</p>
		<?php
		}

		function validate_fields(){
			$valid = true;

			if(!isset($_POST['mwc_cc_type']) || !($this->cc_type = $this->validate_txt($_POST['mwc_cc_type']))){
				wc_add_notice(__('Credit card type is empty.', MWC_TXTDOM), 'error');
				$valid = false;
			}
			if(!isset($_POST['mwc_cc_num']) || !($this->cc_num = $this->validate_cc_num($_POST['mwc_cc_num']))){
				wc_add_notice(__('Credit card number is empty.', MWC_TXTDOM), 'error');
				$valid = false;
			}
			if(!isset($_POST['mwc_cc_holder']) || !($this->cc_holder = $this->validate_txt($_POST['mwc_cc_holder']))){
				wc_add_notice(__('Credit card holder is empty.', MWC_TXTDOM), 'error');
				$valid = false;
			}
			if(!isset($_POST['mwc_cc_exp_m']) || !($this->cc_exp_m = $this->validate_num($_POST['mwc_cc_exp_m']))){
				wc_add_notice(__('Card expiration month is empty.', MWC_TXTDOM), 'error');
				$valid = false;
			}
			if(!isset($_POST['mwc_cc_exp_y']) || !($this->cc_exp_y = $this->validate_num($_POST['mwc_cc_exp_y']))){
				wc_add_notice(__('Card expiration year is empty.', MWC_TXTDOM), 'error');
				$valid = false;
			}
			if(!isset($_POST['mwc_cc_cvv']) || !($this->cc_cvv = $this->validate_cvv($_POST['mwc_cc_cvv']))) {
				wc_add_notice(__('Card verification number is empty.', MWC_TXTDOM), 'error');
				$valid = false;
			}

			return $valid;
		}

		protected function validate_txt($val){
			if(($val = trim($val)) == ''){
				return false;
			}
			return filter_var($val, FILTER_SANITIZE_STRING);
		}

		protected function validate_num($val){
			if(!$val = (int)trim($val)){
				return false;
			}
			return $val;
		}

		protected function validate_cc_num($val){
			if(!$val = trim($val)){
				return false;
			}
			return str_replace(array(' ', '-'), '', $val);
		}

		protected function validate_cvv($val) {
			if(!$val = trim($val)){
				return false;
			}
			return $val;
		}

		function process_payment($order_id){

			$endpoint = 'https://gateway.merchantprocess.net/v2/transaction.aspx';
			if($this->get_option('sandbox') == 'yes'){
				$endpoint = 'https://gatewaysandbox.merchantprocess.net/transaction.aspx';
			}

			$order = new WC_Order($order_id);

			$transaction_result = false;

			switch($this->get_option('transtype')){
				case 'sale':
					$transaction_result = $this->do_metropago_sale($endpoint, $order);
					break;
				case 'preauth':
					$preauth_ballot = $this->do_metropago_preauth($endpoint, $order);
					if(isset($this->adjust_delay) && $this->adjust_delay){
						sleep($this->adjust_delay);
					}
					$transaction_result = $this->do_metropago_adjust($endpoint, $order, $preauth_ballot);
					break;
			}

			if(!$transaction_result){
				wc_add_notice(__('Payment error: could not complete the payment. Please try again later or contact our support.', MWC_TXTDOM), 'error');
				return;
			}

			$order->payment_complete();
			return array(
				'result' => 'success',
				'redirect' => $this->get_return_url($order)
			);
		}

		protected function do_metropago_sale($endpoint, $order) {
			global $woocommerce;

	        $request = array();

			$request['AccessCode'] = $this->get_option('acc_code');
			$request['ProcCode'] = '000000';
			$request['Merchant'] = $this->get_option('merchant_id');
			$request['Terminal'] = $this->get_option('terminal_id');

			$request['CardNumber'] =  $this->cc_num;
			$request['Expiration'] = sprintf('%02d', $this->cc_exp_m) . substr($this->cc_exp_y, -2);
			$request['Amount'] = $order->order_total;
			$request['CurrencyCode'] = $this->get_currency_num($order->order_currency);
			$request['Cardholder'] = $this->cc_holder;
			$request['cvv2'] = $this->cc_cvv;

			$request['Ship_address'] = $order->shipping_address_1;
			$request['Ship_city'] = $order->shipping_city;
			$request['Ship_country'] = $order->shipping_country;
			$request['Ship_state'] = $order->shipping_state;
			$request['Ship_zip_code'] = $order->shipping_postcode;
			$request['Ship_phone_number'] = $order->billing_phone;
			$request['Bill_address'] = $order->billing_address_1;
			$request['Bill_city'] = $order->billing_city;
			$request['Bill_country'] = $order->billing_country;
			$request['Bill_state'] = $order->billing_state;
			$request['Bill_zip_code'] = $order->billing_postcode;
			$request['Bill_phone_number'] = $order->billing_phone;

			if($woocommerce->cart->cart_contents) {
				$cart = array_values($woocommerce->cart->cart_contents);
				$i = 0;
				foreach($cart as $k => $cart_item) {
					$i = $k+1;

					$product_code = $cart_item['product_id'];
					if($sku = get_post_meta($cart_item->product_id, '_sku', true)) {
						$product_code = $sku;
					}

					$request['ProdCode' . $i] = $product_code;
					$request['GenericName' . $i] = $cart_item['data']->post->post_title;
					$request['ProdDescription' . $i] = $cart_item['data']->post->post_title;
					$request['Quantity' . $i] = $cart_item['quantity'];
					$request['Charge' . $i] = $cart_item['data']->price;
				}
				if(isset($woocommerce->cart->shipping_total) && $woocommerce->cart->shipping_total) {
					$i += 1;
					$request['ProdCode' . $i] = 'shippping';
					$request['GenericName' . $i] = __('Shipping', MWC_TXTDOM);
					$request['ProdDescription' . $i] = __('Shipping cost', MWC_TXTDOM);
					$request['Quantity' . $i] = 1;
					$request['Charge' . $i] = $woocommerce->cart->shipping_total;
				}
			}

			$request['Tracking'] = $order->id;
			$args = array('method' => 'POST', 'redirection' => 0, 'timeout' => 30, 'body' => $request, 'cookies' => array());

			$response_raw = wp_remote_post($endpoint, $args);
			$response_raw = wp_remote_retrieve_body($response_raw);
			if(!$response = $this->parse_payment_response($response_raw, $order->id, 'sale')) {
				$this->log_error($response_raw, $order->id, 'sale');
				return 0;
			}

			if(in_array($response['ResponseCode'], array('14', '116', '117'))){
				wc_add_notice(__('Credit card validation failed. Please, review card details.', MWC_TXTDOM), 'error');
				$this->log_error($response_raw, $order->id, 'sale');
				return 0;
			}

			if(!in_array($response['ResponseCode'], array('00', '11'))) {
				$this->log_error($response_raw, $order->id, 'sale');
				return 0;
			}

			$response['WCTimestamp'] = time();
			update_post_meta($order->id, 'metropago_sale', $response);

			return true;
		}

		protected function do_metropago_preauth($endpoint, $order){
			global $woocommerce;

			$saved_preauth = get_post_meta($order->id, 'metropago_preauth', true);
			if(isset($saved_preauth['WCTimestamp']) && (time() - $saved_preauth['WCTimestamp']) < $this->auth_expires && isset($saved_preauth['BallotNumber'])){
				return (int)$saved_preauth['BallotNumber'];
			}

	        $request = array();

			$request['AccessCode'] = $this->get_option('acc_code');
			//$request['ProcCode'] = $this->get_option('proc_code');
			$request['ProcCode'] = 300000;
			$request['Merchant'] = $this->get_option('merchant_id');
			$request['Terminal'] = $this->get_option('terminal_id');

			$request['CardNumber'] =  $this->cc_num;
			$request['Expiration'] = sprintf('%02d', $this->cc_exp_m) . substr($this->cc_exp_y, -2);
			$request['Amount'] = $order->order_total;
			$request['CurrencyCode'] = $this->get_currency_num($order->order_currency);
			$request['Cardholder'] = $this->cc_holder;
			$request['cvv2'] = $this->cc_cvv;

			$request['Ship_address'] = $order->shipping_address_1;
			$request['Ship_city'] = $order->shipping_city;
			$request['Ship_country'] = $order->shipping_country;
			$request['Ship_state'] = $order->shipping_state;
			$request['Ship_zip_code'] = $order->shipping_postcode;
			$request['Ship_phone_number'] = $order->billing_phone;
			$request['Bill_adress'] = $order->billing_address_1;
			$request['Bill_city'] = $order->billing_city;
			$request['Bill_country'] = $order->billing_country;
			$request['Bill_state'] = $order->billing_state;
			$request['Bill_zip_code'] = $order->billing_postcode;
			$request['Bill_phone_number'] = $order->billing_phone;

			if($woocommerce->cart->cart_contents){
				$cart = array_values($woocommerce->cart->cart_contents);
				$i = 0;
				foreach($cart as $k => $cart_item){
					$i = $k+1;

					$product_code = $cart_item['product_id'];
					if($sku = get_post_meta($cart_item->product_id, '_sku', true)){
						$product_code = $sku;
					}

					$request['ProdCode' . $i] = $product_code;
					$request['GenericName' . $i] = $cart_item['data']->post->post_title;
					$request['ProdDescription' . $i] = $cart_item['data']->post->post_title;
					$request['Quantity' . $i] = $cart_item['quantity'];
					$request['Charge' . $i] = $cart_item['data']->price;
				}
				if(isset($woocommerce->cart->shipping_total) && $woocommerce->cart->shipping_total){
					$i += 1;
					$request['ProdCode' . $i] = 'shippping';
					$request['GenericName' . $i] = __('Shipping', MWC_TXTDOM);
					$request['ProdDescription' . $i] = __('Shipping cost', MWC_TXTDOM);
					$request['Quantity' . $i] = 1;
					$request['Charge' . $i] = $woocommerce->cart->shipping_total;
				}
			}

			//$request['SiteIPAddress'] = $_SERVER['SERVER_ADDR'];
			//$request['Email'] = $order->billing_email;
			$request['Tracking'] = $order->id;

			$args = array('method' => 'POST', 'redirection' => 0, 'timeout' => 30, 'body' => $request, 'cookies' => array());

			$response_raw = wp_remote_post($endpoint, $args);
			$response_raw = wp_remote_retrieve_body($response_raw);
			if(!$response = $this->parse_payment_response($response_raw, $order->id, 'preauth')){
				$this->log_error($response_raw, $order->id, 'preauth');
				return 0;
			}

			if(in_array($response['ResponseCode'], array('14', '116', '117'))){
				wc_add_notice(__('Credit card validation failed. Please, review card details.', MWC_TXTDOM), 'error');
				$this->log_error($response_raw, $order->id, 'preauth');
				return 0;
			}

			if(!in_array($response['ResponseCode'], array('00', '11'))){
				$this->log_error($response_raw, $order->id, 'preauth');
				return 0;
			}

			$response['WCTimestamp'] = time();
			update_post_meta($order->id, 'metropago_preauth', $response);

			return (int)$response['BallotNumber'];
		}

		protected function do_metropago_adjust($endpoint, $order, $ballot){
			//global $woocommerce;

	        if(!$ballot){
				$this->log_error(__('No ballot number provided.', MWC_TXTDOM), $order->id, 'adjust');
	        	return false;
	        }

	        $request = array();

			//$request['AccessCode'] = $this->get_option('acc_code');
			$request['ProcCode'] = 290000;
			$request['Merchant'] = $this->get_option('merchant_id');
			$request['Terminal'] = $this->get_option('terminal_id');
			$request['Amount'] = $order->order_total;
			$request['Ballot'] = $ballot;
			$request['Tracking'] = $order->id;

			$args = array('method' => 'POST', 'redirection' => 0, 'timeout' => 30, 'body' => $request, 'cookies' => array());

			$response_raw = wp_remote_post($endpoint, $args);
			$response_raw = wp_remote_retrieve_body($response_raw);
			if(!$response = $this->parse_payment_response($response_raw, $order->id, 'adjust')){
				$this->log_error($response_raw, $order->id, 'adjust');
				return false;
			}

			if(!in_array($response['ResponseCode'], array('00', '11'))){
				$this->log_error($response_raw, $order->id, 'adjust');
				return false;
			}

			$response['WCTimestamp'] = time();
			update_post_meta($order->id, 'metropago_adjust', $response);

			return true;
		}

		protected function get_currency_num($code){
			$currencies = array(
				'USD' => 840,
			    'PAB' => 590,
				'EUR' => 978
				);
			if(isset($currencies[$code])){
				return $currencies[$code];
			}
			return $code;
		}

		protected function parse_payment_response($response_raw, $order_id, $type){
			if(is_wp_error($response_raw)){
				if(isset($response_raw->errors) && $response_raw->errors){
					$this->log_error(implode(' | ', array_values($response_raw->errors)), $order_id, $type);
				}
				return array();
			}

			if(!$response_raw){
				return array();
			}

			$fields = array(
				'ResponseCode',
				'ReferenceNumber',
				'AuthorizationNumber',
				'Time',
				'Date',
				'BallotNumber'
				);
		    if($response_expl = explode('~', $response_raw)){
			    foreach($response_expl as $i => $e){
			    	$e = trim($e); if($e == ''){
			    		unset($response_expl[$i]);
			    	}
		    	}
		    	$response_expl = array_values($response_expl);
	    	}
	    	if(count($fields) != count($response_expl)){
	    		return array();
	    	}
		    return array_combine($fields, $response_expl);
		}

		protected function log_error($msg, $order_id, $type){
			if($this->log_errors){
				$msg = '[' . date('Y-m-d H:i:s') . ' OrderID:' . $order_id . ' Type:' . $type . '] ' . $msg . "\n";
				@file_put_contents($this->log_errors_file, $msg, FILE_APPEND);
			}
		}
	}
}
add_action('plugins_loaded', 'mwc_define_gateway_class');

function mwc_declare_gateway_class($methods){
	$methods[] = 'MWC_Gateway';
	return $methods;
}
add_filter('woocommerce_payment_gateways', 'mwc_declare_gateway_class');

