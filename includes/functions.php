<?php

/**
 * Registers the eWay Rapid Processor
 *
 * @param array		$processors		array of current regestered processors
 *
 * @return array	array of regestered processors
 */
function cf_eway_rapid_register_processor($processors){

	$processors['eway_rapid'] = array(
		"name"				=>	"eWAY Rapid",
		"description"		        =>	"Process a payment via eWAY",
		"icon"				=>	CF_EWAY_RAPID_URL . "icon.png",
		"single"			=>	true,
		"pre_processor"		        =>	'cf_eway_rapid_setup_payment',
		"processor"			=>	'cf_eway_rapid_process_payment',
		"template"			=>	CF_EWAY_RAPID_PATH . "includes/config.php",
		"magic_tags"            	=>	array(
				'transaction_id',
				'currency_code',
				'amount',
				'payment_status',
				'firstname',
				'lastname',
				'name',
				'email',
				'street1',
				'street2',
				'city',
				'state',
				'zip',
				'country_code',
				'phone',
				'shipping_method',
				'shipping_firstname',
				'shipping_lastname',
				'shipping_name',
				'shipping_street1',
				'shipping_street2',
				'shipping_city',
				'shipping_state',
				'shipping_country_code',
				'shipping_zip',
				'shipping_email',
				'shipping_phone',
		)
	);
	return $processors;

}

function getFinalAmountOfInvoice($settings, $form) {
	$amount = Caldera_Forms::get_field_data( $settings["price"], $form );
	$qty = ((!empty($settings['qty']) && !empty(Caldera_Forms::get_field_data( $settings['qty'], $form ))) ? (int) Caldera_Forms::get_field_data( $settings['qty'], $form ) : 1);
	$tax = ((!empty($settings['tax']) && !empty(Caldera_Forms::get_field_data( $settings["tax"], $form ))) ? (int) Caldera_Forms::get_field_data( $settings["tax"], $form ) : 0);
	$amount = ($amount * $qty) + $tax;
	return ($amount * 100);
}

/**
 * Sets up eWAY Responsive Shared URL in the form submission for redirection
 *
 * @param array		$transdata		array of the current submission transient
 * @param array		$form			array of the complete form config structure
 * @param array		$referrer		array structure of the referring URL
 * @param string	$processid		unique ID of the processor instance
 *
 * @return array	array of altered transient data
 */
function cf_eway_rapid_set_redirect_url($transdata, $form, $referrer, $processid) {
	if(!empty($transdata['eway_rapid']['checkout'])){
		return $transdata;
	}

        if( isset( $transdata['eway_rapid'] ) && $transdata['type'] === 'success' ) {
		$returnurl = $referrer['scheme'] . '://' . $referrer['host'] . $referrer['path'];
		$queryvars = array(
		        'cf_tp' => $processid
		);
		if(!empty($referrer['query'])){
		        $queryvars = array_merge($referrer['query'], $queryvars);
		}
		if(!isset($transdata['eway_rapid']['response'])) {
		        $settings = $transdata['eway_rapid']['config'];
		        $apiEndPoint = \Eway\Rapid\Client::ENDPOINT_PRODUCTION;
		        if($settings["sandbox"]) {
		                $apiEndPoint = \Eway\Rapid\Client::ENDPOINT_SANDBOX;
		        }
		        $client = \Eway\Rapid::createClient($settings["key"], $settings["password"], $apiEndPoint);

						$finalAmount = getFinalAmountOfInvoice($settings, $form);

		        $transaction = [
		                'RedirectUrl' => $returnurl . '?'.http_build_query( $queryvars ),
		                'CancelUrl' => $returnurl . '?'.http_build_query( array_merge($queryvars, array('ew_cancel' => 'true') ) ),
		                'TransactionType' => \Eway\Rapid\Enum\TransactionType::PURCHASE,
		                'Payment' => [
		                        'TotalAmount' =>  $finalAmount,
		                        'CurrencyCode' => $settings["currency"],
														'InvoiceNumber' => Caldera_Forms::get_field_data( $settings["invoiceNumber"], $form ),
										        'InvoiceDescription' => Caldera_Forms::get_field_data( $settings["invoiceDescription"], $form ),
										        'InvoiceReference' => Caldera_Forms::get_field_data( $settings["invoiceReference"], $form ),
		                ],
		                'Items' => [
		                        [
																		'UnitCost' => (Caldera_Forms::get_field_data( $settings["price"], $form ) * 100),
		                                'Quantity' => ( !empty( $settings['qty'] ) ? (int) Caldera_Forms::get_field_data( $settings['qty'], $form ) : 1 ),
																		'Tax' => ( !empty( $settings['tax'] ) ? (int) (Caldera_Forms::get_field_data( $settings["tax"], $form )*100) : 0 ),
		                        ],
		                ],
		        ];

						mapCustomerDetails($transaction, $form, $settings);
						mapShippingDetails($transaction, $form, $settings);
						setCustomerCountry($transaction, $form, $settings);

		        $response = $client->createTransaction(\Eway\Rapid\Enum\ApiMethod::RESPONSIVE_SHARED, $transaction);

			if(!$response->getErrors()) {
			        $transdata['eway_rapid']['response'] = $response;
			} else {
				$errors = "";
				foreach ($response->getErrors() as $error) {
				        $errors .= "Error: ".\Eway\Rapid::getMessage($error)."<br>";
    				}

				$transdata['note'] 		= $errors;
				$transdata['type'] 		= 'error';
			}
		}
        }
	return $transdata;
}

