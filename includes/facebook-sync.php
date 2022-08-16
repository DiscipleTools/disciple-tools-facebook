<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

class Disciple_Tools_Facebook_Sync {
    public function __construct(){
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );

        if ( ! wp_next_scheduled( 'facebook_check_for_new_conversations_cron' ) ) {
            wp_schedule_event( time(), '5min', 'facebook_check_for_new_conversations_cron' );
        }
        add_action( 'facebook_check_for_new_conversations_cron', [ $this, 'facebook_check_for_new_conversations_cron' ] );
    }

    public function add_api_routes() {
        $namespace = "dt_facebook/v1";

        register_rest_route(
            $namespace, "get_conversations_endpoint", [
                'methods'  => "GET",
                'callback' => [ $this, 'get_conversations_endpoint' ],
                'permission_callback' => '__return_true',
            ]
        );
        register_rest_route(
            $namespace, "process_conversations_job", [
                'methods'  => "GET",
                'callback' => [ $this, 'process_conversations_job' ],
                'permission_callback' => '__return_true',
            ]
        );
        register_rest_route(
            $namespace, "count_remaining_conversations_save", [
                'methods'  => "GET",
                'callback' => [ $this, 'count_remaining_conversations_save' ],
                'permission_callback' => '__return_true',
            ]
        );
    }

    public function facebook_check_for_new_conversations_cron(){
        $last_call = get_option( "dt_facebook_last_call", 0 );
        if ( !empty( $last_call ) && time() - $last_call < 2 * MINUTE_IN_SECONDS ){
            //another process is running
            return;
        }
        update_option( "dt_facebook_last_call", time() );

        $facebook_pages = get_option( "dt_facebook_pages", [] );
        foreach ( $facebook_pages as $page_id => $facebook_page ){
            if ( isset( $facebook_page["integrate"] ) && $facebook_page["integrate"] === 1 && !empty( $facebook_page["access_token"] ) ){
                self::get_conversations( $page_id, empty( $facebook_page["reached_the_end"] ) );
                if ( empty( $facebook_page["reached_the_end"] ) ){
                    break;
                }
            }
        }
    }

    public function get_conversations_endpoint( WP_REST_Request $request ){
        $params = $request->get_params();
        if ( isset( $params["page_id"] ) ){
            $data = self::get_conversations( $params["page_id"] );
            $data['jobs'] = wp_queue_count_jobs( 'facebook_conversation' );
            return $data;
        }
        return false;
    }
    public function process_conversations_job( WP_REST_Request $request ){
        wp_queue()->cron()->cron_worker();
        return [
            "count" => wp_queue_count_jobs( 'facebook_conversation' )
        ];
    }
    public function count_remaining_conversations_save( WP_REST_Request $request ){
        return [
            "count" => wp_queue_count_jobs( 'facebook_conversation' )
        ];
    }

    public static function get_conversations( $page_id, $first_sync ){
        dt_write_log( "getting_conversations_for_page: " . $page_id );
        // get conversations until most recent
        $facebook_pages = get_option( "dt_facebook_pages", [] );
        if ( !isset( $facebook_pages[$page_id] ) ){
            return;
        }
        $access_token = $facebook_pages[$page_id]["access_token"];
        $number_to_sync = $first_sync ? 50 : 10;
        $facebook_conversations_url = "https://graph.facebook.com/v14.0/$page_id/conversations?limit=$number_to_sync&fields=link,message_count,messages.limit(500){from,created_time,message},participants,updated_time&access_token=" . $access_token;
        if ( $facebook_pages[$page_id]["next_page"] ){
            $facebook_conversations_url = $facebook_pages[$page_id]["next_page"];
        }
        $conversations_page = Disciple_Tools_Facebook_Api::get_page( $facebook_conversations_url );
        $facebook_pages = get_option( "dt_facebook_pages", [] );
        if ( is_wp_error( $conversations_page ) ){
            if ( $conversations_page->get_error_code() === 190 ){
                 $facebook_pages[$page_id]["integrate"] = 0;
                 update_option( "dt_facebook_pages", $facebook_pages );
            }
            return;
        }

        foreach ( $conversations_page["data"] as $conv ){
            wp_queue()->push( new DT_Save_Facebook_Conversation( $conv, $page_id ), 0, "facebook_conversation" );
        }

        $facebook_pages[$page_id]["next_page"] = null;
        if ( empty( $facebook_pages[$page_id]["reached_the_end"] ) ){
            if ( !empty( $conversations_page["paging"]["next"] ) ){
                $facebook_pages[$page_id]["next_page"] = $conversations_page["paging"]["next"];

            } else {
                $facebook_pages[$page_id]["reached_the_end"] = time();
            }
        } else {
            $oldest_conversation = end( $conversations_page["data"] );
            $oldest_updated_time = strtotime( $oldest_conversation["updated_time"] );
            $latest_conversation = isset( $facebook_pages[$page_id]["latest_conversation"] ) ? $facebook_pages[$page_id]["latest_conversation"] : 0;
            if ( $oldest_updated_time > intval( $latest_conversation ) ) {
                $facebook_pages[$page_id]["next_page"] = $conversations_page["paging"]["next"];
            }
        }

        $facebook_pages[$page_id]["last_api_call"] = time();
        update_option( "dt_facebook_pages", $facebook_pages );
        return [
            "conversations_saved" => sizeof( $conversations_page["data"] ),
            "next" => !empty( $facebook_pages[$page_id]["next_page"] )
        ];
    }

    public static function save_conversation( $page_id, $conversation ){
        $facebook_pages = get_option( "dt_facebook_pages", [] );

        $latest_conversation = isset( $facebook_pages[$page_id]["latest_conversation"] ) ? $facebook_pages[$page_id]["latest_conversation"] : 0;
        if ( strtotime( $conversation["updated_time"] ) >= $latest_conversation ){
            foreach ( $conversation["participants"]["data"] as $participant ) {
                if ( (string) $participant["id"] != $page_id ) {
                    $contact_id = self::update_or_create_contact( $participant, $conversation["updated_time"], $facebook_pages[$page_id], $conversation );
                    if ( $contact_id ){
                        $facebook_pages = get_option( "dt_facebook_pages", [] );
                        $facebook_pages[$page_id]["last_contact_id"] = $contact_id;
                        update_option( "dt_facebook_pages", $facebook_pages );
                        self::update_facebook_messages_on_contact( $contact_id, $conversation, $participant["id"] );
                    }
                }
            }
        }
    }



    /**
     * Find the Facebook id in contacts and update or create the record. Then retrieve any missing messages
     * from the conversation.
     *
     * @param $participant
     * @param $updated_time , the time of the last message
     * @param $page , the Facebook page where the conversation is happening
     *
     * @param $conversation
     *
     * @return int|null|WP_Error contact_id
     */
    private static function update_or_create_contact( $participant, $updated_time, $page, $conversation ) {
        //get page scoped ids available by using a Facebook business manager
        $page_scoped_ids = [ $participant["id"] ];

        $app_id = get_option( "disciple_tools_facebook_app_id", null );
        if ( empty( $app_id ) ){
            Disciple_Tools_Facebook_Api::save_log_message( "missing app_id" );
            return new WP_Error( "app_id", "missing app_id" );
        }

        $contacts = dt_facebook_find_contacts_with_ids( $page_scoped_ids, $participant["id"], $app_id );

        $contact_id   = null;

        if ( sizeof( $contacts ) > 1 ) {
            foreach ( $contacts as $contact_post ) {
                $contact = DT_Posts::get_post( "contacts", $contact_post->ID, true, false );
                if ( isset( $contact["overall_status"]["key"] ) && $contact["overall_status"]["key"] != "closed" ) {
                    $contact_id = $contact["ID"];
                }
            }

            if ( !$contact_id ) {
                $contact_id = $contacts[0]->ID;
            }
        }
        if ( sizeof( $contacts ) == 1 ) {
            $contact_id = $contacts[0]->ID;
        }

        $facebook_url = "https://www.facebook.com/" . $participant["id"];
        if ( $contact_id ) {
            $contact                          = DT_Posts::get_post( "contacts", $contact_id, true, false );
            $facebook_data                    = maybe_unserialize( $contact["facebook_data"] ) ?? [];
            $initial_facebook_data = $facebook_data;
            $facebook_data["last_message_at"] = $updated_time;

            if ( !isset( $facebook_data["page_scoped_ids"] ) ) {
                $facebook_data["page_scoped_ids"] = [];
            }
            if ( !isset( $facebook_data["app_scoped_ids"] ) ) {
                $facebook_data["app_scoped_ids"] = [];
            }
            if ( !isset( $facebook_data["page_ids"] ) ) {
                $facebook_data["page_ids"] = [];
            }
            if ( !isset( $facebook_data["links"] ) ) {
                $facebook_data["links"] = [];
            }
            if ( !isset( $facebook_data["names"] ) ) {
                $facebook_data["names"] = [];
            }
            foreach ( $page_scoped_ids as $id ) {
                if ( !in_array( $id, $facebook_data["page_scoped_ids"] ) ) {
                    $facebook_data["page_scoped_ids"][] = $id;
                }
            }
            if ( !isset( $facebook_data["app_scoped_ids"][ $app_id ] ) ) {
                $facebook_data["app_scoped_ids"][ $app_id ] = $participant["id"];
                $facebook_data["page_ids"][] = $participant["id"];
            }

            if ( !in_array( $page["id"], $facebook_data["page_ids"] ) ) {
                $facebook_data["page_ids"][] = $page["id"];
            }
            if ( !in_array( $participant["name"], $facebook_data["names"] ) ) {
                $facebook_data["names"][] = $participant["name"];
            }
            if ( !in_array( $conversation["link"], $facebook_data["links"] ) ) {
                $facebook_data["links"][] = $conversation["link"];
            }
            $update = [ "facebook_data" => $facebook_data ];
            if ( isset( $contact["overall_status"]["key"], $contact["reason_closed"]["key"] ) && $contact["overall_status"]["key"] === "closed" && $contact["reason_closed"]["key"] === 'no_longer_responding' ){
                $update["overall_status"] = "from_facebook";
            }
            $update["last_message_received"] = strtotime( $updated_time );
            if ( $facebook_data != $initial_facebook_data ) {
                DT_Posts::update_post( "contacts", $contact_id, $update, true, false );
            }
            return $contact_id;
        } else {
            $fields = [
                "title"            => $participant["name"],
                "contact_facebook" => [ [ "value" => $facebook_url ] ],
                "sources"          => [
                    "values" => [
                        [ "value" => $page["id"] ]
                    ]
                ],
                "overall_status"   => "from_facebook",
                "facebook_data"    => [
                    "page_scoped_ids" => $page_scoped_ids,
                    "app_scoped_ids"  => [ $app_id => $participant["id"] ],
                    "page_ids"        => [ $page["id"] ],
                    "names"           => [ $participant["name"] ],
                    "last_message_at" => $updated_time,
                    "links" => [ $conversation["link"] ]
                ],
                "last_message_received" => strtotime( $updated_time )
            ];
            if ( isset( $page["assign_to"] ) ){
                $fields["assigned_to"] = $page["assign_to"];
            }
            $new_contact = DT_Posts::create_post( "contacts", $fields, true, false );
            if ( is_wp_error( $new_contact ) ){
                dt_write_log( "Facebook contact creation failure" );
                dt_write_log( $fields );
                Disciple_Tools_Facebook_Api::save_log_message( $new_contact->get_error_message(), $new_contact->get_error_code() );

                self::dt_facebook_log_email( "Creating a contact failed", "The Facebook integration was not able to create a contact from Facebook. If this persists, please contact support." );
            }
            return $new_contact["ID"];
        }
    }

    public static function dt_facebook_log_email( $subject, $text ){
        $email_address = get_option( "dt_facebook_contact_email" );
        if ( !empty( $email_address ) ){
            dt_send_email( $email_address, $subject, $text );
        }
    }


    public static function update_facebook_messages_on_contact( $contact_id, $conversation, $participant_id ){
        $facebook_data = maybe_unserialize( get_post_meta( $contact_id, "facebook_data", true ) ) ?? [];
        $message_count = $conversation["message_count"];
        $number_of_messages = sizeof( $conversation["messages"]["data"] );
        $saved_number = $facebook_data["message_count"] ?? 0;
        $messages = $conversation["messages"]["data"];

        if ( $message_count != $saved_number && $message_count > $number_of_messages && isset( $conversation["messages"]["paging"]["next"] ) ){
            dt_write_log( "GETTING ALL FACEBOOK MESSAGES" );
            $all_convs = Disciple_Tools_Facebook_Api::get_all_with_pagination( $conversation["messages"]["paging"]["next"] );
            $messages = array_merge( $all_convs, $messages );
        }
        $facebook_data = maybe_unserialize( get_post_meta( $contact_id, "facebook_data", true ) ) ?? [];
        if ( $message_count != $saved_number ){
            foreach ( $messages as $message ){
                $saved_ids = $facebook_data["message_ids"] ?? [];
                if ( !in_array( $message["id"], $saved_ids ) ){
                    $comment = $message["message"];
                    if ( empty( $comment ) ){
                        $comment = "[picture, sticker or emoji]";
                    }
//                    if ( $participant_id == $message["from"]["id"] ){
//                        //is the contact
//                        if ( !isset( $facebook_data["profile_pic"] ) ){
////                            $facebook_data["profile_pic"] = $this->get_participant_profile_pic( $participant_id, $facebook_data, $contact_id );
//                            update_post_meta( $contact_id, "facebook_data", $facebook_data );
//                        }
//                        $image = $facebook_data["profile_pic"] !== false ? $facebook_data["profile_pic"] : "";
//                    } else {
//                        //is the page
//                        $image = "https://graph.facebook.com/" . $message['from']['id'] . "/picture?type=square";
//                    }
                    $add_comment = DT_Posts::add_post_comment( "contacts", $contact_id, $comment, "facebook", [
                        "user_id" => 0,
                        "comment_author" => $message["from"]["name"],
                        "comment_date" => dt_format_date( $message["created_time"], 'Y-m-d H:i:s' ),
                    ], false, true );
                    if ( !is_wp_error( $add_comment ) ){
                        $saved_ids[] = $message["id"];
                        $facebook_data = maybe_unserialize( get_post_meta( $contact_id, "facebook_data", true ) ) ?? [];
                        $facebook_data["message_ids"][] = $message["id"];
                        update_post_meta( $contact_id, "facebook_data", $facebook_data );
                    }
                }
            }
            $facebook_data = maybe_unserialize( get_post_meta( $contact_id, "facebook_data", true ) ) ?? [];
            $facebook_data["message_count"] = $message_count;
            update_post_meta( $contact_id, "facebook_data", $facebook_data );
        }
    }
}

use WP_Queue\Job;
class DT_Save_Facebook_Conversation extends Job {
     /**
     * @var int
     */
    public $conversation;
    public $page_id;

    /**
     * Job constructor.
     */
    public function __construct( $conversation, $page_id ){
        $this->conversation = $conversation;
        $this->page_id = $page_id;

    }

    /**
     * Handle job logic.
     */
    public function handle(){

        dt_write_log( "facebook saving conversation at " . time() );
        Disciple_Tools_Facebook_Sync::save_conversation( $this->page_id, $this->conversation );
    }
}
new Disciple_Tools_Facebook_Sync();
