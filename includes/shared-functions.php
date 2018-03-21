<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly
/**
 * Get all the records if we don't already have them.
 *
 * @param  $url             , the orginal url or the paging next
 * @param  $current_records , the records (messages) gotten with the initial api call
 *
 * @return array, all the records
 */
function dt_facebook_get_object_with_paging( $url, $current_records = [] ) {
    $response = wp_remote_get( $url );
    $more_records = json_decode( $response["body"], true );
    if ( !isset( $more_records["data"] ) ){
        //@todo return error
    }
    $current_records = array_map( "unserialize", array_unique( array_map( "serialize", array_merge( $current_records, $more_records["data"] ) ) ) );

    if ( !isset( $more_records["paging"] ) || !isset( $more_records["paging"]["next"] ) ) {
        return $current_records;
    } else {
        return dt_facebook_get_object_with_paging( $more_records["paging"]["next"], $current_records );
    }
}

function dt_facebook_api( $endpoint, $main_id, $access_token ){
    switch ($endpoint) {
        case "page_labels":
            $uri_for_page_labels = "https://graph.facebook.com/v2.12/" . $main_id . "/labels?fields=name&access_token=" . $access_token;
            return dt_facebook_get_object_with_paging( $uri_for_page_labels );
            break;
        case "label_users":
            $uri_for_page_labels = "https://graph.facebook.com/v2.12/" . $main_id . "/users?&access_token=" . $access_token;
            return dt_facebook_get_object_with_paging( $uri_for_page_labels );
            break;
        default:
            return [];
    }
}

function dt_facebook_find_contacts_with_ids( $ids ){
    $meta_query = [
        'relation' => "OR",
    ];
    foreach ( $ids as $id ){
        $meta_query[] = [
            'key' => 'facebook_data',
            'value' => $id,
            'compare' => 'LIKE'
        ];
    }

    $query = new WP_Query(
        [
            'post_type'  => 'contacts',
            'meta_query' => $meta_query
        ]
    );

    return $query->get_posts();
}
