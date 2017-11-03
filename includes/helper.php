<?php
  global $customer_fields;
  global $shipping_fields;

  $customer_fields = array(
 	 "keys"   => array(
     "customer_title",
 		 "customer_first_name",
 		 "customer_last_name",
 		 "customer_street1",
 		 "customer_street2",
 		 "customer_city",
 		 "customer_state",
 		 "customer_postal_code",
 		 "customer_country",
 		 "customer_phone",
 		 "customer_mobile",
 		 "customer_email",
 		 "customer_url",
 	 ),
 	 "labels" => array(
 		 "Title",
 		 "First Name",
 		 "Last Name",
 		 "Street1",
 		 "Street2",
 		 "City",
 		 "State",
 		 "Postal Code",
 		 "Country",
 		 "Phone",
 		 "Mobile",
 		 "Email",
 		 "Url"
 	 ),
 	 "ewaykeys" => array(
 		 "Title",
 		 "FirstName",
 		 "LastName",
 		 "Street1",
 		 "Street2",
 		 "City",
 		 "State",
 		 "PostalCode",
 		 "Country",
 		 "Phone",
 		 "Mobile",
 		 "Email",
 		 "Url"
 	 )
 );

 $shipping_fields = array(
  "keys"   => array(
    "shipping_first_name",
    "shipping_last_name",
    "shipping_street1",
    "shipping_street2",
    "shipping_city",
    "shipping_state",
    "shipping_country",
    "shipping_postal_code",
    "shipping_phone",
    "shipping_email",
  ),
  "labels" => array(
    "First Name",
    "Last Name",
    "Street1",
    "Street2",
    "City",
    "State",
    "Country",
    "Postal Code",
    "Phone",
    "Email"
  ),
  "ewaykeys" => array(
    "FirstName",
    "LastName",
    "Street1",
    "Street2",
    "City",
    "State",
    "Country",
    "PostalCode",
    "Phone",
    "Email"
  )
);
?>
