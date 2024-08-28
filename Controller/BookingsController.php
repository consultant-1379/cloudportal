<?php

class BookingsController extends AppController {

    var $components = array('Session');
    var $uses = array('Booking');

    public function beforeFilter() {
        parent::beforeFilter();
        $this->Auth->allow("process_queue_api", "queue_counts_api", "queue_time_api");
        $pooling_settings = $this->Booking->get_pooling_settings();
        $flagvalue = $pooling_settings['banner_settings']['flag'];
        if ($flagvalue){
            $this->set('flagtext', $pooling_settings['banner_settings']['text']);
        }
    }

    function isAuthorized($user) {
        $have_booking_permission = false;
        if ($user['is_admin'] || (isset($user['permissions']['bookings']['admin_permission']) && $user['permissions']['bookings']['admin_permission'])) {
            $have_booking_permission = true;
        }
        if (in_array($this->action, array('index')))
        {
            if ($have_booking_permission)
            {
                $this->redirect(array('controller' => 'Bookings', 'action' => 'bookings'));
            } else {
                $this->Session->setFlash('Your user id currently doesnt have access to Test Environment on Demand. Please log a ticket to request access','flash_bad');
                return true;
            }
        }
        if (in_array($this->action, array('reports', 'reports_api')))
        {
            return true;
        }
        if (in_array($this->action, array('bookings','bookings_api','select_team','set_users_team','create_api','cancel_api','cancel_queued_booking_api', 'extend_api', 'quotas_api'))) {
            if ($have_booking_permission)
            {
                $users_team = $this->Booking->get_users_team($user['username']);
                $user_in_valid_team = $this->Booking->is_team_valid($users_team);
                $team_selection_actions = array('select_team','set_users_team');
                if (!in_array($this->action,$team_selection_actions) && $user_in_valid_team == null)
                {
                        $this->redirect(array('controller' => 'Bookings', 'action' => 'select_team'));
                }
                return true;
            }
        }

        if ($user['is_admin']) {
            return true;
        }

        return false;
    }

    function index() {

    }

    function bookings() {
        $user = $this->Auth->user();
        $team = $this->Booking->get_users_team($user['username']);
        $this->set('page_for_layout', 'book');
        $this->set("title_for_layout", 'Bookings');
        $pooling_settings = $this->Booking->get_pooling_settings();
        $this->set('vapp_types', $pooling_settings['vapp_types']);
        $this->set('limit_type_message', $this->Booking->get_team_booking_limit_type_message($team));
        $this->set('max_duration_hours', $pooling_settings['max_duration_hours']);
        $this->set('default_duration_hours', $pooling_settings['default_duration_hours']);
        $this->set('users_team', $team);
        $this->set('default_extension_time_hours', $pooling_settings['default_extension_time_hours']);
        $this->set('extension_limit', $pooling_settings['extension_limit']);
    }

    function bookings_api() {
        try {
            $user = $this->Auth->user();
            $users_team = $this->Booking->get_users_team($user['username']);
            $bookings = $this->Booking->get_team_bookings($users_team);
        } catch (Exception $e)
        {
            throw new BadRequestException($e->getMessage());
        }
        $this->set('bookings', $bookings);
        $this->set('_serialize', array('bookings'));
    }

    function queue_time_api() {
        try {
            $queue_times = $this->Booking->get_wait_time_in_queue();
        } catch (Exception $e)
        {
            throw new BadRequestException($e->getMessage());
        }
        $this->set('queue_times', $queue_times);
        $this->set('_serialize', array('queue_times'));
    }

    function queue() {
        $this->set('page_for_layout', 'admin');
        $this->set("title_for_layout", 'Queue Details');
        $pooling_settings = $this->Booking->get_pooling_settings();
        $this->set('vapp_types', $pooling_settings['vapp_types']);
    }

    function queue_api() {
        try {
            $queued_bookings = $this->Booking->get_all_queued_bookings();
        } catch (Exception $e)
        {
            throw new BadRequestException($e->getMessage());
        }
        $this->set('queued_bookings', $queued_bookings);
        $this->set('_serialize', array('queued_bookings'));
    }

    function queue_counts_api() {
        try {
            $get_queue_counts_per_type = $this->Booking->get_queue_counts_per_type();
        } catch (Exception $e)
        {
            throw new BadRequestException($e->getMessage());
        }
        $this->set('queue_counts', $get_queue_counts_per_type);
        $this->set('_serialize', array('queue_counts'));
    }

    function select_team() {
        $current_user = $this->Auth->user();
        $this->set("title_for_layout", 'Select Your Team');
        $this->set('teams', $this->Booking->get_teams_and_parent());
        $this->set('users_team', $this->Booking->get_users_team($current_user['username']));
    }

    function admin() {
        $this->set('page_for_layout', 'admin');
        $this->set("title_for_layout", 'Pooling Usage');
        $pooling_settings = $this->Booking->get_pooling_settings();
        $this->set('vapp_types', $pooling_settings['vapp_types']);
        $this->set('jenkins_url', $pooling_settings['jenkins_settings']['base_url']);
        $this->set('booking_counts_and_limits', $this->Booking->get_booking_counts_and_limits());
    }