/**
 * Map customer details with transaction
 *
 * @param array		$transaction			Transaction array of the payment by reference.
 * @param array		$form			        Array of the complete form config structure
 * @param array		$settings			    Config array of the processor
 */
function mapCustomerDetails(&$transaction, $form, $settings) {
	global $customer_fields;
	$transaction["Customer"] = array();
	$keys = $customer_fields["ewaykeys"];
	foreach ($keys as $index => $customer_field_eway_key) {
		$customer_field_key = $customer_fields["keys"][$index];
		$field_value = Caldera_Forms::get_field_data($settings[$customer_field_key], $form);
		$transaction["Customer"][$customer_field_eway_key] = $field_value;
	}
}

/**
 * Modify customer country from ID to ISO Code.
 *
 * @param array $transaction       Transaction array of the payment by reference.
 * @param array $form              Array of the complete form config structure
 * @param array $settings          Config array of the processor
 */
function setCustomerCountry(&$transaction, $form, $settings) {
	if (isset($transaction['Customer']['Country']) && !empty($transaction['Customer']['Country'])) {
		$countryISO = getCountryISOFromID($transaction['Customer']['Country']);
		$transaction['Customer']['Country'] = $countryISO;
	}
	if (isset($transaction['ShippingAddress']['Country']) && !empty($transaction['ShippingAddress']['Country'])) {
		$countryISO = getCountryISOFromID($transaction['ShippingAddress']['Country']);
		$transaction['ShippingAddress']['Country'] = $countryISO;
	}
}


/**
 * Get country ISO code from its ID.
 *
 * @param $id
 */
function getCountryISOFromID($id) {
	try {
		$country = civicrm_api3('Country', 'getsingle', array(
			'id' => $id
		));
		return $country['iso_code'];
	}
	catch (CiviCRM_API3_Exception $e) {
		// Country not found, return blank.
		return '';
	}
}

/**
 * Map shipping details with transaction
 *
 * @param array		$transaction			Transaction array of the payment by reference.
 * @param array		$form			        Array of the complete form config structure
 * @param array		$settings			    Config array of the processor
 */
function mapShippingDetails(&$transaction, $form, $settings) {
	global $shipping_fields;
	$transaction["ShippingAddress"] = array();
	$keys = $shipping_fields["ewaykeys"];
	foreach ($keys as $index => $shipping_field_eway_key) {
		$shipping_field_key = $shipping_fields["keys"][$index];
		$field_value = Caldera_Forms::get_field_data($settings[$shipping_field_key], $form);
		$transaction["ShippingAddress"][$shipping_field_eway_key] = $field_value;
	}
}

/**
 * Requests and redirects to eWAY for authentication
 *
 * @param array		$config			Config array of the processor
 * @param array		$form			array of the complete form config structure
 *
 * @return array	result array and redirect status
 */
