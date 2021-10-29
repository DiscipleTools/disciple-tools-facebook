<?php

/**
 * Disciple.Tools
 *
 * @class   Disciple_Tools_Reports_Integrations
 * @version 0.1.0
 * @since   0.1.0
 * @package Disciple_Tools
 *
 */

if ( ! defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly

/**
 * Class Disciple_Tools_Reports_Integrations
 */
class Disciple_Tools_Facebook_Reports {

    /**
     * Constructor function.
     *
     * @access public
     * @since  0.1.0
     */
    public function __construct() {
//        add_action( "dt_async_dt_facebook_stats", [ $this, "build_all_facebook_reports_async" ] );
//        add_action( 'build_disciple_tools_reports', [ $this, 'register_stats_cron' ] );
    } // End __construct()


    public function register_stats_cron(){
        do_action( "dt_facebook_stats" );
    }
    /**
     * @param $url, the facebook url to query for the next stats
     * @param $since, how far back to go to get stats
     * @param $page_id
     * @return array()
     */
    private static function get_facebook_insights_with_paging( $url, $since, $page_id ){
        $request = wp_remote_get( $url );
        if ( !is_wp_error( $request ) ) {
            $body = wp_remote_retrieve_body( $request );
            $data = json_decode( $body );
            if ( !empty( $data ) ) {
                if ( isset( $data->error ) ) {
                    return $data->error->message;
                } elseif ( isset( $data->data ) ) {
                    //create reports for each day in the month
                    $first_value = $data->data[0]->values[0];
                    $has_value = isset( $first_value->value );
                    $earliest = gmdate( 'Y-m-d', strtotime( $first_value->end_time ) );

                    if ( $since <= $earliest && isset( $data->paging->previous ) && $has_value ){
                        $next_page = self::get_facebook_insights_with_paging( $data->paging->previous, $since, $page_id );
                        return array_merge( $data->data, $next_page );
                    } else {
                        return $data->data;
                    }
                }
            }
        }
        return [];
    }

    /**
     * Facebook report data
     * Returns a prepared array for the dt_report_insert()
     *
     * @see    Disciple_Tools_Reports_API
     *
     * @param $date_of_last_record
     * @param $facebook_page
     *
     */
    public static function get_and_save_stats_data( $date_of_last_record, $facebook_page ) {
        $date_of_last_record = gmdate( 'Y-m-d', strtotime( $date_of_last_record ) );
        $since = gmdate( 'Y-m-d', strtotime( '-60 days' ) );
        if ( $date_of_last_record > $since ){
            $since = $date_of_last_record;
        }
        if ( isset( $facebook_page["rebuild"] ) && $facebook_page["rebuild"] == true ){
            $since = gmdate( 'Y-m-d', strtotime( '-90 days' ) );
            $date_of_last_record = gmdate( 'Y-m-d', strtotime( '-1 years' ) );
        }
        $page_reports = [];

        if ( isset( $facebook_page["report"] ) && $facebook_page["report"] == 1 ){
            $access_token = $facebook_page["access_token"];
            $url = "https://graph.facebook.com/v2.12/" . $facebook_page["id"] . "/insights?metric=";
            $url .= "page_fans";
            $url .= ",page_engaged_users";
            $url .= ",page_admin_num_posts";
            $url .= "&since=" . $since;
            $url .= "&until=" . gmdate( 'Y-m-d', strtotime( 'tomorrow' ) );
            $url .= "&access_token=" . $access_token;

            $all_page_data = self::get_facebook_insights_with_paging( $url, $date_of_last_record, $facebook_page["id"] );

            $month_metrics = [];
            foreach ( $all_page_data as $metric ){
                if ( $metric->name === "page_engaged_users" && $metric->period === "day" ){
                    foreach ( $metric->values as $day ){
                        $month_metrics[ $day->end_time ]['page_engagement'] = isset( $day->value ) ? $day->value : 0;
                    }
                }
                if ( $metric->name === "page_fans" ){
                    foreach ( $metric->values as $day ){
                        $month_metrics[ $day->end_time ]['page_likes_count'] = isset( $day->value ) ? $day->value : 0;
                    }
                }
                if ( $metric->name === "page_admin_num_posts" && $metric->period === "day" ){
                    foreach ( $metric->values as $day ){
                        $month_metrics[ $day->end_time ]['page_post_count'] = isset( $day->value ) ? $day->value : 0;
                    }
                }
            }
            foreach ( $month_metrics as $day => $value ){
                array_push(
                    $page_reports, [
                        'report_date' => gmdate( 'Y-m-d h:m:s', strtotime( $day ) ),
                        'report_source' => "Facebook",
                        'report_subsource' => $facebook_page["id"],
                        'meta_input' => $value,
                    ]
                );
            }

            if ( $facebook_page["rebuild"] ){
                self::disable_rebuild_flag_on_facebook_page( $facebook_page["id"] );
            }
        }

        // Request dates needed for reporting (loop)
        foreach ( $page_reports as $report ) {
            // Insert Reports
            dt_report_insert( $report );
        }
    }


    /**
     * Update the flag for rebuilding the reports for a page.
     *
     * @param $page_id
     */
    public static function disable_rebuild_flag_on_facebook_page( $page_id ){
        $facebook_pages = get_option( "dt_facebook_pages", [] );
        $facebook_pages[ $page_id ]["rebuild"] = false;
        update_option( "dt_facebook_pages", $facebook_pages );
    }

        /**
     * Build all Facebook reports
     * This defines the outstanding days of reports needed to be logged (one day or multiple days), and
     * then loops those days through the Disciple_Tools_Reports_Integrations class. These loops return success or error
     * reports that are then logged to the reports database as a history of update and debugging.
     */
    public function build_all_facebook_reports_async() {
        //get the facebook pages and access tokens from the settings
        $facebook_pages = get_option( "dt_facebook_pages", [] );
//        foreach ( $facebook_pages as $page_id => $facebook_page ) {
//            $last_facebook_report = Disciple_Tools_Reports_API::get_last_record_of_source_and_subsource( 'Facebook', $page_id );
//            if ( $last_facebook_report && isset( $last_facebook_report->report_date ) ) {
//                $date_of_last_record = gmdate( 'Y-m-d', strtotime( $last_facebook_report->report_date ) );
//            } else {
//                //set to yesterday to get today's report
//                $date_of_last_record = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
//            }
//
//            self::get_and_save_stats_data( $date_of_last_record, $facebook_page );
//        }
    }

}
