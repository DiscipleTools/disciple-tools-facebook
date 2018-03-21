<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

/**
 * Class Disciple_Tools_Facebook_Integration
 */
class Disciple_Tools_Facebook_Integration
{
    /**
     * Disciple_Tools_Admin The single instance of Disciple_Tools_Admin.
     *
     * @var    object
     * @access private
     * @since  0.1.0
     */
    private static $_instance = null;

    /**
     * Main Disciple_Tools_Facebook_Integration Instance
     * Ensures only one instance of Disciple_Tools_Facebook_Integration is loaded or can be loaded.
     *
     * @since  0.1.0
     * @static
     * @return Disciple_Tools_Facebook_Integration instance
     */
    public static function instance()
    {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    } // End instance()

    private $version = 1.0;
    private $context = "dt_facebook";
    private $namespace;

    /**
     * Constructor function.
     *
     * @access public
     * @since  0.1.0
     */
    public function __construct()
    {
        $this->namespace = $this->context . "/v" . intval( $this->version );
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
        add_action( 'dt_contact_meta_boxes_setup', [ $this, 'add_contact_meta_box' ] );
//        add_action( 'admin_notices', [ $this, 'dt_admin_notice' ] );
        add_action( 'wp_ajax_dt-facebook-notice-dismiss', [ $this, 'dismiss_error' ] );
        add_filter( "dt_custom_fields_settings", [ $this, "dt_facebook_fields" ], 1, 2 );
        add_filter( "dt_details_additional_section_ids", [ $this, "dt_facebook_declare_section_id" ], 999, 2 );
        add_action( "dt_details_additional_section", [ $this, "dt_facebook_add_section" ] );
        add_action( 'dt_build_report', [ $this, 'get_users_for_labels' ] );
        add_action( 'dt_async_dt_get_users_for_labels', [ $this, 'get_users_for_labels_async' ] );

    } // End __construct()

    /**
     * Setup the api routs for the plugin
     *
     * @since  0.1.0
     */
    public function add_api_routes()
    {

        register_rest_route(
            $this->namespace . "/dt-public/", 'webhook', [
                'methods'  => 'POST',
                'callback' => [ $this, 'update_from_facebook' ],
            ]
        );
        register_rest_route(
            $this->namespace . "/dt-public/", 'webhook', [
                'methods'  => 'GET',
                'callback' => [ $this, 'verify_facebook_webhooks' ],
            ]
        );
        register_rest_route(
            $this->namespace, "auth", [
                'methods'  => "GET",
                'callback' => [ $this, 'authenticate_app' ],
            ]
        );
        register_rest_route(
            $this->namespace, "add-app", [
                'methods'  => "POST",
                'callback' => [ $this, 'add_app' ],
            ]
        );
        register_rest_route(
            $this->namespace, 'rebuild', [
                "methods"  => "GET",
                'callback' => [ $this, 'rebuild_all_data' ],
            ]
        );
    }


    public static function dt_facebook_fields( array $fields, string $post_type = ""){
        //check if we are dealing with a contact
        if ($post_type === "contacts"){
            //check if the language field is already set
            if ( !isset( $fields["facebook_data"] )){
                //define the language field
                $fields["facebook_data"] = [
                    "name" => __( "Facebook Ids", "disciple_tools_facebook" ),
                    "type" => "array",
                    "default" => []
                ];
            }
        }
        //don't forget to return the update fields array
        return $fields;
    }


    public static function dt_facebook_declare_section_id( $sections, $post_type = "" ){
        //check if we are on a contact
        if ($post_type === "contacts"){
            $contact_fields = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings();
            //check if the language field is set
            if ( isset( $contact_fields["facebook_data"] ) ){
                $sections[] = "contact_facebook_data";
            }
            //add more section ids here if you want...
        }
        return $sections;
    }


    public static function dt_facebook_add_section( $section ){
        if ($section == "contact_facebook_data"){
            $contact_id = get_the_ID();
            $contact_fields = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings();
            $contact = Disciple_Tools_Contacts::get_contact( $contact_id, true );
            $facebook_data = [];
            if (isset( $contact["facebook_data"] )){
                $facebook_data = maybe_unserialize( $contact["facebook_data"] );
            }
        ?>
            <!-- need you own css? -->
            <style type="text/css">
                .required-style-example {
                    color: red
                }
            </style>

            <label class="section-header">
                <?php esc_html_e( 'Facebook', 'disciple_tools' )?>
            </label>
            <?php
            foreach ( $facebook_data as $key => $value ){
            ?>
                <div class="section-subheader">
                    <?php echo esc_html( $key )?>
                </div>
                <?php
                if ( is_array( $value )){
                    foreach ( $value as $id ){
                        ?>
                        <p><?php echo esc_html( $id )?></p>
                        <?php
                    }
                } else {
                    ?>
                    <p><?php echo esc_html( $value )?></p>
                    <?php
                }
            }
            ?>


            <script type="application/javascript">
                //enter jquery here if you need it
                jQuery(($)=>{
                })
            </script>
        <?php
        }

        //add more sections here if you want...
    }


