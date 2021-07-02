<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

/**
 * Class Disciple_Tools_Facebook_Tile
 */
class Disciple_Tools_Facebook_Tile {
    /**
     * Disciple_Tools_Admin The single instance of Disciple_Tools_Admin.
     *
     * @var    object
     * @access private
     * @since  0.1.0
     */
    private static $_instance = null;

    /**
     * Main Disciple_Tools_Facebook_Tile Instance
     * Ensures only one instance of Disciple_Tools_Facebook_Integration is loaded or can be loaded.
     *
     * @since  0.1.0
     * @static
     * @return Disciple_Tools_Facebook_Tile instance
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
        add_filter( "dt_details_additional_tiles", [ $this, "dt_details_additional_tiles" ], 10, 2 );
        add_action( "dt_details_additional_section", [ $this, "dt_facebook_add_section" ] );
        add_filter( "dt_contact_duplicate_fields_to_check", [ $this, "add_duplicate_check_field" ] );
        add_filter( "dt_comments_additional_sections", [ $this, "add_comment_section" ], 10, 2 );
    } // End __construct()


    public static function dt_details_additional_tiles( $sections, $post_type = "" ) {
        if ( $post_type === "contacts" ){
            //check if content is there before adding empty tile
            $contact_id    = get_the_ID();
            if ( $contact_id && "contacts" === get_post_type( $contact_id ) ){
                $contact = DT_Posts::get_post( "contacts", $contact_id, true, true );
                if ( !is_wp_error( $contact ) && isset( $contact["facebook_data"] ) ) {
                    $contact_fields = DT_Posts::get_post_field_settings( $post_type );
                    if ( isset( $contact_fields["facebook_data"] ) ) {
                        $sections["contact_facebook_data"] = [ "label" => __( "Facebook", 'disciple_tools' ) ];
                    }
                }
            }
        }
        return $sections;
    }


    public static function dt_facebook_add_section( $section ) {
        if ( $section == "contact_facebook_data" ) {
            $contact_id    = get_the_ID();
            $contact       = DT_Posts::get_post( "contacts", $contact_id, true, true );
            $facebook_data = [];
            if ( is_wp_error( $contact ) ){
                return $section;
            }
            if ( isset( $contact["facebook_data"] ) ) {
                $facebook_data = maybe_unserialize( $contact["facebook_data"] );
            }
            ?>

            <?php
            if ( isset( $facebook_data["names"] ) ) {
                ?>
                <div class="section-subheader">
                    <?php esc_html_e( "Names", 'disciple-tools-facebook' ) ?>
                </div>
                <?php
                if ( is_array( $facebook_data["names"] ) ) {
                    foreach ( $facebook_data["names"] as $id ) {
                        ?>
                        <p><?php echo esc_html( $id ) ?></p>
                    <?php }
                }
            }

            if ( isset( $facebook_data["last_message_at"] ) ) {
                $date = strtotime( $facebook_data["last_message_at"] )
                ?>
                <div class="section-subheader">
                    <?php esc_html_e( "Last message at:", 'disciple-tools-facebook' ) ?>
                </div>
                <p class="last_message_at"><?php echo esc_html( gmdate( "Y-m-d H:m", $date ) ) ?></p>
                <?php
            }

            if ( isset( $facebook_data["links"] ) ) {
                ?>
                <div class="section-subheader">
                    <?php esc_html_e( "Conversation Links:", 'disciple-tools-facebook' ) ?>
                </div>
                <?php
                foreach ( $facebook_data["links"] as $link ): ?>
                    <p class="facebook_message_links"><a href="<?php echo esc_html( 'http://facebook.com'. $link ) ?>" target="_blank"><?php echo esc_html( $link )?></a></p>
                <?php endforeach; ?>
                <?php
            }
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

    public function add_comment_section( $sections, $post_type ){
        if ( $post_type === "contacts" ){
            $sections[] = [
                "key" => "facebook",
                "label" => __( "Facebook", 'disciple-tools-facebook' )
            ];
        }
        return $sections;
    }

    public function add_duplicate_check_field( $fields ) {
        $fields[] = "facebook_data";

        return $fields;
    }




}
