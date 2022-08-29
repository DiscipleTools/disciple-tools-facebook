<?php
/**
 * DT_Facebook_Menu class for the admin page
 *
 * @class       DT_Facebook_Menu
 * @version     0.1.0
 * @since       0.1.0
 */

if ( !defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class DT_Facebook_Menu
 */
class DT_Facebook_Menu {

    public $token = 'dt_facebook';

    private static $_instance = null;

    /**
     * DT_Facebook_Menu Instance
     *
     * Ensures only one instance of DT_Facebook_Menu is loaded or can be loaded.
     *
     * @since 0.1.0
     * @static
     * @return DT_Facebook_Menu instance
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    } // End instance()


    /**
     * Constructor function.
     * @access  public
     * @since   0.1.0
     */
    public function __construct() {

        add_action( "admin_menu", array( $this, "register_menu" ) );

    } // End __construct()


    /**
     * Loads the subnav page
     * @since 0.1
     */
    public function register_menu() {
        add_submenu_page( 'dt_extensions', __( 'Facebook', 'disciple-tools-facebook' ), __( 'Facebook', 'disciple-tools-facebook' ), 'manage_dt', $this->token, [
            $this,
            'content'
        ] );
    }

    /**
     * Menu stub. Replaced when Disciple.Tools Theme fully loads.
     */
    public function extensions_menu() {
    }

    /**
     * Builds page contents
     * @since 0.1
     */
    public function content() {

        if ( !current_user_can( 'manage_dt' ) ) {
            wp_die( esc_attr__( 'You do not have sufficient permissions to access this page.' ) );
        }

        if ( isset( $_GET["tab"] ) ) {
            $tab = sanitize_key( wp_unslash( $_GET["tab"] ) );
        } else {
            $tab = 'general';
        }

        $link = 'admin.php?page=' . $this->token . '&tab=';

        ?>
        <div class="wrap">
            <h2><?php esc_attr_e( 'Disciple.Tools - FACEBOOK', 'disciple-tools-facebook' ) ?></h2>
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_attr( $link ) . 'general' ?>"
                   class="nav-tab <?php ( $tab == 'general' || !isset( $tab ) ) ? esc_attr_e( 'nav-tab-active', 'disciple-tools-facebook' ) : print ''; ?>"><?php esc_attr_e( 'General', 'disciple-tools-facebook' ) ?></a>
                <a href="<?php echo esc_attr( $link ) . 'instructions' ?>"
                   class="nav-tab <?php ( $tab == 'instructions' || !isset( $tab ) ) ? esc_attr_e( 'nav-tab-active', 'disciple-tools-facebook' ) : print ''; ?>"><?php esc_attr_e( 'Instructions', 'disciple-tools-facebook' ) ?></a>
                <a href="<?php echo esc_attr( $link ) . 'log' ?>"
                   class="nav-tab <?php ( $tab == 'log' || !isset( $tab ) ) ? esc_attr_e( 'nav-tab-active', 'disciple-tools-facebook' ) : print ''; ?>"><?php esc_attr_e( 'Log', 'disciple-tools-facebook' ) ?></a>
            </h2>

            <?php
            switch ( $tab ) {
                case "general":
                    $object = new DT_Facebook_Tab_General();
                    $object->content();
                    break;
                case "instructions":
                    $object = new DT_Facebook_Tab_Instructions();
                    $object->content();
                    break;
                case "log":
                    $object = new DT_Facebook_Tab_Log();
                    $object->content();
                    break;
                default:
                    break;
            }
            ?>

        </div><!-- End wrap -->

        <?php
    }

    /**
     * Admin alert for when Disciple.Tools Theme is not available
     */
    public function dt_facebook_no_disciple_tools_theme_found() {
        ?>
        <div class="updated notice notice-error is-dismissible notice-facebook" data-notice="prefix_deprecated">
            <p><?php esc_html_e( "'Disciple.Tools - Facebook' plugin requires 'Disciple.Tools' theme to work. Please activate 'Disciple.Tools' theme or deactivate 'Disciple.Tools - Facebook' plugin.", 'disciple-tools-facebook' ); ?></p>
        </div>

        <?php
    }

}

DT_Facebook_Menu::instance();

/**
 * Class DT_Facebook_Tab_General
 */
class DT_Facebook_Tab_General {
    public function content() {
        ?>
        <div class="wrap">
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <!-- Main Column -->

                        <?php $this->main_column() ?>

                        <!-- End Main Column -->
                    </div><!-- end post-body-content -->
                    <div id="postbox-container-1" class="postbox-container">
                        <!-- Right Column -->

                        <?php //$this->right_column() ?>

                        <!-- End Right Column -->
                    </div><!-- postbox-container 1 -->
                    <div id="postbox-container-2" class="postbox-container">
                    </div><!-- postbox-container 2 -->
                </div><!-- post-body meta box container -->
            </div><!--poststuff end -->
        </div><!-- wrap end -->
        <?php
    }

    public function main_column() {
        ?>
        <!-- Box -->


        <table class="widefat striped">
            <thead>
            <tr>
                <th><?php esc_html_e( "Facebook Integration Settings", 'disciple_tools' ) ?></th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    <?php Disciple_Tools_Facebook_Integration::instance()->facebook_settings_page() ?>
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }

    public function right_column() {
        ?>
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <tr>
                <th><?php esc_html_e( "Information", 'disciple_tools' ) ?></th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    <?php esc_html_e( "Content", 'disciple_tools' ) ?>
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }

}

class DT_Facebook_Tab_Log {
    public function content() {
        ?>
        <div class="wrap">
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <!-- Main Column -->

                        <?php $this->main_column() ?>

                        <!-- End Main Column -->
                    </div><!-- end post-body-content -->
                    <div id="postbox-container-1" class="postbox-container">
                        <!-- Right Column -->

                        <?php //$this->right_column() ?>

                        <!-- End Right Column -->
                    </div><!-- postbox-container 1 -->
                    <div id="postbox-container-2" class="postbox-container">
                    </div><!-- postbox-container 2 -->
                </div><!-- post-body meta box container -->
            </div><!--poststuff end -->
        </div><!-- wrap end -->
        <?php
    }

    public function main_column() {
        ?>
        <!-- Box -->


        <h1><?php esc_html_e( "Recent Logs", 'disciple_tools' ) ?></h1>
        <table class="widefat striped">
            <thead>
            <tr>
                <th>Time</th>
                <th>Type</th>
                <th>Message</th>
            </tr>
            </thead>
            <tbody>
            <?php $log = array_reverse( get_option( "dt_facebook_error_logs", [] ) );
            foreach ( $log as $l ): ?>
                <tr>
                    <td>
                        <?php echo esc_html( dt_format_date( $l["time"], "long" ) ) ?>
                    </td>
                    <td>
                        <?php echo esc_html( $l["type"] ?? "Error" ); ?>
                    </td>
                    <td>
                        <?php echo esc_html( $l["message"] ); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }

    public function right_column() {
        ?>
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <tr>
                <th><?php esc_html_e( "Information", 'disciple_tools' ) ?></th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    <?php esc_html_e( "Content", 'disciple_tools' ) ?>
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }

}

class DT_Facebook_Tab_Instructions {
    public function content() {
        ?>
        <div class="wrap">
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <!-- Main Column -->

                        <?php $this->main_column() ?>

                        <!-- End Main Column -->
                    </div><!-- end post-body-content -->
                    <div id="postbox-container-1" class="postbox-container">
                        <!-- Right Column -->

                        <?php $this->right_column() ?>

                        <!-- End Right Column -->
                    </div><!-- postbox-container 1 -->
                    <div id="postbox-container-2" class="postbox-container">
                    </div><!-- postbox-container 2 -->
                </div><!-- post-body meta box container -->
            </div><!--poststuff end -->
        </div><!-- wrap end -->
        <?php
    }

    public function main_column() {
        $rest_url = Disciple_Tools_Facebook_Integration::instance()->get_rest_url();
        ?>
        <h1><a id="create_app"></a>1. Create Facebook App</h1>
        <p>In order to get contacts and conversations from Facebook we need to create a Facebook app. This app will be the bridge between Disciple.Tools and your Facebook page.</p>
        <p>Usually Facebook apps need to go through a review process. But since this is hard to implement for Disciple.Tools and only 1 person will be using the app we can keep it in development mode.
            The user who will sign in needs to be the app creator or an admin added to the app.

        <p>Being in development mode has it's own limitations, but at the current time this works for the contact synchronisation. Unfortunately Facebook can at any time change their api or limit access.</p>

        <style>
            .instructions-table td {
                padding: 1em;
                border-bottom: 1px solid #a7a7a79c;
            }
        </style>
        <table class="instructions-table">
            <tr>
                <td>
                    1. Let start the creation progress: go to:
                    <a href="https://developers.facebook.com/apps" target="_blank">https://developers.facebook.com/apps</a>
                </td>
                <td></td>
            </tr>
            <tr>
                <td>
                    2. Click the <strong>Add new app</strong> button
                </td>
                <td><img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/add_new_app.png" ) ?>" /></td>
            </tr>
            <tr>
                <td>
                    3. Select Business Integration
                </td>
                <td>
                    <img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/select_business.png" ) ?>" height="300px" />
                </td>
            </tr>

            <tr>
                <td>
                    4. You can name the app "Disciple.Tools integration"
                    <br>
                    Add your business manage account if you have one already.
                </td>
                <td>
                    <img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/create_app_name.png" ) ?>" height="500px" />
                </td>
            </tr>
            <tr>
                <td>5. You should be on the "Add a Product screen." Click <strong>Set Up</strong> on the "Facebook Login" box</td>
                <td><img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/products.png" ) ?>" height="400px" /></td>
            </tr>
            <tr>
                <td>6. Choose the <strong>Other</strong> option</td>
                <td><img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/login_type.png" ) ?>" height="200px" /></td>
            </tr>
            <tr>
                <td>7. On the left click <strong>settings</strong> under <strong>Facebook Login</strong></td>
                <td><img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/other_type.png" ) ?>" height="400px" /></td>
            </tr>
            <tr>
                <td>8. In the <strong>Valid OAuth Redirect URIs</strong> field add:
                    <br>
                    <br>
                    <strong><?php echo esc_url( $rest_url. "/auth" ); ?></strong>
                </td>
                <td><img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/oauth_redirect.png" ) ?>" height="400px" /></td>
            </tr>
            <tr>
                <td>9. Save Changes</td>
                <td><img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/save_changes.png" ) ?>" /></td>
            </tr>
            <tr>
                <td>
                    10. Click <strong>Settings</strong> on the left (right under Dashboard) and then <strong>Basic</strong>. In the <strong>App Domains</strong> box put:
                    <br>
                    <br>
                    <strong><?php echo esc_url( get_site_url() ); ?></strong>

                </td>
                <td><img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/app_domain.png" ) ?>" height="250px" /></td>
            </tr>
            <tr>
                <td>
                    11. Under <strong>Privacy Policy URL</strong> add:
                    <br>
                    <br>
                    https://raw.githubusercontent.com/DiscipleTools/disciple-tools-facebook/master/privacy.md
                </td>
                <td><img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/privacy_policy.png" ) ?>" height="250px" /></td>
            </tr>
            <tr>
                <td>12. Scroll down. Click <strong>Add Platform</strong>.</td>
                <td><img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/add_platform.png" ) ?>" width="500px" /></td>
            </tr>
            <tr>
                <td>13. Choose <strong>Website</strong>. </td>
                <td><img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/platforms.png" ) ?>" height="250px" /></td>
            </tr>
            <tr>
                <td>14. In "Site URL" put: <br><br> <strong><?php echo esc_url( get_site_url() ); ?></strong></td>
                <td><img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/site_url.png" ) ?>" width="500px" /></td>
            </tr>
            <tr>
                <td>15. Save Changes</td>
                <td><img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/save_changes.png" ) ?>" /></td>
            </tr>
            <tr>
                <td>16. In Settings > Basic: Get the <strong>APP ID</strong> and the <strong>APP SECRET</strong></td>
                <td><img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/app_ids.png" ) ?>" height="250px"/></td>
            </tr>

        </table>


        <br>
        <br>
        <h1 style="margin-top: 40px"><a id="login"></a>2. Login to connect Disciple.Tools to Facebook</h1>
        <ul style="list-style-type: disc; padding-left:40px">
            <li>Enter the <strong>APP ID</strong> and the <strong>APP SECRET</strong> in on the first tab and click <strong>Login with Facebook</strong></li>
            <img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/login.png" ) ?>" />
            <li>
                Hit continue on this page: <br>
                <img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/confirm_login_1.png" ) ?>" height="250px" />
            </li>
            <li>
                And then press OK: <br>
                <img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/confirm_login_2.png" ) ?>" height="250px" />
            </li>
            <li>
                Add your email address so the Integration can let you know if there is an issue. Click <strong>Save Email</strong>
                <br><img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/save_email.png" ) ?>" />
            </li>
            <li>Check <strong>Sync contacts</strong> next to the pages you want to set up to import contacts from</li>
            <img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/sync_contacts.png" ) ?>" height="200px"/>
            <li>Click <strong>Save Pages Settings</strong></li>
            <img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/save_pages_settings.png" ) ?>"/>
        </ul>

        <br>
        <br>
        <h1 style="margin-top: 40px"><a id="business_manager"></a>3. Associate your app with Business Manager</h1>
        <p>Highly recommended. Business manager adds a layer of protecting and helps track contacts across pages.</p>
        <p>Here are instructions on creating a business manager: <a href="https://www.facebook.com/business/help/1710077379203657">Setup business manager</a></p>
        <p>To associate you new app with your business manager account:</p>
        <ul style="list-style-type: disc; padding-left:40px">
            <li>Open <a href="https://business.facebook.com/settings" target="_blank">Business Settings</a></li>
            <li>Under Accounts click <strong>Apps.</strong></li>
            <img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/business_apps.png" ) ?>" height="250px"/>
            <li>Click <strong>Add New App</strong> and select <strong>Add an App</strong></li>
            <img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/business_add_app.png" ) ?>" height="250px"/>
            <li>Enter the Facebook App ID from the app you just created.</li>
            <img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/business_app_id.png" ) ?>" height="200px"/>
        </ul>
        <p>We also suggest adding your page to business manage</p>


        <br>
        <br>
        <h1 style="margin-top: 40px"><a id="uptime_robot"></a>4. Set up cron to get contacts every 5 minutes.</h1>
        <p>Disciple.Tools uses CRON to look to run automated tasks. Wordpress does not come with this set up correctly out of the box.</p>
        <p>This Facebook plugin needs CRON to be set up correctly to check for new contacts to sync to Disciple.Tools.</p>

        <p>
            Status:
            <?php if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON === true ): ?>
                <strong>Cron Enabled.</strong>
                It looks like you have CRON already set set up correctly.
            <?php else : ?>
                <strong>Cron Disabled.</strong> Cron jobs appear to not be set up. The constant <code>DISABLE_WP_CRON</code> is not set. See the Cron Documentation.
            <?php endif; ?>
        </p>

        <h3>CRON Instructions</h3>
        <p>How to set up cron jobs for your instance: <a href="https://developers.disciple.tools/hosting/cron/">CRON Documentation</a></p>


        <h1>Well done. You are all set!</h1>


        <?php
    }

    public function right_column() {
        ?>
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <tr>
                <th><?php esc_html_e( "Links", 'disciple_tools' ) ?></th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    <a href="#create_app">1. Create a Facebook App</a>
                </td>
            </tr>
            <tr>
                <td>
                    <a href="#login">2. Login</a>
                </td>
            </tr>
            <tr>
                <td>
                    <a href="#business_manager">3. Set up Business Manager</a>
                </td>
            </tr>
            <tr>
                <td>
                    <a href="#uptime_robot">4. Set up Uptime Robot</a>
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <?php
    }
}
