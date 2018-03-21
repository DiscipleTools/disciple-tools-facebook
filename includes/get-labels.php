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
        do_action( "dt_async_$this->action" );
    }

}
