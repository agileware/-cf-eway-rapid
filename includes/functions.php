<?php

/**
 * Registers the eWay Rapid Processor
 *
 * @param array $processors array of current regestered processors
 *
 * @return array    array of regestered processors
 */
function cf_eway_rapid_register_processor( $processors ) {

	$processors['eway_rapid'] = [
		"name"          => "eWAY Rapid",
		"description"   => "Process a payment via eWAY",
		"icon"          => CF_EWAY_RAPID_URL . "icon.png",
		"single"        => TRUE,
		"pre_processor" => 'cf_eway_rapid_setup_payment',
		"template"      => CF_EWAY_RAPID_PATH . "includes/config.php",
		"magic_tags"    => [
			'transaction_id',
			'currency_code',
			'amount',
			'payment_status',
			'customer_token',
			'card_details',
			'card_number',
			'expired_date'
		],
	];

	return $processors;

}

/**
 * Hook caldera_forms_submit_pre_process_start
 *
 *
 * @param $form
 * @param $referrer
 * @param $process_id
 */
function cf_eway_rapid_restore_meta( $form, $referrer, $process_id ) {
	global $processed_meta, $transdata;
	if ( $_GET['cf_tp'] && $transdata['processed_meta'] ) {
		$processed_meta = $transdata['processed_meta'];
	}
}

function getFinalAmountOfInvoice( $settings, $form ) {
	$amount =  $settings["price"]? $settings['price'] : 0;
	$qty    = ( ( ! empty( $settings['qty'] ) ) ? (int) $settings['qty'] : 1 );
	$tax    = ( ( ! empty( $settings['tax'] ) ) ?       $settings["tax"] : 0 );
	$amount = round(( $amount * $qty + $tax ) * 100);

	return $amount;
}

/**
 * Hook caldera_forms_submit_return_transient_pre_process
 * Sets up eWAY Responsive Shared URL in the form submission for redirection
 *
 * @param array  $transdata array of the current submission transient
 * @param array  $form      array of the complete form config structure
 * @param array  $referrer  array structure of the referring URL
 * @param string $processid unique ID of the processor instance
 *
 * @return array    array of altered transient data
 */
