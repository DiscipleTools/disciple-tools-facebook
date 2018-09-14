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
        add_filter( "dt_custom_fields_settings", [ $this, "dt_facebook_fields" ], 1, 2 );
        add_filter( "dt_details_additional_section_ids", [ $this, "dt_facebook_declare_section_id" ], 999, 2 );
        add_action( "dt_details_additional_section", [ $this, "dt_facebook_add_section" ] );
        add_action( "dt_async_dt_conversation_update", [ $this, "get_conversation_update" ], 10, 2 );
        add_action( "dt_async_dt_facebook_all_conversations", [ $this, "get_conversations_with_pagination" ], 10, 4 );
        add_filter( "dt_contact_duplicate_fields_to_check", [ $this, "add_duplicate_check_field" ] );
        add_filter( "dt_comments_additional_sections", [ $this, "add_comment_section" ], 10, 2 );
        add_filter( "dt_search_extra_post_meta_fields", [ $this, "add_fields_in_dt_search" ] );

    } // End __construct()

    /**
     * Setup the api routs for the plugin
     *
     * @since  0.1.0
     */
    public function add_api_routes() {

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
            $this->namespace ."/dt-public/", "cron", [
                'methods'  => "GET",
                'callback' => [ $this, 'cron_hook' ],
            ]
        );
    }


    public static function dt_facebook_fields( array $fields, string $post_type = "" ) {
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


    public static function dt_facebook_declare_section_id( $sections, $post_type = "" ) {
        //check if we are on a contact
        if ( $post_type === "contacts" ) {
            $contact_fields = Disciple_Tools_Contact_Post_Type::instance()->get_custom_fields_settings();
            //check if the language field is set
            if ( isset( $contact_fields["facebook_data"] ) ) {
                $sections[] = "contact_facebook_data";
            }
            //add more section ids here if you want...
        }
        return $sections;
    }


    public static function dt_facebook_add_section( $section ) {
        if ( $section == "contact_facebook_data" ) {
            $contact_id    = get_the_ID();
            $contact       = Disciple_Tools_Contacts::get_contact( $contact_id, true );
            $facebook_data = [];
            if ( isset( $contact["facebook_data"] ) ) {
                $facebook_data = maybe_unserialize( $contact["facebook_data"] );
            }
            ?>
            <!-- need you own css? -->
            <style type="text/css">
                .facebook-label {
                    background: #ecf5fc;
                    padding: 2px 4px;
                    border-radius: 2px;
                    border: 1px solid #c2e0ff;
                }
            </style>

            <label class="section-header">
                <?php esc_html_e( 'Facebook', 'dt_facebook' ) ?>
            </label>


            <?php
            if ( isset( $facebook_data["names"] ) ) {
                ?>
                <div class="section-subheader">
                    <?php esc_html_e( "Names", "dt_facebook" ) ?>
                </div>
                <?php
                if ( is_array( $facebook_data["names"] ) ) {
                    foreach ( $facebook_data["names"] as $id ) {
                        ?>
                        <p><?php echo esc_html( $id ) ?></p>
                    <?php }
                }
            }
            if ( isset( $facebook_data["labels"] ) ) {
                ?>
                <div class="section-subheader">
                    <?php esc_html_e( "Labels", "dt_facebook" ) ?>
                </div>
                <p>
                <?php foreach ( $facebook_data["labels"] as $id => $value ) {
                    ?>
                    <span class="facebook-label"><?php echo esc_html( $id ) ?></span>
                <?php }
                ?></p><?php
            }

            if ( isset( $facebook_data["last_message_at"] ) ) {
                $date = strtotime( $facebook_data["last_message_at"] )
                ?>
                <div class="section-subheader">
                    <?php esc_html_e( "Last massage at:", "dt_facebook" ) ?>
                </div>
                <p class="last_message_at"><?php echo esc_html( date( "Y-m-d H:m", $date ) ) ?></p>
                <?php
            }

            if ( isset( $facebook_data["links"] ) ) {
                ?>
                <div class="section-subheader">
                    <?php esc_html_e( "Conversation Links:", "dt_facebook" ) ?>
                </div>
                <?php
                foreach ( $facebook_data["links"] as $link ): ?>
                    <p class="facebook_message_links"><a href="<?php echo esc_html( 'http://facebook.com'. $link ) ?>" target="_blank"><?php echo esc_html( $link )?></a></p>
                <?php endforeach; ?>
                <?php
            }




//            foreach ( $facebook_data as $key => $value ){
//
            ?>
            <!--                <div class="section-subheader">-->
            <!--                    --><?php //echo esc_html( $key )
            ?>
            <!--                </div>-->
            <!--                --><?php
//                if ( is_array( $value )){
//                    foreach ( $value as $id ){
//
            ?>
            <!--                        <p>--><?php //echo esc_html( $id )
            ?><!--</p>-->
            <!--                        --><?php
//                    }
//                } else {
//
            ?>
            <!--                    <p>--><?php //echo esc_html( $value )
            ?><!--</p>-->
            <!--                    --><?php
//                }
//            }

            ?>

            <script type="application/javascript">
                //enter jquery here if you need it
                jQuery(($) => {
                    <?php if ( isset( $facebook_data["last_message_at"] ) ) : ?>
                    $('.last_message_at').html(
                        moment("<?php echo esc_html( $facebook_data["last_message_at"] ) ?>").format(
                            "MMMM Do YYYY, h:mm:ss a"))
                    <?php endif; ?>
                })
            </script>
            <?php
        }

        //add more sections here if you want...
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


    /**
     * Render the Facebook Settings Page
     */
    public function facebook_settings_page() {

        ?>
        <h3><?php esc_html_e( "Link Disciple tools to a Facebook app in order to get contacts or useful stats from your Facebook
            pages.", 'dt_facebook' ) ?></h3>
        <div class="wrap">
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <strong><?php esc_html_e( "Create Facebook App", 'dt_facebook' ) ?></strong>
                        <ul style="list-style-type: disc; padding-left:40px">
                            <li><?php esc_html_e( "Go to:", 'dt_facebook' ) ?>
                                <a href="https://developers.facebook.com/apps">https://developers.facebook.com/apps</a>
                            </li>
                            <li><?php esc_html_e( 'Click the "Add new app" button', 'dt_facebook' ) ?></li>
                            <li><?php esc_html_e( 'You can name the app "D.T integration"', 'dt_facebook' ) ?></li>
                            <li><?php esc_html_e( 'You should be on the "Add a Product screen." Click "Set Up" on the "Facebook Login" box', 'dt_facebook' ) ?></li>
                            <li><?php esc_html_e( 'Choose the "Other" option', 'dt_facebook' ) ?></li>
                            <li><?php esc_html_e( 'On the left click settings under Facebook Login', 'dt_facebook' ) ?></li>
                            <li><?php esc_html_e( 'In the "Valid OAuth Redirect URIs" field add:', 'dt_facebook' ) ?> <strong><?php echo esc_url( $this->get_rest_url() . "/auth" ); ?></strong></li>
                            <li><?php esc_html_e( 'Click Settings on the left (right under Dashboard) and the "Basic". ', 'dt_facebook' ) ?></li>
                            <li><?php esc_html_e( 'In the "App Domains box put":', 'dt_facebook' ) ?> <strong><?php echo esc_url( get_site_url() ); ?></strong></li>
                            <li><?php esc_html_e( 'Scroll down. Click Add Platform. Choose Website. In "Site URL" put:', 'dt_facebook' ) ?> <strong><?php echo esc_url( get_site_url() ); ?></strong></li>
                            <li><?php esc_html_e( 'Save Changes', 'dt_facebook' ) ?></li>
                            <li><?php esc_html_e( 'Keep your app in development mode. Don\'t make it public', 'dt_facebook' ) ?></li>
                            <li>
                                <?php esc_html_e( 'In Settings > Basic: Get the APP ID and the APP SECRET, enter them in below and click "Login with Facebook"', 'dt_facebook' ) ?>
                            </li>
                            <!-- For making pubic
                            <li><?php esc_html_e( 'In Privacy Policy Url put:', 'dt_facebook' ) ?> https://github.com/DiscipleTools/disciple-tools-facebook/blob/master/privacy.md</li>
                            <li><?php esc_html_e( 'Under Category choose "Business and Pages"', 'dt_facebook' ) ?></li>
                            -->
                        </ul>
                        <strong><?php esc_html_e( "Associate your app with Business Manager to help track contacts", 'dt_facebook' ) ?></strong>
                        <p>We strongly recommend you set up facebook business manager if you have not already.  <a href="https://www.facebook.com/business/help/1710077379203657">More Info</a> </p>
                        To associate you new app with your business manager account:
                        <ul style="list-style-type: disc; padding-left:40px">
                            <li>Open <a href="https://beta.mailbutler.io/tracking/hit/92221EF4-CA16-45D2-B2BC-25BAE8DB97E9/4DE418B3-6A4F-47A1-947F-64E5B46028A7/?notrack=true">Business Settings</a></li>
                            <li>Under Data Sources click Apps.</li>
                            <li>Click Add New App and select Add an App</li>
                            <li>Enter the Facebook App ID from the app you just created.</li>
                        </ul>


                        <?php esc_html_e( 'Note: You will need to re-authenticate (by clicking the "Save App Settings" button bellow) if:', 'dt_facebook' ) ?>
                        <ul style="list-style-type: disc; padding-left:40px">
                            <li><?php esc_html_e( "You change your Facebook account password", 'dt_facebook' ) ?></li>
                            <li><?php esc_html_e( "You delete or de­authorize your Facebook App", 'dt_facebook' ) ?></li>
                        </ul>

                        <strong><?php esc_html_e( "Set up cron to get contacts every 5 minutes", 'dt_facebook' ) ?></strong>
                        <ul style="list-style-type: disc; padding-left:40px">
                            <li><a href="https://uptimerobot.com/"><?php esc_html_e( "Sign up for a Uptime Robot Account", 'dt_facebook' ) ?></a></li>
                            <li><?php esc_html_e( "Once logged in. Click Add New Monitor", 'dt_facebook' ) ?></li>
                            <li><?php esc_html_e( "Monitor type: HTTP(s)", 'dt_facebook' ) ?></li>
                            <li><?php esc_html_e( "Friendly Name: Facebook Cron", 'dt_facebook' ) ?></li>
                            <li><?php esc_html_e( "Url:", 'dt_facebook' ) ?> <strong><?php echo esc_url( $this->get_rest_url() . "/dt-public/cron" ); ?></strong></li>
                            <li><?php esc_html_e( "Monitoring Interval: 5 mins", 'dt_facebook' ) ?></li>
                            <li><?php esc_html_e( "Click Create Monitor", 'dt_facebook' ) ?></li>
                        </ul>

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
                                        <?php echo( get_option( "disciple_tools_facebook_access_token" ) ? esc_html__( 'Access token is saved', 'dt_facebook' ) : esc_html__( 'No Access Token', 'dt_facebook' ) ) ?>
                                    </td>
                                </tr>

                                <tr>
                                    <td><?php esc_html_e( "Save or Refresh", 'dt_facebook' ) ?></td>
                                    <td><button type="submit" class="button" name="save_app" style="padding:3px">
                                            <img style="height: 100%;" src="<?php echo esc_html( plugin_dir_url( __FILE__ ) . 'assets/flogo_RGB_HEX-72.svg' ) ?>"/>
                                            <span style="vertical-align: top"><?php esc_html_e( "Login with Facebook", 'dt_facebook' ) ?></span></button>
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
                                    <th><?php esc_html_e( "Facebook Pages", 'dt_facebook' ) ?></th>
                                    <th><?php esc_html_e( "Sync Contacts", 'dt_facebook' ) ?></th>
                                    <th><?php esc_html_e( "Include in Stats", 'dt_facebook' ) ?></th>
                                    <th><?php esc_html_e( "Part of Business Manager", 'dt_facebook' ) ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                $facebook_pages = get_option( "dt_facebook_pages", [] );

                                foreach ( $facebook_pages

                                as $id => $facebook_page ){
                                ?>
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
                                    <td>
                                        <input title="Report"
                                               name="<?php echo esc_attr( $facebook_page["id"] ) . "-report"; ?>"
                                               type="checkbox"
                                               value="<?php echo esc_attr( $facebook_page["id"] ); ?>" <?php echo checked( 1, isset( $facebook_page["report"] ) ? $facebook_page["report"] : false, false ); ?> />
                                    </td>
                                    <td>
                                        <input title="In Business Manager" disabled
                                               type="checkbox"
                                            <?php echo checked( 1, isset( $facebook_page["business"] ), false ); ?> />
                                    </td>
                                    <?php } ?>
                                </tbody>
                            </table>
                            <input type="submit" class="button" name="get_pages"
                                   value="<?php esc_html_e( "Refresh Page List", 'dt_facebook' ) ?>"/>
                            <input type="submit" class="button" name="save_pages"
                                   value="<?php esc_html_e( "Save Pages Settings", 'dt_facebook' ) ?>"/>
                            <br>
                            <button type="submit" class="button" name="get_recent_conversations">
                                <?php esc_html_e( "Get recent conversations (launches in the background. This might take a while)", 'dt_facebook' ) ?>
                            </button>


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
                //Add the page to the apps subscriptions (to allow webhooks)
                if ( $facebook_pages[ $id ]["integrate"] == 1 && ( !isset( $facebook_pages[ $id ]["subscribed"] ) || ( isset( $facebook_pages[ $id ]["subscribed"] ) && $facebook_pages[ $id ]["subscribed"] != 1 ) ) ) {
                    $url     = "https://graph.facebook.com/v2.8/" . $id . "/subscribed_apps?access_token=" . $facebook_page["access_token"];
                    $request = wp_remote_post( $url );
                    if ( is_wp_error( $request ) ) {
                        $this->display_error( $request );
                    } else {
                        $body = wp_remote_retrieve_body( $request );
                        $data = json_decode( $body, true );
                        if ( !empty( $data ) && isset( $data["error"] ) ) {
                            $this->display_error( $data["error"]["message"] );
                        }
                        $facebook_pages[ $id ]["subscribed"] = 1;
                    }
                }
                //enable and set up webhooks for getting page feed and conversations
                if ( isset( $facebook_pages[ $id ]["subscribed"] ) && $facebook_pages[ $id ]["subscribed"] == 1 && !isset( $facebook_pages[ $id ]["webhooks"] ) && $facebook_pages[ $id ]["integrate"] === 1 ) {
                    $url     = "https://graph.facebook.com/v2.12/" . $id . "/subscriptions?access_token=" . get_option( "disciple_tools_facebook_app_id", "" ) . "|" . get_option( "disciple_tools_facebook_app_secret", "" );
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
                            $facebook_pages[ $id ]["webhooks_set"] = 1;
                        }
                    }
                }
            }
            update_option( "dt_facebook_pages", $facebook_pages );
            //if a new page is added, get the reports for that page.
            if ( $get_historical_data === true ) {
                do_action( "dt_facebook_stats" );
            }
        }


        if ( isset( $_POST["get_recent_conversations"] ) ){
            $this->get_recent_conversations();
        }
    }

    public function get_rest_url() {
        return get_site_url() . "/wp-json/" . $this->namespace;
    }


    /**
     * Facebook Authentication and webhooks
     */

    // Generate authorization secret
    public static function authorize_secret() {
        return 'dt_auth_' . substr( md5( AUTH_KEY ? AUTH_KEY : get_bloginfo( 'url' ) ), 0, 10 );
    }

    /**
     * called by facebook when initialising the webhook
     *
     * @return mixed
     */
    public function verify_facebook_webhooks() {
        if ( isset( $_GET["hub_verify_token"] ) && $_GET["hub_verify_token"] === $this->authorize_secret() ) {
            if ( isset( $_GET['hub_challenge'] ) ) {
                echo esc_html( sanitize_text_field( wp_unslash( $_GET['hub_challenge'] ) ) );
                exit();
            }
        }

        return "Could not verify";
    }

    private function get_or_refresh_pages( $access_token ) {

        $facebook_pages_url = "https://graph.facebook.com/v2.8/me/accounts?fields=access_token,id,name,business&access_token=" . $access_token;
        $pages_request      = wp_remote_get( $facebook_pages_url );


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
    public function add_app() {
        // Check noonce
        if ( isset( $_POST['_wpnonce'] ) && !wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'wp_rest' ) ) {
            return 'Are you cheating? Where did this form come from?';
        }
        if ( current_user_can( "manage_options" ) && check_admin_referer( 'wp_rest' ) && isset( $_POST["save_app"] ) && isset( $_POST["app_secret"] ) && isset( $_POST["app_id"] ) ) {
            update_option( 'disciple_tools_facebook_app_id', sanitize_key( $_POST["app_id"] ) );
            $secret = sanitize_key( $_POST["app_secret"] );
            if ( $secret !== "app_secret" ) {
                update_option( 'disciple_tools_facebook_app_secret', $secret );
            }
            delete_option( 'disciple_tools_facebook_access_token' );

            $url = "https://facebook.com/v2.8/dialog/oauth";
            $url .= "?client_id=" . sanitize_key( $_POST["app_id"] );
            $url .= "&redirect_uri=" . $this->get_rest_url() . "/auth";
            $url .= "&scope=public_profile,read_insights,manage_pages,read_page_mailboxes";
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
    public function update_from_facebook() {
        //decode the facebook post request from json
//        $body = json_decode( file_get_contents( 'php://input' ), true );
//
//        foreach ( $body['entry'] as $entry ) {
//            $facebook_page_id = $entry['id'];
//            if ( $entry['changes'] ) {
//                foreach ( $entry['changes'] as $change ) {
//                    if ( $change['field'] == "conversations" ) {
//                        //there is a new update in a conversation
//                        $thread_id = $change['value']['thread_id'];
//                        do_action( "dt_conversation_update", $facebook_page_id, $thread_id );
//                    }
////                    elseif ( $change['field'] == "feed" ) {
////                        //the facebook page feed has an update
////                    }
//                }
//            }
//        }

//        do_action( "dt_update_from_facebook", $body );
    }

    /**
     * get the conversation details from facebook
     *
     * @param $page_id
     * @param $thread_id , the id for the conversation containing the messages
     */
    public function get_conversation_update( $page_id, $thread_id ) {
        //check the settings array to see if we have settings saved for the page
        //get the access token and custom page name by looking for the page Id
        $facebook_pages = get_option( "dt_facebook_pages", [] );
        //if we have the access token, get and save the conversation
        //make sure the "sync contacts" setting is set.
        if ( isset( $facebook_pages[ $page_id ] ) && isset( $facebook_pages[ $page_id ]["integrate"] ) && $facebook_pages[ $page_id ]["integrate"] == 1 ) {

            $access_token = $facebook_pages[ $page_id ]["access_token"];
            $uri_for_conversations = "https://graph.facebook.com/v2.7/" . $thread_id . "?fields=link,message_count,messages{from,created_time,message},updated_time,participants&access_token=" . $access_token;
//            $uri_for_conversations = "https://graph.facebook.com/v2.7/" . $thread_id . "?fields=updated_time,participants,messages{from,created_time,message}&access_token=" . $access_token;
            $response              = wp_remote_get( $uri_for_conversations );

            $body = json_decode( $response["body"], true );
            if ( $body ) {
                $participants = $body["participants"]["data"];
                //go through each participant to save their conversations on their contact record
                foreach ( $participants as $participant ) {
                    if ( (string) $participant["id"] != $page_id ) {
                        $this->update_or_create_contact( $participant, $body["updated_time"], $facebook_pages[ $page_id ], $body );
                    }
                }
            }
        }
    }


    //The app secret proof is a sha256 hash of your access token, using the app secret as the key.
    public function get_app_secret_proof( $access_token ) {
        $app_secret       = get_option( "disciple_tools_facebook_app_secret" );
        $app_secret_proof = hash_hmac( 'sha256', $access_token, $app_secret );

        return $app_secret_proof;
    }

    public function get_page_scoped_ids( $used_id, $access_token ) {
        $app_secret_proof  = $this->get_app_secret_proof( $access_token );
        $ids_for_pages_uri = "https://graph.facebook.com/v2.12/$used_id?fields=name,ids_for_pages&access_token=$access_token&appsecret_proof=$app_secret_proof";
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
            sleep( 5 );
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
                ]
            ];
            $new_contact_id = Disciple_Tools_Contacts::create_contact( $fields, false );
            if ( is_wp_error( $new_contact_id ) ){
                dt_send_email( "corsacca@gmail.com", "fb create failed", serialize( $fields ) );
            }
            return $new_contact_id;
        }
    }

    public function add_duplicate_check_field( $fields ) {
        $fields[] = "facebook_data";

        return $fields;
    }


    public function get_conversations_with_pagination( $url, $id, $latest_conversation = 0 ) {
        $conversations_request = wp_remote_get( $url );

        if ( !is_wp_error( $conversations_request ) ) {
            $conversations_body = wp_remote_retrieve_body( $conversations_request );
            $conversations_page = json_decode( $conversations_body, true );
            if ( !empty( $conversations_page ) && isset( $conversations_page["data"] ) ) {
                $this->save_conversation_page( $conversations_page["data"], $id, $latest_conversation );
                if ( isset( $conversations_page["paging"]["next"] ) ){
                    $oldest_conversation = end( $conversations_page["data"] );
                    if ( strtotime( $oldest_conversation["updated_time"] ) >= $latest_conversation ){
                        global $timestart;
                        $time_end = microtime( true );
                        $elapsed = $time_end - $timestart;
                        sleep( ( 30 - $elapsed > 0 ) ? 30 - $elapsed : 0 ); // don't spam facebook
                        do_action( "dt_facebook_all_conversations", $conversations_page["paging"]["next"], $id, $latest_conversation );
                    }
                } else {
                    $facebook_pages = get_option( "dt_facebook_pages", [] );
                    $facebook_pages[$id]["reached_the_end"] = time();
                    update_option( "dt_facebook_pages", $facebook_pages );
                }
            }
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
                    sleep( 20 ); // don't spam facebook
                    $next_page = $this->get_all_with_pagination( $page["paging"]["next"] );
                    return array_merge( $page["data"], $next_page );
                }
            } else {
                return [];
            }
        }
    }

    public function get_recent_conversations(){
        $facebook_pages      = get_option( "dt_facebook_pages", [] );
        foreach ( $facebook_pages as $id => $facebook_page ) {
            if ( isset( $facebook_page["integrate"] ) && $facebook_page["integrate"] === 1 && isset( $facebook_page["access_token"] )){
                //get conversations
                $latest_conversation = $facebook_page["latest_conversation"] ?? 0;
                $facebook_conversations_url = "https://graph.facebook.com/v3.0/$id/conversations?fields=link,message_count,messages.limit(500){from,created_time,message},participants,updated_time&access_token=" . $facebook_page["access_token"];
                do_action( "dt_facebook_all_conversations", $facebook_conversations_url, $id, $latest_conversation );
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
                            $this->update_facebook_messages_on_contact( $contact_id, $conversation );
                            $this->set_notes_on_conversation( $contact_id, $page, $participant["id"] );
                        }
                    }
                }
            }
        }
        $facebook_pages = get_option( "dt_facebook_pages", [] );
        $new_latest_conversation = isset( $conversations[0]["updated_time"] ) ? strtotime( $conversations[0]["updated_time"] ) : 0;
        if ( isset( $facebook_pages[$id]["reached_the_end"] ) && $latest_conversation < $new_latest_conversation && $facebook_pages[$id]["latest_conversation"] < $new_latest_conversation ){
            $facebook_pages[$id]["latest_conversation"] = $new_latest_conversation;
            update_option( "dt_facebook_pages", $facebook_pages );
        }
    }



    public function cron_hook(){
        // calls get_recent_conversations
        $this->get_recent_conversations();
        return "ok";
    }

    public function add_comment_section( $sections, $post_type ){
        if ( $post_type === "contacts" ){
            $sections[] = [
                "key" => "facebook",
                "label" => __( "Facebook", "dt_facebook" )
            ];
        }
        return $sections;
    }

    public function add_fields_in_dt_search( $fields ){
        $fields[] = "facebook_data";
        return $fields;
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
            sleep( 20 );
            $messages = array_merge( $all_convs, $messages );
        }
        if ( $message_count != $saved_number ){
            foreach ( $messages as  $message ){
                if ( !in_array( $message["id"], $saved_ids )){
                    $comment = $message["message"];
                    $image = "https://graph.facebook.com/" . $message['from']['id'] . "/picture?type=square";
                    Disciple_Tools_Contacts::add_comment( $contact_id, $comment, false, "facebook", 0, $message["from"]["name"], $message["created_time"], true, $image );
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
        $url = 'https://graph.facebook.com/v3.0/ ' . $page_id . "/admin_notes?access_token=" . $access_token;
        return wp_remote_post( $url, [
            "body" => [
                "body" => $note,
                "user_id" => $user_id
            ]
        ]);
    }
}
