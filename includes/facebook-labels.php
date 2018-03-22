<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

/**
 * Class Disciple_Tools_Facebook_Labels
 */
class Disciple_Tools_Facebook_Labels {
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
     * @return Disciple_Tools_Facebook_Labels instance
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

        add_action( 'dt_async_dt_get_users_for_labels', [ $this, 'get_users_for_labels_async' ] );
        add_action( 'build_disciple_tools_reports', [ $this, 'get_users_for_labels' ] );
        add_filter( 'dt_facebook_label_workflows', [ $this, 'facebook_label_workflows' ] );
        add_action( 'dt_facebook_label_workflows_close', [ $this, "dt_facebook_label_workflows_close" ] );

    } // End __construct()

    /**
     * Setup the api routs for the plugin
     *
     * @since  0.1.0
     */
    public function add_api_routes()
    {
//        @todo remove
        register_rest_route(
            $this->namespace, 'dt-public/test', [
                "methods"  => "GET",
                'callback' => [ $this, 'get_users_for_labels' ],
            ]
        );
    }

    public function get_facebook_page_labels(){
        $facebook_pages = get_option( "dt_facebook_pages", [] );
        $facebook_labels = get_option( "dt_facebook_labels", [] );
        foreach ( $facebook_pages as $page ){
            if ( isset( $page["integrate"] ) && $page["integrate"] == 1 ){
                $labels = dt_facebook_api( "page_labels", $page["id"], $page["access_token"] );
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

    public static function apply_label_to_conversation( $page_label_id, $facebook_user_id, $page_id ){

    }

    public function get_users_for_labels(){
        do_action( "dt_get_users_for_labels" );
    }
    public function get_users_for_labels_async(){
        $facebook_pages = get_option( "dt_facebook_pages", [] );
        $facebook_labels = get_option( "dt_facebook_labels", [] );
        foreach ( $facebook_pages as $page ){
            if ( isset( $page["integrate"] ) && $page["integrate"] == 1 ){
                $labels = dt_facebook_api( "page_labels", $page["id"], $page["access_token"] );
                if ( !isset( $facebook_labels[$page["id"]] ) ){
                    $facebook_labels[$page["id"]] = [];
                }
                foreach ( $labels as $label ){
                    if ( !isset( $facebook_labels[$page["id"]][$label["id"]] ) ){
                        $facebook_labels[$page["id"]][$label["id"]] = [];
                    }
                    $facebook_labels[$page["id"]][$label["id"]]["name"] = $label["name"];
                    if ( !empty( $facebook_labels[$page["id"]][$label["id"]]["sync"] )){
                        $users = dt_facebook_api( "label_users", $label["id"], $page["access_token"] );
                        $facebook_labels[$page["id"]][$label["id"]]["users"] = $users;
                        foreach ( $users as $user ){
                            $contacts = dt_facebook_find_contacts_with_ids( [ $user["id"] ] );
                            foreach ( $contacts as $contact_post ){
                                $contact = Disciple_Tools_Contacts::get_contact( $contact_post->ID, false );
                                $facebook_data = maybe_unserialize( $contact["facebook_data"] ) ?? [];
                                if ( !isset( $facebook_data["labels"] ) ){
                                    $facebook_data["labels"] = [];
                                }
                                if ( !isset( $facebook_data["labels"][$label["id"]] )){
                                    $facebook_data["labels"][$label["id"]] = $label["name"];
                                    Disciple_Tools_Contacts::add_comment( $contact["ID"], "This label was applied on Facebook: " . $label['name'] );
                                    Disciple_Tools_Contacts::update_contact( $contact["ID"], [ "facebook_data" => $facebook_data ], false );
                                    $this->trigger_label_workflow( $contact, $label["id"], $page["id"] );
                                }
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

                            <select title="sync" name="page-id">
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
                            <?php if ( $page_id ){ ?>
                                <input type="submit" class="button" name="refresh_labels" value="Refresh Labels"/>
                            <?php } ?>
                        </form>
                        <br>
                        <?php
                        if ( isset( $_POST["refresh_labels"] )){
                            $this->get_facebook_page_labels();
                        }

                        $facebook_labels = get_option( "dt_facebook_labels", [] );
                        if ( isset( $_POST["save_labels"] ) && isset( $facebook_labels[$page_id] )){
                            foreach ( $facebook_labels[ $page_id ] as $label_key => $label_value ){
                                $facebook_labels[$page_id][$label_key]["sync"] = isset( $_POST[ $label_key ] );
                                if ( !empty( $_POST[ $label_key . "-workflow"] ) ){
                                    $facebook_labels[$page_id][$label_key]["workflow"] = esc_html( sanitize_text_field( wp_unslash( $_POST[ $label_key . "-workflow"] ) ) );
                                } else {
                                    $facebook_labels[$page_id][$label_key]["workflow"] = "";
                                }
                            }
                            update_option( "dt_facebook_labels", $facebook_labels );
                        }

                        if ( isset( $_POST["page-id"] ) ){
                            $facebook_labels = get_option( "dt_facebook_labels", [] );
                            if ( isset( $facebook_labels[$_POST["page-id"]] ) ){
                                ?>
                                <form method="post">
                                    <input type="hidden" name="_wpnonce" id="_wpnonce"
                                           value="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"/>
                                    <table id="facebook_labels" class="widefat striped">
                                        <thead>
                                            <tr>
                                                <th>Labels</th>
                                                <th>Sync Label</th>
                                                <th>Workflow</th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                        <?php
                                        $workflows = apply_filters( "dt_facebook_label_workflows", [] );
                                        foreach ( $facebook_labels[ $page_id ] as $label_key => $label_value ){
                                            ?>
                                            <tr>
                                                <td><?php echo esc_html( $label_value["name"] . " (" . $label_key . ")" )?></td>
                                                <td>
                                                    <input title="sync" name="<?php echo esc_attr( $label_key ) ?>"
                                                           type="checkbox"
                                                        <?php echo checked( 1, !empty( $label_value["sync"] ), false ); ?>
                                                           value="<?php echo esc_attr( $label_key ); ?>" />
                                                </td>
                                                <td>
                                                    <select title="workflow" name="<?php echo esc_attr( $label_key ) ?>-workflow">
                                                        <option></option>
                                                        <?php foreach ( $workflows as $workflow ){ ?>
                                                            <option value="<?php echo esc_html( $workflow["key"] ) ?>"
                                                                <?php echo ( isset( $label_value["workflow"] ) && $label_value["workflow"] === $workflow["key"] ) ? "selected" : "" ?>>
                                                                <?php echo esc_html( $workflow["name"] ) ?>
                                                            </option>
                                                        <?php } ?>
                                                    </select>
                                                </td>
                                            </tr>
                                            <?php
                                        }
                                        ?>
                                        </tbody>
                                    </table>

                                    <input name="page-id" value="<?php echo esc_html( $page_id ); ?>" type="hidden"/>
                                    <input type="submit" class="button" name="save_labels" value="Save Labels to Sync"/>
                                </form>
                                <?php
                            }
                        }


                        ?>

                    </div><!-- end post-body-content -->
                </div><!-- post-body meta box container -->
            </div><!--poststuff end -->
        </div><!-- wrap end -->

        <?php
        return true;
    }


    public function facebook_label_workflows( $workflows ){
        $workflows[] = [
            "key" => "close",
            "name" => "Automatic Closing",
            "description" => "Closes the Disciple.Tools contact(s) linked to the conversation.",
            "action" => "dt_facebook_label_workflows_close"
        ];
        return $workflows;

    }

    public function display_facebook_label_workflows(){
        $workflows = apply_filters( "dt_facebook_label_workflows", [] );
        ?>
        <table class="widefat striped">
            <thead>
            <tr>
                <th>Name</th>
                <th>Key</th>
                <th>Description</th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach ( $workflows as $workflow ){

                ?>
                <tr>
                    <td><?php echo esc_html( $workflow["name"] ) ?></td>
                    <td><?php echo esc_html( $workflow["key"] ) ?></td>
                    <td><?php echo esc_html( $workflow["description"] ) ?></td>
                </tr>
                <?php
            }
            ?>
            </tbody>
        </table>
        <?php
    }

    public function trigger_label_workflow( $contact, $label_id, $page_id ){
        $facebook_labels = get_option( "dt_facebook_labels", [] );
        $workflows = apply_filters( "dt_facebook_label_workflows", [] );

        if ( !empty( $facebook_labels[$page_id][$label_id]["workflow"] ) ){
            $workflow_key = $facebook_labels[$page_id][$label_id]["workflow"];
            foreach ( $workflows as $workflow ){
                if ( $workflow["key"] == $workflow_key ){
                    do_action( $workflow["action"], $contact );
                }
            }
        }
    }

    public function dt_facebook_label_workflows_close( $contact ){
//        @todo include reason closed
        $fields = [
            "overall_status" => "closed"
        ];
        Disciple_Tools_Contacts::update_contact( $contact["ID"], $fields, false );
    }

}