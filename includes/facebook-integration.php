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
    private $facebook_api_version = '7.0';

    /**
     * Constructor function.
     *
     * @access public
     * @since  0.1.0
     */
    public function __construct() {
        $this->namespace = $this->context . "/v" . intval( $this->version );
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
        add_action( 'admin_notices', [ $this, 'dt_admin_notice' ] );
        add_action( 'wp_ajax_dt-facebook-notice-dismiss', [ $this, 'dismiss_error' ] );
        add_action( "dt_async_dt_conversation_update", [ $this, "get_conversation_update" ], 10, 2 );
        add_action( "dt_async_dt_facebook_all_conversations", [ $this, "get_conversations_with_pagination" ], 10, 4 );
        add_action( 'updated_recent_conversations', [ $this, 'get_recent_conversations' ] );

    } // End __construct()

    /**
     * Setup the api routs for the plugin
     *
     * @since  0.1.0
     */
    public function add_api_routes() {
        register_rest_route(
            $this->namespace . "/dt-public",
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
            $this->namespace ."/dt-public", "cron", [
                'methods'  => "GET",
                'callback' => [ $this, 'cron_hook' ],
            ]
        );
        register_rest_route(
            $this->namespace ."/dt-public", "cron", [
                'methods'  => "POST",
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
            <script>
                jQuery(function($) {
                    $( document ).on( 'click', '.dt-facebook-notice .notice-dismiss', function () {
                        $.ajax( ajaxurl, {
                            type: 'POST',
                            data: {
                                action: 'dt-facebook-notice-dismiss',
                                type: 'dt-facebook',
                                //security: '<?php //echo esc_html( wp_create_nonce( 'wp_rest_dismiss' ) ) ?>//'
                            }
                        })
                    });
                });
            </script>
        <?php }
    }

    public function dismiss_error() {
        update_option( 'dt_facebook_error', "" );
    }

    /**
     * Render the Facebook Settings Page
     */
    public function facebook_settings_page() {

        $access_token = get_option( "disciple_tools_facebook_access_token", "" );

        // make sure cron is running if a page is set to sync
        $facebook_pages = get_option( "dt_facebook_pages", [] );
        $sync_enabled = false;
        foreach ( $facebook_pages as $id => $facebook_page ){
            if ( isset( $facebook_page["integrate"] ) && $facebook_page["integrate"] === 1 && !empty( $facebook_page["access_token"] ) ){
                $sync_enabled = true;
            }
        }
        if ( $sync_enabled && !wp_next_scheduled( 'updated_recent_conversations' )){
            wp_schedule_event( time(), '5min', 'updated_recent_conversations' );
        }

        ?>
        <p> This Facebook integration will provide a link between your Facebook pages and Disciple.Tools</p>
        <p>When a contact messages you page, a record for them will be created automatically. Pretty cool right?</p>

<!--        <h3>--><?php //esc_html_e( "Link Disciple tools to a Facebook app in order to get contacts or useful stats from your Facebook pages.", 'dt_facebook' ) ?><!--</h3>-->

        To get started, head over to the instructions tab where we'll help you get a couple things set up:
        <ul style="list-style-type: disc; padding-left:40px">
            <li>A Facebook app</li>
            <li>Facebook Business Manager</li>
            <li>Scheduled update checker (cron job)</li>
        </ul>

        <p>Use the information from the instructions fill the form below</p>
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
                                            <img style="height: 25px;vertical-align: middle;" src="<?php echo esc_html( plugin_dir_url( __FILE__ ) . 'assets/flogo_RGB_HEX-72.svg' ) ?>"/>
                                            <span style="vertical-align: top"><?php esc_html_e( "Login with Facebook", 'dt_facebook' ) ?></span></button>

                                        <p style="margin-top: 20px"><?php esc_html_e( 'Note: You will need to re-authenticate (by clicking the "Login with Facebook" button again) if:', 'dt_facebook' ) ?></p>
                                        <ul style="list-style-type: disc; padding-left:40px">
                                            <li><?php esc_html_e( "You change your Facebook account password", 'dt_facebook' ) ?></li>
                                            <li><?php esc_html_e( "You delete or de-authorize your Facebook App", 'dt_facebook' ) ?></li>
                                        </ul>
                                    </td>
                                </tr>
                                <?php if ( !empty( $access_token ) || !empty( get_option( "dt_facebook_pages", [] ) ) ) :?>
                                <tr>
                                    <td>
                                        Completely log out and delete Facebook settings and the page list below
                                    </td>
                                    <td>
                                        <button class="button" name="log_out" type="submit">Log out</button>

                                    </td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td>
                                        Email Address to contact if the Disciple.Tools to Facebook link breaks
                                    </td>
                                    <td>
                                        <input name="contact_email_address" type="email" value="<?php echo esc_html( get_option( "dt_facebook_contact_email", "" ) ) ?>">
                                        <button class="button" name="save_email" type="submit">Save Email</button>

                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        Close contacts with status "From Facebook" if there is no activity on the contact for a number of months.
                                        Default: 3 months. Choose 0 for never.
                                    </td>
                                    <td>
                                        Months:
                                        <input name="close_after_months" type="number"  min="0" style="width: 70px" value="<?php echo esc_html( get_option( "dt_facebook_close_after_months", "3" ) ) ?>">
                                        <button class="button" name="save_close_after_months" type="submit">Update</button>

                                    </td>
                                </tr>
                                <tr>
                                    <?php $disable_wp_cron = get_option( 'dt_facebook_disable_cron', false ); ?>
                                    <td>
                                        Disable getting updates with the wordpress scheduler.
                                        <br>Check if using the 'wp-json/dt_facebook/v1/dt-public/cron' endpoint with service like "Uptime Robot".
                                        <br>And if you are getting duplicate contacts or comments from Facebook
                                    </td>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="dt_facebook_disable_cron" value="<?php echo esc_html( $disable_wp_cron ); ?>" <?php checked( $disable_wp_cron ) ?> />
                                            Disable wp-cron for Facebook
                                        </label>
                                       <button class="button" name="save_disable_cron" type="submit">Update</button>

                                    </td>
                                </tr>

                                </tbody>
                            </table>
                        </form>



                        <br>
                        <p>To sync your Facebook conversation to Disciple.Tools check the "Sync Contacts" checkbox next to the pages you to enable the integration with</p>
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
                                        <td>Login to list your Facebook pages</td>
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
        $err = 'Facebook Extension error at ' .  gmdate( "Y-m-d h:i:sa" ) . ': ' . $err; ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html( $err ); ?></p>
        </div>
        <?php
        dt_write_log( $err );
//        update_option( 'dt_facebook_error', $err );
        $log = get_option( "dt_facebook_error_logs", [] );
        $log[] = [
            "time" => time(),
            "message" => $err,
        ];
        if ( sizeof( $log ) > 50 ){
            array_shift( $log );
        }
        update_option( "dt_facebook_error_logs", $log );
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
                //set the user new Facebook contacts should be assigned to.
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
        $pages_data = $this->get_all_with_pagination( $facebook_pages_url );
        if ( !empty( $pages_data ) ) {
            $pages = get_option( "dt_facebook_pages", [] );
            foreach ( $pages_data as $page ) {
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
        }
    }

    /**
     * authenticate the Facebook app to get the user access token and Facebook pages
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
     * redirect workfloww for authorizing the Facebook app
     */
    public function add_app() {
        // Check noonce
        if ( isset( $_POST['_wpnonce'] ) && !wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'wp_rest' ) ) {
            return 'Are you cheating? Where did this form come from?';
        }
        if ( current_user_can( "manage_dt" ) && check_admin_referer( 'wp_rest' ) ){
            if ( isset( $_POST["save_app"] ) && isset( $_POST["app_secret"] ) && isset( $_POST["app_id"] ) ) {
                update_option( 'disciple_tools_facebook_app_id', sanitize_key( $_POST["app_id"] ) );
                $secret = sanitize_key( $_POST["app_secret"] );
                if ( $secret !== "app_secret" ) {
                    update_option( 'disciple_tools_facebook_app_secret', $secret );
                }
                delete_option( 'disciple_tools_facebook_access_token' );

                $url = "https://facebook.com/v" . $this->facebook_api_version . "/dialog/oauth";
                $url .= "?client_id=" . sanitize_key( $_POST["app_id"] );
                $url .= "&redirect_uri=" . $this->get_rest_url() . "/auth";
                $url .= "&scope=public_profile,read_insights,pages_messaging,pages_show_list,pages_read_engagement,pages_manage_metadata,read_page_mailboxes,business_management";
                $url .= "&state=" . $this->authorize_secret();

                wp_redirect( $url );
                exit;
            } elseif ( isset( $_POST["log_out"] ) ){
                delete_option( "disciple_tools_facebook_app_secret" );
                delete_option( "disciple_tools_facebook_app_id" );
                delete_option( "dt_facebook_pages" );
                delete_option( "disciple_tools_facebook_access_token" );
                if ( isset( $_SERVER["HTTP_REFERER"] )){
                    wp_redirect( esc_url_raw( wp_unslash( $_SERVER["HTTP_REFERER"] ) ) );
                    exit;
                }
            } elseif ( isset( $_POST["save_email"], $_POST["contact_email_address"] ) ){
                $email = sanitize_text_field( wp_unslash( $_POST["contact_email_address"] ) );
                if ( !empty( $email )){
                    update_option( "dt_facebook_contact_email", $email );
                }
                if ( isset( $_SERVER["HTTP_REFERER"] )){
                    wp_redirect( esc_url_raw( wp_unslash( $_SERVER["HTTP_REFERER"] ) ) );
                    exit;
                }
            } elseif ( isset( $_POST["save_close_after_months"], $_POST["close_after_months"] ) ){
                $months = sanitize_text_field( wp_unslash( $_POST["close_after_months"] ) );
                if ( isset( $months )){
                    update_option( "dt_facebook_close_after_months", $months );
                }
                if ( isset( $_SERVER["HTTP_REFERER"] )){
                    wp_redirect( esc_url_raw( wp_unslash( $_SERVER["HTTP_REFERER"] ) ) );
                    exit;
                }
            } elseif ( isset( $_POST["save_disable_cron"] ) ){
                if ( isset( $_POST["dt_facebook_disable_cron"] ) ){
                    update_option( "dt_facebook_disable_cron", true );
                } else {
                    update_option( "dt_facebook_disable_cron", false );
                }
                if ( isset( $_SERVER["HTTP_REFERER"] )){
                    wp_redirect( esc_url_raw( wp_unslash( $_SERVER["HTTP_REFERER"] ) ) );
                    exit;
                }
            }
        }
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
        if ( is_wp_error( $response ) ){
            $this->display_error( $response->get_error_message() );
            dt_write_log( $response );
            return [];
        }
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
    private function update_or_create_contact( $participant, $updated_time, $page, $conversation ) {
        //get page scoped ids available by using a Facebook business manager
        $page_scoped_ids = [ $participant["id"] ];

        $app_id = get_option( "disciple_tools_facebook_app_id", null );
        if ( empty( $app_id ) ){
            $this->display_error( "missing app_id" );
            return new WP_Error( "app_id", "missing app_id" );
        }

        $contacts = dt_facebook_find_contacts_with_ids( $page_scoped_ids, $participant["id"], $app_id );

        //if no contact saved make sure they did not visit us from a different page first
        //this no longer works because the page scoped id is now the app id.
//        if ( sizeof( $contacts ) == 0 && isset( $page["business"] ) ) {
//            $page_scoped_ids = $this->get_page_scoped_ids( $participant["id"], $page["access_token"] );
//            $contacts = dt_facebook_find_contacts_with_ids( $page_scoped_ids, $participant["id"], $app_id );
//        }
        $contact_id   = null;

        if ( sizeof( $contacts ) > 1 ) {
            foreach ( $contacts as $contact_post ) {
                $contact = Disciple_Tools_Contacts::get_contact( $contact_post->ID, false, true );
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
                Disciple_Tools_Contacts::update_contact( $contact_id, $update, false, true );
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
                "last_message_received" => strtotime( $updated_time )
            ];
            if ( isset( $page["assign_to"] )){
                $fields["assigned_to"] = $page["assign_to"];
            }
            $new_contact_id = Disciple_Tools_Contacts::create_contact( $fields, false, true );
            dt_write_log( "Facebook contact creation failure" );
            dt_write_log( $fields );
            if ( is_wp_error( $new_contact_id ) ){
                $this->display_error( $new_contact_id->get_error_message() );
                $this->dt_facebook_log_email( "Creating a contact failed", "The Facebook integration was not able to create a contact from Facebook. If this persists, please contact support." );
            }
            return $new_contact_id;
        }
    }


    public function get_participant_profile_pic( $user_id, $facebook_data, $contact_id, $page_id = null ){
        $facebook_pages = get_option( "dt_facebook_pages", [] );
        if ( isset( $facebook_data["profile_pic"] ) ) {
            return $facebook_data["profile_pic"];
        }

        $page_id = $page_id ?: $facebook_data["page_ids"][0];
        if ( ! isset( $facebook_pages[ $page_id ] )){
            return false;
        }
        $page = $facebook_pages[ $page_id ];
        $access_token = $page['access_token'];
        $url = "https://graph.facebook.com/v" . $this->facebook_api_version . "/$user_id/picture?redirect=0&access_token=$access_token";
        $request = wp_remote_get( $url );

        if ( is_wp_error( $request ) ) {
            return false;
        } else {
            $body_json = wp_remote_retrieve_body( $request );
            $body = json_decode( $body_json, true );
            if ( isset( $body["data"]["url"] ) ) {
                $facebook_data["profile_pic"] = $body["data"]["url"];
                update_post_meta( $contact_id, "facebook_data", $facebook_data );
                return $body["data"]["url"];
            } else {
                return false;
            }
        }
    }

    public function get_conversations_with_pagination( $url, $id, $latest_conversation = 0, $limit_to_one = false ) {
        $conversations_request = wp_remote_get( $url, [ "timeout" => 15 ] );
        if ( !is_wp_error( $conversations_request ) ) {
            $conversations_body = wp_remote_retrieve_body( $conversations_request );
            $conversations_page = json_decode( $conversations_body, true );
            if ( !empty( $conversations_page ) && isset( $conversations_page["data"] ) ) {
                $this->save_conversation_page( $conversations_page["data"], $id, $latest_conversation );
                $new_latest_conversation = isset( $conversations_page["data"][0]["updated_time"] ) ? strtotime( $conversations_page["data"][0]["updated_time"] ) : 0;
                if ( isset( $conversations_page["paging"]["next"] ) ){
                    $oldest_conversation = end( $conversations_page["data"] );
                    // if we haven't yet caught up on conversations.
                    if ( strtotime( $oldest_conversation["updated_time"] ) >= $latest_conversation && !$limit_to_one ){
                        $facebook_pages = get_option( "dt_facebook_pages", [] );
                        $depth = $facebook_pages[$id]["depth"] ?? 0;
                        $facebook_pages[$id]["depth"] = ++$depth;
                        //save the next page if the process get interrupted.
                        $facebook_pages[$id]["next_page"] = $conversations_page["paging"]["next"];
                        update_option( "dt_facebook_pages", $facebook_pages );
                        wp_remote_post( $this->get_rest_url() . "/dt-public/cron?page=" . $id );
                    } else {
                        //if has finished all the way we have a new recent conversation.
                        $facebook_pages = get_option( "dt_facebook_pages", [] );
                        $facebook_pages[$id]["next_page"] = null;
                        $facebook_pages[ $id ]["depth"] = 0;
                        $facebook_pages[$id]["latest_conversation"] = $new_latest_conversation;
                        update_option( "dt_facebook_pages", $facebook_pages );
                    }
                } else {
                    $facebook_pages = get_option( "dt_facebook_pages", [] );
                    $facebook_pages[$id]["reached_the_end"] = time();
                    $facebook_pages[$id]["next_page"] = null;
                    $facebook_pages[ $id ]["depth"] = 0;
                    update_option( "dt_facebook_pages", $facebook_pages );
                }
            } else {
                $facebook_pages = get_option( "dt_facebook_pages", [] );
                if ( isset( $facebook_pages[$id]["next_page"] ) && !empty( $facebook_pages[$id]["next_page"] )){
                     $facebook_pages[$id]["next_page"] = null;
                     update_option( "dt_facebook_pages", $facebook_pages );
                }
                dt_write_log( $conversations_page );
                $this->display_error( "Conversations page: " . $conversations_page["error"]["message"] );
                if ( isset( $conversations_page["error"]["code"] ) && $conversations_page["error"]["code"] == 190 ){
                    // this error sometimes triggers even if all is ok.
                    $message = "Hey, \nThe Facebook integration is no longer authorized with Facebook. Please click 'Login with Facebook' to fix the issue or 'Log out' to stop getting this email: \n";
//                    $dt_facebook_log_settings = get_option( "dt_facebook_log_settings", [] );
//                    $last_email = $dt_facebook_log_settings["last_email"] ?? 0;
//                    $message .= admin_url( 'admin.php?page=dt_facebook', 'https' );
//                    if ( $last_email < ( time() - 60 * 60 * 6 ) ){ // limit to one email every 6 hours.
//                        $this->dt_facebook_log_email( "Facebook Integration Error", $message );
//                        $dt_facebook_log_settings["last_email"] = time();
//                        update_option( "dt_facebook_log_settings", $dt_facebook_log_settings );
//                    }
                } elseif ( isset( $conversations_page["error"]["code"] ) ){
                    $this->display_error( "Conversations page: " . $conversations_page["error"]["message"] );
                    if ( !$conversations_page["error"]["code"] === 283 ){
                        //we wish to track if there are any other issues we are missing.
                        // $conversations_page["error"] contains the code, subcode, id, error message and type
                        dt_send_email( "dev@disciple.tools", "Facebook plugin error", get_site_url() . ' ' . serialize( $conversations_page["error"] ) );
                    }
                }
            }
        } else {
            $facebook_pages = get_option( "dt_facebook_pages", [] );
            if ( isset( $facebook_pages[$id]["next_page"] ) && !empty( $facebook_pages[$id]["next_page"] )){
                 $facebook_pages[$id]["next_page"] = null;
                 update_option( "dt_facebook_pages", $facebook_pages );
            }
            dt_write_log( $conversations_request );
            $this->display_error( "Get conversations: " . $conversations_request->get_error_message() );
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

        //abort if running cron job and getting conversations by cron job is disabled
        if ( wp_doing_cron() && !empty( get_option( 'dt_facebook_disable_cron', false ) ) ){
            return;
        }
        foreach ( $facebook_pages as $id => $facebook_page ) {
            if ( isset( $facebook_page["integrate"] ) && $facebook_page["integrate"] === 1 && !empty( $facebook_page["access_token"] )){
                if ( !$page_id ){
                    //get conversations
                    wp_remote_post( $this->get_rest_url() . "/dt-public/cron?page=" . $id );
                } else if ( $id == $page_id ) {
                    $latest_conversation = $facebook_page["latest_conversation"] ?? 0;
                    $facebook_conversations_url = "https://graph.facebook.com/v" . $this->facebook_api_version . "/$id/conversations?limit=10&fields=link,message_count,messages.limit(500){from,created_time,message},participants,updated_time&access_token=" . $facebook_page["access_token"];
                    if ( !empty( $facebook_page["next_page"] ) ){
                        $facebook_conversations_url = $facebook_page["next_page"];
                    }
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
                            $this->update_facebook_messages_on_contact( $contact_id, $conversation, $participant["id"] );
                        }
                    }
                }
            }
        }
    }



    public function cron_hook( WP_REST_Request $request ){
        $params = $request->get_params();
        $id = $params["page"] ?? null;
        $this->get_recent_conversations( $id );
        return time();
    }


    public function update_facebook_messages_on_contact( $contact_id, $conversation, $participant_id ){
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
                    if ( empty( $comment ) ){
                        $comment = "[picture, sticker or emoji]";
                    }
                    if ( $participant_id == $message["from"]["id"] ){
                        //is the contact
                        if ( !isset( $facebook_data["profile_pic"] ) ){
                            $facebook_data["profile_pic"] = $this->get_participant_profile_pic( $participant_id, $facebook_data, $contact_id );
                        }
                        $image = $facebook_data["profile_pic"] !== false ? $facebook_data["profile_pic"] : "";
                    } else {
                        //is the page
                        $image = "https://graph.facebook.com/" . $message['from']['id'] . "/picture?type=square";
                    }
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


    public function dt_facebook_log_email( $subject, $text ){
        $email_address = get_option( "dt_facebook_contact_email" );
        if ( !empty( $email_address ) ){
            dt_send_email( $email_address, $subject, $text );
        }
    }
}