    /**
     * Admin notice
     */
    public function dt_admin_notice()
    {
        $error = get_option( 'dt_facebook_error', "" );
        if ( $error ) { ?>
            <div class="notice notice-error dt-facebook-notice is-dismissible">
                <p><?php echo esc_html( $error ); ?></p>
            </div>
        <?php }
    }

    public function dismiss_error()
    {
        update_option( 'dt_facebook_error', "" );
    }


    /**
     * Get reports for Facebook pages with stats enabled
     * for the past 10 years (if available)
     */
    public function rebuild_all_data()
    {
        $this->immediate_response();
        $facebook_pages = get_option( "dt_facebook_pages", [] );
        foreach ( $facebook_pages as $page_id => $facebook_page ) {
            if ( isset( $facebook_page["rebuild"] ) && $facebook_page["rebuild"] == true ) {
                $long_time_ago = date( 'Y-m-d', strtotime( '-10 years' ) );
                $reports = Disciple_Tools_Reports_Integrations::facebook_prepared_data( $long_time_ago, $facebook_page );
                foreach ( $reports as $report ) {
                    dt_report_insert( $report );
                }
            }
        }
    }

    /**
     * Render the Facebook Settings Page
     */
    public function facebook_settings_page()
    {

        ?>
        <h3>Hook up Disciple tools to a Facebook app in order to get contacts or useful stats from your Facebook
            pages. </h3>
        <div class="wrap">
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">

                        <form action="<?php echo esc_url( $this->get_rest_url() ); ?>/add-app" method="post">
                            <input type="hidden" name="_wpnonce" id="_wpnonce"
                                   value="<?php echo esc_html( wp_create_nonce( 'wp_rest' ) )?>"/>
                            <p>For this integration to work, go to your <a href="https://developers.facebook.com/apps">Facebook
                                    app's settings page</a>.
                                Under <strong>Add Platform</strong>, choose the website option, put:
                                <strong><?php echo esc_url( get_site_url() ); ?></strong> as the site URL and click save
                                changes.<br>
                                Also add it to the "Valid Oauth redirect URIs
                                <br>
                                From your Facebook App's settings page get the App ID and the App Secret and paste them
                                bellow and click the "Save App Settings" button.<br>
                                If you have any Facebook pages, they should appear in the Facebook Pages Table
                                bellow.<br>
                                You will need to re-authenticate (by clicking the "Save App Settings" button bellow) if:<br>
                                &nbsp;&nbsp; •You change your Facebook account password<br>
                                &nbsp;&nbsp; •You delete or de­authorize your Facebook App
                            </p>
                            <p>Business Manager: Associate your app and page with a business.</p>
                            <p></p>
                            <table class="widefat striped">

                                <thead>
                                <th>Facebook App Settings</th>
                                <th></th>
                                </thead>

                                <tbody>

                                <tr>
                                    <td>Facebook App Id</td>
                                    <td>
                                        <input type="text" class="regular-text" name="app_id"
                                               value="<?php echo esc_attr( get_option( "disciple_tools_facebook_app_id", "" ) ); ?>"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Facebook App Secret</td>
                                    <td>
                                        <input type="text" class="regular-text" name="app_secret"
                                               value="<?php echo esc_attr( get_option( "disciple_tools_facebook_app_secret" ) ); ?>"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Access Token</td>
                                    <td>
                                        <?php echo( get_option( "disciple_tools_facebook_access_token" ) ? "Access token is saved" : "No Access Token" ) ?>
                                    </td>
                                </tr>

                                <tr>
                                    <td>Save app</td>
                                    <td><input type="submit" class="button" name="save_app" value="Save app Settings"/>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </form>

                        <br>
                        <form action="" method="post">
                            <input type="hidden" name="_wpnonce" id="_wpnonce"
                                   value="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"/>
                            <?php $this->facebook_settings_functions(); ?>
                            <table id="facebook_pages" class="widefat striped">
                                <thead>
                                    <tr>
                                        <th>Facebook Pages</th>
                                        <th>Sync Contacts</th>
                                        <th>Include in Stats</th>
                                        <th>Part of Business Manager</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                $facebook_pages = get_option( "dt_facebook_pages", [] );

                                foreach ( $facebook_pages as $id => $facebook_page ){
                                ?>
                                <tr>
                                    <td><?php echo esc_html( $facebook_page["name"] ); ?> (<?php echo esc_html( $facebook_page["id"] ); ?>)</td>
                                    <td>
                                        <input name="<?php echo esc_attr( $facebook_page["id"] ) .  "-integrate"; ?>"
                                               type="checkbox"
                                               value="<?php echo esc_attr( $facebook_page["id"] ); ?>" <?php echo checked( 1, isset( $facebook_page["integrate"] ) ? $facebook_page["integrate"] : false, false ); ?> />
                                    </td>
                                    <td>
                                        <input name="<?php echo esc_attr( $facebook_page["id"] ) . "-report"; ?>"
                                               type="checkbox"
                                               value="<?php echo esc_attr( $facebook_page["id"] ); ?>" <?php echo checked( 1, isset( $facebook_page["report"] ) ? $facebook_page["report"] : false, false ); ?> />
                                    </td>
                                    <td>
                                        <input disabled
                                            type="checkbox"
                                            <?php echo checked( 1, isset( $facebook_page["business"] ), false ); ?> />
                                    </td>
                                    <?php
                                }
                                    ?>
                                </tbody>
                            </table>
                            <input type="submit" class="button" name="get_pages" value="Refresh Page List"/>
                            <input type="submit" class="button" name="save_pages" value="Save Pages Settings"/>


                        </form>
                    </div><!-- end post-body-content -->

                </div><!-- post-body meta box container -->
            </div><!--poststuff end -->
        </div><!-- wrap end -->

        <?php
    }

