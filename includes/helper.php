<?php
  global $customer_fields;
  global $shipping_fields;

  $customer_fields = array(
 	 "keys"   => array(
 		 "customer_first_name",
 		 "customer_last_name",
 		 "customer_street1",
 		 "customer_street2",
 		 "customer_city",
 		 "customer_state",
 		 "customer_postal_code",
 		 "customer_country",
 		 "customer_phone",
 		 "customer_email",
 	 ),
 	 "labels" => array(
 		 "First Name",
 		 "Last Name",
 		 "Street1",
 		 "Street2",
 		 "City",
 		 "State",
 		 "Postal Code",
 		 "Country",
 		 "Phone",
 		 "Email",
 	 ),
 	 "ewaykeys" => array(
 		 "FirstName",
 		 "LastName",
 		 "Street1",
 		 "Street2",
 		 "City",
 		 "State",
 		 "PostalCode",
 		 "Country",
 		 "Phone",
 		 "Email",
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

/**
 * Helper method to map fields values to processor
 *
 * @since 0.4
 *
 * @param array $config The processor settings
 * @param array $form The form settings
 * @param array $form_values The submitted form values
 * @param string $processor The processor key, only necessary for the Contact processor class
 * @return array $form_values
 */
function cf_eway_map_fields_to_processor( $config, $form, &$form_values, $processor = null ){
	foreach ( ( $processor ? $config[$processor] : $config ) as $civi_field => $field_id ) {
		if ( ! empty( $field_id ) ) {

			if ( is_array( $field_id ) ) continue;

			// do bracket magic tag
			if ( strpos( $field_id, '{' ) !== false ) {
				$mapped_field = Caldera_Forms_Magic_Doer::do_bracket_magic( $field_id, $form, NULL, NULL, NULL );

			} elseif ( strpos( $field_id, '%' ) !== false && substr_count( $field_id, '%' ) > 2 ) {

				// multiple fields mapped
				// explode and remove empty indexes
				$field_slugs = array_filter( explode( '%', $field_id ) );

				$mapped_fields = [];
				foreach ( $field_slugs as $k => $slug ) {
					$field = Caldera_Forms::get_field_by_slug( $slug, $form );
					$mapped_fields[] = Caldera_Forms::get_field_data( $field['ID'], $form );
				}

				$mapped_fields = array_filter( $mapped_fields );
				// expect one value, return first value
				$mapped_field = reset( $mapped_fields );

			} elseif (strpos( $field_id, 'fld_' ) !== false) {

				// Get field by ID or slug
				$field = $mapped_field =
					Caldera_Forms_Field_Util::get_field( $field_id, $form ) ?
						Caldera_Forms_Field_Util::get_field( $field_id, $form ) :
						Caldera_Forms::get_field_by_slug(str_replace( '%', '', $field_id ), $form );

				// Get field data
				$mapped_field = Caldera_Forms::get_field_data( $mapped_field['ID'], $form );

				// if not a magic tag nor field id, must be a fixed value
				// $mapped_field = $mapped_field ? $mapped_field : $field_id;

			} else {
				$mapped_field = $field_id;
			}

			if( ! empty( $mapped_field ) || $mapped_field === '0'){

				if ( $processor ) {
					$form_values[$processor][$civi_field] = $mapped_field;
				} else {
					$form_values[$civi_field] = $mapped_field;
				}
			}
		}
	}

	return $form_values;
}
