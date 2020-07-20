<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

/*
 * Hooks that are always available on any request.
 */

add_filter( "dt_search_extra_post_meta_fields", "dt_add_fields_in_dt_search" );
add_filter( "dt_custom_fields_settings", "dt_facebook_fields", 10, 2 );
if ( ! wp_next_scheduled( 'daily_facebook_cron' ) ) {
    wp_schedule_event( strtotime( 'today 1am' ), 'daily', 'daily_facebook_cron' );
}
add_action( 'daily_facebook_cron', "dt_facebook_daily_cron" );
add_filter( "dt_record_picture", "fb_dt_record_picture", 10, 3 );

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
            $fields["last_message_received"] = [
                "name"    => __( "Last Message", "dt_facebook" ),
                "type"    => "date",
                "default" => '',
                "hidden" => true
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

function dt_facebook_daily_cron(){
    global $wpdb;
    $months = get_option( "dt_facebook_close_after_months", "3" );
    if ( $months === "0" ){
        return;
    }

    $facebook_to_close = $wpdb->get_results(  $wpdb->prepare( "
        SELECT pm.post_id FROM $wpdb->postmeta pm 
        INNER JOIN $wpdb->postmeta pm1 ON ( pm.post_id = pm1.post_id AND pm1.meta_key = 'last_modified' )
        WHERE pm.meta_key = 'overall_status' AND pm.meta_value = 'from_facebook'
        AND pm1.meta_value < %d
        LIMIT 300
    ", time() - 24 * 3600 * ( 30 * (int) $months ) ), ARRAY_A );
    $my_id = get_current_user_id();
    wp_set_current_user( 0 );
    $current_user = wp_get_current_user();
    $current_user->display_name = "Facebook Extension";
    foreach ( $facebook_to_close as $post ){
        DT_Posts::update_post( "contacts", $post['post_id'], [
            "overall_status" => 'closed',
            "reason_closed" => 'no_longer_responding'
        ], true, false );
        DT_Posts::add_post_comment( "contacts",
            $post['post_id'],
            "This contact was automatically closed due to inactivity.",
            "comment",
            [
                "user_id" => 0,
                "comment_author" => __( "Facebook Extension", 'disciple_tools' )
            ],
            false,
            true
        );
    }
    wp_set_current_user( $my_id );
}

function fb_dt_record_picture( $picture, $post_type, $contact_id ){
    if ( $post_type === "contacts" ){
        $post = DT_Posts::get_post( $post_type, $contact_id );
        if ( isset( $post["facebook_data"]["profile_pic"] ) ){
            if ( $post["facebook_data"]["profile_pic"] === false ){
                return $picture;
            } else {
                $picture = $post["facebook_data"]["profile_pic"];
            }
        } elseif ( isset( $post["facebook_data"]["page_scoped_ids"] ) && !empty( $post["facebook_data"]["page_scoped_ids"] ) ) {
            $facebook_id = $post["facebook_data"]["page_scoped_ids"][0];
            $profile_pic = Disciple_Tools_Facebook_Integration::instance()->get_participant_profile_pic( $facebook_id, $post["facebook_data"], $contact_id );
            if ( !empty( $profile_pic ) ){
                $picture = $profile_pic;
            }
        }
    }
    return $picture;
}