    function create_api() {
        try {
            $user = $this->Auth->user();
            $vapp_type = $this->passedArgs['vapp_type'];
            $team = $this->passedArgs['team'];
            $duration_seconds = $this->passedArgs['duration_seconds'];
            $pooling_settings = $this->Booking->get_pooling_settings();
            $team_booking_counts_and_limits = $this->Booking->get_team_booking_counts_and_limits($team)[$vapp_type];
            $team_limit_type = $this->Booking->get_team_booking_limit_type_flag($team, $vapp_type);
            if ($team_limit_type['flag'] == true)
            {
                throw new Exception("Cannot create this booking as your team already has a " . $team_limit_type['vapp_type'] . " vApp");
            }
            if (($team_booking_counts_and_limits['booking_count'] + 1) > $team_booking_counts_and_limits['booking_limit'])
            {
                throw new Exception("Creating this booking would bring you over your teams maximum quota of " . $team_booking_counts_and_limits['booking_limit'] . ' for type ' . $vapp_type);
            }
            $booking_details = $this->Booking->create_queued_booking($vapp_type, $duration_seconds, $user['username'], $team, $user['email']);
        } catch (Exception $e)
        {
            throw new BadRequestException($e->getMessage());
        }
        $this->set('booking', $booking_details);
        $this->set('_serialize', array('booking'));
    }

    function cancel_api() {
        try {
            $user = $this->Auth->user();
            $team = $this->passedArgs['team'];
            $unique_booking_id = $this->passedArgs['unique_booking_id'];
            $this->Booking->cancel_booking($unique_booking_id, $user['username'], $team, $user['email']);
        } catch (Exception $e)
        {
            throw new BadRequestException($e->getMessage());
        }
        $this->set('_serialize', array());
    }
    function cancel_queued_booking_api() {
        try {
            $user = $this->Auth->user();
            $team = $this->passedArgs['team'];
            $unique_booking_id = $this->passedArgs['unique_booking_id'];
            $this->Booking->cancel_queued_booking($unique_booking_id, $user['username'], $team, $user['email']);
        } catch (Exception $e)
        {
            throw new BadRequestException($e->getMessage());
        }
        $this->set('_serialize', array());
    }

    function process_queue_api()
    {
        try {
            $user = $this->Auth->user();
            $processed_queue_items_count = $this->Booking->process_queue();
        } catch (Exception $e)
        {
            throw new BadRequestException($e->getMessage());
        }
        $this->set('processed_queue_items_count', $processed_queue_items_count);
        $this->set('_serialize', array('processed_queue_items_count'));
    }

    function extend_api() {
        try {
            $user = $this->Auth->user();
            $team = $this->passedArgs['team'];
            $unique_booking_id = $this->passedArgs['unique_booking_id'];
            $this->Booking->extend_booking($unique_booking_id, $user['username'], $team, $user['email']);
        } catch (Exception $e)
        {
            throw new BadRequestException($e->getMessage());
        }
        $this->set('_serialize', array());
    }

    function pool_status()
    {
        $pooling_settings = $this->Booking->get_pooling_settings();
        $vapp_types = $pooling_settings['vapp_types'];
        $pool_status = array();
        foreach($vapp_types as $vapp_type => $description)
        {
            $pool_status[$vapp_type] = array (
                'total' => 0,
                'available' => 0
            );
        }
        $slave_list = $this->Booking->list_jenkins_slaves();
        foreach ($slave_list as $slave)
        {
            foreach($vapp_types as $vapp_type => $description)
            {
                $matching_label_regexp = $pooling_settings['jenkins_settings']['slave_regexps'][$vapp_type];
                if (preg_match("/^$matching_label_regexp/m", $slave['displayName'] ) )
                {
                    $pool_status[$vapp_type]['total']++;
                    if ($this->Booking->is_slave_available($slave))
                    {
                        $pool_status[$vapp_type]['available']++;
                    }
                    break;
                }
            }
        }
        $this->set('pool_status', $pool_status);
        $this->set('_serialize', array('pool_status'));
    }

    function quotas_api()
    {
        $user = $this->Auth->user();
        $users_team = $this->Booking->get_users_team($user['username']);
        $quotas = $this->Booking->get_team_booking_counts_and_limits($users_team);
        $this->set('quotas', $quotas);
        $this->set('_serialize', array('quotas'));
    }

    function all_teams_quotas_api()
    {
        $user = $this->Auth->user();
        $quotas = $this->Booking->get_booking_counts_and_limits();
        $this->set('quotas', $quotas);
        $this->set('_serialize', array('quotas'));
    }

    function set_users_team()
    {
        $current_user = $this->Auth->user();
        $team = $this->passedArgs['team'];
        $this->Booking->set_users_team($current_user['username'], $team);
        $this->set('result', null);
        $this->set('_serialize', array('result'));
    }

    function reports()
    {
        $this->set('page_for_layout', 'report');
        $this->set("title_for_layout", 'Booking Reports');
        $pooling_settings = $this->Booking->get_pooling_settings();
        $vapp_types = $pooling_settings['vapp_types'];
        $this->set('vapp_types', $vapp_types);
    }

    function reports_api() {
        $startDate = $this->request->query['start'];
        $endDate   = $this->request->query['end'];
        try {
            $report = $this->Booking->get_bookings_report($startDate, $endDate);
        } catch (Exception $e)
        {
            throw new BadRequestException($e->getMessage());
        }
        $this->set('reports', $report['team_reports']);
        $this->set('overallReport', $report['overall_report']);
        $this->set('_serialize', array('reports', 'overallReport'));
    }
}
?>