function cf_eway_rapid_set_redirect_url( $transdata, $form, $referrer, $processid ) {
	if ( ! empty( $transdata['eway_rapid']['checkout'] ) ) {
		return $transdata;
	}

	if ( isset( $transdata['eway_rapid'] ) && $transdata['type'] === 'success' ) {
		$returnurl = $referrer['scheme'] . '://' .
		             $referrer['host'] .
		             ( in_array( $referrer['port'], [ '80', '443' ] ) ? '' : ':' . $referrer['port'] ) .
		             $referrer['path'];
		$queryvars = [
			'cf_tp' => $processid,
		];
		if ( ! empty( $referrer['query'] ) ) {
			$queryvars = array_merge( $referrer['query'], $queryvars );
		}
		if ( ! isset( $transdata['eway_rapid']['response'] ) ) {
			$settings = $transdata['eway_rapid']['config'];
			cf_eway_map_fields_to_processor( $settings, $form, $form_values );
			$apiEndPoint = \Eway\Rapid\Client::ENDPOINT_PRODUCTION;
			if ( $form_values["sandbox"] ) {
				$apiEndPoint = \Eway\Rapid\Client::ENDPOINT_SANDBOX;
			}
			$client = \Eway\Rapid::createClient( $form_values["key"], $form_values["password"], $apiEndPoint );

			if ($form_values['customer']) {
				// process eWay customer
				$transaction = [
					'RedirectUrl'      => $returnurl . '?' . http_build_query( $queryvars ),
					'CancelUrl'        => $returnurl . '?' . http_build_query( array_merge( $queryvars,
							[ 'ew_cancel' => 'true' ] ) ),
					'CustomerReadOnly' => TRUE,
					'SaveCustomer'     => TRUE,
				];
				$customer = [];
				// fixme these functions need to be improved
				mapCustomerDetails( $customer, $form, $settings );
				setCustomerCountry( $customer, $form, $settings );
				setCustomerState( $customer, $form, $settings );
				$transaction = array_merge( $transaction, $customer['Customer'] );
				// the token sometime will be a magic tag - in this case, it means no token
				if ( $form_values['customerTokenID'] && strpos($form_values['customerTokenID'], '{') !== 0 ) {
					$transaction['TokenCustomerID'] = $form_values['customerTokenID'];
					$response = $client->updateCustomer( \Eway\Rapid\Enum\ApiMethod::RESPONSIVE_SHARED, $transaction );
				} else {
					$response = $client->createCustomer( \Eway\Rapid\Enum\ApiMethod::RESPONSIVE_SHARED, $transaction );
				}
			} else {
				// process eWay payment
				$finalAmount = getFinalAmountOfInvoice( $form_values, $form );

				$transaction = [
					'RedirectUrl'      => $returnurl . '?' . http_build_query( $queryvars ),
					'CancelUrl'        => $returnurl . '?' . http_build_query( array_merge( $queryvars,
							[ 'ew_cancel' => 'true' ] ) ),
					'TransactionType'  => \Eway\Rapid\Enum\TransactionType::PURCHASE,
					'Payment'          => [
						'TotalAmount'        => $finalAmount,
						'CurrencyCode'       => $form_values["currency"],
						'InvoiceNumber'      => $form_values["invoiceNumber"],
						'InvoiceDescription' => $form_values["invoiceDescription"],
						'InvoiceReference'   => $form_values["invoiceReference"],
					],
					'Items'            => [
						[
							'UnitCost' => ( $form_values["price"] * 100 ),
							'Quantity' => ( ! empty( $form_values['qty'] ) ? (int) $form_values['qty'] : 1 ),
							'Tax'      => ( ! empty( $form_values['tax'] ) ? (int) ( $form_values["tax"] * 100 ) : 0 ),
						],
					],
					'Capture'          => TRUE,
					'SaveCustomer'     => TRUE,
					'CustomerReadOnly' => TRUE,
				];

				mapCustomerDetails( $transaction, $form, $settings );
				mapShippingDetails( $transaction, $form, $settings );
				setCustomerCountry( $transaction, $form, $settings );
				setCustomerState( $transaction, $form, $settings );

				$response = $client->createTransaction( \Eway\Rapid\Enum\ApiMethod::RESPONSIVE_SHARED, $transaction );
			}
			$transdata['eway_rapid']['response'] = $response;
			global $processed_meta;
			$transdata['processed_meta'] = $processed_meta;
		}
	}

	return $transdata;
}

/**
 * Map customer details with transaction
 *
 * @param array $transaction Transaction array of the payment by reference.
 * @param array $form        Array of the complete form config structure
 * @param array $settings    Config array of the processor
 */
function mapCustomerDetails( &$transaction, $form, $settings ) {
	global $customer_fields;
	$transaction["Customer"] = [];
	$keys                    = $customer_fields["ewaykeys"];
	foreach ( $keys as $index => $customer_field_eway_key ) {
		$customer_field_key = $customer_fields["keys"][ $index ];
		$field_value        = Caldera_Forms::get_field_data( $settings[ $customer_field_key ], $form );

		$transaction["Customer"][ $customer_field_eway_key ] = $field_value;
	}
}

/**
 * Modify customer country from ID to ISO Code.
 *
 * @param array $transaction Transaction array of the payment by reference.
 * @param array $form        Array of the complete form config structure
 * @param array $settings    Config array of the processor
 */
function setCustomerCountry( &$transaction, $form, $settings ) {
	if ( isset( $transaction['Customer']['Country'] ) && ! empty( $transaction['Customer']['Country'] ) ) {
		$countryISO                         = getCountryISOFromID( $transaction['Customer']['Country'] );
		$transaction['Customer']['Country'] = $countryISO;
	}
	if ( isset( $transaction['ShippingAddress']['Country'] )
	     && ! empty( $transaction['ShippingAddress']['Country'] )
	) {
		$countryISO                                = getCountryISOFromID( $transaction['ShippingAddress']['Country'] );
		$transaction['ShippingAddress']['Country'] = $countryISO;
	}
}


/**
 * Modify customer state from ID to Name.
 *
 * @param $transaction
 * @param $form
 * @param $settings
 */