function cf_eway_rapid_setup_payment($config, $form) {
	global $transdata;
	if(!empty($_GET['ew_cancel'])){

		if(!empty($transdata['eway_rapid'])){
			unset($transdata['eway_rapid']);
		}

		$return = array(
			'type'	=> 'error',
			'note'	=> 'Transaction has been canceled'
		);

		return $return;

	} else{
		if( !empty($_GET['cf_tp']) && !empty($_GET['AccessCode']) && empty($transdata['eway_rapid']['checkout']) ){
			$accessCode = $_GET['AccessCode'];
			$apiEndPoint = \Eway\Rapid\Client::ENDPOINT_PRODUCTION;
                        if($config["sandbox"]) {
                                $apiEndPoint = \Eway\Rapid\Client::ENDPOINT_SANDBOX;
                        }

			$client = \Eway\Rapid::createClient($config["key"], $config["password"], $apiEndPoint);
			$response = $client->queryTransaction($accessCode);

			$transactionResponse = $response->Transactions[0];

			if ($transactionResponse->TransactionStatus) {
			    $transdata['eway_rapid']['checkout'] = $transactionResponse;
			} else {
			    $errors = split(',', $transactionResponse->ResponseMessage);
			    $errorMessage = "Payment failed: ";
			    foreach ($errors as $error) {
				$errorMessage .= \Eway\Rapid::getMessage(trim($error))."<br>";
			    }
			    $transdata['note'] 		= $errorMessage;
			    $transdata['type'] 		= 'error';
			}

		}

		if(empty($transdata['eway_rapid']['checkout'])) {

			$transdata['expire'] = 1200;
			$transdata['eway_rapid']['config'] = $config;
			$return = array(
				'type'	=> 'success',
			);

			return $return;
		}
	}
}

/**
 * Processes the actual payment and returns the payment result
 *
 * @param array		$config			Config array of the processor
 * @param array		$form			array of the complete form config structure
 *
 * @return array	array of the transaction result
 */
function cf_eway_rapid_process_payment($config, $form) {
	global $transdata;
	if(!empty($transdata['eway_rapid']['result'])){
		return $transdata['eway_rapid']['result'];
	}

	if(!empty($transdata['eway_rapid']['checkout'])) {
		$transactionResponse = $transdata['eway_rapid']['checkout'];

    $returns = array(
			"transaction_id" 		=> 	$transactionResponse->TransactionID,
			'currency_code'			=>	$config["currency"],
			'amount'            		=>	($transactionResponse->TotalAmount/100),
			'payment_status'		=>	($transactionResponse->TransactionStatus) ? "Completed" : "Failed",
			'firstname'			=>	$transactionResponse->Customer->FirstName,
			'lastname'			=>	$transactionResponse->Customer->LastName,
			'name'				=>	$transactionResponse->Customer->FirstName . " " . $transactionResponse->Customer->LastName,
			'email'				=>	$transactionResponse->Customer->Email,
			'street1'			=>	$transactionResponse->Customer->Street1,
			'street2'			=>	$transactionResponse->Customer->Street2,
			'city'				=>	$transactionResponse->Customer->City,
			'state'				=>	$transactionResponse->Customer->State,
			'zip'				=>	$transactionResponse->Customer->PostalCode,
			'country_code'			=>	$transactionResponse->Customer->Country,
			'phone'			=>	$transactionResponse->Customer->Phone,
			'shipping_method'			=>	$transactionResponse->ShippingAddress->ShippingMethod,
			'shipping_firstname'			=>	$transactionResponse->ShippingAddress->FirstName,
			'shipping_lastname'			=>	$transactionResponse->ShippingAddress->LastName,
			'shipping_name'				=>	$transactionResponse->ShippingAddress->FirstName . " " . $transactionResponse->ShippingAddress->LastName,
			'shipping_email'				=>	$transactionResponse->ShippingAddress->Email,
			'shipping_street1'			=>	$transactionResponse->ShippingAddress->Street1,
			'shipping_street2'			=>	$transactionResponse->ShippingAddress->Street2,
			'shipping_city'				=>	$transactionResponse->ShippingAddress->City,
			'shipping_state'				=>	$transactionResponse->ShippingAddress->State,
			'shipping_zip'				=>	$transactionResponse->ShippingAddress->PostalCode,
			'shipping_country_code'			=>	$transactionResponse->ShippingAddress->Country,
			'shipping_phone'			=>	$transactionResponse->ShippingAddress->Phone,
    );

		$transdata['eway_rapid']['result'] = $returns;

		return $returns;
	}
}

/**
 * Filteres the redirect url and substitutes with eWAY auth if needed.
 *
 * @param array		$url			current redirect url
 * @param array		$form			array of the complete form config structure
 * @param array		$config			config array of processor instance
 * @param string	$processid		unique ID if the processor instance
 *
 * @return array	array of altered transient data
 */
function cf_eway_rapid_redirect_toeway($url, $form, $config, $processid) {
	global $transdata;
	if(empty($transdata['eway_rapid']['checkout']) && !empty($transdata['eway_rapid']['response'])){
		$response = $transdata['eway_rapid']['response'];
		return $response->SharedPaymentUrl;
	}
	return $url;
}

?>
