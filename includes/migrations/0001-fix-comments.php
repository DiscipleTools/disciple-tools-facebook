<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly

/**
 * Class DT_Facebook_Migration_0001
 */
class DT_Facebook_Migration_0001 extends DT_Facebook_Migration {
    /**
     * @throws \Exception  Got error when creating table $name.
     */
    public function up() {
        //find all facebook contacts updated after 01 jan 2022
        //create job for each contact
        global $wpdb;
        $query_res = $wpdb->get_results( "
            SELECT post_id
            FROM $wpdb->postmeta pm
            WHERE pm.meta_key = 'last_message_received'
            AND pm.meta_value > 1641054023
            AND pm.meta_value < 1648488044
        ", ARRAY_A );

        $missmatch_detected = false;
        foreach ( $query_res as $res ){
            dt_write_log( "DT_Facebook_Comments_Fix " . $res["post_id"] );

            $facebook_data = get_post_meta( $res["post_id"], "facebook_data", true );
            if ( empty( $facebook_data ) ){
                continue;
            }
            $facebook_data = maybe_unserialize( $facebook_data );
            $facebook_comment_count = $wpdb->get_var( $wpdb->prepare( "
                SELECT COUNT(comment_ID)
                FROM $wpdb->comments c
                WHERE c.comment_post_ID = %s
            ", $res["post_id"] ) );
            if ( (int) $facebook_data["message_count"] !== (int) $facebook_comment_count ){
                $facebook_data["message_count"] = $facebook_comment_count;
                $facebook_data["message_ids"] = array_slice( $facebook_data["message_ids"], 0, (int) $facebook_comment_count );
                $missmatch_detected = true;
            }
            update_post_meta( $res["post_id"], 'facebook_data', $facebook_data );
        }
        if ( $missmatch_detected ){
            dt_write_log( "DT_Facebook_Reset_Sync " . 1641054023 );
            $facebook_pages = get_option( "dt_facebook_pages", [] );

            foreach ( $facebook_pages as $id => $facebook_page ){
                if ( isset( $facebook_page["latest_conversation"] ) && $facebook_page["latest_conversation"] > 1641054023 ){
                    $facebook_pages[$id]["latest_conversation"] = 1641054023;
                }
            }
            update_option( "dt_facebook_pages", $facebook_pages );

        }
    }

    /**
     * @throws \Exception  Got error when dropping table $name.
     */
    public function down() {
    }

    /**
     * Test function
     */
    public function test() {
    }

}
