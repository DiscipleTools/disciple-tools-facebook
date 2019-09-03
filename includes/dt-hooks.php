<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

/*
 * Hooks that are always available on any request.
 */

add_filter( "dt_search_extra_post_meta_fields", "dt_add_fields_in_dt_search" );
add_filter( "dt_custom_fields_settings", "dt_facebook_fields", 10, 2 );

function dt_add_fields_in_dt_search( $fields ){
    $fields[] = "facebook_data";
    return $fields;
}

function dt_facebook_fields( array $fields, string $post_type = "" ) {
    //check if we are dealing with a contact
    if ( $post_type === "contacts" ) {
        //check if the language field is already set
        if ( !isset( $fields["facebook_data"] ) ) {
            //define the language field
            $fields["facebook_data"] = [
                "name"    => __( "Facebook Ids", "dt_facebook" ),
                "type"    => "array",
                "default" => []
            ];
        }
        if ( !isset( $fields["reason_closed"]["default"]["closed_from_facebook"] ) ) {
            $fields["reason_closed"]["default"]["closed_from_facebook"] = __( "Closed from Facebook", "dt_facebook" );
        }
        if ( !isset( $fields["overall_status"]["default"]["from_facebook"] ) ) {
            $fields["overall_status"]["default"]["from_facebook"] = __( "From Facebook", "dt_facebook" );
        }
    }
    //don't forget to return the update fields array
    return $fields;
}

