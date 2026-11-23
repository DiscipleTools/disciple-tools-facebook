<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Meta Data Deletion Request Callback: Meta POSTs a signed request here when a user removes the app
 * and asks for their data to be deleted.
 * https://developers.facebook.com/docs/development/create-an-app/app-dashboard/data-deletion-callback
 */
class Disciple_Tools_Facebook_Data_Deletion {
    private static $namespace = 'dt_facebook/v1';
    private static $requests_option = 'dt_facebook_data_deletion_requests';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
    }

    public function add_api_routes() {
        register_rest_route(
            self::$namespace, 'dt-public/data-deletion', [
                'methods'  => 'POST',
                'callback' => [ $this, 'data_deletion_callback' ],
                'permission_callback' => '__return_true',
            ]
        );
        register_rest_route(
            self::$namespace, 'dt-public/data-deletion-status', [
                'methods'  => 'GET',
                'callback' => [ $this, 'data_deletion_status' ],
                'permission_callback' => '__return_true',
            ]
        );
    }

    public static function get_callback_url(): string {
        return rest_url( self::$namespace . '/dt-public/data-deletion' );
    }

    public function data_deletion_callback( WP_REST_Request $request ) {
        $app_id = get_option( 'disciple_tools_facebook_app_id', '' );
        $app_secret = get_option( 'disciple_tools_facebook_app_secret', '' );
        if ( empty( $app_id ) || empty( $app_secret ) ) {
            return new WP_Error( 'not_configured', 'Facebook app is not configured', [ 'status' => 500 ] );
        }

        $data = self::parse_signed_request( (string) $request->get_param( 'signed_request' ), $app_secret );
        if ( empty( $data['user_id'] ) ) {
            return new WP_Error( 'invalid_signed_request', 'Invalid signed request', [ 'status' => 400 ] );
        }
        $user_id = (string) $data['user_id'];

        $deleted = 0;
        foreach ( self::find_contact_ids( $user_id, $app_id ) as $post_id ) {
            if ( dt_facebook_delete_data( 'contacts', $post_id, false ) ) {
                $deleted++;
            }
        }

        $code = wp_generate_password( 20, false );
        $requests = get_option( self::$requests_option, [] );
        $requests[ $code ] = [
            'requested_at' => time(),
            'records' => $deleted,
        ];
        update_option( self::$requests_option, $requests, false );

        return [
            'url' => add_query_arg( 'code', $code, rest_url( self::$namespace . '/dt-public/data-deletion-status' ) ),
            'confirmation_code' => $code,
        ];
    }

    public function data_deletion_status( WP_REST_Request $request ) {
        $code = sanitize_text_field( (string) $request->get_param( 'code' ) );
        $requests = get_option( self::$requests_option, [] );
        if ( empty( $code ) || !isset( $requests[ $code ] ) ) {
            return new WP_Error( 'not_found', 'Unknown confirmation code', [ 'status' => 404 ] );
        }

        return [
            'confirmation_code' => $code,
            'status' => 'completed',
            'completed_at' => gmdate( 'c', $requests[ $code ]['requested_at'] ),
        ];
    }

    /**
     * @return array|null the decoded payload, or null if the signature does not match the app secret
     */
    private static function parse_signed_request( string $signed_request, string $app_secret ): ?array {
        $parts = explode( '.', $signed_request, 2 );
        if ( count( $parts ) !== 2 ) {
            return null;
        }
        [ $encoded_sig, $payload ] = $parts;

        $sig = self::base64_url_decode( $encoded_sig );
        $expected_sig = hash_hmac( 'sha256', $payload, $app_secret, true );
        if ( $sig === false || !hash_equals( $expected_sig, $sig ) ) {
            return null;
        }

        $data = json_decode( self::base64_url_decode( $payload ), true );
        if ( !is_array( $data ) || strtoupper( $data['algorithm'] ?? '' ) !== 'HMAC-SHA256' ) {
            return null;
        }
        return $data;
    }

    private static function base64_url_decode( string $input ) {
        return base64_decode( strtr( $input, '-_', '+/' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
    }

    /**
     * Contacts whose stored Facebook ids exactly include $user_id. The shared lookup matches with LIKE,
     * so its results are re-checked here before anything is deleted.
     *
     * @return int[]
     */
    private static function find_contact_ids( string $user_id, string $app_id ): array {
        $post_ids = [];
        foreach ( dt_facebook_find_contacts_with_ids( [], $user_id, $app_id ) as $post ) {
            $facebook_data = maybe_unserialize( get_post_meta( $post->ID, 'facebook_data', true ) );
            if ( !is_array( $facebook_data ) ) {
                continue;
            }
            $ids = array_merge(
                array_values( (array) ( $facebook_data['app_scoped_ids'] ?? [] ) ),
                (array) ( $facebook_data['page_scoped_ids'] ?? [] )
            );
            if ( in_array( $user_id, array_map( 'strval', $ids ), true ) ) {
                $post_ids[] = (int) $post->ID;
            }
        }
        return array_values( array_unique( $post_ids ) );
    }
}
new Disciple_Tools_Facebook_Data_Deletion();
