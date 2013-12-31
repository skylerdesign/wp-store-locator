<?php
add_action( 'wp_ajax_store_search', 'wpsl_store_search' );
add_action( 'wp_ajax_nopriv_store_search', 'wpsl_store_search' );
            
/**
 * Handle the ajax store search on the frontend.
 * 
 * Search for stores that fall within the selected distance range. 
 * This happens by calculating the distance between the latlng of the starting point 
 * and the latlng from the stores.
 * 
 * @since 1.0
 * @return json The list of stores in $store_results that matches with the request or false if the query returns no results
 */
function wpsl_store_search() {
    
    global $wpdb;

    $options       = get_option( 'wpsl_settings' );
    $distance_unit = ( $options['distance_unit'] == 'km' ) ? '6371' : '3959'; 
    $allowed_html  = '';
    
    /* If no max results is set, we get the default value from the settings. 
     * The only situation when it can be empty, is when the "Show the limit results dropdown" 
     * checkbox is unchecked on the settings page.
     */
    if ( !$_GET['max_results'] ) {
        $max_results = get_default_list_value( $type = 'max_results' );   
    } else {
        $max_results = $_GET['max_results'];
    }
    
    $result = $wpdb->get_results( 
                    $wpdb->prepare(
                            "
                            SELECT *, ( $distance_unit * acos( cos( radians( %s ) ) * cos( radians( lat ) ) * cos( radians( lng ) - radians( %s ) ) + sin( radians( %s ) ) * sin( radians( lat ) ) ) ) 
                            AS distance FROM $wpdb->wpsl_stores
                            WHERE active = 1
                            HAVING distance < %d 
                            ORDER BY distance LIMIT 0 ,%d
                            ", 
                            $_GET['lat'],
                            $_GET['lng'],
                            $_GET['lat'],
                            $_GET['radius'],
                            $max_results
                    )
                );
    
    if ( $result === false ) {
		wp_send_json_error();
    } else {
		$store_results = array();

		foreach ( $result as $k => $v ) {
			/* If we have a valid thumb id, get the src */
			if ( absint ( $result[$k]->thumb_id ) ) {
				$thumb_src = wp_get_attachment_image_src( $result[$k]->thumb_id );
				$result[$k]->thumb_src = $thumb_src[0];
			} else {
				$result[$k]->thumb_src = '';
			}

			/* Sanitize the results before they are returned */
			$store_results[] = array (
				'id'          => wp_kses( $result[$k]->wpsl_id, $allowed_html ),
				'store'       => wp_kses( $result[$k]->store, $allowed_html ),
				'street'      => wp_kses( $result[$k]->street, $allowed_html ),
				'city'        => wp_kses( $result[$k]->city, $allowed_html ),
				'state'       => wp_kses( $result[$k]->state, $allowed_html ),
				'zip'         => wp_kses( $result[$k]->zip, $allowed_html ),
				'country'     => wp_kses( $result[$k]->country, $allowed_html ),	
				'distance'    => wp_kses( $result[$k]->distance, $allowed_html ),
				'lat'         => wp_kses( $result[$k]->lat, $allowed_html ),
				'lng'         => wp_kses( $result[$k]->lng, $allowed_html ),
				'description' => wpautop( wp_kses( $result[$k]->description, $allowed_html ) ),	
				'phone'       => wp_kses( $result[$k]->phone, $allowed_html ),	
				'fax'         => wp_kses( $result[$k]->fax, $allowed_html ),
				'email'       => wp_kses( $result[$k]->email, $allowed_html ),	
				'hours'       => wpautop( wp_kses( $result[$k]->hours, $allowed_html ) ),
				'url'         => esc_url( $result[$k]->url, $allowed_html ),
    			'thumb'       => esc_url( $result[$k]->thumb_src, $allowed_html )	
			);
		}

		wp_send_json( $store_results );	
    }

    die();
}
            
/**
 * Get the default selected value for a dropdown
 * 
 * @since 1.0
 * @param string $type The request list type
 * @return string $response The default list value
 */
function get_default_list_value( $type ) {

    $settings = get_option( 'wpsl_settings' );
    $list_values = explode( ',', $settings[$type] );

    foreach ( $list_values as $k => $list_value ) {

        /* The default radius has a () wrapped around it, so we check for that and filter out the () */
        if ( strpos( $list_value, '(' ) !== false ) {
            $response = filter_var( $list_value, FILTER_SANITIZE_NUMBER_INT );
            break;
        }
    }	

    return $response;		
}