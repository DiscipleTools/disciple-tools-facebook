<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

/*
 * Hooks that are always available on any request.
 */

add_filter( "dt_search_extra_post_meta_fields", "add_fields_in_dt_search" );


function add_fields_in_dt_search( $fields ){
    $fields[] = "facebook_data";
    return $fields;
}