    /**
     * Display an error message
     *
     * @param $err
     */
    private function display_error( $err )
    {
        $err = date( "Y-m-d h:i:sa" ) . ' ' . $err; ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html( $err ); ?></p>
        </div>
        <?php
        update_option( 'dt_facebook_error', $err );
    }

    /**
     * Functions for the pages section of the Facebook settings
     */
    public function facebook_settings_functions()
    {
        // Check noonce
        if ( isset( $_POST['dt_app_form_noonce'] ) && !wp_verify_nonce( sanitize_key( $_POST['dt_app_form_noonce'] ), 'dt_app_form' ) ) {
            echo 'Are you cheating? Where did this form come from?';

            return;
        }

        // get the pages the user has access to.
        if ( isset( $_POST["get_pages"] ) ) {
            $this->get_or_refresh_pages( get_option( 'disciple_tools_facebook_access_token' ) );
        }

        //save changes made to the pages in the page list
        if ( isset( $_POST["save_pages"] ) ) {
            $get_historical_data = false;
            $facebook_pages = get_option( "dt_facebook_pages", [] );
            foreach ( $facebook_pages as $id => $facebook_page ) {
                //if sync contact checkbox is selected
                $integrate = str_replace( ' ', '_', $facebook_page["id"] . "-integrate" );
                if ( isset( $_POST[ $integrate ] ) ) {
                    $facebook_pages[$id]["integrate"] = 1;
                } else {
                    $facebook_pages[$id]["integrate"] = 0;
                }
                //if the include in stats checkbox is selected
                $report = str_replace( ' ', '_', $facebook_page["id"] . "-report" );
                if ( isset( $_POST[ $report ] ) ) {
                    $facebook_pages[$id]["report"] = 1;
                    $facebook_pages[$id]["rebuild"] = true;
                    $get_historical_data = true;
                } else {
                    $facebook_pages[$id]["report"] = 0;
                }
                //Add the page to the apps subscriptions (to allow webhooks)
                if ( $facebook_pages[$id]["integrate"] == 1 && ( !isset( $facebook_pages[$id]["subscribed"] ) || ( isset( $facebook_pages[$id]["subscribed"] ) && $facebook_pages[$id]["subscribed"] != 1 ) ) ) {
                    $url = "https://graph.facebook.com/v2.8/" . $id . "/subscribed_apps?access_token=" . $facebook_page["access_token"];
                    $request = wp_remote_post( $url );
                    if ( is_wp_error( $request ) ) {
                        $this->display_error( $request );
                    } else {
                        $body = wp_remote_retrieve_body( $request );
                        $data = json_decode( $body, true );
                        if ( !empty( $data ) && isset( $data["error"] ) ) {
                            $this->display_error( $data["error"]["message"] );
                        }
                        $facebook_pages[$id]["subscribed"] = 1;
                    }
                }
                //enable and set up webhooks for getting page feed and conversations
                if ( isset( $facebook_pages[$id]["subscribed"] ) && $facebook_pages[$id]["subscribed"] == 1 && !isset( $facebook_pages[$id]["webhooks"] ) && $facebook_pages[$id]["integrate"] === 1 ) {
                    $url = "https://graph.facebook.com/v2.12/" . $id . "/subscriptions?access_token=" . get_option( "disciple_tools_facebook_app_id", "" ) . "|" . get_option( "disciple_tools_facebook_app_secret", "" );
                    $request = wp_remote_post(
                        $url, [
                            'body' => [
                                'object'       => 'page',
                                'callback_url' => $this->get_rest_url() . "/dt-public/webhook",
                                'verify_token' => $this->authorize_secret(),
                                'fields'       => [ 'conversations', 'feed' ],
                            ],
                        ]
                    );
                    if ( is_wp_error( $request ) ) {
                        $this->display_error( $request );
                    } else {

                        $body = wp_remote_retrieve_body( $request );
                        $data = json_decode( $body, true );
                        if ( !empty( $data ) && isset( $data["error"] ) ) {
                            $this->display_error( $data["error"]["message"] );
                        }
                        if ( !empty( $data ) && isset( $data["success"] ) ) {
                            $facebook_pages[$id]["webhooks_set"] = 1;
                        }
                    }
                }
            }
            update_option( "dt_facebook_pages", $facebook_pages );
            //if a new page is added, get the reports for that page.
            if ( $get_historical_data === true ) {
                wp_remote_get( $this->get_rest_url() . "/rebuild" );
            }
        }
    }