function setCustomerState( &$transaction, $form, $settings ) {
	if ( isset( $transaction['Customer']['State'] ) && ! empty( $transaction['Customer']['State'] ) ) {
		$stateID                          = getStateAbrFromID( $transaction['Customer']['State'] );
		$transaction['Customer']['State'] = $stateID;
	}
	if ( isset( $transaction['ShippingAddress']['State'] ) && ! empty( $transaction['ShippingAddress']['State'] ) ) {
		$stateID                                 = getStateAbrFromID( $transaction['ShippingAddress']['State'] );
		$transaction['ShippingAddress']['State'] = $stateID;
	}
}

function getStateAbrFromID( $id ) {
	try {
		$state = civicrm_api3( 'StateProvince',
			'getsingle',
			[
				'id' => $id,
			] );

		return $state['abbreviation'];
	} catch ( CiviCRM_API3_Exception $e ) {
		// Country not found, return blank.
		return '';
	}
}


/**
 * Get country ISO code from its ID.
 *
 * @param $id
 */
function getCountryISOFromID( $id ) {
	try {
		$country = civicrm_api3( 'Country',
			'getsingle',
			[
				'id' => $id,
			] );

		return $country['iso_code'];
	} catch ( CiviCRM_API3_Exception $e ) {
		// Country not found, return blank.
		return '';
	}
}

/**
 * Map shipping details with transaction
 *
 * @param array $transaction Transaction array of the payment by reference.
 * @param array $form        Array of the complete form config structure
 * @param array $settings    Config array of the processor
 */
function mapShippingDetails( &$transaction, $form, $settings ) {
	global $shipping_fields;
	$transaction["ShippingAddress"] = [];
	$keys                           = $shipping_fields["ewaykeys"];
	foreach ( $keys as $index => $shipping_field_eway_key ) {
		$shipping_field_key = $shipping_fields["keys"][ $index ];
		$field_value        = Caldera_Forms::get_field_data( $settings[ $shipping_field_key ], $form );

		$transaction["ShippingAddress"][ $shipping_field_eway_key ] = $field_value;
	}
}

/**
 * The pre processor for eway
 * Requests and redirects to eWAY for authentication
 *
 * @param array $config Config array of the processor
 * @param array $form   array of the complete form config structure
 *
 * @return array    result array and redirect status
 */
function cf_eway_rapid_setup_payment( $config, $form ) {
	global $transdata;
	if ( ! empty( $_GET['ew_cancel'] ) ) {

		if ( ! empty( $transdata['eway_rapid'] ) ) {
			unset( $transdata['eway_rapid'] );
		}

		$return = [
			'type' => 'error',
			'note' => 'Transaction has been canceled',
		];

		return $return;

	} else {
		if ( ! empty( $_GET['cf_tp'] ) && ! empty( $_GET['AccessCode'] )
		     && empty( $transdata['eway_rapid']['checkout'] )
		) {
			// query the result
			$accessCode  = $_GET['AccessCode'];
			$apiEndPoint = \Eway\Rapid\Client::ENDPOINT_PRODUCTION;
			if ( $config["sandbox"] ) {
				$apiEndPoint = \Eway\Rapid\Client::ENDPOINT_SANDBOX;
			}

			$client   = \Eway\Rapid::createClient( $config["key"], $config["password"], $apiEndPoint );
			$response = $client->queryTransaction( $accessCode );
			$transactionResponse = $response->Transactions[0];

			/**
			 * Use response code here instead of the transaction status
			 * Because the transaction status is false for all customer token endpoint
			 * @see https://eway.io/api-v3/?php#transaction-response-messages
			 * for other response code
			 */
			if ( $transactionResponse->ResponseMessage == 'A2000' ) {
				$transdata['eway_rapid']['checkout'] = $transactionResponse;
				$customResponse = $client->queryCustomer( $transactionResponse->TokenCustomerID);
				$customResponse = $customResponse->Customers[0];
				$transdata['eway_rapid']['customer'] = $customResponse;
				cf_eway_rapid_process_meta( $form, $config );
			} else {
				// fixme the error not right
				$errors       = preg_split( ',', $transactionResponse->ResponseMessage );
				$errorMessage = "Payment failed: ";
				foreach ( $errors as $error ) {
					$errorMessage .= \Eway\Rapid::getMessage( trim( $error ) ) . "<br>";
				}
				$transdata['note']  = $errorMessage;
				$transdata['type']  = 'error';

				// raise error or ignore
				if ( ! $config['ignore_error'] ) {
					return $transdata;
				}
				// keep the response for other processor
				$transdata['eway_rapid']['checkout'] = $transactionResponse;
			}

		}

		if ( empty( $transdata['eway_rapid']['checkout'] ) ) {

			$transdata['expire']               = 1200;
			$transdata['eway_rapid']['config'] = $config;
			$return                            = [
				'type' => 'success',
			];

			return $return;
		}
	}
}

