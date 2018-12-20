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
        add_menu_page( __( 'Extensions (DT)', 'disciple_tools' ), __( 'Extensions (DT)', 'disciple_tools' ), 'manage_dt', 'dt_extensions', [
            $this,
            'extensions_menu'
        ], 'dashicons-admin-generic', 59 );
        add_submenu_page( 'dt_extensions', __( 'Facebook', 'dt_facebook' ), __( 'Facebook', 'dt_facebook' ), 'manage_dt', $this->token, [
            $this,
            'content'
        ] );
    }

    /**
     * Menu stub. Replaced when Disciple Tools Theme fully loads.
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
            <h2><?php esc_attr_e( 'DISCIPLE TOOLS - FACEBOOK', 'dt_facebook' ) ?></h2>
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_attr( $link ) . 'general' ?>"
                   class="nav-tab <?php ( $tab == 'general' || !isset( $tab ) ) ? esc_attr_e( 'nav-tab-active', 'dt_facebook' ) : print ''; ?>"><?php esc_attr_e( 'General', 'dt_facebook' ) ?></a>
                <a href="<?php echo esc_attr( $link ) . 'instructions' ?>"
                   class="nav-tab <?php ( $tab == 'instructions' || !isset( $tab ) ) ? esc_attr_e( 'nav-tab-active', 'dt_facebook' ) : print ''; ?>"><?php esc_attr_e( 'Instructions', 'dt_facebook' ) ?></a>
<!--                <a href="--><?php //echo esc_attr( $link ) . 'second' ?><!--"-->
<!--                   class="nav-tab --><?php //( $tab == 'second' ) ? esc_attr_e( 'nav-tab-active', 'dt_facebook' ) : print ''; ?><!--">--><?php //esc_attr_e( 'Labels', 'dt_facebook' ) ?><!--</a>-->
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
                case "second":
                    $object = new DT_Facebook_Tab_Second();
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
     * Admin alert for when Disciple Tools Theme is not available
     */
    public function dt_facebook_no_disciple_tools_theme_found() {
        ?>
        <div class="updated notice notice-error is-dismissible notice-facebook" data-notice="prefix_deprecated">
            <p><?php esc_html_e( "'Disciple Tools - Facebook' plugin requires 'Disciple Tools' theme to work. Please activate 'Disciple Tools' theme or deactivate 'Disciple Tools - Facebook' plugin.", "dt_facebook" ); ?></p>
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

/**
 * Class DT_Facebook_Tab_Second
 */
class DT_Facebook_Tab_Second {
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
                <th><?php esc_html_e( "Labels", 'disciple_tools' ) ?></th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    <?php Disciple_Tools_Facebook_Labels::instance()->facebook_labels_page() ?>
                </td>
            </tr>
            </tbody>
        </table>
        <br>
        <!-- End Box -->
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <tr>
                <th><?php esc_html_e( "Label Workflows", 'disciple_tools' ) ?></th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    <?php Disciple_Tools_Facebook_Labels::instance()->display_facebook_label_workflows() ?>
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
        <ul style="list-style-type: disc; padding-left:40px">
            <li>Let start the creation progress: go to:
                <a href="https://developers.facebook.com/apps">https://developers.facebook.com/apps</a>
            </li>
            <li>Click the <strong>Add new app</strong> button</li>
            <img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/add_new_app.png" ) ?>" />
            <li>You can name the app "Disciple.Tools integration"</li>
            <img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/create_app_name.png" ) ?>" height="200px" />
            <li>You should be on the "Add a Product screen." Click <strong>Set Up</strong> on the "Facebook Login" box</li>
            <img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/products.png" ) ?>" height="200px" />
            <li>Choose the <strong>Other</strong> option</li>
            <img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/login_type.png" ) ?>" height="200px" />
            <li>On the left click <strong>settings</strong> under <strong>Facebook Login</strong></li>
            <img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/other_type.png" ) ?>" height="250px" />
            <li>In the <strong>Valid OAuth Redirect URIs</strong> field add: <strong><?php echo esc_url( $rest_url. "/auth" ); ?></strong></li>
            <img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/oauth_redirect.png" ) ?>" height="400px" />
            <li>Save Changes</li>
            <img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/save_changes.png" ) ?>" />
            <li>Click <strong>Settings</strong> on the left (right under Dashboard) and then <strong>Basic</strong>. In the <strong>App Domains</strong> box put: <strong><?php echo esc_url( get_site_url() ); ?></strong></li>
            <img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/app_domain.png" ) ?>" height="250px" />
            <li>Scroll down. Click <strong>Add Platform</strong>.</li>
            <img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/add_platform.png" ) ?>" width="500px" />
            <li> Choose <strong>Website</strong>. </li>
            <img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/platforms.png" ) ?>" height="250px" />
            <li>In "Site URL" put: <strong><?php echo esc_url( get_site_url() ); ?></strong></li>
            <img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/site_url.png" ) ?>" width="500px" />
            <li>Save Changes</li>
            <img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/save_changes.png" ) ?>" />
            <li>In Settings > Basic: Get the <strong>APP ID</strong> and the <strong>APP SECRET</strong></li>
            <img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/app_ids.png" ) ?>" height="250px"/>
        </ul>


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
        <h1 style="margin-top: 40px"><a id="uptime_robot"></a>4. Set up cron to get contacts every 5 minutes (Recommended)</h1>
        <p>This will make sure Disciple.Tools looks for new contacts every 5 minutes</p>
        <p>Wordpress will try to do this on it's own, but can sometimes go long periods without checking for updates. Uptime Robot makes sure it the checks happen often.</p>
        <ul style="list-style-type: disc; padding-left:40px">
            <li><a href="https://uptimerobot.com/">Sign up for a Uptime Robot Account</a></li>
            <li>Once logged in. Click <strong>Add New Monitor</strong></li>
            <img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/ur_add_new.png" ) ?>" />
            <li>Monitor type: HTTP(s)</li>
            <li>Friendly Name: Facebook Cron</li>
            <li>Url: <strong><?php echo esc_html( $rest_url . "/dt-public/cron" ); ?></strong></li>
            <li>Monitoring Interval: 5 mins</li>
            <img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/ur_fields.png" ) ?>" height="250px" />
            <li>Click <strong>Create Monitor</strong></li>
            <img src="<?php echo esc_html( plugin_dir_url( __DIR__ ) . "assets/ur_save.png" ) ?>" =/>
        </ul>


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


