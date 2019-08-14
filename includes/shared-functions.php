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

function dt_facebook_find_contacts_with_ids( array $page_scoped_ids, string $app_scoped_id = null, string $app_id = null ){
    if ( sizeof( $page_scoped_ids ) === 0 && ( empty( $app_scoped_id ) || empty( $app_id ) ) ){
        return [];
    }
    $meta_query = [
        'relation' => "OR",
    ];
    $ids = array_merge( $page_scoped_ids, [ $app_scoped_id ] );
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

    $posts = $query->get_posts();
    $matching = [];
    $matching_ids = [];
    foreach ( $posts as $post ) {
        $facebook_data = get_post_meta( $post->ID, "facebook_data", true );
        foreach ( $page_scoped_ids as $page_scoped_id ){
            if ( isset( $facebook_data["page_scoped_ids"] ) && in_array( $page_scoped_id, $facebook_data["page_scoped_ids"] ) ){
                if ( !in_array( $post->ID, $matching_ids )){
                    $matching[] = $post;
                    $matching_ids[] = $post->ID;
                }
            }
        }
        if ( isset( $facebook_data["app_scoped_ids"] ) && !empty( $app_scoped_id ) && !empty( $app_id ) ){
            if ( ( isset( $facebook_data["app_scoped_ids"][$app_id] ) && $facebook_data["app_scoped_ids"][$app_id] == $app_scoped_id ) ){
                if ( !in_array( $post->ID, $matching_ids )){
                    $matching[] = $post;
                    $matching_ids[] = $post->ID;
                }
            }
        }
    }
    return $matching;
}