    public function get_rest_url()
    {
        return get_site_url() . "/wp-json/" . $this->namespace;
    }


    /**
     * Facebook Authentication and webhooks
     */

    // Generate authorization secret
    public static function authorize_secret()
    {
        return 'dt_auth_' . substr( md5( AUTH_KEY ? AUTH_KEY : get_bloginfo( 'url' ) ), 0, 10 );
    }

    /**
     * called by facebook when initialising the webook
     *
     * @return mixed
     */
    public function verify_facebook_webhooks()
    {
        if ( isset( $_GET["hub_verify_token"] ) && $_GET["hub_verify_token"] === $this->authorize_secret() ) {
            if ( isset( $_GET['hub_challenge'] ) ) {
                echo esc_html( sanitize_text_field( wp_unslash( $_GET['hub_challenge'] ) ) );
                exit();
            }
        } else {
            return "Could not verify";
        }
    }

    /**
     * Facebook waits for a response from our server to see if we received the webhook update
     * If our server does not respond, Facebook will try the webhook again
     * Because we go on to do more ajax and database calls which takes several seconds
     * we need to respond to the return right away.
     */
    private function immediate_response()
    {
        // Buffer all upcoming output...
        ob_start();
        // Send your response.
        new WP_REST_Response( "ok", 200 );
        // Get the size of the output.
        $size = ob_get_length();
        // Disable compression (in case content length is compressed).
        header( "Content-Encoding: none" );
        // Set the content length of the response.
        header( "Content-Length: {$size}" );
        // Close the connection.
        header( "Connection: close" );
        // Flush all output.
        ob_end_flush();
        ob_flush();
        flush();
        // Close current session (if it exists).
        // TODO: look into whether session_ functions should really be used
        // here, as PHPCS does not like it
        // @codingStandardsIgnoreStart
        if( session_id() ) {
            session_write_close();
        }
        //for nginx systems
        session_write_close(); //close the session
        // @codingStandardsIgnoreEnd
        fastcgi_finish_request(); //this returns 200 to the user, and processing continues
    }

    private function get_or_refresh_pages( $access_token ){

        $facebook_pages_url = "https://graph.facebook.com/v2.8/me/accounts?fields=access_token,id,name,business&access_token=" . $access_token;
        $pages_request = wp_remote_get( $facebook_pages_url );


        if ( is_wp_error( $pages_request ) ) {
            $this->display_error( $pages_request );
            echo "There was an error";
        } else {
            $pages_body = wp_remote_retrieve_body( $pages_request );
            $pages_data = json_decode( $pages_body, true );
            if ( !empty( $pages_data ) ) {
                if ( isset( $pages_data["data"] ) ) {
                    $pages = get_option( "dt_facebook_pages", [] );
                    foreach ( $pages_data["data"] as $page ) {
                        if ( !isset( $pages[$page["id"]] ) ){
                            $pages[ $page["id"] ] = $page;
                        } else {
                            $pages[ $page["id"] ]["access_token"] = $page["access_token"];
                            $pages[ $page["id"] ]["name"] = $page["name"];
                            if ( isset( $page["business"] ) ){
                                $pages[ $page["id"] ]["business"] = $page["business"];
                            }
                        }
                    }
                    update_option( "dt_facebook_pages", $pages );
                } elseif ( isset( $pages_data["error"] ) && isset( $pages_data["error"]["message"] ) ) {
                    $this->display_error( $pages_data["error"]["message"] );
                }
            }
        }
    }

