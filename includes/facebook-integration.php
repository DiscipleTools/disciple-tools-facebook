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
    private $context = 'dt_facebook';
    private $namespace;
    private $facebook_api_version = '14.0';

    /**
     * Constructor function.
     *
     * @access public
     * @since  0.1.0
     */
    public function __construct() {
        $this->namespace = $this->context . '/v' . intval( $this->version );
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
        add_action( 'admin_notices', [ $this, 'dt_admin_notice' ] );
        add_action( 'wp_ajax_dt-facebook-notice-dismiss', [ $this, 'dismiss_error' ] );
    }

    /**
     * Setup the api routs for the plugin
     *
     * @since  0.1.0
     */
    public function add_api_routes() {
        register_rest_route(
            $this->namespace, '/auth', [
                'methods'  => 'GET',
                'callback' => [ $this, 'authenticate_app' ],
                'permission_callback' => '__return_true',
            ]
        );
        register_rest_route(
            $this->namespace, '/add-app', [
                'methods'  => 'POST',
                'callback' => [ $this, 'add_app' ],
                'permission_callback' => '__return_true',
            ]
        );
    }

    /**
     * Admin notice
     */
    public function dt_admin_notice() {
        $error = get_option( 'dt_facebook_error', '' );
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
        update_option( 'dt_facebook_error', '' );
    }

    /**
     * Render the Facebook Settings Page
     */
    public function facebook_settings_page() {

        $this->facebook_settings_functions();

        $access_token = get_option( 'disciple_tools_facebook_access_token', '' );

        // make sure cron is running if a page is set to sync
        $facebook_pages = get_option( 'dt_facebook_pages', [] );
        $sync_enabled = false;
        foreach ( $facebook_pages as $id => $facebook_page ){
            if ( isset( $facebook_page['integrate'] ) && $facebook_page['integrate'] === 1 && !empty( $facebook_page['access_token'] ) ){
                $sync_enabled = true;
            }
        }
        if ( !$sync_enabled ){
            wp_clear_scheduled_hook( 'facebook_check_for_new_conversations_cron' );
        }
        $schedule_error = null;
        if ( $sync_enabled && !wp_next_scheduled( 'facebook_check_for_new_conversations_cron' ) ){
            $schedule_error = wp_schedule_event( time(), '5min', 'facebook_check_for_new_conversations_cron' );
             wp_schedule_event( time(), '5min', 'facebook_check_for_new_conversations_cron', [], true );
        }

        if ( !defined( 'DISABLE_WP_CRON' ) || DISABLE_WP_CRON === false ){
            $this->display_error( 'It appears that CRON jobs are not set up correctly.', '', false );
        }


        $sync_needed = false;
        foreach ( $facebook_pages as $page_id => $facebook_page ){
            if ( isset( $facebook_page['integrate'] ) && $facebook_page['integrate'] === 1 && !empty( $facebook_page['access_token'] ) && !empty( $facebook_page['id'] ) ){
                if ( empty( $facebook_page['reached_the_end'] ) ){
                    $sync_needed = $facebook_page;
                }
                break;
            }
        }
        if ( $sync_needed ) : ?>
        <div id="facebook_sync_section" style="min-height:200px; margin: 20px; padding: 20px; background-color: #ffcfcf;border-radius: 5px; border: solid red 2px;">
            <p>
                <img class="dt-icon" src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/broken.svg' ) ?>"/>
                Discovering Conversations for page: <span id="page_name"><?php echo esc_html( $sync_needed['name'] ); ?></span>
            </p>
            <p>
                Discovered <span id="conversation_count">0</span> conversations <span id="discover_spinner"><img src="<?php echo esc_url( trailingslashit( get_stylesheet_directory_uri() ) ) ?>spinner.svg" width="22px" alt="spinner "/></span><br>
            </p>
            <p>Please do <strong>not close</strong> this page until the process is done.</p>

            <script>
                jQuery(document).ready(function ($) {
                    function make_api_call(url_end){
                         const options = {
                            type: "GET",
                            contentType: "application/json; charset=utf-8",
                            dataType: "json",
                            url: `<?php echo esc_url_raw( rest_url() ) ?>dt_facebook/v1/${url_end}?time=${new Date().getTime()}`,
                            data: {page_id:'<?php echo esc_html( $sync_needed['id'] ); ?>'},
                            beforeSend: (xhr) => {
                                xhr.setRequestHeader("X-WP-Nonce", '<?php echo esc_html( wp_create_nonce( 'wp_rest' ) ) ?>');
                            },
                        };
                        return jQuery.ajax(options)
                    }

                    let conversation_count = 0;
                    function discover_conversations() {
                        make_api_call('get_conversations_endpoint').then(resp=>{
                            if ( resp.conversations_saved ){
                                conversation_count += resp.conversations_saved
                            }
                            if ( resp.jobs ){
                                $('#job_count').text(resp.jobs)
                            }
                            if ( resp.next ){
                                $('#conversation_count').text(conversation_count)
                                discover_conversations()
                            } else if ( resp && !resp.next ) {
                                window.location.reload();
                            }
                        }).catch(error=>{
                            console.log(error)
                        })
                    }
                    discover_conversations();
                })
            </script>
        </div>
        <?php endif;

        $conversations_to_sync = wp_queue_count_jobs( 'facebook_conversation' );
        if ( empty( $sync_needed ) && !empty( $conversations_to_sync ) && $conversations_to_sync >= 5 ) : ?>
            <div id="facebook_sync_section" style="min-height:200px; margin: 20px; padding: 20px; background-color: #ffcfcf;border-radius: 5px; border: solid red 2px;">
                <p>
                    <img class="dt-icon" src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/broken.svg' ) ?>"/>
                    Saving Conversations: <span id="job_count"><?php echo esc_html( $conversations_to_sync ); ?></span> <span id="saving_convs_spinner"><img src="<?php echo esc_url( trailingslashit( get_stylesheet_directory_uri() ) ) ?>spinner.svg" width="22px" alt="spinner "/></span><br>
                </p>
                <p>Please do not close this page until the process is done.</p>

                <script>
                    jQuery(document).ready(function ($) {

                        function make_api_call(url_end){
                            const options = {
                                type: "GET",
                                contentType: "application/json; charset=utf-8",
                                dataType: "json",
                                url: `<?php echo esc_url_raw( rest_url() ) ?>dt_facebook/v1/${url_end}?time=${new Date().getTime()}`,
                                beforeSend: (xhr) => {
                                    xhr.setRequestHeader("X-WP-Nonce", '<?php echo esc_html( wp_create_nonce( 'wp_rest' ) ) ?>');
                                },
                            };

                            return jQuery.ajax(options)
                        }

                        let timeout = undefined;
                        function save_conversations(){
                            make_api_call( 'count_remaining_conversations_save' ).then(resp=>{
                                if ( resp.count && resp.count > 0 ){
                                    $('#job_count').text(resp.count)
                                    make_api_call( 'process_conversations_job' ).then(process_resp=>{
                                        $('#job_count').text(process_resp.count)
                                        if ( process_resp.count && parseInt( process_resp.count ) < 5 ){
                                            window.location.reload();
                                        }
                                        if ( process_resp.count && process_resp.count > 0 && !process_resp.cron_stuck ){
                                            clearTimeout(timeout)
                                            save_conversations();
                                        }
                                    }).catch(err=>{
                                        console.log(err);
                                    })
                                    timeout = setTimeout( ()=>{
                                        save_conversations();
                                    }, 31*1000 )
                                } else {
                                    window.location.reload();
                                }
                            })
                        }
                        save_conversations()

                    })
                </script>
            </div>
        <?php endif; ?>


        <p> This Facebook integration will provide a link between your Facebook pages and Disciple.Tools</p>
        <p>When a contact messages you page, a record for them will be created automatically. Pretty cool right?</p>

<!--        <h3>--><?php //esc_html_e( "Link Disciple.Tools to a Facebook app in order to get contacts or useful stats from your Facebook pages.", 'disciple-tools-facebook' ) ?><!--</h3>-->

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
                                    <th><?php esc_html_e( 'Facebook App Settings', 'disciple-tools-facebook' ) ?></th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr>
                                    <td><?php esc_html_e( 'Facebook App Id', 'disciple-tools-facebook' ) ?></td>
                                    <td>
                                        <input title="App Id" type="text" class="regular-text" name="app_id"
                                               value="<?php echo esc_attr( get_option( 'disciple_tools_facebook_app_id', '' ) ); ?>"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td><?php esc_html_e( 'Facebook App Secret', 'disciple-tools-facebook' ) ?></td>
                                    <td>
                                        <?php
                                        $secret = get_option( 'disciple_tools_facebook_app_secret', '' );
                                        if ( $secret != '' ) {
                                            $secret = 'app_secret';
                                        }
                                        ?>
                                        <input title="App Secret" type="<?php echo $secret ? 'password' : 'text' ?>"
                                               class="regular-text" name="app_secret"
                                               value="<?php echo esc_attr( $secret ); ?>"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td><?php esc_html_e( 'Access Token', 'disciple-tools-facebook' ) ?></td>
                                    <td>
                                        <?php echo( !empty( $access_token ) ? esc_html__( 'Access token is saved', 'disciple-tools-facebook' ) : esc_html__( 'No Access Token', 'disciple-tools-facebook' ) ) ?>
                                    </td>
                                </tr>

                                <tr>
                                    <td><?php esc_html_e( 'Save or Refresh', 'disciple-tools-facebook' ) ?></td>
                                    <td><button type="submit" class="button" name="save_app" style="padding:3px">
                                            <img style="height: 25px;vertical-align: middle;" src="<?php echo esc_html( plugin_dir_url( __FILE__ ) . 'assets/flogo_RGB_HEX-72.svg' ) ?>"/>
                                            <span style="vertical-align: top"><?php esc_html_e( 'Login with Facebook', 'disciple-tools-facebook' ) ?></span></button>

                                        <p style="margin-top: 20px"><?php esc_html_e( 'Note: You will need to re-authenticate (by clicking the "Login with Facebook" button again) if:', 'disciple-tools-facebook' ) ?></p>
                                        <ul style="list-style-type: disc; padding-left:40px">
                                            <li><?php esc_html_e( 'You change your Facebook account password', 'disciple-tools-facebook' ) ?></li>
                                            <li><?php esc_html_e( 'You delete or de-authorize your Facebook App', 'disciple-tools-facebook' ) ?></li>
                                        </ul>
                                    </td>
                                </tr>
                                <?php if ( !empty( $access_token ) || !empty( get_option( 'dt_facebook_pages', [] ) ) ) :?>
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
                                        <input name="contact_email_address" type="email" value="<?php echo esc_html( get_option( 'dt_facebook_contact_email', '' ) ) ?>">
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
                                        <input name="close_after_months" type="number"  min="0" style="width: 70px" value="<?php echo esc_html( get_option( 'dt_facebook_close_after_months', '3' ) ) ?>">
                                        <button class="button" name="save_close_after_months" type="submit">Update</button>

                                    </td>
                                </tr>
<!--                                <tr>-->
<!--                                    --><?php //$disable_wp_cron = get_option( 'dt_facebook_disable_cron', false ); ?>
<!--                                    <td>-->
<!--                                        Disable getting updates with the wordpress scheduler.-->
<!--                                        <br>Check if using the 'wp-json/dt_facebook/v1/dt-public/cron' endpoint with service like "Uptime Robot".-->
<!--                                        <br>And if you are getting duplicate contacts or comments from Facebook-->
<!--                                    </td>-->
<!--                                    <td>-->
<!--                                        <label>-->
<!--                                            <input type="checkbox" name="dt_facebook_disable_cron" value="--><?php //echo esc_html( $disable_wp_cron ); ?><!--" --><?php //checked( $disable_wp_cron ) ?><!-- />-->
<!--                                            Disable wp-cron for Facebook-->
<!--                                        </label>-->
<!--                                       <button class="button" name="save_disable_cron" type="submit">Update</button>-->
<!---->
<!--                                    </td>-->
<!--                                </tr>-->
                                <tr>
                                    <td>
                                        Next scheduled sync
                                    </td>
                                    <td>
                                        <?php
                                        $next_time = wp_next_scheduled( 'facebook_check_for_new_conversations_cron' );
                                        if ( !empty( $next_time ) ){
                                            if ( $next_time - time() >= 0 ){
                                                echo esc_html( 'In ' . round( ( $next_time - time() ) / 60 ) . ' minutes' );
                                            } else {
                                                echo esc_html( round( ( $next_time - time() ) / 60 ) . ' minutes ago' );
                                            }
                                        }
                                        if ( !$sync_enabled ){
                                            echo 'Sync is disabled';
                                        }
                                        if ( is_wp_error( $schedule_error ) ){
                                            echo esc_html( $schedule_error->get_error_message() );
                                        }
                                        ?>
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
                            <table id="facebook_pages" class="widefat striped">
                                <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Facebook Pages', 'disciple-tools-facebook' ) ?></th>
                                    <th><?php esc_html_e( 'Sync Contacts', 'disciple-tools-facebook' ) ?></th>
<!--                                    <th>--><?php //esc_html_e( "Include in Stats", 'disciple-tools-facebook' ) ?><!--</th>-->
                                    <th><?php esc_html_e( 'Part of Business Manager', 'disciple-tools-facebook' ) ?></th>
                                    <th><?php esc_html_e( 'Digital Responder', 'disciple-tools-facebook' ) ?></th>
                                    <th><?php esc_html_e( 'Finished sync', 'disciple-tools-facebook' ) ?></th>
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
                                $facebook_pages = get_option( 'dt_facebook_pages', [] );

                                foreach ( $facebook_pages as $id => $facebook_page ){ ?>
                                <tr>
                                    <td><?php echo esc_html( $facebook_page['name'] ); ?>
                                        (<?php echo esc_html( $facebook_page['id'] ); ?>)
                                        <?php if ( empty( $facebook_page['access_token'] ) ) : ?>
                                            <img class="dt-icon" src="<?php echo esc_html( get_template_directory_uri() . '/dt-assets/images/broken.svg' ) ?>"/>
                                            <span>You do not have access to this page</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <input title="Integrate"
                                               name="<?php echo esc_attr( $facebook_page['id'] ) . '-integrate'; ?>"
                                               type="checkbox"
                                               <?php disabled( empty( $facebook_page['access_token'] ) ); ?>
                                               value="<?php echo esc_attr( $facebook_page['id'] ); ?>" <?php echo checked( 1, ( isset( $facebook_page['integrate'] ) && !empty( $facebook_page['access_token'] ) ) ? $facebook_page['integrate'] : false, false ); ?> />
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
                                            <?php echo checked( 1, isset( $facebook_page['business'] ), false ); ?> />
                                    </td>
                                    <td>
                                        <?php
                                        if ( isset( $facebook_page['assign_to'] ) ){
                                            $user_for_page = get_user_by( 'ID', $facebook_page['assign_to'] );
                                        }
                                        ?>
                                        <select name="<?php echo esc_attr( $facebook_page['id'] ); ?>-assign_new_contacts_to">
                                            <option value="<?php echo esc_attr( $user_for_page->ID ?? 'unknown' ) ?>"><?php echo esc_attr( $user_for_page->display_name ?? 'Unknown User' ) ?></option>
                                            <option disabled>---</option>
                                            <?php foreach ( $potential_user_list as $potential_user ) : ?>
                                                <option value="<?php echo esc_attr( $potential_user->ID ) ?>"><?php echo esc_attr( $potential_user->display_name ) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                    <?php if ( isset( $facebook_page['reached_the_end'] ) && isset( $facebook_page['integrate'] ) && $facebook_page['integrate'] === 1 && isset( $facebook_page['access_token'] ) ) : ?>
                                        Finished full sync on <?php echo esc_html( dt_format_date( $facebook_page['reached_the_end'] ) ); ?>
<!--                                        <form action="" method="post">-->
<!--                                            <input type="hidden" name="_wpnonce" id="_wpnonce"-->
<!--                                                   value="--><?php //echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?><!--"/>-->
<!---->
<!--                                            <input type="hidden" class="button" name="page_id" value="--><?php //echo esc_attr( $facebook_page["id"] ); ?><!--" />-->
<!--                                            <button type="submit" name="get_recent_conversations">--><?php //esc_html_e( "Get all conversations (launches in the background. This might take a while)", 'disciple-tools-facebook' ) ?><!--</button>-->
<!--                                        </form>-->
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
                                   value="<?php esc_html_e( 'Refresh Page List', 'disciple-tools-facebook' ) ?>"/>
                            <input type="submit" class="button" name="save_pages"
                                   value="<?php esc_html_e( 'Save Pages Settings', 'disciple-tools-facebook' ) ?>"/>
                            <br>




                            <br>
                            <br>
                            <h3>Tools</h3>
                            <form action="" method="post">
                                <input type="hidden" name="_wpnonce" id="_wpnonce"
                                       value="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"/>

                                <input type="hidden" class="button" name="page_id" />
                                <button type="submit" class="button" name="delete_duplicates"><?php esc_html_e( 'Try deleting duplicates', 'disciple-tools-facebook' ) ?></button>
                                <?php
                                $dup_number_option = get_option( 'dt_facebook_dups_found', 0 );
                                if ( !empty( $dup_number_option ) ){
                                    echo 'Remaining potential duplicates to process: ' . esc_html( $dup_number_option );
                                }
                                ?>
                            </form>

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
     * @param $code
     */
    private function display_error( $err, $code = '', $log = true ) {
        $err = $err . ( empty( $code ) ? '' : " ( $code ) " ); ?>
        <div class="notice notice-error is-dismissible">
            <p>Facebook Extension: <?php echo esc_html( $err ); ?></p>
        </div>
        <?php
        if ( $log ){
            $this->save_log_message( $err, 'error' );
        }
    }

    private function save_log_message( $message, $type ){
        dt_write_log( $message );
        $log = get_option( 'dt_facebook_error_logs', [] );
        $log[] = [
            'type' => $type,
            'time' => time(),
            'message' => $message,
        ];
        if ( sizeof( $log ) > 100 ){
            array_shift( $log );
        }
        update_option( 'dt_facebook_error_logs', $log );
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
        if ( isset( $_POST['get_pages'] ) ) {
            $this->get_or_refresh_pages( get_option( 'disciple_tools_facebook_access_token' ) );
        }

        //save changes made to the pages in the page list
        if ( isset( $_POST['save_pages'] ) ) {
            $get_historical_data = false;
            $facebook_pages      = get_option( 'dt_facebook_pages', [] );
            $dt_custom_lists     = dt_get_option( 'dt_site_custom_lists' );
            foreach ( $facebook_pages as $id => $facebook_page ) {
                //if sync contact checkbox is selected
                $integrate = str_replace( ' ', '_', $facebook_page['id'] . '-integrate' );
                if ( isset( $_POST[ $integrate ] ) ) {
                    $facebook_pages[ $id ]['integrate'] = 1;
                    if ( !isset( $dt_custom_lists['sources'][ $id ] ) ) {
                        $dt_custom_lists['sources'][ $id ] = [
                            'label'       => $facebook_page['name'],
                            'key'         => $id,
                            'type'        => 'facebook',
                            'description' => 'Contacts coming from Facebook page: ' . $facebook_page['name'],
                            'enabled'     => true,
                        ];
                        update_option( 'dt_site_custom_lists', $dt_custom_lists );
                    }
                    if ( !wp_next_scheduled( 'facebook_check_for_new_conversations_cron' ) ) {
                        wp_schedule_event( time(), '5min', 'facebook_check_for_new_conversations_cron' );
                    }
                } else {
                    $facebook_pages[ $id ]['integrate'] = 0;
                }
                //if the include in stats checkbox is selected
                $report = str_replace( ' ', '_', $facebook_page['id'] . '-report' );
                if ( isset( $_POST[ $report ] ) ) {
                    if ( !isset( $facebook_pages[ $id ]['report'] ) || $facebook_pages[ $id ]['report'] == 0 ) {
                        $get_historical_data = true;
                    }
                    $facebook_pages[ $id ]['report']  = 1;
                    $facebook_pages[ $id ]['rebuild'] = true;
                } else {
                    $facebook_pages[ $id ]['report'] = 0;
                }
                //set the user new Facebook contacts should be assigned to.
                $assign_to = str_replace( ' ', '_', $facebook_page['id'] . '-assign_new_contacts_to' );
                if ( isset( $_POST[$assign_to] ) ){
                    $facebook_pages[$id]['assign_to'] = sanitize_text_field( wp_unslash( $_POST[ $assign_to ] ) );
                }
            }
            update_option( 'dt_facebook_pages', $facebook_pages );
            //if a new page is added, get the reports for that page.
            if ( $get_historical_data === true ) {
                do_action( 'dt_facebook_stats' );
            }
        }


//        if ( isset( $_POST["get_recent_conversations"], $_POST["page_id"] ) ){
//            $id = sanitize_text_field( wp_unslash( $_POST["page_id"] ) );
//            $facebook_pages = get_option( "dt_facebook_pages", [] );
//            $facebook_pages[$id]["last_contact_id"] = null;
//            $facebook_pages[$id]["last_paging_cursor"] = null;
//            $facebook_pages[$id]["latest_conversation"] = null;
//            $facebook_pages[$id]["reached_the_end"] = null;
//            update_option( "dt_facebook_pages", $facebook_pages );
//
//            $this->get_recent_conversations( $id );
//        }
        if ( isset( $_POST['delete_duplicates'] ) ){
            self::delete_obvious_duplicates();
        }
    }

    public function get_rest_url() {
        return get_site_url() . '/wp-json/' . $this->namespace;
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

        $pages_data = Disciple_Tools_Facebook_Api::get_facebook_pages( $access_token );
        if ( is_wp_error( $pages_data ) ){
            $this->display_error( $pages_data->get_error_message(), $pages_data->get_error_code() );
            return;
        }
        if ( !empty( $pages_data ) ) {
            $page_ids = [];
            $pages = get_option( 'dt_facebook_pages', [] );
            foreach ( $pages_data as $page ) {
                $page_ids[] = (int) $page['id'];
                if ( !isset( $pages[ $page['id'] ] ) ) {
                    $pages[ $page['id'] ] = $page;
                } else {
                    $pages[ $page['id'] ]['access_token'] = $page['access_token'];
                    $pages[ $page['id'] ]['name']         = $page['name'];
                    if ( isset( $page['business'] ) ) {
                        $pages[ $page['id'] ]['business'] = $page['business'];
                    }
                }
            }
            foreach ( $pages as $page_id => $page ){
                if ( !in_array( (int) $page_id, $page_ids, true ) ){
                    unset( $pages[$page_id]['access_token'] );
                }
            }
            update_option( 'dt_facebook_pages', $pages );
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

        if ( isset( $get['state'] ) && strpos( $get['state'], $this->authorize_secret() ) !== false && isset( $get['code'] ) ) {
            $url = 'https://graph.facebook.com/v' . $this->facebook_api_version . '/oauth/access_token';
            $url .= '?client_id=' . get_option( 'disciple_tools_facebook_app_id' );
            $url .= '&redirect_uri=' . $this->get_rest_url() . '/auth';
            $url .= '&client_secret=' . get_option( 'disciple_tools_facebook_app_secret' );
            $url .= '&code=' . $get['code'];

            $request = wp_remote_get( $url );
            if ( is_wp_error( $request ) ) {
                $this->display_error( $request->get_error_message(), $request->get_error_code() );

                return $request->errors;
            } else {
                $body = wp_remote_retrieve_body( $request );
                $data = json_decode( $body, true );
                if ( !empty( $data ) ) {
                    if ( isset( $data['access_token'] ) ) {
                        update_option( 'disciple_tools_facebook_access_token', $data['access_token'] );
                        $this->get_or_refresh_pages( $data['access_token'] );
                    }
                    if ( isset( $data['error'] ) ) {
                        $this->display_error( $data['error']['message'] );
                    }
                }
            }
        }
        wp_redirect( admin_url( 'admin.php?page=' . $this->context ) );
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
        if ( current_user_can( 'manage_dt' ) && check_admin_referer( 'wp_rest' ) ){
            if ( isset( $_POST['save_app'] ) && isset( $_POST['app_secret'] ) && isset( $_POST['app_id'] ) ) {
                update_option( 'disciple_tools_facebook_app_id', sanitize_key( $_POST['app_id'] ) );
                $secret = sanitize_key( $_POST['app_secret'] );
                if ( $secret !== 'app_secret' ) {
                    update_option( 'disciple_tools_facebook_app_secret', $secret );
                }
                delete_option( 'disciple_tools_facebook_access_token' );

                $url = 'https://facebook.com/v' . $this->facebook_api_version . '/dialog/oauth';
                $url .= '?client_id=' . sanitize_key( $_POST['app_id'] );
                $url .= '&redirect_uri=' . $this->get_rest_url() . '/auth';
                $url .= '&scope=public_profile,read_insights,pages_messaging,pages_show_list,pages_read_engagement,pages_manage_metadata,read_page_mailboxes,business_management';
                $url .= '&state=' . $this->authorize_secret();

                wp_redirect( $url );
                exit;
            } elseif ( isset( $_POST['log_out'] ) ){
                delete_option( 'disciple_tools_facebook_app_secret' );
                delete_option( 'disciple_tools_facebook_app_id' );
                delete_option( 'dt_facebook_pages' );
                delete_option( 'disciple_tools_facebook_access_token' );
                if ( isset( $_SERVER['HTTP_REFERER'] ) ){
                    wp_redirect( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) );
                    exit;
                }
            } elseif ( isset( $_POST['save_email'], $_POST['contact_email_address'] ) ){
                $email = sanitize_text_field( wp_unslash( $_POST['contact_email_address'] ) );
                if ( !empty( $email ) ){
                    update_option( 'dt_facebook_contact_email', $email );
                }
                if ( isset( $_SERVER['HTTP_REFERER'] ) ){
                    wp_redirect( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) );
                    exit;
                }
            } elseif ( isset( $_POST['save_close_after_months'], $_POST['close_after_months'] ) ){
                $months = sanitize_text_field( wp_unslash( $_POST['close_after_months'] ) );
                if ( isset( $months ) ){
                    update_option( 'dt_facebook_close_after_months', $months );
                }
                if ( isset( $_SERVER['HTTP_REFERER'] ) ){
                    wp_redirect( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) );
                    exit;
                }
            } elseif ( isset( $_POST['save_disable_cron'] ) ){
                if ( isset( $_POST['dt_facebook_disable_cron'] ) ){
                    update_option( 'dt_facebook_disable_cron', true );
                } else {
                    update_option( 'dt_facebook_disable_cron', false );
                }
                if ( isset( $_SERVER['HTTP_REFERER'] ) ){
                    wp_redirect( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) );
                    exit;
                }
            }
        }
    }


    public function get_participant_profile_pic( $user_id, $facebook_data, $contact_id, $page_id = null ){
        $facebook_pages = get_option( 'dt_facebook_pages', [] );
        if ( isset( $facebook_data['profile_pic'] ) ) {
            return $facebook_data['profile_pic'];
        }

        $page_id = $page_id ?: $facebook_data['page_ids'][0];
        if ( ! isset( $facebook_pages[ $page_id ] ) ){
            return false;
        }
        $page = $facebook_pages[ $page_id ];
        $access_token = $page['access_token'];
        $url = 'https://graph.facebook.com/v' . $this->facebook_api_version . "/$user_id/picture?redirect=0&access_token=$access_token";
        $request = wp_remote_get( $url );

        if ( is_wp_error( $request ) ) {
            return false;
        } else {
            $body_json = wp_remote_retrieve_body( $request );
            $body = json_decode( $body_json, true );
            if ( isset( $body['data']['url'] ) ) {
                $facebook_data['profile_pic'] = $body['data']['url'];
                update_post_meta( $contact_id, 'facebook_data', $facebook_data );
                return $body['data']['url'];
            } else {
                return false;
            }
        }
    }

    public function delete_obvious_duplicates(){
        global $wpdb;
        $dup_number_option = get_option( 'dt_facebook_dups_found', 0 );
        $dups = $wpdb->get_results("
            SELECT pm.meta_value fb_id, COUNT(*) c, pm2.meta_value
            FROM $wpdb->postmeta pm
            INNER JOIN $wpdb->posts p on ( p.ID = pm.post_id AND p.post_date > '2019-01-01' )
            LEFT JOIN $wpdb->postmeta pm2 ON (
                pm.post_id = pm2.post_id
                and pm2.meta_key = 'overall_status'
            )
            WHERE pm.meta_key LIKE 'contact_facebook%'
            AND pm.meta_key NOT LIKE '%_details'
            AND pm.meta_value LIKE 'https://www.facebook.com%'
            GROUP BY pm.meta_value, pm2.meta_value HAVING c>1
        ", ARRAY_A );
        $dup_number_option = sizeof( $dups );
        update_option( 'dt_facebook_dups_found', $dup_number_option );

        foreach ( $dups as $dup ){
            $to_delete = [];
            $rows = $wpdb->get_results( $wpdb->prepare( "
                SELECT pm.post_id, MAX(m.user_id) as user_id, COUNT( DISTINCT c.comment_ID ) as c_count
                FROM $wpdb->postmeta pm
                INNER JOIN $wpdb->posts p on ( p.ID = pm.post_id AND p.post_date > '2019-01-01' )
                LEFT JOIN $wpdb->dt_activity_log m ON ( pm.post_id = m.object_id )
                LEFT JOIN $wpdb->comments c ON ( pm.post_id = c.comment_post_ID AND c.comment_type = 'facebook' )
                WHERE pm.meta_key LIKE %s
                AND pm.meta_key NOT LIKE %s
                AND pm.meta_value = %s
                GROUP BY pm.post_id
                ORDER BY pm.post_id
            ", 'contact_facebook%', '%_details', $dup['fb_id'] ), ARRAY_A );

            $with_user_activity = [];
            $max_comments_index = 0;
            foreach ( $rows as $index => $row ){
                if ( !empty( $row['user_id'] ) ){
                    $with_user_activity[] = $row['post_id'];
                }
                if ( (int) $row['c_count'] >= (int) $rows[$max_comments_index]['c_count'] ){
                    $max_comments_index = $index;
                }
            }
            if ( sizeof( $with_user_activity ) === 0 ){
                //keep contact with most facebook comments
                $with_user_activity[] = $rows[$max_comments_index]['post_id'];
            }
            foreach ( $rows as $index => $row ){
                if ( sizeof( $with_user_activity ) > 0 && !in_array( $row['post_id'], $with_user_activity ) && empty( $row['user_id'] ) ){
                    $to_delete[] = $row;
                }
            }
            foreach ( $to_delete as $row ){
                DT_Posts::delete_post( 'contacts', $row['post_id'], false );
                dt_write_log( $row );
            }
        }
        $dup_number_option = 0;
        update_option( 'dt_facebook_dups_found', $dup_number_option, false );
    }



    public function dt_facebook_log_email( $subject, $text ){
        $email_address = get_option( 'dt_facebook_contact_email' );
        if ( !empty( $email_address ) ){
            dt_send_email( $email_address, $subject, $text );
        }
    }
}
