<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

class Disciple_Tools_Facebook_Api {

    public static $facebook_api_version = '14.0';

    //The app secret proof is a sha256 hash of your access token, using the app secret as the key.
    public static function get_app_secret_proof( $access_token ) {
        $app_secret       = get_option( 'disciple_tools_facebook_app_secret' );
        $app_secret_proof = hash_hmac( 'sha256', $access_token, $app_secret );

        return $app_secret_proof;
    }

    public static function get_all_with_pagination( $url ) {
        $request = wp_remote_get( $url );

        if ( is_wp_error( $request ) ) {
            return [];
        } else {
            $body = wp_remote_retrieve_body( $request );
            $page = json_decode( $body, true );
            if ( isset( $page['error'] ) ){
                self::api_error( $page['error'] );
                return new WP_Error( $page['error']['code'], $page['error']['message'], $page['error'] );
            }
            if ( !empty( $page ) ) {
                if ( !isset( $page['paging']['next'] ) ){
                    return $page['data'];
                } else {
                    $next_page = self::get_all_with_pagination( $page['paging']['next'] );
                    return array_merge( $page['data'], $next_page );
                }
            } else {
                return [];
            }
        }
    }

    public static function get_page( $url ){
        update_option( 'dt_facebook_last_call', time() );
        $request = wp_remote_get( $url, [ 'timeout' => 15 ] );

        if ( is_wp_error( $request ) ) {
            return $request;
        }
        $body = wp_remote_retrieve_body( $request );
        $page = json_decode( $body, true );
        if ( isset( $page['error'] ) ){
            self::api_error( $page['error'] );
            return new WP_Error( $page['error']['code'], $page['error']['message'], $page['error'] );
        }
        if ( !empty( $page ) && isset( $page['data'] ) ) {
            return $page;
        }
        return [];
    }

    public static function get_facebook_pages( $access_token ){
        $facebook_pages_url = 'https://graph.facebook.com/v' . self::$facebook_api_version . '/me/accounts?fields=access_token,id,name,business&access_token=' . $access_token;
        return self::get_all_with_pagination( $facebook_pages_url );
    }

    public static function api_error( $error ){
        self::save_log_message( $error['message'], 'error' );
//        if ( isset( $error["code"] ) && $error["code"] == 190 ){
//            if ( "The access token could not be decrypted" === $error["message"] ){
//                $facebook_pages[$id]["integrate"] = 0;
//            }
//        } elseif ( isset( $error["code"] ) ){
//            $this->display_error( "Conversations page: " . $error["message"] );
//            if ( !$error["code"] === 283 ){
//                //we wish to track if there are any other issues we are missing.
//                // $error contains the code, subcode, id, error message and type
//                dt_send_email( "dev@disciple.tools", "Facebook plugin error", get_site_url() . ' ' . serialize( $error ) );
//            }
//        }
    }
    public static function save_log_message( $message, $type = 'error' ){
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

}