    /**
     * authenticate the facebook app to get the user access token and facebook pages
     *
     * @param  $get
     *
     * @return bool
     */
    public function authenticate_app( $get )
    {

        //get the access token

        if ( isset( $get["state"] ) && strpos( $get['state'], $this->authorize_secret() ) !== false && isset( $get["code"] ) ) {
            $url = "https://graph.facebook.com/v2.8/oauth/access_token";
            $url .= "?client_id=" . get_option( "disciple_tools_facebook_app_id" );
            $url .= "&redirect_uri=" . $this->get_rest_url() . "/auth";
            $url .= "&client_secret=" . get_option( "disciple_tools_facebook_app_secret" );
            $url .= "&code=" . $get["code"];

            $request = wp_remote_get( $url );
            if ( is_wp_error( $request ) ) {
                $this->display_error( $request->get_error_message() );

                return $request->errors;
            } else {
                $body = wp_remote_retrieve_body( $request );
                $data = json_decode( $body, true );
                if ( !empty( $data ) ) {
                    if ( isset( $data["access_token"] ) ) {
                        update_option( 'disciple_tools_facebook_access_token', $data["access_token"] );
                        $this->get_or_refresh_pages( $data["access_token"] );
                    }
                    if ( isset( $data["error"] ) ) {
                        $this->display_error( $data["error"]["message"] );
                    }
                }
            }
        }
        wp_redirect( admin_url( "admin.php?page=" . $this->context ) );
        exit;
    }

    /**
     * redirect workfloww for authorizing the facebook app
     */
    public function add_app()
    {
        // Check noonce
        if ( isset( $_POST['_wpnonce'] ) && !wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'wp_rest' ) ) {
            return 'Are you cheating? Where did this form come from?';
        }
        if ( current_user_can( "manage_options" ) && check_admin_referer( 'wp_rest' ) && isset( $_POST["save_app"] ) && isset( $_POST["app_secret"] ) && isset( $_POST["app_id"] ) ) {
            update_option( 'disciple_tools_facebook_app_id', sanitize_key( $_POST["app_id"] ) );
            update_option( 'disciple_tools_facebook_app_secret', sanitize_key( $_POST["app_secret"] ) );
            delete_option( 'disciple_tools_facebook_access_token' );

            $url = "https://facebook.com/v2.8/dialog/oauth";
            $url .= "?client_id=" . sanitize_key( $_POST["app_id"] );
            $url .= "&redirect_uri=" . $this->get_rest_url() . "/auth";
            $url .= "&scope=public_profile,read_insights,manage_pages,read_page_mailboxes,business_management";
            $url .= "&state=" . $this->authorize_secret();

            wp_redirect( $url );
            exit;
        }

