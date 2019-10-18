<?php
require_once get_template_directory() . '/dt-metrics/charts-base.php';

class DT_Facebook_Metrics extends DT_Metrics_Chart_Base
{

    public $base_slug = 'contacts'; // lowercase
    public $base_title = "Contacts";

    public $title = 'Facebook metrics';
    public $slug = 'facebook'; // lowercase
    public $js_object_name = 'wp_json_object'; // This object will be loaded into the metrics.js file by the wp_localize_script.
    public $js_file_name = 'facebook-metrics.js'; // should be full file name plus extension
    public $deep_link_hash = '#facebook_metrics'; // should be the full hash name. #template_of_hash
    public $permissions = [ 'view_any_contacts', 'view_project_metrics' ];

    public function __construct(){
        parent::__construct();
        if ( !$this->has_permission() ){
            return;
        }
        $url_path = dt_get_url_path();

        // only load scripts if exact url
        if ( "metrics/$this->base_slug/$this->slug" === $url_path ) {
            add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ], 99 );
        }
//        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
    } // End __construct

    public function scripts(){
        wp_enqueue_script( 'dt_'.$this->slug.'_script', trailingslashit( plugin_dir_url( __FILE__ ) ) . $this->js_file_name, [
            'moment',
            'jquery',
            'jquery-ui-core',
            'amcharts-core',
            'amcharts-charts',
        ], filemtime( plugin_dir_path( __FILE__ ) .$this->js_file_name ), true );

        wp_localize_script(
            'dt_'.$this->slug.'_script', $this->js_object_name, [
                'name_key' => $this->slug,
                'root' => esc_url_raw( rest_url() ),
                'plugin_uri' => plugin_dir_url( __DIR__ ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'current_user_login' => wp_get_current_user()->user_login,
                'current_user_id' => get_current_user_id(),
                'stats' => [
                    'message_to_meeting' => $this->time_from_1st_message_to_meeting(),
                    'facebook_messages' => $this->facebook_messages(),
                    'facebook_contacts' => $this->facebook_contacts(),
                    'facebook_assignments' => $this->facebook_assignments(),
                    'facebook_meetings' => $this->facebook_meetings(),
                ],
                'translations' => [
                    "title" => $this->title,
                    "Sample API Call" => __( "Sample API Call" )
                ]
            ]
        );
    }


    private function time_from_1st_message_to_meeting( $start = 0, $end = 0 ) {
        if ( !$end ){
            $end = time();
        }
        //contacts with source = facebook
        //contacts with facebook comments
        //contacts that are not with status "from facebook"
        //contacts that have a seeker_path = "met"
        global $wpdb;
        $results = $wpdb->get_results( $wpdb->prepare( "
            SELECT log.object_id, MIN(hist_time) as met, MIN(c.comment_date) as message
            FROM $wpdb->dt_activity_log log
            JOIN $wpdb->comments c ON ( 
                c.comment_post_ID = log.object_id 
                AND c.comment_type = 'facebook'
                AND c.comment_date >= %s      
                AND c.comment_date < %s      
            )
            WHERE object_type = 'contacts' AND meta_key = 'seeker_path' AND meta_value = 'met'
            GROUP BY object_id
        ", dt_format_date( $start, 'Y-m-d' ), dt_format_date( $end, 'Y-m-d' ) ), ARRAY_A );

        $times = [];
        foreach ( $results as $r ){
            $diff = $r['met'] - strtotime( $r['message'] );
            if ( $r['met'] > strtotime( $r['message'] ) ){
                $times[] = (int) ( $diff / ( 60 * 60 * 24 ) );
            }
        }
        $ordered = array_count_values( $times );
        ksort( $ordered );
        $return = [];
        foreach ( $ordered as $days => $occurrences ) {
            $return[] = [
                'd' => $days,
                'occ' => $occurrences
            ];
        }
        return $return;
    }

    private function facebook_messages(){
        global $wpdb;
        $results = $wpdb->get_results( "
            SELECT  DATE(comment_date) date, COUNT(DISTINCT comment_ID) count
            FROM    $wpdb->comments
            WHERE comment_type = 'facebook'
            GROUP   BY  DATE(comment_date)
        ", ARRAY_A);
        return $results;
    }

    private function facebook_contacts(){
        global $wpdb;
        $results = $wpdb->get_results( "
            SELECT DATE(post_date) date, COUNT(DISTINCT posts.ID) count
            FROM $wpdb->posts as posts
            INNER JOIN $wpdb->comments ON ( comment_type = 'facebook' AND posts.ID = comment_post_ID )   
            WHERE post_type = 'contacts'
            GROUP BY DATE( post_date )  
        ", ARRAY_A);

        return $results;
    }

    private function facebook_assignments(){
        global $wpdb;
        $results = $wpdb->get_results( "
            SELECT from_unixtime(hist_time, '%Y-%m-%d')  date, COUNT(DISTINCT object_id) count
            FROM $wpdb->dt_activity_log as log
            INNER JOIN $wpdb->comments ON ( comment_type = 'facebook' AND log.object_id = comment_post_ID )   
            WHERE object_type = 'contacts'
            AND meta_key = 'overall_status' 
            AND ( meta_value = 'assigned' OR meta_value = 'active' ) 
            GROUP BY from_unixtime(hist_time, '%Y-%m-%d')  
        ", ARRAY_A);

        return $results;
    }
    private function facebook_meetings(){
        global $wpdb;
        $results = $wpdb->get_results( "
            SELECT from_unixtime(hist_time, '%Y-%m-%d')  date, COUNT(DISTINCT object_id) count
            FROM $wpdb->dt_activity_log as log
            INNER JOIN $wpdb->comments ON ( comment_type = 'facebook' AND log.object_id = comment_post_ID )   
            WHERE object_type = 'contacts'
            AND meta_key = 'seeker_path' 
            AND ( meta_value = 'met' ) 
            GROUP BY from_unixtime(hist_time, '%Y-%m-%d')  
        ", ARRAY_A);

        return $results;
    }
}
new DT_Facebook_Metrics();
