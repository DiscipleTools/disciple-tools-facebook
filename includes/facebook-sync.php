<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

class Disciple_Tools_Facebook_Sync {
    public function __construct(){
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );


        add_action( 'facebook_check_for_new_conversations_cron', [ $this, 'facebook_check_for_new_conversations_cron' ] );
    }

    public function add_api_routes() {
        $namespace = 'dt_facebook/v1';

        register_rest_route(
            $namespace, 'get_conversations_endpoint', [
                'methods'  => 'GET',
                'callback' => [ $this, 'get_conversations_endpoint' ],
                'permission_callback' => '__return_true',
            ]
        );
        register_rest_route(
            $namespace, 'process_conversations_job', [
                'methods'  => 'GET',
                'callback' => [ $this, 'process_conversations_job' ],
                'permission_callback' => '__return_true',
            ]
        );
        register_rest_route(
            $namespace, 'count_remaining_conversations_save', [
                'methods'  => 'GET',
                'callback' => [ $this, 'count_remaining_conversations_save' ],
                'permission_callback' => '__return_true',
            ]
        );
        register_rest_route(
            $namespace, 'dt-public/cron', [
                'methods'  => 'GET',
                'callback' => [ $this, 'cron_trigger' ],
                'permission_callback' => '__return_true',
            ]
        );
    }

    public function cron_trigger(){
//        $this->facebook_check_for_new_conversations_cron();
//        wp_queue()->cron()->cron_worker();
    }

    public function facebook_check_for_new_conversations_cron(){
        $last_call = get_option( 'dt_facebook_last_call', 0 );
        if ( !empty( $last_call ) && time() - intval( $last_call ) < 2 * MINUTE_IN_SECONDS ){
            //another process is running
            return;
        }
        update_option( 'dt_facebook_last_call', time() );

        $facebook_pages = get_option( 'dt_facebook_pages', [] );
        $sync = false;
        foreach ( $facebook_pages as $page_id => $facebook_page ){
            if ( isset( $facebook_page['integrate'] ) && $facebook_page['integrate'] === 1 && !empty( $facebook_page['access_token'] ) ){
                $sync = true;
                self::get_conversations( $page_id, empty( $facebook_page['reached_the_end'] ) );
                if ( empty( $facebook_page['reached_the_end'] ) ){
                    break;
                }
            }
        }
        if ( ( empty( $facebook_pages ) || $sync === false ) && wp_next_scheduled( 'facebook_check_for_new_conversations_cron' ) ){
            wp_clear_scheduled_hook( 'facebook_check_for_new_conversations_cron' );
        }
    }

    public function get_conversations_endpoint( WP_REST_Request $request ){
        $params = $request->get_params();
        if ( isset( $params['page_id'] ) ){
            $data = self::get_conversations( $params['page_id'], true, true );
            $data['jobs'] = wp_queue_count_jobs( 'facebook_conversation' );
            return $data;
        }
        return false;
    }
    public function process_conversations_job( WP_REST_Request $request ){
        $count = wp_queue_count_jobs( 'facebook_conversation' );
        wp_queue()->cron()->cron_worker();
        $count_after = wp_queue_count_jobs( 'facebook_conversation' );
        return [
            'count' => $count_after,
            'cron_stuck' => $count === $count_after
        ];
    }
    public function count_remaining_conversations_save( WP_REST_Request $request ){
        return [
            'count' => wp_queue_count_jobs( 'facebook_conversation' )
        ];
    }

    public static function get_conversations( $page_id, $first_sync = false, $skip_save = false ){
        // get conversations until most recent
        $facebook_pages = get_option( 'dt_facebook_pages', [] );
        if ( !isset( $facebook_pages[$page_id] ) ){
            return;
        }
        dt_write_log( 'getting_conversations_for_page: ' . $page_id );
        Disciple_Tools_Facebook_Api::save_log_message( 'Getting conversations for page: ' . $facebook_pages[$page_id]['name'], 'log' );
        $access_token = $facebook_pages[$page_id]['access_token'];
        $number_to_sync = $first_sync ? 50 : 10;
        $facebook_conversations_url = "https://graph.facebook.com/v14.0/$page_id/conversations?limit=$number_to_sync&fields=link,message_count,messages.limit(500){from,created_time,message},participants,updated_time&access_token=" . $access_token;
        if ( !empty( $facebook_pages[$page_id]['next_page'] ) ){
            $facebook_conversations_url = $facebook_pages[$page_id]['next_page'];
        }
        $conversations_page = Disciple_Tools_Facebook_Api::get_page( $facebook_conversations_url );
        $facebook_pages = get_option( 'dt_facebook_pages', [] );
        if ( is_wp_error( $conversations_page ) ){
            if ( $conversations_page->get_error_code() !== 190 ){
                if ( 'The access token could not be decrypted' === $conversations_page['error']['message'] ){
                    $facebook_pages[$page_id]['integrate'] = 0;
                    update_option( 'dt_facebook_pages', $facebook_pages );
                }
            }
            return;
        }

        $latest_conversation = $facebook_pages[$page_id]['latest_conversation'] ?? 0;
        $number_of_convs_to_save = 0;
        foreach ( $conversations_page['data'] as $conv ){
            if ( isset( $conv['updated_time'] ) && strtotime( $conv['updated_time'] ) >= $latest_conversation && ( empty( $facebook_pages[$page_id]['new_latest_conversation_id'] ) || $conv['messages']['data'][0]['id'] !== $facebook_pages[$page_id]['new_latest_conversation_id'] ) ){
                $number_of_convs_to_save++;
                wp_queue()->push( new DT_Save_Facebook_Conversation( $conv, $page_id ), 0, 'facebook_conversation' );
            }
        }

        $oldest_conversation = end( $conversations_page['data'] );
        $oldest_updated_time = strtotime( $oldest_conversation['updated_time'] );
        $facebook_pages[$page_id]['next_page'] = null;
        $new_latest_conversation = isset( $conversations_page['data'][0]['updated_time'] ) ? strtotime( $conversations_page['data'][0]['updated_time'] ) : 0;
        $new_latest_conversation_id = isset( $conversations_page['data'][0]['messages']['data'][0]['id'] ) ? $conversations_page['data'][0]['messages']['data'][0]['id'] : 0;
        if ( empty( $facebook_pages[$page_id]['reached_the_end'] ) ){
            if ( empty( $facebook_pages[$page_id]['saved_latest'] ) ){
                $facebook_pages[$page_id]['saved_latest'] = $new_latest_conversation;
            }
            if ( !empty( $conversations_page['paging']['next'] ) ){
                $facebook_pages[$page_id]['next_page'] = $conversations_page['paging']['next'];
            } else {
                $facebook_pages[$page_id]['reached_the_end'] = time();
                $facebook_pages[$page_id]['latest_conversation'] = $facebook_pages[$page_id]['saved_latest'];
                $facebook_pages[$page_id]['saved_latest'] = null;
            }
        } else {
            if ( $oldest_updated_time > intval( $latest_conversation ) && !empty( $conversations_page['paging']['next'] ) ) {
                $facebook_pages[$page_id]['next_page'] = $conversations_page['paging']['next'];
                $facebook_pages[$page_id]['saved_latest'] = max( $new_latest_conversation, $facebook_pages[$page_id]['saved_latest'] ?? 0 ) ;
            } else {
                $facebook_pages[$page_id]['latest_conversation'] = max( $new_latest_conversation, $facebook_pages[$page_id]['saved_latest'] ?? 0 ) ;
                $facebook_pages[$page_id]['new_latest_conversation_id'] = $new_latest_conversation_id;
                $facebook_pages[$page_id]['saved_latest'] = null;
            }
        }

        $facebook_pages[$page_id]['last_api_call'] = time();
        update_option( 'dt_facebook_pages', $facebook_pages );
        if ( !$skip_save && !empty( $facebook_pages[$page_id]['next_page'] ) ){
            return self::get_conversations( $page_id );
        } elseif ( !$skip_save && $number_of_convs_to_save > 0 ) {
            wp_queue()->cron()->cron_worker();
        }
        return [
            'conversations_saved' => sizeof( $conversations_page['data'] ),
            'next' => !empty( $facebook_pages[$page_id]['next_page'] )
        ];
    }

    public static function save_conversation( $page_id, $conversation ){
        $facebook_pages = get_option( 'dt_facebook_pages', [] );
        foreach ( $conversation['participants']['data'] as $participant ) {
            if ( (string) $participant['id'] != $page_id ) {
                $contact_id = self::update_or_create_contact( $participant, $conversation['updated_time'], $facebook_pages[$page_id], $conversation );
                if ( $contact_id ){
                    $facebook_pages = get_option( 'dt_facebook_pages', [] );
                    $facebook_pages[$page_id]['last_contact_id'] = $contact_id;
                    update_option( 'dt_facebook_pages', $facebook_pages );
                    self::update_facebook_messages_on_contact( $contact_id, $conversation, $participant['id'] );
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
        $page_scoped_ids = [ $participant['id'] ];

        $app_id = get_option( 'disciple_tools_facebook_app_id', null );
        if ( empty( $app_id ) ){
            Disciple_Tools_Facebook_Api::save_log_message( 'missing app_id' );
            return new WP_Error( 'app_id', 'missing app_id' );
        }

        $contacts = dt_facebook_find_contacts_with_ids( $page_scoped_ids, $participant['id'], $app_id );

        $contact_id   = null;

        if ( sizeof( $contacts ) > 1 ) {
            foreach ( $contacts as $contact_post ) {
                $contact = DT_Posts::get_post( 'contacts', $contact_post->ID, true, false );
                if ( isset( $contact['overall_status']['key'] ) && $contact['overall_status']['key'] != 'closed' ) {
                    $contact_id = $contact['ID'];
                }
            }

            if ( !$contact_id ) {
                $contact_id = $contacts[0]->ID;
            }
        }
        if ( sizeof( $contacts ) == 1 ) {
            $contact_id = $contacts[0]->ID;
        }

        $facebook_url = 'https://www.facebook.com/' . $participant['id'];
        if ( $contact_id ) {
            $contact                          = DT_Posts::get_post( 'contacts', $contact_id, true, false );
            $facebook_data                    = maybe_unserialize( $contact['facebook_data'] ) ?? [];
            $initial_facebook_data = $facebook_data;
            $facebook_data['last_message_at'] = $updated_time;

            if ( !isset( $facebook_data['page_scoped_ids'] ) ) {
                $facebook_data['page_scoped_ids'] = [];
            }
            if ( !isset( $facebook_data['app_scoped_ids'] ) ) {
                $facebook_data['app_scoped_ids'] = [];
            }
            if ( !isset( $facebook_data['page_ids'] ) ) {
                $facebook_data['page_ids'] = [];
            }
            if ( !isset( $facebook_data['links'] ) ) {
                $facebook_data['links'] = [];
            }
            if ( !isset( $facebook_data['names'] ) ) {
                $facebook_data['names'] = [];
            }
            foreach ( $page_scoped_ids as $id ) {
                if ( !in_array( $id, $facebook_data['page_scoped_ids'] ) ) {
                    $facebook_data['page_scoped_ids'][] = $id;
                }
            }
            if ( !isset( $facebook_data['app_scoped_ids'][ $app_id ] ) ) {
                $facebook_data['app_scoped_ids'][ $app_id ] = $participant['id'];
                $facebook_data['page_ids'][] = $participant['id'];
            }

            if ( !in_array( $page['id'], $facebook_data['page_ids'] ) ) {
                $facebook_data['page_ids'][] = $page['id'];
            }
            if ( !in_array( $participant['name'], $facebook_data['names'] ) ) {
                $facebook_data['names'][] = $participant['name'];
            }
            if ( !in_array( $conversation['link'], $facebook_data['links'] ) ) {
                $facebook_data['links'][] = $conversation['link'];
            }
            $update = [ 'facebook_data' => $facebook_data ];
            if ( isset( $contact['overall_status']['key'], $contact['reason_closed']['key'] ) && $contact['overall_status']['key'] === 'closed' && $contact['reason_closed']['key'] === 'no_longer_responding' ){
                $update['overall_status'] = 'from_facebook';
            }
            $update['last_message_received'] = strtotime( $updated_time );
            if ( $facebook_data != $initial_facebook_data ) {
                DT_Posts::update_post( 'contacts', $contact_id, $update, true, false );
            }
            return $contact_id;
        } else {
            $fields = [
                'title'            => $participant['name'],
                'contact_facebook' => [ [ 'value' => $facebook_url ] ],
                'sources'          => [
                    'values' => [
                        [ 'value' => $page['id'] ]
                    ]
                ],
                'overall_status'   => 'from_facebook',
                'facebook_data'    => [
                    'page_scoped_ids' => $page_scoped_ids,
                    'app_scoped_ids'  => [ $app_id => $participant['id'] ],
                    'page_ids'        => [ $page['id'] ],
                    'names'           => [ $participant['name'] ],
                    'last_message_at' => $updated_time,
                    'links' => [ $conversation['link'] ]
                ],
                'last_message_received' => strtotime( $updated_time )
            ];
            if ( isset( $page['assign_to'] ) ){
                $fields['assigned_to'] = $page['assign_to'];
            }
            $new_contact = DT_Posts::create_post( 'contacts', $fields, true, false );
            if ( is_wp_error( $new_contact ) ){
                dt_write_log( 'Facebook contact creation failure' );
                dt_write_log( $fields );
                Disciple_Tools_Facebook_Api::save_log_message( $new_contact->get_error_message(), $new_contact->get_error_code() );

                self::dt_facebook_log_email( 'Creating a contact failed', 'The Facebook integration was not able to create a contact from Facebook. If this persists, please contact support.' );
            }
            return $new_contact['ID'];
        }
    }

    public static function dt_facebook_log_email( $subject, $text ){
        $email_address = get_option( 'dt_facebook_contact_email' );
        if ( !empty( $email_address ) ){
            dt_send_email( $email_address, $subject, $text );
        }
    }

    public static $allowable_comment_tags = array(
        'a' => array(
            'href' => array(),
            'title' => array()
        ),
        'br' => array(),
        'em' => array(),
        'strong' => array(),
    );

    public static function update_facebook_messages_on_contact( $contact_id, $conversation, $participant_id ){
        $facebook_data = maybe_unserialize( get_post_meta( $contact_id, 'facebook_data', true ) ) ?? [];
        $message_count = $conversation['message_count'];
        $number_of_messages = sizeof( $conversation['messages']['data'] );
        $saved_number = $facebook_data['message_count'] ?? 0;
        $messages = $conversation['messages']['data'];

        if ( $message_count != $saved_number && $message_count > $number_of_messages && isset( $conversation['messages']['paging']['next'] ) ){
            dt_write_log( 'GETTING ALL FACEBOOK MESSAGES' );
            $all_convs = Disciple_Tools_Facebook_Api::get_all_with_pagination( $conversation['messages']['paging']['next'] );
            $messages = array_merge( $all_convs, $messages );
        }
        $facebook_data = maybe_unserialize( get_post_meta( $contact_id, 'facebook_data', true ) ) ?? [];
        global $wpdb;
        $sql = "INSERT INTO $wpdb->comments (comment_post_ID, comment_author, comment_date, comment_date_gmt, comment_content, comment_approved, comment_type, comment_parent, user_id) VALUES ";
        $comments_to_add = '';
        if ( $message_count != $saved_number ){
            foreach ( $messages as $message ){
                $saved_ids = $facebook_data['message_ids'] ?? [];
                if ( !in_array( $message['id'], $saved_ids ) ){
                    $comment = wp_kses( $message['message'], self::$allowable_comment_tags );
                    if ( empty( $comment ) ){
                        $comment = '[picture, sticker or emoji]';
                    }
                    $date = dt_format_date( $message['created_time'], 'Y-m-d H:i:s' );
                    $comments_to_add .= $wpdb->prepare( '( %d, %s, %s, %s, %s, %d, %s, %d, %d ),',
                        $contact_id,
                        $message['from']['name'],
                        $date,
                        $date,
                        $comment,
                        1,
                        'facebook',
                        0,
                        0
                    );
                    $saved_ids[] = $message['id'];
                    $facebook_data['message_ids'][] = $message['id'];
                }
            }
            if ( !empty( $comments_to_add ) ){
                $sql .= $comments_to_add;
                $sql .= ';';
                $sql = str_replace( ',;', ';', $sql ); // remove last comma
            }
            $insert_comments = $wpdb->query( $sql ); // @phpcs:ignore
            if ( empty( $insert_comments ) || is_wp_error( $insert_comments ) ) {
                return new WP_Error( __FUNCTION__, 'Failed to insert comments' );
            }

            $message_ids = $facebook_data['message_ids'];
            $facebook_data = maybe_unserialize( get_post_meta( $contact_id, 'facebook_data', true ) ) ?? [];
            $facebook_data['message_count'] = $message_count;
            $facebook_data['message_ids'] = $message_ids;
            update_post_meta( $contact_id, 'facebook_data', $facebook_data );
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

        dt_write_log( 'facebook saving conversation at ' . time() );
        Disciple_Tools_Facebook_Sync::save_conversation( $this->page_id, $this->conversation );
    }
}
new Disciple_Tools_Facebook_Sync();
