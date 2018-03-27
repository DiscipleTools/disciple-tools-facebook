<?php

class DT_Facebook_Get_Users_For_Labels extends Disciple_Tools_Async_Task {

    protected $action = 'dt_get_users_for_labels';

    /**
     * Prepare data for the asynchronous request
     *
     * @throws Exception If for any reason the request should not happen.
     *
     * @param array $data An array of data sent to the hook
     *
     * @return array
     */
    protected function prepare_data( $data ) {
        return $data;
    }

    /**
     * Run the async task action
     */
    protected function run_action() {
        do_action( "dt_async_dt_get_users_for_labels" );
    }

}

class DT_Facebook_Conversation_Update extends Disciple_Tools_Async_Task {

    protected $action = 'dt_conversation_update';

    /**
     * Prepare data for the asynchronous request
     *
     * @throws Exception If for any reason the request should not happen.
     *
     * @param array $data An array of data sent to the hook
     *
     * @return array
     */
    protected function prepare_data( $data ) {

        return [
            "page_id" => $data[0],
            "thread_id" => $data[1]
        ];
    }

    /**
     * Run the async task action
     */
    protected function run_action() {
        // The nonce is checked by the Disciple_Tools_Async_Task library
        // @codingStandardsIgnoreStart
        if ( isset( $_POST["page_id"] ) && isset( $_POST["thread_id"] ) ){
            $page_id = esc_html( sanitize_text_field( wp_unslash( $_POST["page_id"] ) ) );
            $thread_id = esc_html( sanitize_text_field( wp_unslash( $_POST["thread_id"] ) ) );
            do_action( "dt_async_dt_conversation_update", $page_id, $thread_id );
        }
        // @codingStandardsIgnoreEnd
    }

}

class DT_Facebook_Stats extends Disciple_Tools_Async_Task {

    protected $action = 'dt_facebook_stats';

    protected function prepare_data( $data ) {
        return $data;
    }

    protected function run_action() {
        do_action( "dt_async_dt_facebook_stats" );
    }
}
