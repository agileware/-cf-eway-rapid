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
		)
	);
	return $processors;

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
		        $transaction = [
		                'RedirectUrl' => $returnurl . '?'.http_build_query( $queryvars ),
		                'CancelUrl' => $returnurl . '?'.http_build_query( array_merge($queryvars, array('ew_cancel' => 'true') ) ),
		                'TransactionType' => \Eway\Rapid\Enum\TransactionType::PURCHASE,
		                'Payment' => [
		                        'TotalAmount' =>  (Caldera_Forms::get_field_data( $settings["price"], $form ) * 100),
		                        'CurrencyCode' => $settings["currency"],
		                ],
		                'Items' => [
		                        [
		                                'Description' => $settings["desc"],
						'UnitCost' => (Caldera_Forms::get_field_data( $settings["price"], $form ) * 100),
		                                'Quantity' => ( !empty( $settings['qty'] ) ? (int) Caldera_Forms::get_field_data( $settings['qty'], $form ) : 1 ),
		                        ],
		                ],
		        ];

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
			'email'				=>	$transactionResponse->Email,
			'firstname'			=>	$transactionResponse->Customer->FirstName,
			'lastname'			=>	$transactionResponse->Customer->LastName,
			'name'				=>	$transactionResponse->ShippingAddress->FirstName . " " . $transactionResponse->ShippingAddress->LastName,
			'street'			=>	$transactionResponse->ShippingAddress->Street1 . " " . $transactionResponse->ShippingAddress->Street2,
			'city'				=>	$transactionResponse->ShippingAddress->City,
			'state'				=>	$transactionResponse->ShippingAddress->State,
			'zip'				=>	$transactionResponse->ShippingAddress->PostalCode,
			'country_code'			=>	$transactionResponse->ShippingAddress->Country,
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