/**
 * Directly store the meta data aka magic tag values
 * Before calling this function, make sure the transaction and customer response is stored in the transient
 * @param $form array the caldera form
 * @param $config array the processor config
 */
function cf_eway_rapid_process_meta( $form, $config ) {
	global $transdata;
	$transactionResponse = $transdata['eway_rapid']['checkout'];
	/** @var \Eway\Rapid\Model\Customer $customerResponse */
	$customerResponse = $transdata['eway_rapid']['customer'];
	$date             = new DateTime();
	$date->setDate( $customerResponse->CardDetails->ExpiryYear, $customerResponse->CardDetails->ExpiryMonth, 1 );
	$expired_date = $date->format( 'Y-m-t 23:59:59' );

	$meta = [
		"transaction_id" => $transactionResponse->TransactionID,
		'currency_code'  => $config["currency"],
		'amount'         => ( $transactionResponse->TotalAmount / 100 ),
		'payment_status' => ( $transactionResponse->TransactionStatus ) ? 1 : 0,
		'customer_token' => $transactionResponse->TokenCustomerID,
		'card_details'   => $customerResponse->CardDetails->toArray(),
		'card_number'    => $customerResponse->CardDetails->Number,
		'expired_date'   => $expired_date,
	];

	foreach ( $meta as $key => $value ) {
		Caldera_Forms::set_submission_meta( $key, $value, $form, $config['processor_id'] );
	}
}

/**
 * The processor function for eway
 * Processes the actual payment and returns the payment result
 *
 * This function was returning the magic tag values, but it now moves to the pre_processor
 *
 * @param array $config Config array of the processor
 * @param array $form   array of the complete form config structure
 *
 * @return array    array of the transaction result
 */
function cf_eway_rapid_process_payment( $config, $form ) {
}

/**
 * Hook caldera_forms_submit_return_redirect-eway_rapid
 * Filteres the redirect url and substitutes with eWAY auth if needed.
 *
 * @param array  $url       current redirect url
 * @param array  $form      array of the complete form config structure
 * @param array  $config    config array of processor instance
 * @param string $processid unique ID if the processor instance
 *
 * @return array    array of altered transient data
 */
function cf_eway_rapid_redirect_toeway( $url, $form, $config, $processid ) {
	global $transdata;
	// need to check the type to prevent redirect looping, because eway will just redirect if the payment is failed
	if ( $transdata['type'] != 'error'
	     && empty( $transdata['eway_rapid']['checkout'] )
	     && ! empty( $transdata['eway_rapid']['response'] )
	) {
		$response = $transdata['eway_rapid']['response'];

		$errors = $transdata['eway_rapid']['response']->getErrors();

		if ($errors) {
			$request_type = get_class($response);
			$request_type = preg_replace('/^.*\W(\w+?)(Response)?$/', '$1', $request_type);
			$request_type = preg_replace('/\B(\p{Lu})/', ' $1', $request_type);
			$transdata['type'] = 'error';
			$transdata['note'] .= "Could not {$request_type} in eWAY: <ul>";
			foreach($errors as $error) {
				$transdata['note'] .= sprintf('<li>%s: %s</li>', $error, htmlentities(\Eway\Rapid::getMessage($error)));
			}
			$transdata['note'] .= '</ul>';
		} else {
			$url = $response->SharedPaymentUrl;
		}
	}

	return $url;
}

?>