        return "ok";
    }





    /**
     * Handle updates from facebook via webhooks
     * - conversations
     */

    /**
     * This is the route called by the Facebook webhook.
     */
    public function update_from_facebook()
    {
//        respond to facebook immediately. Disabled because it was not going past this point.
//        $this->immediate_response();
        //decode the facebook post request from json
        $body = json_decode( file_get_contents( 'php://input' ), true );

        foreach ( $body['entry'] as $entry ) {
            $facebook_page_id = $entry['id'];
            if ( $entry['changes'] ) {
                foreach ( $entry['changes'] as $change ) {
                    if ( $change['field'] == "conversations" ) {
                        //there is a new update in a conversation
                        $thread_id = $change['value']['thread_id'];
                        $this->get_conversation_update( $facebook_page_id, $thread_id );
                    } elseif ( $change['field'] == "feed" ) {
                        //the facebook page feed has an update
                    }
                }
            }
        }

        do_action( "dt_update_from_facebook", $body );
    }

    /**
     * get the conversation details from facebook
     *
     * @param $page_id
     * @param $thread_id , the id for the conversation containing the messages
     */
    private function get_conversation_update( $page_id, $thread_id )
    {
        //check the settings array to see if we have settings saved for the page
        //get the access token and custom page name by looking for the page Id
        $facebook_pages = get_option( "dt_facebook_pages", [] );
        //if we have the access token, get and save the conversation
        //make sure the "sync contacts" setting is set.
        if ( isset( $facebook_pages[ $page_id ] ) && isset( $facebook_pages[ $page_id ]["integrate"] ) && $facebook_pages[ $page_id ]["integrate"] == 1 ) {

            $access_token = $facebook_pages[ $page_id ]["access_token"];
//            $uri_for_conversations = "https://graph.facebook.com/v2.7/" . $thread_id . "?fields=message_count,messages{from,created_time,message},updated_time,participants&access_token=" . $access_token;
            $uri_for_conversations = "https://graph.facebook.com/v2.7/" . $thread_id . "?fields=updated_time,participants&access_token=" . $access_token;
            $response = wp_remote_get( $uri_for_conversations );

            $body = json_decode( $response["body"], true );
            if ( $body ) {
                $participants = $body["participants"]["data"];
                //go through each participant to save their conversations on their contact record
                foreach ( $participants as $participant ) {
                    if ( (string) $participant["id"] != $page_id ) {
                        $this->update_or_create_contact( $participant, $body["updated_time"], $facebook_pages[ $page_id ] );
                    }
                }
            }
        }
    }

    public function find_contacts_with_facebook_ids( $ids ){
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

    //The app secret proof is a sha256 hash of your access token, using the app secret as the key.
    public function get_app_secret_proof( $access_token ){
        $app_secret = get_option( "disciple_tools_facebook_app_secret" );
        $app_secret_proof = hash_hmac( 'sha256', $access_token, $app_secret );
        return $app_secret_proof;
    }

    public function get_page_scoped_ids( $used_id, $access_token ){
        $app_secret_proof = $this->get_app_secret_proof( $access_token );
        $ids_for_pages_uri = "https://graph.facebook.com/v2.12/$used_id?fields=name,ids_for_pages&access_token=$access_token&appsecret_proof=$app_secret_proof";
        $response = wp_remote_get( $ids_for_pages_uri );
        $ids = json_decode( $response["body"], true );
        $ids_for_pages = [];
        if ( isset( $ids["ids_for_pages"] ) && isset( $ids["ids_for_pages"]["data"] ) ){
            foreach ( $ids["ids_for_pages"]["data"] as $user ){
                $ids_for_pages[] = $user["id"];
            }
        }
        return $ids_for_pages;
    }

    /**
     * Find the facebook id in contacts and update or create the record. Then retrieve any missing messages
     * from the conversation.
     *
     * @param $participant
     * @param $updated_time  , the time of the last message
     * @param $page       , the facebook page where the conversation is happening
     */
    private function update_or_create_contact( $participant, $updated_time, $page )
    {
        //get page scoped ids available by using a Facebook business manager
        $page_scoped_ids = [];
        if ( isset( $page["business"] ) ){
            $page_scoped_ids = $this->get_page_scoped_ids( $participant["id"], $page["access_token"] );
        }


        $ids = array_merge( $page_scoped_ids, [ $participant["id"] ] );
        $contacts = $this->find_contacts_with_facebook_ids( $ids );

        $facebook_url = "https://www.facebook.com/" . $participant["id"];
        $contact_id = null;

        if ( sizeof( $contacts ) > 1 ){
            foreach ( $contacts as $contact_post ){
                $contact = Disciple_Tools_Contacts::get_contact( $contact_post->ID, false );
                if ( $contact["overall_status"] != "closed" ){
                    $contact_id = $contacts["ID"];
                }
            }

            if ( !$contact_id ){
                $contact_id = $contacts[0]->ID;
            }
        }
        if ( sizeof( $contacts ) == 1 ) {
            $contact_id = $contacts[0]->ID;
        }

        if ( $contact_id ){
            Disciple_Tools_Contacts::add_comment( $contact_id, $updated_time, false );
            $contact = Disciple_Tools_Contacts::get_contact( $contact_id, false );
            $facebook_data = maybe_unserialize( $contact["facebook_data"] ) ?? [];
            $facebook_data["last_message_at"] = $updated_time;

            if ( !isset( $facebook_data["page_scoped_ids"] ) ){
                $facebook_data["page_scoped_ids"] = [];
            }
            if ( !isset( $facebook_data["app_scoped_ids"] ) ){
                $facebook_data["app_scoped_ids"] = [];
            }
            if ( !isset( $facebook_data["names"] ) ){
                $facebook_data["names"] = [];
            }
            foreach ( $page_scoped_ids as $id ){
                if ( !in_array( $id, $facebook_data["page_scoped_ids"] )){
                    $facebook_data["page_scoped_ids"][] = $id;
                }
            }
            if ( !in_array( $participant["id"], $facebook_data["app_scoped_ids"] )){
                $facebook_data["app_scoped_ids"][] = $participant["id"];
            }
            if ( !in_array( $participant["name"], $facebook_data["names"] )){
                $facebook_data["names"][] = $participant["name"];
            }
            Disciple_Tools_Contacts::update_contact( $contact_id, [ "facebook_data" => $facebook_data ], false );
        } else if ( !$contact_id ){
            $fields = [
                "title" => $participant["name"],
                "source_details" => "Facebook Page: " . $page["name"],
                "contact_facebook" => [ [ "value" => $facebook_url ] ],
                "facebook_data" => [
                    "page_scoped_ids" => $page_scoped_ids,
                    "app_scoped_ids" => [ $participant["id"] ],
                    "names" => [ $participant["name"] ],
                    "last_message_at" => $updated_time
                ]
            ];

            $contact_id = Disciple_Tools_Contacts::create_contact( $fields, false );
        }

    }


        /**
     * Get all the records if we don't already have them.
     *
     * @param  $url             , the orginal url or the paging next
     * @param  $current_records , the records (messages) gotten with the initial api call
     *
     * @return array, all the records
     */
    private function get_facebook_object_with_paging( $url, $current_records = [] ) {
        $response = wp_remote_get( $url );
        $more_records = json_decode( $response["body"], true );
        if ( !isset( $more_records["data"] ) ){
            //@todo return error
        }
        $current_records = array_map( "unserialize", array_unique( array_map( "serialize", array_merge( $current_records, $more_records["data"] ) ) ) );

        if ( !isset( $more_records["paging"] ) || !isset( $more_records["paging"]["next"] ) ) {
            return $current_records;
        } else {
            return $this->get_facebook_object_with_paging( $more_records["paging"]["next"], $current_records );
        }
    }

    public function facebook_api( $endpoint, $main_id, $access_token ){
        switch ($endpoint) {
            case "page_labels":
                $uri_for_page_labels = "https://graph.facebook.com/v2.12/" . $main_id . "/labels?fields=name&access_token=" . $access_token;
                return $this->get_facebook_object_with_paging( $uri_for_page_labels );
                break;
            case "label_users":
                $uri_for_page_labels = "https://graph.facebook.com/v2.12/" . $main_id . "/users?&access_token=" . $access_token;
                return $this->get_facebook_object_with_paging( $uri_for_page_labels );
                break;
        }
    }

    public function get_facebook_page_labels(){
        do_action( "dt_get_labels" );
    }

    public static function apply_label_to_conversation( $page_label_id, $facebook_user_id, $page_id ){

    }

    public function get_facebook_page_labels_async(){
        $facebook_pages = get_option( "dt_facebook_pages", [] );
        $facebook_labels = get_option( "dt_facebook_labels", [] );
        foreach ( $facebook_pages as $page ){
            if ( isset( $page["integrate"] ) && $page["integrate"] == 1 ){
                $labels = $this->facebook_api( "page_labels", $page["id"], $page["access_token"] );
                if ( !isset( $facebook_labels[$page["id"]] ) ){
                    $facebook_labels[$page["id"]] = [];
                }
                foreach ( $labels as $label ){
                    if ( !isset( $facebook_labels[$page["id"]][$label["id"]] ) ){
                        $facebook_labels[$page["id"]][$label["id"]] = [];
                    }
                    $facebook_labels[$page["id"]][$label["id"]]["name"] = $label["name"];
                }
                update_option( "dt_facebook_labels", $facebook_labels );
            }
        }
    }
    public function get_users_for_labels(){
        do_action( "dt_get_users_for_labels" );
    }
    public function get_users_for_labels_async(){
        $facebook_pages = get_option( "dt_facebook_pages", [] );
        $facebook_labels = get_option( "dt_facebook_labels", [] );
        foreach ( $facebook_pages as $page ){
            if ( isset( $page["integrate"] ) && $page["integrate"] == 1 ){
                $labels = $this->facebook_api( "page_labels", $page["id"], $page["access_token"] );
                if ( !isset( $facebook_labels[$page["id"]] ) ){
                    $facebook_labels[$page["id"]] = [];
                }
                foreach ( $labels as $label ){
                    if ( !isset( $facebook_labels[$page["id"]][$label["id"]] ) ){
                        $facebook_labels[$page["id"]][$label["id"]] = [];
                    }
                    $facebook_labels[$page["id"]][$label["id"]]["name"] = $label["name"];
                    if ( !empty( $facebook_labels[$page["id"]][$label["id"]]["sync"] )){
                        $users = $this->facebook_api( "label_users", $label["id"], $page["access_token"] );
                        $facebook_labels[$page["id"]][$label["id"]]["users"] = $users;
                        foreach ( $users as $user ){
                            $contacts = $this->find_contacts_with_facebook_ids( [ $user["id"] ] );
                            foreach ( $contacts as $contact_post ){
                                $contact = Disciple_Tools_Contacts::get_contact( $contact_post->ID, false );
                                $facebook_data = maybe_unserialize( $contact["facebook_data"] ) ?? [];
                                if ( !isset( $facebook_data["labels"] ) ){
                                    $facebook_data["labels"] = [];
                                }
                                $facebook_data["labels"][$label["id"]] = $label["name"];
                                Disciple_Tools_Contacts::update_contact( $contact["ID"], [ "facebook_data" => $facebook_data ] ,false );
                            }
                        }
                    }
                }
                update_option( "dt_facebook_labels", $facebook_labels );
            }
        }
    }




    public function facebook_labels_page()
    {

        ?>
        <div class="wrap">
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <?php
                        if ( isset( $_POST['_wpnonce'] ) && !wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'wp_rest' ) ) {
                            return 'Are you cheating? Where did this form come from?';
                        }
                        $page_id = 0;
                        if ( isset( $_POST["page-id"] ) ){
                            $page_id = esc_html( sanitize_text_field( wp_unslash( $_POST["page-id"] ) ) );
                        }
//                        $current_page = "";
//                        if ( isset( $_SERVER["REQUEST_URI"] ) ){
//                            $current_page = esc_html( sanitize_text_field( wp_unslash( $_SERVER["REQUEST_URI"] ) ) );
//                        }
                        $facebook_pages = get_option( "dt_facebook_pages", [] );
                        ?>


                        <form method="post">
                            <input type="hidden" name="_wpnonce" id="_wpnonce"
                                   value="<?php echo esc_html( wp_create_nonce( 'wp_rest' ) )?>"/>

                            <select name="page-id">
                                <option value="0"></option>
                            <?php


                            foreach ( $facebook_pages as $page ){
                                if ( !empty( $page["integrate"] ) ) {
                                    ?>
                                    <option value="<?php echo esc_html( $page["id"] ) ?>"
                                    <?php echo $page_id === $page["id"] ? "selected" : "" ?>
                                    ><?php echo esc_html( $page["name"] ) ?></option>
                                    <?php
                                }
                            }
                            ?>
                            </select>

                           <input type="submit" class="button" name="show_labels" value="Show Page Labels"/>


                        </form>

                        <br>

                        <?php
                        // Check noonce


                        if ( isset( $_POST["page-id"] ) ){
                            $facebook_labels = get_option( "dt_facebook_labels", [] );
                            if ( isset( $facebook_labels[$_POST["page-id"]] ) ){
                                ?>
                                <form method="post">
                                    <input type="hidden" name="_wpnonce" id="_wpnonce"
                                   value="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"/>
                                    <?php $this->facebook_settings_functions(); ?>
                                    <table id="facebook_labels" class="widefat striped">
                                        <thead>
                                            <th>Labels</th>
                                            <th>Sync Label</th>
                                        </thead>
                                        <tbody>

                                        <?php

                                        foreach ( $facebook_labels[ $page_id ] as $label_key => $label_value ){
                                            ?>
                                            <tr>
                                                <td><?php echo esc_html( $label_value["name"] . " (" . $label_key . ")" )?></td>
                                                <td>
                                                    <input name="<?php echo esc_attr( $label_key )  ?>"
                                                        type="checkbox"
                                                        <?php echo checked( 1, !empty( $label_value["sync"] ), false ); ?>
                                                        value="<?php echo esc_attr( $label_key ); ?>" />
                                                </td>
                                            </tr>
                                            <?php
                                        }
                                        ?>
                                        </tbody>
                                    </table>

                                   <input name="page-id" value="<?php echo esc_html( $page_id ); ?>" type="hidden"/>
                                   <input type="submit" class="button" name="save_labels" value="Save Labels"/>
                                </form>
                            <?php
                            }
                        }


                        $facebook_labels = get_option( "dt_facebook_labels", [] );
                        if ( isset( $_POST["save_labels"] ) && isset( $facebook_labels[$page_id] )){
                            foreach ( $facebook_labels[ $page_id ] as $label_key => $label_value ){
                                $facebook_labels[$page_id][$label_key]["sync"] = isset( $_POST[ $label_key ] );
                            }
                            update_option( "dt_facebook_labels", $facebook_labels );
                        }


                        ?>

                    </div><!-- end post-body-content -->

                </div><!-- post-body meta box container -->
            </div><!--poststuff end -->
        </div><!-- wrap end -->

        <?php
    }
}
