<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

/**
 * Class Disciple_Tools_Facebook_Integration
 */
class Disciple_Tools_Facebook_Integration {
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
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    } // End instance()

    private $version = 1.0;
    private $context = "dt_facebook";
    private $namespace;
    private $facebook_api_version = '3.2';

    /**
     * Constructor function.
     *
     * @access public
     * @since  0.1.0
     */
    public function __construct() {
        $this->namespace = $this->context . "/v" . intval( $this->version );
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
        add_action( 'wp_ajax_dt-facebook-notice-dismiss', [ $this, 'dismiss_error' ] );
        add_action( "dt_async_dt_conversation_update", [ $this, "get_conversation_update" ], 10, 2 );
        add_action( "dt_async_dt_facebook_all_conversations", [ $this, "get_conversations_with_pagination" ], 10, 4 );

        add_filter( 'cron_schedules', [ $this, 'my_cron_schedules' ] );
        add_action( 'updated_recent_conversations', [ $this, 'get_recent_conversations' ] );
    } // End __construct()

    /**
     * Setup the api routs for the plugin
     *
     * @since  0.1.0
     */
    public function add_api_routes() {
        register_rest_route(
            $this->namespace . "/dt-public/",
            'webhook',
            [
                'methods'  => 'POST',
                'callback' => [ $this, 'update_from_facebook' ],
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
            $this->namespace ."/dt-public/", "cron", [
                'methods'  => "GET",
                'callback' => [ $this, 'cron_hook' ],
            ]
        );
    }

    /**
     * Admin notice
     */
    public function dt_admin_notice() {
        $error = get_option( 'dt_facebook_error', "" );
        if ( $error ) { ?>
            <div class="notice notice-error dt-facebook-notice is-dismissible">
                <p><?php echo esc_html( $error ); ?></p>
            </div>
        <?php }
    }

    public function dismiss_error() {
        update_option( 'dt_facebook_error', "" );
    }

    public function my_cron_schedules( $schedules ) {
        if ( !isset( $schedules["5min"] ) ) {
            $schedules["5min"] = array(
                'interval' => 5 * 60,
                'display'  => __( 'Once every 5 minutes' )
            );
        }
        return $schedules;
    }

    /**
     * Render the Facebook Settings Page
     */
    public function facebook_settings_page() {

        $access_token = get_option( "disciple_tools_facebook_access_token", "" );

        ?>
        <p> This Facebook integration will provide a link between your facebook pages and D.T. </p>
        <p>When a contact messages you page, a record for them will be created automatically. Pretty cool right?</p>

<!--        <h3>--><?php //esc_html_e( "Link Disciple tools to a Facebook app in order to get contacts or useful stats from your Facebook pages.", 'dt_facebook' ) ?><!--</h3>-->

        To get started, head over to the instructions tab where we'll help you get a couple things set up:
        <ul style="list-style-type: disc; padding-left:40px">
            <li>A facebook app</li>
            <li>Facebook Business Manager</li>
            <li>Uptime Robot</li>
        </ul>

        <p>When you those steps you can use the new information below</p>
        <div class="wrap">
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">

                        <form action="<?php echo esc_url( $this->get_rest_url() ); ?>/add-app" method="post">
                            <input type="hidden" name="_wpnonce" id="_wpnonce"
                                   value="<?php echo esc_html( wp_create_nonce( 'wp_rest' ) ) ?>"/>



                            <table class="widefat striped">

                                <thead>
                                <tr>
                                    <th><?php esc_html_e( "Facebook App Settings", 'dt_facebook' ) ?></th>
                                    <th></th>
                                </tr>
                                </thead>

                                <tbody>

                                <tr>
                                    <td><?php esc_html_e( "Facebook App Id", 'dt_facebook' ) ?></td>
                                    <td>
                                        <input title="App Id" type="text" class="regular-text" name="app_id"
                                               value="<?php echo esc_attr( get_option( "disciple_tools_facebook_app_id", "" ) ); ?>"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td><?php esc_html_e( "Facebook App Secret", 'dt_facebook' ) ?></td>
                                    <td>
                                        <?php
                                        $secret = get_option( "disciple_tools_facebook_app_secret", "" );
                                        if ( $secret != "" ) {
                                            $secret = "app_secret";
                                        }
                                        ?>
                                        <input title="App Secret" type="<?php echo $secret ? "password" : "text" ?>"
                                               class="regular-text" name="app_secret"
                                               value="<?php echo esc_attr( $secret ); ?>"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td><?php esc_html_e( "Access Token", 'dt_facebook' ) ?></td>
                                    <td>
                                        <?php echo( !empty( $access_token ) ? esc_html__( 'Access token is saved', 'dt_facebook' ) : esc_html__( 'No Access Token', 'dt_facebook' ) ) ?>
                                    </td>
                                </tr>

                                <tr>
                                    <td><?php esc_html_e( "Save or Refresh", 'dt_facebook' ) ?></td>
                                    <td><button type="submit" class="button" name="save_app" style="padding:3px">
                                            <img style="height: 100%;" src="<?php echo esc_html( plugin_dir_url( __FILE__ ) . 'assets/flogo_RGB_HEX-72.svg' ) ?>"/>
                                            <span style="vertical-align: top"><?php esc_html_e( "Login with Facebook", 'dt_facebook' ) ?></span></button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                    </td>
                                    <td><p><?php esc_html_e( 'Note: You will need to re-authenticate (by clicking the "Login with Facebook" button again) if:', 'dt_facebook' ) ?></p>
                                        <ul style="list-style-type: disc; padding-left:40px">
                                            <li><?php esc_html_e( "You change your Facebook account password", 'dt_facebook' ) ?></li>
                                            <li><?php esc_html_e( "You delete or de­authorize your Facebook App", 'dt_facebook' ) ?></li>
                                        </ul></td>
                                </tr>
                                </tbody>
                            </table>
                        </form>



                        <br>
                        <p>To sync your facebook conversation to D.T check the "Sync Contacts" checkbox next to the pages you to enable the integration with</p>
                        <form action="" method="post">
                            <input type="hidden" name="_wpnonce" id="_wpnonce"
                                   value="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"/>
                            <?php $this->facebook_settings_functions(); ?>
                            <table id="facebook_pages" class="widefat striped">
                                <thead>
                                <tr>
                                    <th><?php esc_html_e( "Facebook Pages", 'dt_facebook' ) ?></th>
                                    <th><?php esc_html_e( "Sync Contacts", 'dt_facebook' ) ?></th>
<!--                                    <th>--><?php //esc_html_e( "Include in Stats", 'dt_facebook' ) ?><!--</th>-->
                                    <th><?php esc_html_e( "Part of Business Manager", 'dt_facebook' ) ?></th>
                                    <th><?php esc_html_e( "Digital Responder", 'dt_facebook' ) ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                $user_for_page = dt_get_base_user();
                                $potential_user_list = get_users(
                                    [
                                        'role__in' => [ 'dispatcher', 'marketer', 'dt_admin' ],
                                        'order'    => 'ASC',
                                        'orderby'  => 'display_name',
                                    ]
                                );
                                $facebook_pages = get_option( "dt_facebook_pages", [] );

                                foreach ( $facebook_pages as $id => $facebook_page ){ ?>
                                <tr>
                                    <td><?php echo esc_html( $facebook_page["name"] ); ?>
                                        (<?php echo esc_html( $facebook_page["id"] ); ?>)
                                    </td>
                                    <td>
                                        <input title="Integrate"
                                               name="<?php echo esc_attr( $facebook_page["id"] ) . "-integrate"; ?>"
                                               type="checkbox"
                                               value="<?php echo esc_attr( $facebook_page["id"] ); ?>" <?php echo checked( 1, isset( $facebook_page["integrate"] ) ? $facebook_page["integrate"] : false, false ); ?> />
                                    </td>
<!--                                    <td>-->
<!--                                        <input title="Report"-->
<!--                                               name="--><?php //echo esc_attr( $facebook_page["id"] ) . "-report"; ?><!--"-->
<!--                                               type="checkbox"-->
<!--                                               value="--><?php //echo esc_attr( $facebook_page["id"] ); ?><!--" --><?php //echo checked( 1, isset( $facebook_page["report"] ) ? $facebook_page["report"] : false, false ); ?><!-- />-->
<!--                                    </td>-->
                                    <td>
                                        <input title="In Business Manager" disabled
                                               type="checkbox"
                                            <?php echo checked( 1, isset( $facebook_page["business"] ), false ); ?> />
                                    </td>
                                    <td>
                                        <?php
                                        if ( isset( $facebook_page["assign_to"] )){
                                            $user_for_page = get_user_by( "ID", $facebook_page["assign_to"] );
                                        }
                                        ?>
                                        <select name="<?php echo esc_attr( $facebook_page["id"] ); ?>-assign_new_contacts_to">
                                            <option value="<?php echo esc_attr( $user_for_page->ID ) ?>"><?php echo esc_attr( $user_for_page->display_name ) ?></option>
                                            <option disabled>---</option>
                                            <?php foreach ( $potential_user_list as $potential_user ) : ?>
                                                <option value="<?php echo esc_attr( $potential_user->ID ) ?>"><?php echo esc_attr( $potential_user->display_name ) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <?php if ( !isset( $facebook_page["reached_the_end"] ) && isset( $facebook_page["integrate"] ) && $facebook_page["integrate"] === 1 && isset( $facebook_page["access_token"] ) ) : ?>
                                        <form action="" method="post">
                                            <input type="hidden" name="_wpnonce" id="_wpnonce"
                                                   value="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"/>

                                            <input type="hidden" class="button" name="page_id" value="<?php echo esc_attr( $facebook_page["id"] ); ?>" />
                                            <button type="submit" name="get_recent_conversations"><?php esc_html_e( "Get all conversations (launches in the background. This might take a while)", 'dt_facebook' ) ?></button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php } ?>
                                <?php if ( empty( $access_token ) ) :?>
                                    <tr>
                                        <td>Login to list your facebook pages</td>
                                    </tr>
                                <?php elseif ( sizeof( $facebook_pages ) === 0 ) : ?>
                                    <tr>
                                        <td>No pages where found.</td>
                                        <td></td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                            <input type="submit" class="button" name="get_pages"
                                   value="<?php esc_html_e( "Refresh Page List", 'dt_facebook' ) ?>"/>
                            <input type="submit" class="button" name="save_pages"
                                   value="<?php esc_html_e( "Save Pages Settings", 'dt_facebook' ) ?>"/>
                            <br>



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
    private function display_error( $err ) {
        $err = date( "Y-m-d h:i:sa" ) . ' ' . $err; ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html( $err ); ?></p>
        </div>
        <?php
        dt_write_log( $err );
        update_option( 'dt_facebook_error', $err );
    }

    /**
     * Functions for the pages section of the Facebook settings
     */
    public function facebook_settings_functions() {
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
            $facebook_pages      = get_option( "dt_facebook_pages", [] );
            $dt_custom_lists     = dt_get_option( 'dt_site_custom_lists' );
            foreach ( $facebook_pages as $id => $facebook_page ) {
                //if sync contact checkbox is selected
                $integrate = str_replace( ' ', '_', $facebook_page["id"] . "-integrate" );
                if ( isset( $_POST[ $integrate ] ) ) {
                    $facebook_pages[ $id ]["integrate"] = 1;
                    if ( !isset( $dt_custom_lists["sources"][ $id ] ) ) {
                        $dt_custom_lists["sources"][ $id ] = [
                            'label'       => $facebook_page["name"],
                            'key'         => $id,
                            'type'        => "facebook",
                            'description' => 'Contacts coming from Facebook page: ' . $facebook_page["name"],
                            'enabled'     => true,
                        ];
                        update_option( "dt_site_custom_lists", $dt_custom_lists );
                    }
                    if ( !wp_next_scheduled( 'updated_recent_conversations' ) ) {
                        wp_schedule_event( time(), '5min', 'updated_recent_conversations' );
                    }
                } else {
                    $facebook_pages[ $id ]["integrate"] = 0;
                }
                //if the include in stats checkbox is selected
                $report = str_replace( ' ', '_', $facebook_page["id"] . "-report" );
                if ( isset( $_POST[ $report ] ) ) {
                    if ( !isset( $facebook_pages[ $id ]["report"] ) || $facebook_pages[ $id ]["report"] == 0 ) {
                        $get_historical_data = true;
                    }
                    $facebook_pages[ $id ]["report"]  = 1;
                    $facebook_pages[ $id ]["rebuild"] = true;
                } else {
                    $facebook_pages[ $id ]["report"] = 0;
                }
                //set the user new facebook contacts should be assigned to.
                $assign_to = str_replace( ' ', '_', $facebook_page["id"] . "-assign_new_contacts_to" );
                if ( isset( $_POST[$assign_to] ) ){
                    $facebook_pages[$id]["assign_to"] = sanitize_text_field( wp_unslash( $_POST[ $assign_to ] ) );
                }
            }
            update_option( "dt_facebook_pages", $facebook_pages );
            //if a new page is added, get the reports for that page.
            if ( $get_historical_data === true ) {
                do_action( "dt_facebook_stats" );
            }
        }


        if ( isset( $_POST["get_recent_conversations"], $_POST["page_id"] ) ){
            $id = sanitize_text_field( wp_unslash( $_POST["page_id"] ) );
            $facebook_pages = get_option( "dt_facebook_pages", [] );
            $facebook_pages[$id]["last_contact_id"] = null;
            $facebook_pages[$id]["last_paging_cursor"] = null;
            $facebook_pages[$id]["latest_conversation"] = null;
            $facebook_pages[$id]["reached_the_end"] = null;
            update_option( "dt_facebook_pages", $facebook_pages );

            $this->get_recent_conversations( $id );
        }
    }

    public function get_rest_url() {
        return get_site_url() . "/wp-json/" . $this->namespace;
    }


    /**
     * Facebook Authentication
     */

    // Generate authorization secret
    public static function authorize_secret() {
        return 'dt_auth_' . substr( md5( AUTH_KEY ? AUTH_KEY : get_bloginfo( 'url' ) ), 0, 10 );
    }

    /**
     * Refresh list of saved pages
     *
     * @param $access_token
     *
     * @return mixed
     */

    private function get_or_refresh_pages( $access_token ) {

        $facebook_pages_url = "https://graph.facebook.com/v" . $this->facebook_api_version . "/me/accounts?fields=access_token,id,name,business&access_token=" . $access_token;
        $pages_request      = wp_remote_get( $facebook_pages_url );


        if ( is_wp_error( $pages_request ) ) {
            $this->display_error( $pages_request->get_error_message() );
            echo "There was an error";
        } else {
            $pages_body = wp_remote_retrieve_body( $pages_request );
            $pages_data = json_decode( $pages_body, true );
            if ( !empty( $pages_data ) ) {
                if ( isset( $pages_data["data"] ) ) {
                    $pages = get_option( "dt_facebook_pages", [] );
                    foreach ( $pages_data["data"] as $page ) {
                        if ( !isset( $pages[ $page["id"] ] ) ) {
                            $pages[ $page["id"] ] = $page;
                        } else {
                            $pages[ $page["id"] ]["access_token"] = $page["access_token"];
                            $pages[ $page["id"] ]["name"]         = $page["name"];
                            if ( isset( $page["business"] ) ) {
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
     * @return array
     */
    public function authenticate_app( $get ) {

        //get the access token

        if ( isset( $get["state"] ) && strpos( $get['state'], $this->authorize_secret() ) !== false && isset( $get["code"] ) ) {
            $url = "https://graph.facebook.com/v" . $this->facebook_api_version . "/oauth/access_token";
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
    public function add_app() {
        // Check noonce
        if ( isset( $_POST['_wpnonce'] ) && !wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'wp_rest' ) ) {
            return 'Are you cheating? Where did this form come from?';
        }
        if ( current_user_can( "manage_dt" ) && check_admin_referer( 'wp_rest' ) && isset( $_POST["save_app"] ) && isset( $_POST["app_secret"] ) && isset( $_POST["app_id"] ) ) {
            update_option( 'disciple_tools_facebook_app_id', sanitize_key( $_POST["app_id"] ) );
            $secret = sanitize_key( $_POST["app_secret"] );
            if ( $secret !== "app_secret" ) {
                update_option( 'disciple_tools_facebook_app_secret', $secret );
            }
            delete_option( 'disciple_tools_facebook_access_token' );

            $url = "https://facebook.com/v" . $this->facebook_api_version . "/dialog/oauth";
            $url .= "?client_id=" . sanitize_key( $_POST["app_id"] );
            $url .= "&redirect_uri=" . $this->get_rest_url() . "/auth";
            $url .= "&scope=public_profile,read_insights,manage_pages,read_page_mailboxes,business_management";
            $url .= "&state=" . $this->authorize_secret();

            wp_redirect( $url );
            exit;
        }

        return "ok";
    }

    //legacy
    public function update_from_facebook() {
        return;
    }

    //The app secret proof is a sha256 hash of your access token, using the app secret as the key.
    public function get_app_secret_proof( $access_token ) {
        $app_secret       = get_option( "disciple_tools_facebook_app_secret" );
        $app_secret_proof = hash_hmac( 'sha256', $access_token, $app_secret );

        return $app_secret_proof;
    }

    public function get_page_scoped_ids( $used_id, $access_token ) {
        $app_secret_proof  = $this->get_app_secret_proof( $access_token );
        $ids_for_pages_uri = "https://graph.facebook.com/v" . $this->facebook_api_version . "/$used_id?fields=name,ids_for_pages&access_token=$access_token&appsecret_proof=$app_secret_proof";
        $response          = wp_remote_get( $ids_for_pages_uri );
        $ids               = json_decode( $response["body"], true );
        $ids_for_pages     = [];
        if ( isset( $ids["ids_for_pages"] ) && isset( $ids["ids_for_pages"]["data"] ) ) {
            foreach ( $ids["ids_for_pages"]["data"] as $user ) {
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
     * @param $updated_time , the time of the last message
     * @param $page , the facebook page where the conversation is happening
     *
     * @param $conversation
     *
     * @return int|null|WP_Error contact_id
     */
    private function update_or_create_contact( $participant, $updated_time, $page, $conversation ) {
        //get page scoped ids available by using a Facebook business manager
        $page_scoped_ids = [];

        $app_id = get_option( "disciple_tools_facebook_app_id", null );

        $contacts = dt_facebook_find_contacts_with_ids( $page_scoped_ids, $participant["id"], $app_id );

        //if no contact saved make sure they did not visit us from a different page first
        if ( sizeof( $contacts ) == 0 && isset( $page["business"] ) ) {
            $page_scoped_ids = $this->get_page_scoped_ids( $participant["id"], $page["access_token"] );
            $contacts = dt_facebook_find_contacts_with_ids( $page_scoped_ids, $participant["id"], $app_id );
        }
        $contact_id   = null;

        if ( sizeof( $contacts ) > 1 ) {
            foreach ( $contacts as $contact_post ) {
                $contact = Disciple_Tools_Contacts::get_contact( $contact_post->ID, false );
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
            $contact                          = Disciple_Tools_Contacts::get_contact( $contact_id, false );
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
            if ( $facebook_data != $initial_facebook_data ) {
                Disciple_Tools_Contacts::update_contact( $contact_id, [ "facebook_data" => $facebook_data ], false );
            }
            return $contact_id;
        } else if ( !$contact_id ) {
            $fields = [
                "title"            => $participant["name"],
                "source_details"   => "Facebook Page: " . $page["name"],
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
            ];
            if ( isset( $page["assign_to"] )){
                $fields["assigned_to"] = $page["assign_to"];
            }
            $new_contact_id = Disciple_Tools_Contacts::create_contact( $fields, false );
            if ( is_wp_error( $new_contact_id ) ){
                dt_send_email( "corsacca@gmail.com", "fb create failed", serialize( $fields ) );
            }
            return $new_contact_id;
        }
    }


    public function get_conversations_with_pagination( $url, $id, $latest_conversation = 0, $limit_to_one = false, $depth = 0 ) {
        $conversations_request = wp_remote_get( $url );
        if ( !is_wp_error( $conversations_request ) ) {
            $conversations_body = wp_remote_retrieve_body( $conversations_request );
            $conversations_page = json_decode( $conversations_body, true );
            if ( !empty( $conversations_page ) && isset( $conversations_page["data"] ) ) {
                $this->save_conversation_page( $conversations_page["data"], $id, $latest_conversation );
                if ( isset( $conversations_page["paging"]["next"] ) ){
                    $oldest_conversation = end( $conversations_page["data"] );
                    if ( strtotime( $oldest_conversation["updated_time"] ) >= $latest_conversation && !$limit_to_one ){
                        do_action( "dt_facebook_all_conversations", $conversations_page["paging"]["next"], $id, $latest_conversation, $limit_to_one, $depth + 1 );
                    }
                } else {
                    $facebook_pages = get_option( "dt_facebook_pages", [] );
                    $facebook_pages[$id]["reached_the_end"] = time();
                    update_option( "dt_facebook_pages", $facebook_pages );
                    $facebook_conversations_url = "https://graph.facebook.com/v" . $this->facebook_api_version . "/$id/conversations?fields=link,message_count,messages.limit(500){from,created_time,message},participants,updated_time&access_token=" . $facebook_pages[$id]["access_token"];
                    if ( $depth !== 0 && ! $limit_to_one ){
                        $this->get_conversations_with_pagination( $facebook_conversations_url, $id, $latest_conversation, true, 0 );
                    }
                }
            } else {
                $this->display_error( $conversations_request->get_error_message() );
            }
        } else {
            $this->display_error( $conversations_request->get_error_message() );
        }
    }

    public function get_all_with_pagination( $url ) {
        $request = wp_remote_get( $url );

        if ( is_wp_error( $request ) ) {
            return [];
        } else {
            $body = wp_remote_retrieve_body( $request );
            $page = json_decode( $body, true );
            if ( !empty( $page ) ) {
                if ( !isset( $page["paging"]["next"] ) ){
                    return $page["data"];
                } else {
                    $next_page = $this->get_all_with_pagination( $page["paging"]["next"] );
                    return array_merge( $page["data"], $next_page );
                }
            } else {
                return [];
            }
        }
    }

    public function get_recent_conversations( $page_id = null ){
        $facebook_pages      = get_option( "dt_facebook_pages", [] );
        foreach ( $facebook_pages as $id => $facebook_page ) {
            if ( isset( $facebook_page["integrate"] ) && $facebook_page["integrate"] === 1 && isset( $facebook_page["access_token"] )){
                if ( !$page_id ){
                    //get conversations
                    wp_remote_get( $this->get_rest_url() . "/dt-public/cron?page=" . $id );
                } else if ( $id == $page_id ) {
                    $latest_conversation = $facebook_page["latest_conversation"] ?? 0;
                    $facebook_conversations_url = "https://graph.facebook.com/v" . $this->facebook_api_version . "/$id/conversations?fields=link,message_count,messages.limit(500){from,created_time,message},participants,updated_time&access_token=" . $facebook_page["access_token"];
                    do_action( "dt_facebook_all_conversations", $facebook_conversations_url, $id, $latest_conversation );
                }
            }
        }
    }

    public function save_conversation_page( $conversations, $id, $latest_conversation = 0 ){
        $facebook_pages = get_option( "dt_facebook_pages", [] );
        $page = $facebook_pages[$id];
        foreach ( $conversations as $conversation ){
            if ( strtotime( $conversation["updated_time"] ) >= $latest_conversation ){
                foreach ( $conversation["participants"]["data"] as $participant ) {
                    if ( (string) $participant["id"] != $id ) {
                        $contact_id = $this->update_or_create_contact( $participant, $conversation["updated_time"], $page, $conversation );
                        if ( $contact_id ){
                            $facebook_pages = get_option( "dt_facebook_pages", [] );
                            $facebook_pages[$id]["last_contact_id"] = $contact_id;
                            update_option( "dt_facebook_pages", $facebook_pages );
                            $this->update_facebook_messages_on_contact( $contact_id, $conversation );
                            $this->set_notes_on_conversation( $contact_id, $page, $participant["id"] );
                        }
                    }
                }
            }
        }
        $facebook_pages = get_option( "dt_facebook_pages", [] );
        $new_latest_conversation = isset( $conversations[0]["updated_time"] ) ? strtotime( $conversations[0]["updated_time"] ) : 0;
        if ( isset( $facebook_pages[$id]["reached_the_end"] ) && $facebook_pages[$id]["reached_the_end"] != null && $latest_conversation < $new_latest_conversation && $facebook_pages[$id]["latest_conversation"] < $new_latest_conversation ){
            $facebook_pages[$id]["latest_conversation"] = $new_latest_conversation;
            update_option( "dt_facebook_pages", $facebook_pages );
        }
    }



    public function cron_hook( WP_REST_Request $request ){
        $params = $request->get_params();
        $id = $params["page"] ?? null;
        $this->get_recent_conversations( $id );
        return time();
    }


    public function update_facebook_messages_on_contact( $contact_id, $conversation ){
        $facebook_data = maybe_unserialize( get_post_meta( $contact_id, "facebook_data", true ) ) ?? [];
        $message_count = $conversation["message_count"];
        $number_of_messages = sizeof( $conversation["messages"]["data"] );
        $saved_number = $facebook_data["message_count"] ?? 0;
        $messages = $conversation["messages"]["data"];
        $saved_ids = $facebook_data["message_ids"] ?? [];
        if ( $message_count != $saved_number && $message_count > $number_of_messages && isset( $conversation["messages"]["paging"]["next"] )){
            $all_convs = $this->get_all_with_pagination( $conversation["messages"]["paging"]["next"] );
            $messages = array_merge( $all_convs, $messages );
        }
        if ( $message_count != $saved_number ){
            foreach ( $messages as  $message ){
                if ( !in_array( $message["id"], $saved_ids )){
                    $comment = $message["message"];
                    $image = "https://graph.facebook.com/" . $message['from']['id'] . "/picture?type=square";
                    Disciple_Tools_Contacts::add_comment( $contact_id, $comment, "facebook", [
                        "user_id" => 0,
                        "comment_author" => $message["from"]["name"],
                        "comment_date" => $message["created_time"],
                        "comment_author_url" => $image
                    ], false, true );
                    $saved_ids[] = $message["id"];
                }
            }
            $facebook_data["message_count"] = $message_count;
            $facebook_data["message_ids"] = $saved_ids;
            update_post_meta( $contact_id, "facebook_data", $facebook_data );
        }
    }

    public function set_notes_on_conversation( $contact_id, $page, $participant_id ){
        $facebook_data = maybe_unserialize( get_post_meta( $contact_id, "facebook_data", true ) ) ?? [];

        if ( !isset( $facebook_data["set_dt_link"] ) ){
            $dt_link = "DT link: " . esc_url( get_site_url() ) . '/contacts/' . $contact_id;
            $this->set_note_on_conversation( $page["id"], $participant_id, $dt_link, $page["access_token"] );
            $facebook_data["set_dt_link"] = true;
            update_post_meta( $contact_id, "facebook_data", $facebook_data );
        }
    }

    public function set_note_on_conversation( $page_id, $user_id, $note, $access_token ){
        $url = 'https://graph.facebook.com/v' . $this->facebook_api_version . '/ ' . $page_id . "/admin_notes?access_token=" . $access_token;
        return wp_remote_post( $url, [
            "body" => [
                "body" => $note,
                "user_id" => $user_id
            ]
        ]);
    }
}
