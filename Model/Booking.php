<?php

App::uses('CakeEmail', 'Network/Email');

class Booking extends AppModel {

    var $name = 'Booking';
    var $useTable = false;

    function book_vapp($vapp_type, $duration_seconds, $username, $team, $email, $slave_to_use, $vapp_template_name, $vapp_gateway_hostname, $unique_booking_id)
    {
        try {
            $this->make_slave_busy($slave_to_use, $duration_seconds, $username, $team, $email, $vapp_type, $vapp_gateway_hostname, $vapp_template_name, $unique_booking_id);
            $queued_makeslavebusy_jenkins_job_id = $this->get_queued_makeslavebusy_jenkins_job_id($unique_booking_id);
            if ($queued_makeslavebusy_jenkins_job_id !== null)
            {
                CakeLog::write('pooling', 'INFO: A user (' . $username . ') from team "' . $team . ' failed to book a vApp due to MakeSlaveBusy job went into build queue on Jenkins. The booking is still at the same place on the queue on TEOD system and will be processed. The queued MakeSlaveBusy job will be cancelled now');
                $this->cancel_queued_makeslavebusy_jenkins_job($queued_makeslavebusy_jenkins_job_id);
                throw new Exception("Booking failed due to MakeSlaveBusy Jenkins job went to into queue on Jenkins.");
            }
            $BookingLog = ClassRegistry::init('BookingLog');
            $data = array('id' => $unique_booking_id, 'team' => $team, 'duration_seconds' => $duration_seconds, 'vapp_type' => $vapp_type);
            $BookingLog->create();
            $BookingLog->save($data);
            $vapp_gateway_ipaddress = gethostbyname($vapp_gateway_hostname);
            $vapp_gateway_fqhn = gethostbyaddr($vapp_gateway_ipaddress);
            $duration_hours = round($duration_seconds / 3600);
            $deletion_date_time = date('l jS F \a\t H:i T', strtotime('+' . $duration_hours . ' hours'));
            $latest_templateversion = $this->get_latest_template_version_in_pool($vapp_template_name, $vapp_type);
            if($latest_templateversion === null){
                $templatestatus = "The assigned vApp " . $vapp_gateway_hostname . " is on the latest template version in the pool.";
                $latest_templateversion = $vapp_template_name;
                $this->send_booking_created_notification($vapp_type, $duration_hours, $username, $email, $vapp_gateway_hostname, $vapp_gateway_fqhn, $vapp_gateway_ipaddress, $vapp_template_name, $deletion_date_time, $templatestatus, $latest_templateversion);
            }else{
                $templatestatus = "The assigned vApp " . $vapp_gateway_hostname . " is NOT on the latest template version in the pool.";
                $this->send_booking_created_notification($vapp_type, $duration_hours, $username, $email, $vapp_gateway_hostname, $vapp_gateway_fqhn, $vapp_gateway_ipaddress, $vapp_template_name, $deletion_date_time, $templatestatus, $latest_templateversion);
            }
            CakeLog::write('pooling', 'INFO: A user (' . $username . ') from team "' . $team . '" got a "' . $vapp_type . '" Test Environment vApp with gateway name ' . $vapp_gateway_fqhn . ' until ' . $deletion_date_time);
        } catch (Exception $e)
        {
            CakeLog::write('pooling_error', 'ERROR: A user (' . $username . ') from team "' . $team . '" was not able to be given a "' . $vapp_type . '" Test Environment vApp because of the following exception: ' . $e->getMessage());
            $verbose_details = $username . ' from team "' . $team . '" was not able to book a "' . $vapp_type . '" vApp';
            $exception_message = $e->getMessage();
            $this->report_booking_exception('book_vapp', $e->getMessage(), $e, $verbose_details);
            throw $e;
        }
    }

    function get_latest_template_version_in_pool($vapp_template_name, $vapp_type)

    {
        list($part1, $part2) = explode("Ready_", $vapp_template_name, 2);
        list($drop,$version) = explode("_", $part2);
        $vapp_template_version = $drop . "." . $version;
        $pooling_settings = $this->get_pooling_settings();
        $job_url = $pooling_settings['jenkins_settings']['base_url'] . '/job/' . $pooling_settings['jenkins_settings'][$vapp_type . '_template_version_jobname'] . '/api/json?tree=builds[number]{0,1}';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);


        curl_setopt($ch, CURLOPT_URL, $job_url);
        $result = curl_exec($ch);
        $response_details = curl_getinfo($ch);
        curl_close($ch);
        if ($response_details['http_code'] != 200)
        {
            throw new Exception("Didn't receive a 200 response code from jenkins when getting the latest build ID. Got response code " . $response_details['http_code'] . ". Here was the output: " . $result);
        }
        $template_log = json_decode($result, true);
        $matching_label_regexp = $pooling_settings['jenkins_settings']['slave_regexps'][$vapp_type];
        foreach ($template_log['builds'] as $build)
        {
            $build_number = $build['number'];
            $build_num_url = $pooling_settings['jenkins_settings']['base_url'] . '/job/' . $pooling_settings['jenkins_settings'][$vapp_type.'_template_version_jobname'] . '/' . $build_number .  '/consoleText';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, $build_num_url);
            $result = curl_exec($ch);
            $response_details = curl_getinfo($ch);
            curl_close($ch);
            if ($response_details['http_code'] != 200)
            {
                throw new Exception("Didn't receive a 200 response code from jenkins when getting the list of template versions in pool. Got response code " . $response_details['http_code'] . ". Here was the output: " . $result);
            }
            $rows = explode("\n", $result);
            $array_rows = array();
            foreach($rows as $row => $data)
            {
                if (preg_match("/^$matching_label_regexp/m", $data ) )
                {
                    $row_data = explode('-----', $data);
                    $info[$row]['Jenkins_Slave']           = $row_data[0];
                    $info[$row]['Vapp_Template_Name']      = $row_data[1];
                    $row_templates = $info[$row]['Vapp_Template_Name'];
                    array_push($array_rows, $row_templates);
                 }
            }
            arsort($array_rows);
            foreach($array_rows as $template_value)
            {
                list($part1, $part2) = explode("Ready_", $template_value, 2);
                list($drop,$version) = explode("_", $part2);
                $template_version = $drop . "." . $version;
                if(version_compare($template_version ,$vapp_template_version, '>'))
                {
                    return $template_value;
                }
            }
            return null;
        }
    }

    function create_queued_booking($vapp_type, $duration_seconds, $username, $team, $email)
    {
        $pooling_settings = $this->get_pooling_settings();
        $duration_hours = round($duration_seconds / 3600);
        if ($duration_hours > $pooling_settings['max_duration_hours'])
        {
            throw new Exception('The requested duration of the Test Environment vApp booking, ' . $this->seconds_to_hours_minutes_and_seconds($duration_seconds) . ', is greater than the maximum allowed of ' . $pooling_settings['max_duration_hours'] . ' hours');
        }
        $unique_booking_id = substr( "abcdefghijklmnopqrstuvwxyz" ,mt_rand( 0 ,25 ) ,1 ).substr( md5( time( ) ) ,1 );
        $queued_booking = array (
            'slave_to_use' => '-',
            'duration_seconds' => $duration_seconds,
            'username' => $username,
            'team' => $team,
            'vapp_type' => $vapp_type,
            'vapp_gateway_hostname' => '-',
            'vapp_template_name' => '-',
            'email' => $email,
            'unique_booking_id' => $unique_booking_id,
            'created_datetime' => date('d/m/Y H:i:s'),
            'time_remaining_seconds' => 0,
            'build_number' => '-',
            'status' => 'queued'
        );
        CakeLog::write('pooling', 'INFO: A user (' . $username . ') from team "' . $team . '" booked a "' . $vapp_type . '" Test Environment vApp. Its going to be queued now with unique booking id of ' . $unique_booking_id);
        $this->add_booking_to_queue($vapp_type, $queued_booking);
        return $queued_booking;
    }

    function cancel_queued_booking($unique_booking_id, $username, $team, $email)
    {
        try {
            $cancellation_details = $this->cancel_queued_booking_internal($unique_booking_id, $username, $email);
            $original_username = $cancellation_details['original_username'];
            CakeLog::write('pooling', 'INFO: A user (' . $username . ') from team "' . $team . '" cancelled a queued booking which was originally booked by user (' . $original_username . ') with unique booking id of ' . $unique_booking_id);
        } catch (Exception $e)
        {
            CakeLog::write('pooling_error', 'ERROR: A user (' . $username . ') from team "' . $team . '" was not able to cancel a queued booking because of the following exception: ' . $e->getMessage());
            $verbose_details = $username . ' from team "' . $team . '" was not able to cancel a queued booking with unique booking id of ' . $unique_booking_id;
            $exception_message = $e->getMessage();
            $this->report_booking_exception('cancel_queued_booking', $e->getMessage(), $e, $verbose_details);
            throw $e;
        }
    }

    function cancel_queued_booking_internal($unique_booking_id, $username, $email)
    {
        $pooling_settings = $this->get_pooling_settings();
        $vapp_types = $pooling_settings['vapp_types'];
        foreach ($vapp_types as $vapp_type => $description)
        {
            $this->get_booking_lock($vapp_type,6000);
            $memcache_key = $vapp_type . '_queue';
            if (($queued_bookings = Cache::read($memcache_key, 'memcache_default')) === false) {
                $queued_bookings = array();
            }
            foreach ($queued_bookings as $key => $queued_booking)
            {
                if ($queued_booking['unique_booking_id'] === $unique_booking_id)
                {
                    $cancellation_details = array();
                    $cancellation_details['original_username'] = $queued_booking['username'];
                    unset ($queued_bookings[$key]);
                    Cache::write($memcache_key, $queued_bookings, 'memcache_default');
                    $this->clear_booking_lock($vapp_type);
                    $this->send_booking_cancelled_notification($queued_booking['vapp_type'], $username, $email, $queued_booking['username'], $queued_booking['email'], null, $queued_booking['created_datetime'],$queued_booking['status']);
                    return $cancellation_details;
                }
            }
            $this->clear_booking_lock($vapp_type);
        }
        throw new Exception("Couldn't find a booking with id of " . $unique_booking_id . ". Therefore it couldn't be cancelled.");
    }

    function cancel_booking($unique_booking_id, $username, $team, $email)
    {
        try {
            $cancellation_details = $this->cancel_booking_internal($unique_booking_id, $username, $email);
            $original_username = $cancellation_details['original_username'];
            $vapp_gateway_hostname = $cancellation_details['vapp_gateway_hostname'];
            CakeLog::write('pooling', 'INFO: A user (' . $username . ') from team "' . $team . '" cancelled a booking with gateway name ' . $vapp_gateway_hostname . ' which was originally booked by user (' . $original_username . ')');
        } catch (Exception $e)
        {
            CakeLog::write('pooling_error', 'ERROR: A user (' . $username . ') from team "' . $team . '" was not able to cancel a booking with gateway name "' . $vapp_gateway_hostname . '" because of the following exception: ' . $e->getMessage());
            $verbose_details = $username . ' from team "' . $team . '" was not able to cancel a booking with gateway name "' . $vapp_gateway_hostname . '"';
            $exception_message = $e->getMessage();
            $this->report_booking_exception('cancel_booking', $e->getMessage(), $e, $verbose_details);
            throw $e;
        }
    }

    function cancel_booking_internal($unique_booking_id, $username, $email)
    {
        $bookings = $this->get_all_nonqueued_bookings();
        $build_number = null;
        foreach ($bookings as $booking)
        {
            if($booking['unique_booking_id'] == $unique_booking_id)
            {
                $build_number = $booking['build_number'];
                $vapp_type = $booking['vapp_type'];
                $vapp_gateway_hostname = $booking['vapp_gateway_hostname'];
                $original_username = $booking['username'];
                $original_email = $booking['email'];
                $created_timestamp = $booking['created_timestamp'];
                $created_datetime = $booking['created_datetime'];
                break;
            }
        }
        if ($build_number == null)
        {
            throw new Exception('Couldn\'t figure out the build number that is making this slave busy, can\'t cancel the booking because of this');
        }
        $output = $this->get_script_output($vapp_gateway_hostname,"
            (
                touch /delete_me
            ) 2>&1
        ");
        if ($output != '')
        {
            throw new Exception('ERROR: Something went wrong cancelling the booking:' . $output);
        }

        $description = 'Cancelled';
        $this->set_job_description($build_number,$description);
        $this->send_booking_cancelled_notification($vapp_type, $username, $email, $original_username, $original_email, $vapp_gateway_hostname, $created_datetime,$booking['status']);
        $cancellation_details = array(
            'original_username' => $original_username,
            'vapp_gateway_hostname' => $vapp_gateway_hostname
        );
        if (strlen($unique_booking_id) == 32)
        {
            $BookingLog = ClassRegistry::init('BookingLog');
            $duration_seconds = time() - $created_timestamp;
            $BookingLog->id = $unique_booking_id;
            $data = array('duration_seconds' => $duration_seconds, 'canceled' => true);
            $BookingLog->save($data);
        }
        return $cancellation_details;
    }


    function extend_booking($unique_booking_id, $username, $team, $email)
    {
        try {
            $extension_details = $this->extend_booking_internal($unique_booking_id, $username, $email);
            $original_username = $extension_details['original_username'];
            $vapp_gateway_hostname = $extension_details['vapp_gateway_hostname'];
            CakeLog::write('pooling', 'INFO: A user (' . $username . ') from team "' . $team . '" extended a booking with gateway name ' . $vapp_gateway_hostname . ' which was originally booked by user (' . $original_username . ')');
        } catch (Exception $e)
        {
            CakeLog::write('pooling_error', 'ERROR: A user (' . $username . ') from team "' . $team . '" was not able to extend a booking with booking id "' . $unique_booking_id . '" because of the following exception: ' . $e->getMessage());
            $verbose_details = $username . ' from team "' . $team . '" was not able to extend a booking with booking id "' . $unique_booking_id . '"';
            $this->report_booking_exception('extend_booking', $e->getMessage(), $e, $verbose_details);
            throw $e;
        }
    }

    function extend_booking_internal($unique_booking_id, $username, $email)
    {
        $bookings = $this->get_all_bookings();
        $build_number = null;
        $pooling_settings = $this->get_pooling_settings();
        $extension_seconds = $pooling_settings['default_extension_time_hours'] * 3600;
        foreach ($bookings as $booking)
        {
            if($booking['unique_booking_id'] == $unique_booking_id)
            {
                $build_number = $booking['build_number'];
                $vapp_type = $booking['vapp_type'];
                $vapp_gateway_hostname = $booking['vapp_gateway_hostname'];
                $original_username = $booking['username'];
                $original_email = $booking['email'];
                $created_datetime = $booking['created_datetime'];
                $created_timestamp = $booking['created_timestamp'];
                $duration_seconds = $booking['duration_seconds'];
                $duration_seconds_before_extension = $booking['duration_seconds_before_extension'];
                $extension_count = $booking['extension_count'];
                break;
            }
        }
        if ($build_number == null)
        {
            throw new Exception('Couldn\'t figure out the build number that is making this slave busy, can\'t extend the booking because of this');
        }
        if ($extension_count > $pooling_settings['extension_limit']) {
            throw new Exception("Extending this booking is not permitted, as you have reached the maximum number of available extensions");
        }
        $extension_count += 1;
        if ($extension_count == 1) {
            $extension_allowed_before_end_percentage = 20; // This is the percentage of the initial booking duration that must be left before you can make an extension
            $first_extension_allowed_datetime = $created_timestamp + $duration_seconds_before_extension - ($extension_allowed_before_end_percentage * $duration_seconds_before_extension / 100);
            if (strtotime("now") < $first_extension_allowed_datetime) {
                throw new Exception("You cannot extend the initial booking yet. You can extend after " . date('l jS F \a\t H:i T', $first_extension_allowed_datetime));
            }
        } else {
            $extension_allowed_before_end_minutes = 30; // This is how many minutes must be left before the end of an existing extension before you can make another one
            $extension_allowed_datetime = $created_timestamp + $duration_seconds - ($extension_allowed_before_end_minutes * 60);
            if (strtotime("now") < $extension_allowed_datetime) {
                throw new Exception("You cannot re-extend this booking yet. You can extend after " . date('l jS F \a\t H:i T', $extension_allowed_datetime));
            }
        }
        $output = $this->get_script_output($vapp_gateway_hostname,"
            (
                echo " . $pooling_settings['default_extension_time_hours'] * 3600 . " > /extend_me" . $extension_count . "
            ) 2>&1
        ");
        if ($output != '')
        {
            throw new Exception('ERROR: Something went wrong extending the booking:' . $output);
        }

        $description = 'Extended_' . $extension_count . '_times';
        $this->set_job_description($build_number,$description);
        $this->send_booking_extended_notification($vapp_type, $username, $email, $original_username, $original_email, $vapp_gateway_hostname, $created_datetime, $extension_seconds/3600);
        $extension_details = array(
            'original_username' => $original_username,
            'vapp_gateway_hostname' => $vapp_gateway_hostname
        );
        if (strlen($unique_booking_id) == 32)
        {
            $BookingLog = ClassRegistry::init('BookingLog');
            $duration_seconds += $extension_seconds;
            $BookingLog->id = $unique_booking_id;
            $data = array('duration_seconds' => $duration_seconds, 'extended' => true, 'extension_count' => $extension_count);
            $BookingLog->save($data);
        }
        return $extension_details;
    }


    function set_job_description($build_number,$description)
    {
        $pooling_settings = $this->get_pooling_settings();
        $run_job_url = $pooling_settings['jenkins_settings']['base_url'] . '/job/' . $pooling_settings['jenkins_settings']['job_name']. '/' . $build_number .'/submitDescription?description=' . $description;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $pooling_settings['jenkins_settings']['username'] . ':' . $pooling_settings['jenkins_settings']['password']);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_URL, $run_job_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        $result = curl_exec($ch);
        $response_details = curl_getinfo($ch);
        curl_close($ch);
        if ($response_details['http_code'] != 302)
        {
            throw new Exception("Didn't receive a 302 response code from jenkins when setting the job description. Got response code " . $response_details['http_code'] . ". Here was the output: " . $result);
        }
    }

    function get_pooling_settings()
    {
        Configure::load('pooling');
        return Configure::read('pooling_settings');
    }
    function list_jenkins_slaves()
    {
        $pooling_settings = $this->get_pooling_settings();
        $computer_url = $pooling_settings['jenkins_settings']['base_url'] . '/computer/api/json?tree=computer[displayName,idle,temporarilyOffline,offline]';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $computer_url);
        $result = curl_exec($ch);
        $response_details = curl_getinfo($ch);
        curl_close($ch);
        if ($response_details['http_code'] != 200)
        {
            throw new Exception("Didn't receive a 200 response code from jenkins when getting the list of slaves. Got response code " . $response_details['http_code'] . ". Here was the output: " . $result);
        }
        $slave_list = json_decode($result, true);
        return $slave_list['computer'];
    }

    function get_jenkins_slave_details($slave_name)
    {


        $pooling_settings = $this->get_pooling_settings();
        $slave_details_url = $pooling_settings['jenkins_settings']['base_url'] . '/computer/' . $slave_name . '/config.xml';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $pooling_settings['jenkins_settings']['username'] . ':' . $pooling_settings['jenkins_settings']['password']);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_URL, $slave_details_url);
        $result = curl_exec($ch);
        $response_details = curl_getinfo($ch);
        curl_close($ch);
        if ($response_details['http_code'] != 200)
        {
            throw new Exception("Didn't receive a 200 response code from jenkins when getting details about the slave. Got response code " . $response_details['http_code'] . ". Here was the output: " . $result);
        }

        $xml_object = simplexml_load_string($result, "SimpleXMLElement", LIBXML_NOCDATA | LIBXML_NOERROR |  LIBXML_ERR_NONE | LIBXML_NOWARNING);
        $json_string = json_encode($xml_object);

        $slave_details = json_decode($json_string, true);
        return $slave_details;
    }

    function make_slave_busy($slave_name, $duration_seconds, $username, $team, $email, $vapp_type, $vapp_gateway_hostname, $vapp_template_name, $unique_booking_id)
    {
        $pooling_settings = $this->get_pooling_settings();
        $run_job_url = $pooling_settings['jenkins_settings']['base_url'] . '/job/' . $pooling_settings['jenkins_settings']['job_name']. '/buildWithParameters?slave_to_use=' . $slave_name . '&duration_seconds=' . $duration_seconds . '&username=' . $username . '&team=' . urlencode($team) . '&vapp_type=' . $vapp_type . '&vapp_gateway_hostname=' . $vapp_gateway_hostname . '&vapp_template_name=' . $vapp_template_name . '&email=' . $email . '&unique_booking_id=' . $unique_booking_id;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $pooling_settings['jenkins_settings']['username'] . ':' . $pooling_settings['jenkins_settings']['password']);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_URL, $run_job_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        $result = curl_exec($ch);
        $response_details = curl_getinfo($ch);
        curl_close($ch);
        if ($response_details['http_code'] != 201)
        {
            throw new Exception("Didn't receive a 200 response code from jenkins when making the vApp busy. Got response code " . $response_details['http_code'] . ". Here was the output: " . $result . " " . $run_job_url);
        }
    }

    function get_queued_makeslavebusy_jenkins_job_id($unique_booking_id)
    {
        $pooling_settings = $this->get_pooling_settings();
        $run_job_url = $pooling_settings['jenkins_settings']['base_url'] . '/job/' . $pooling_settings['jenkins_settings']['job_name']. '/api/json?tree=queueItem[params,url]';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $pooling_settings['jenkins_settings']['username'] . ':' . $pooling_settings['jenkins_settings']['password']);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_URL, $run_job_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        $result = curl_exec($ch);
        $response_details = curl_getinfo($ch);
        curl_close($ch);
        if ($response_details['http_code'] != 200)
        {
            throw new Exception("Didn't receive a 200 response code from jenkins when checking queued MakeSlaveBusy Jenkins job id. Got response code " . $response_details['http_code'] . ". Here was the output: " . $result . " " . $run_job_url);
        }
        if (strpos($result, $unique_booking_id))
        {
            $queued_makeslavebusy_job_details = json_decode($result, true);
            $queued_makeslavebusy_job_url = $queued_makeslavebusy_job_details['queueItem']['url'];
            $queued_makeslavebusy_job_id = explode('/', $queued_makeslavebusy_job_url)[2];
            return $queued_makeslavebusy_job_id;
        }else{
            return null;
        }
    }

    function cancel_queued_makeslavebusy_jenkins_job($queued_makeslavebusy_jenkins_job_id)
    {
        $pooling_settings = $this->get_pooling_settings();
        $run_job_url = $pooling_settings['jenkins_settings']['base_url'] . '/queue/cancelItem?id=' . $queued_makeslavebusy_jenkins_job_id;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $pooling_settings['jenkins_settings']['username'] . ':' . $pooling_settings['jenkins_settings']['password']);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_URL, $run_job_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        $result = curl_exec($ch);
        $response_details = curl_getinfo($ch);
        curl_close($ch);
        if ($response_details['http_code'] != 302)
        {
            throw new Exception("Didn't receive a 302 response code from jenkins when cancelling queued MakeSlaveBusy Jenkins job. Got response code " . $response_details['http_code'] . ". Here was the output: " . $result . " " . $run_job_url);
        }
    }

    function is_slave_available($slave)
    {
        if ($slave['idle'] && !$slave['offline'] && !$slave['temporarilyOffline'])
        {
            return true;
        } else {
            return false;
        }
    }

    function find_next_available_slave($vapp_type)
    {
        $pooling_settings = $this->get_pooling_settings();
        $slave_list = $this->list_jenkins_slaves();
        $matching_label_regexp = $pooling_settings['jenkins_settings']['slave_regexps'][$vapp_type];
        $slave_to_use = null;
        foreach ($slave_list as $slave)
        {
            if (preg_match("/^$matching_label_regexp/m", $slave['displayName'] ) )
            {
                if ($this->is_slave_available($slave))
                {
                    $slave_to_use = $slave;
                    break;
                }
            }
        }
        if ($slave_to_use == null)
        {
            return null;
        }
        $unique_booking_id = substr( "abcdefghijklmnopqrstuvwxyz" ,mt_rand( 0 ,25 ) ,1 ).substr( md5( time( ) ) ,1 );
        $slave_details = $this->get_jenkins_slave_details($slave_to_use['displayName']);
        return $slave_details;
    }




    function is_slave_offline($slave)
    {
        if ($slave['offline'])
        {
            return true;
        } else {
            return false;
        }
    }

    function find_offline_slaves_list($vapp_type)
    {
        $pooling_settings = $this->get_pooling_settings();
        $slave_list = $this->list_jenkins_slaves();
        $matching_label_regexp = $pooling_settings['jenkins_settings']['slave_regexps'][$vapp_type];
        $offline_slaves_list = array();
        foreach ($slave_list as $slave)
        {
            if (preg_match("/^$matching_label_regexp/m", $slave['displayName'] ) )
            {
                if ($this->is_slave_offline($slave))
                {
                    array_push($offline_slaves_list, $slave);
                }
            }
        }
        return $offline_slaves_list;
    }


    function add_booking_to_queue($vapp_type, $queued_booking)
    {
        $this->get_booking_lock($vapp_type,6000);
        $key = $vapp_type . '_queue';
        if (($vapp_type_queue = Cache::read($key, 'memcache_default')) === false) {
            $vapp_type_queue = array();
        }
        array_push($vapp_type_queue, $queued_booking);
        Cache::write($key, $vapp_type_queue, 'memcache_default');
        $this->clear_booking_lock($vapp_type);
    }

    function process_queue()
    {
        $processed_queue_items_count = 0;
        $pooling_settings = $this->get_pooling_settings();
        $vapp_types = $pooling_settings['vapp_types'];
        foreach ($vapp_types as $vapp_type => $description)
        {
            $this->get_booking_lock($vapp_type,6000);
            $key = $vapp_type . '_queue';
            if (($vapp_type_queue = Cache::read($key, 'memcache_default')) === false) {
                $vapp_type_queue = array();
            }
            try {
                while($vapp_type_queue)
                {
                    $slave_details = $this->find_next_available_slave($vapp_type);
                    if ($slave_details == null)
                    {
                        break;
                    } else {
                        $queued_booking = array_shift($vapp_type_queue);
                        CakeLog::write('pooling', 'INFO: Found a free slave of type ' . $vapp_type . ', creating an actual booking for unique booking id of ' . $queued_booking['unique_booking_id'] . ', giving it slave ' . $slave_details['name']);
                        $this->book_vapp($queued_booking['vapp_type'],$queued_booking['duration_seconds'],$queued_booking['username'],$queued_booking['team'],$queued_booking['email'],$slave_details['name'],$slave_details['nodeMetaData']['vAppTemplateName'],$slave_details['nodeMetaData']['gateway__details']['gateway__hostname'],$queued_booking['unique_booking_id']);
                        Cache::write($key, $vapp_type_queue, 'memcache_default');
                        $seconds_in_queue = time() - strtotime(str_replace('/', '-', $queued_booking['created_datetime']));
                        CakeLog::write('pooling', 'INFO: Booking completed for unique booking id of ' . $queued_booking['unique_booking_id'] . '. It was in the queue for ' . $this->seconds_to_hours_minutes_and_seconds($seconds_in_queue));
                        $BookingLog = ClassRegistry::init('BookingLog');
                        $BookingLog->id = $queued_booking['unique_booking_id'];
                        $data = array('queue_seconds' => $seconds_in_queue);
                        $BookingLog->save($data);
                        $processed_queue_items_count++;
                    }
                }
            } catch (Exception $e)
            {
                $this->clear_booking_lock($vapp_type);
                throw $e;
            }
            $this->clear_booking_lock($vapp_type);
        }
        return $processed_queue_items_count;
    }

    function get_all_queued_bookings_grouped_by_type()
    {
        $queued_bookings = array();
        $pooling_settings = $this->get_pooling_settings();
        $vapp_types = $pooling_settings['vapp_types'];
        foreach ($vapp_types as $vapp_type => $description)
        {
            $key = $vapp_type . '_queue';
            if (($queued_bookings_of_type = Cache::read($key, 'memcache_default')) === false) {
                $queued_bookings_of_type = array();
            }
            $queued_bookings[$vapp_type] = $queued_bookings_of_type;
        }
        return $queued_bookings;
    }

    function get_all_queued_bookings()
    {
        $queued_bookings_grouped_by_type = $this->get_all_queued_bookings_grouped_by_type();
        $queued_bookings = array();
        $pooling_settings = $this->get_pooling_settings();
        $vapp_types = $pooling_settings['vapp_types'];
        $queue_position_counter = array();
        foreach ($vapp_types as $vapp_type => $description)
        {
            $queue_position_counter[$vapp_type] = 0;
        }
        foreach ($queued_bookings_grouped_by_type as $vapp_type => $bookings)
        {
            foreach ($bookings as $booking)
            {
                $queue_position_counter[$vapp_type]++;
                $booking['queue_position'] = $queue_position_counter[$vapp_type];
                array_push($queued_bookings,$booking);
            }
        }
        return $queued_bookings;
    }

    function get_queue_counts_per_type()
    {
        $pooling_settings = $this->get_pooling_settings();
        $vapp_types = $pooling_settings['vapp_types'];
        $counts = array();
        $queued_bookings_grouped_by_type = $this->get_all_queued_bookings_grouped_by_type();
        foreach ($vapp_types as $vapp_type => $description)
        {
            $counts[$vapp_type] = count($queued_bookings_grouped_by_type[$vapp_type]);
        }
        return $counts;
    }

    function get_booking_lock($vapp_type, $timeout = 30)
    {
        $app_path = dirname(APP) . "/" . basename(APP);
        $LOCKFILE = "files/locks/vapp_locks/" . $vapp_type;
        $cmd = $app_path . "/webroot/Locker/get_lock.sh -f " . $LOCKFILE . " -p 1234 -t $timeout -r yes 2>&1";
        $output = shell_exec($cmd);
    }

    function clear_booking_lock($vapp_type)
    {
        $app_path = dirname(APP) . "/" . basename(APP);
        $LOCKFILE = "files/locks/vapp_locks/" . $vapp_type;
        $cmd = $app_path . "/webroot/Locker/clear_lock.sh -f " . $LOCKFILE . " -p 1234";
        $output = shell_exec($cmd);
    }

    function get_team_booking_limit_for_type($team,$vapp_type)
    {
        $pooling_settings = $this->get_pooling_settings();
        if (isset($pooling_settings['team_booking_limits'][$team]['vapp_types'][$vapp_type]))
        {
            $limit = $pooling_settings['team_booking_limits'][$team]['vapp_types'][$vapp_type];
        } else {
            $limit = $pooling_settings['default_booking_limits']['vapp_types'][$vapp_type];
        }
        return $limit;
    }

    function get_team_limit_type($team)
    {
        $pooling_settings = $this->get_pooling_settings();
        if (isset($pooling_settings['team_booking_limits'][$team]['limit_type']))
        {
            $limit_type = $pooling_settings['team_booking_limits'][$team]['limit_type'];
        } else {
            $limit_type = $pooling_settings['default_booking_limits']['limit_type'];
        }
        return $limit_type;
    }

    function send_booking_cancelled_notification($vapp_type, $username, $email, $original_username, $original_email, $vapp_gateway_hostname, $created_datetime, $status)
    {
        $subject = "'" . $vapp_type . "' Test Environment vApp Booking Cancelled";
        if ($status == 'booked')
        {
            $subject = $subject . '- ' . $vapp_gateway_hostname;
        } else
        {
            $subject = 'Queued ' . $subject;
        }
        $Email = new CakeEmail();
        $Email->emailFormat('html');
        $Email->from(array('no_reply@ericsson.com' => 'Test Environment on Demand'));
        $Email->to(array($email,$original_email));
        $Email->subject($subject);
        $Email->template('booking_cancelled', 'custom');
        $Email->viewVars(array(
            'username' => $username,
            'original_username' => $original_username,
            'vapp_gateway_hostname' => $vapp_gateway_hostname,
            'created_datetime' => $created_datetime,
            'status' => $status
        ));
        $Email->send();
    }

    function send_booking_extended_notification($vapp_type, $username, $email, $original_username, $original_email, $vapp_gateway_hostname, $created_datetime, $extension)
    {
        $subject = "'" . $vapp_type . "' Test Environment vApp Booking Extended";
        $subject = $subject . '- ' . $vapp_gateway_hostname;
        $Email = new CakeEmail();
        $Email->emailFormat('html');
        $Email->from(array('no_reply@ericsson.com' => 'Test Environment on Demand'));
        $Email->to(array($email,$original_email));
        $Email->subject($subject);
        $Email->template('booking_extended', 'custom');
        $Email->viewVars(array(
            'username' => $username,
            'original_username' => $original_username,
            'vapp_gateway_hostname' => $vapp_gateway_hostname,
            'created_datetime' => $created_datetime,
            'extension' => $extension
        ));
        $Email->send();
    }

    function send_booking_created_notification($vapp_type, $duration_hours, $username, $email, $vapp_gateway_hostname, $vapp_gateway_fqhn, $vapp_gateway_ipaddress, $vapp_template_name, $deletion_date_time, $templatestatus, $latest_templateversion)
    {
        $team = $this->get_users_team ($username);
        $limit_type = $this->get_team_limit_type($team);
        $vpn_path = $this->create_vpn_file($vapp_gateway_hostname, $vapp_gateway_ipaddress);
        if ($duration_hours == 1)
        {
            $hours_string = "hour";
        } else {
            $hours_string = "hours";
        }
        $subject = "'" . $vapp_type . "' Test Environment vApp Booking Complete - " . $vapp_gateway_hostname;
        $Email = new CakeEmail();
        $Email->emailFormat('html');
        $Email->from(array('no_reply@ericsson.com' => 'Test Environment on Demand'));
        $Email->to($email);
        $Email->subject($subject);
        $Email->attachments($vpn_path);
        $Email->template('booking_success', 'custom');
        $Email->viewVars(array(
            'username' => $username,
            'vapp_type' => $vapp_type,
            'vapp_gateway_fqhn' => $vapp_gateway_fqhn,
            'vapp_template_name' => $vapp_template_name,
            'duration_hours' => $duration_hours,
            'deletion_date_time' => $deletion_date_time,
            'templatestatus' => $templatestatus,
            'latest_templateversion' => $latest_templateversion,
            'limit_type' => $limit_type,
        ));
        $Email->send();
    }

    function create_vpn_file($gateway_hostname, $gateway_ipaddress)
    {
        $app_path = dirname(APP) . "/" . basename(APP);
        $vpn_file = $gateway_hostname . '_psk.vpn';
        $vpn_path = $app_path . '/webroot/files/shrewsoft/' . $vpn_file;

        //content of VPN Configuration File
        $vpnConfigurationFile = "n:version:4
n:network-ike-port:500
n:network-mtu-size:1380
n:client-addr-auto:1
n:network-natt-port:4500
n:network-natt-rate:30
n:network-frag-size:540
n:network-dpd-enable:1
n:client-banner-enable:0
n:network-notify-enable:1
n:client-wins-used:0
n:client-wins-auto:0
n:client-dns-used:0
n:client-dns-auto:0
n:client-splitdns-used:0
n:client-splitdns-auto:0
n:phase1-dhgroup:0
n:phase1-life-secs:86400
n:phase1-life-kbytes:0
n:vendor-chkpt-enable:0
n:phase2-life-secs:3600
n:phase2-life-kbytes:0
n:policy-nailed:0
n:policy-list-auto:0
n:phase2-keylen:0
n:client-dns-suffix-auto:0
s:network-host:" . $gateway_ipaddress . "
s:client-auto-mode:disabled
s:client-iface:direct
s:network-natt-mode:enable
s:network-frag-mode:enable
s:auth-method:mutual-psk
s:ident-client-type:address
s:ident-server-type:any
b:auth-mutual-psk:c2hyb290MTI=
s:phase1-exchange:main
s:phase1-cipher:3des
s:phase1-hash:sha1
s:phase2-transform:esp-aes
s:phase2-hmac:sha1
s:ipcomp-transform:disabled
n:phase2-pfsgroup:-1
s:policy-level:auto
s:policy-list-include:192.168.0.0 / 255.255.0.0
";

        //create the vpn file
        $file1 = new File($vpn_path, true);
        $file1->write($vpnConfigurationFile);
        $file1->close();
        $file1 = new File($vpn_path, true);
        $file1->write($vpnConfigurationFile);
        $file1->close();

        return $vpn_path;
    }

    function report_booking_exception($action, $exception_message, $full_exception, $verbose_details)
    {
        $hostshortname = strtok(shell_exec('hostname -s'), "\n");
        $fullhostname = strtok(shell_exec('hostname -f'), "\n");
        Configure::load('admin_email_list');
        $mail_list = Configure::read('list');
        $email = new CakeEmail();
        $email->emailFormat('html');
        $email->from(array(
            'no_reply@ericsson.com' => 'Test Environment on Demand'
        ));
        $email->to($mail_list);
        $email->subject('Test Environment on Demand: ' . $hostshortname . ' - ' . $action . ' failed');
        $email->template('booking_exception', 'custom');
        $email->viewVars(array(
            'hostname' => $fullhostname,
            'action' => $action,
            'verbose_details' => $verbose_details,
            'exception_message' => htmlspecialchars($exception_message),
            'full_exception' => htmlspecialchars($full_exception)
        ));
        $email->send();
    }
    function get_teams_and_parent()
    {
        $key = 'team_names';
        $store = 'team_names_cache';

        if (($teams = Cache::read($key, $store)) === false) {
        }
        else {
            #foreach ($teams as $key1 => $val1){
            #    CakeLog::write('debug', $teams[$key1]['team']);
            #}
            return $teams;
        }
        $teams_url = 'https://ci-portal.seli.wh.rnd.internal.ericsson.com/api/cireports/component/ENM/Team/';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $teams_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        $result = curl_exec($ch);
        $response_details = curl_getinfo($ch);
        curl_close($ch);
        if ($response_details['http_code'] != 200)
        {
            throw new Exception("Didn't receive a 200 response code from the CI Portal when getting the list of teams. Got response code " . $response_details['http_code'] . ". Here was the output: " . $result);
        }
        $team_list = json_decode($result, true);
        $teams = array();
        foreach ($team_list as $team)
        {
            array_push($teams,array('team' =>$team['element'], 'parent' => $team['parentElement']));
        }
        array_push($teams,array('team' => 'Cheetah', 'parent' => 'SON'));
        array_push($teams,array('team' => 'BorderControl', 'parent' => 'CA'));
        Cache::write($key, $teams, $store);
        return $teams;
    }

    function is_team_valid($team)
    {
        foreach($this->get_teams_and_parent() as $key) {
           if($team == $key['team'])
           {
            return true;
           }
        }
        return false;
    }

    function get_users_team ($username)
    {
        $key = $username;
        $store = 'user_to_team_name_cache';
        if (($team = Cache::read($key, $store)) === false) {
            return null;
        }
        else {
            return $team;
        }
    }
    function set_users_team ($username, $team)
    {
        $key = $username;
        $store = 'user_to_team_name_cache';
        Cache::write($key, $team, $store);
    }

    function get_all_nonqueued_bookings()
    {
        $pooling_settings = $this->get_pooling_settings();
        $job_url = $pooling_settings['jenkins_settings']['base_url'] . '/job/' . $pooling_settings['jenkins_settings']['job_name'] . '/api/json?tree=allBuilds[building,description,number,timestamp,actions[parameters[name,value]]]';
        $extension = $pooling_settings['default_extension_time_hours'] * 3600;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        #curl_setopt($ch, CURLOPT_URL, $job_url);

        curl_setopt($ch, CURLOPT_USERPWD, $pooling_settings['jenkins_settings']['username'] . ':' . $pooling_settings['jenkins_settings']['password']);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_URL, $job_url);


        $result = curl_exec($ch);
        $response_details = curl_getinfo($ch);
        curl_close($ch);
        if ($response_details['http_code'] != 200)
        {
            throw new Exception("Didn't receive a 200 response code from jenkins when getting the list of bookings. Got response code " . $response_details['http_code'] . ". Here was the output: " . $result);
        }
        $build_history = json_decode($result, true);
        $bookings = array();

        foreach ($build_history['allBuilds'] as $build)
        {
            if ($build['building'] && strpos($build['description'],'Cancelled') === false)
            {
                $parameters = array();
                foreach ($build['actions'] as $action)
                {
                    if (isset($action['parameters']))
                    {
                        foreach ($action['parameters'] as $parameter)
                        {
                            $parameters[$parameter['name']] = $parameter['value'];
                        }
                    }
                }
                $parameters['created_timestamp'] = round($build['timestamp'] / 1000);
                $parameters['created_datetime'] = date('d/m/Y H:i:s', round($build['timestamp'] / 1000));
                $parameters['time_remaining_seconds'] = round(($build['timestamp'] / 1000) - time() + $parameters['duration_seconds']);
                $parameters['build_number'] = $build['number'];
                $parameters['status'] = 'booked';
                $parameters['duration_seconds_before_extension'] = $parameters['duration_seconds'];
                if (strpos($build['description'], 'Extended') !== false){
                    $description_exploded = explode("_", $build['description']);
                    if (sizeof($description_exploded) > 1) {
                        $extension_count = $description_exploded[1];
                    } else {
                        $extension_count = 1;
                    }
                    $parameters['extended'] = true;
                    $parameters['extension_count'] = $extension_count;
                    $parameters['time_remaining_seconds'] += $extension * $extension_count;
                    $parameters['duration_seconds'] += $extension * $extension_count;
                } else {
                    $parameters['extended'] = false;
                    $parameters['extension_count'] = 0;
                }
                array_push($bookings, $parameters);
            }
        }
        return $bookings;
    }

    function filter_bookings_by_type($bookings)
    {
        $pooling_settings = $this->get_pooling_settings();
        $vapp_types = $pooling_settings['vapp_types'];
        $filtered_bookings = array();
        foreach ($vapp_types as $vapp_type => $description) {
              $filtered_bookings[$vapp_type] = array_filter($bookings, function($booking) use ($vapp_type) {
                  return $booking['vapp_type'] == $vapp_type;
              });
        }
        return $filtered_bookings;
    }

    function sort_bookings_by_time_remaining($bookings)
    {
        foreach ($bookings as $vapp_type => $booking) {
            usort($booking, function ($first, $second) {
              return ($second['time_remaining_seconds'] > $first['time_remaining_seconds']) ? -1 : 1;
            });
            $bookings[$vapp_type] = $booking;
        }
        return $bookings;
    }

    function calculate_time_for_queue_position($bookings, $queue_position)
    {
        $total_duration = $bookings[$queue_position - 1]['duration_seconds'];
        $total_duration += $bookings[$queue_position - 1]['time_left_in_queue'];
        return $total_duration;
    }

    function is_queue_time_less_than_corresponding_vapp_time($current_queue_time, $time_to_corresponding_vapp)
    {
        return ($current_queue_time < $time_to_corresponding_vapp);
    }

    function is_queue_size_larger_than_bookings($queue_position, $bookings)
    {
        return (($queue_position + 1) > count($bookings));
    }

    function get_spin_up_time()
    {
        $pooling_settings = $this->get_pooling_settings();
        $spin_up_time = $pooling_settings['default_spin_up_hours'];
        return ($spin_up_time * 60 * 60);
    }

    function calculate_wait_time_for_queued_booking($queue_position, $queued_bookings, $non_queued_bookings, $vapp_type)
    {
        $queued_bookings = array_values($queued_bookings);
        $non_queued_bookings = array_values($non_queued_bookings);


        usort($non_queued_bookings, function ($first, $second) {
              return ($second['time_remaining_seconds'] > $first['time_remaining_seconds']) ? -1 : 1;
            });


        if ($queue_position == 0) {
            if ($this->find_next_available_slave($vapp_type)) {
                return 0;
            }
           

            $offline_slaves_list = $this->find_offline_slaves_list($vapp_type);
            if (count($offline_slaves_list) > 0) {
                $time_for_vapp_1 = 1;
            } else {
                 $time_for_vapp_1 = $non_queued_bookings[0]['time_remaining_seconds'];
            }
           
            return $time_for_vapp_1;
        } else {
            if (count($offline_slaves_list) > $queue_position){
                $time_to_corresponding_vapp = 1;
            }else{
                $time_to_corresponding_vapp = $non_queued_bookings[$queue_position]['time_remaining_seconds'];
            }
            #$time_to_corresponding_vapp = $non_queued_bookings[$queue_position]['time_remaining_seconds'];
            $current_queue_time = $this->calculate_time_for_queue_position($queued_bookings, $queue_position);
            if ($this->is_queue_size_larger_than_bookings($queue_position, $non_queued_bookings) ||
                $this->is_queue_time_less_than_corresponding_vapp_time($current_queue_time, $time_to_corresponding_vapp)) {
                return $current_queue_time;
            } else {
                return $time_to_corresponding_vapp;
            }
        }
    }

    function update_bookings_with_time_left_in_queue($queued_bookings, $non_queued_bookings, $vapp_type)
    {
        $queued_bookings = array_values($queued_bookings);
        $non_queued_bookings = array_values($non_queued_bookings);
        $spin_up_time = $this->get_spin_up_time();
        foreach ($queued_bookings as $queued_booking) {
            $index = $queued_booking['queue_position'] - 1;
            $queue_time = $this->calculate_wait_time_for_queued_booking($index, $queued_bookings, $non_queued_bookings, $vapp_type);

            $offline_slaves_list = $this->find_offline_slaves_list($vapp_type);
            if ($queue_time == 0) {
                $queued_bookings[$index]['time_left_in_queue'] = $queue_time;
            }else{ 
                #if ($queued_bookings[$index]['time_left_in_queue'] < $queue_time){
                if ($index < count($offline_slaves_list)) {
                    $queued_bookings[$index]['time_left_in_queue'] = $spin_up_time;
                }else{
                    $queued_bookings[$index]['time_left_in_queue'] = $queue_time + $spin_up_time;
                } 
            }
        }
        return $queued_bookings;
    }

    function update_queued_booking_with_queue_time($queued_bookings, $non_queued_bookings)
    {
        $queued_bookings = $this->filter_bookings_by_type($queued_bookings);
        $non_queued_bookings = $this->filter_bookings_by_type($non_queued_bookings);
        $non_queued_bookings = $this->sort_bookings_by_time_remaining($non_queued_bookings);
        $updated_bookings = array();
        foreach ($queued_bookings as $vapp_type => $bookings) {
            $updated_bookings = array_merge($updated_bookings, $this->update_bookings_with_time_left_in_queue($bookings, $non_queued_bookings[$vapp_type], $vapp_type));
        }
        return $updated_bookings;
    }

    function get_wait_time_in_queue(){
        $spin_up_time = $this->get_spin_up_time();
        $queued_bookings = $this->get_all_queued_bookings();
        $non_queued_bookings = $this->get_all_nonqueued_bookings();
        $updated_queued = $this->update_queued_booking_with_queue_time($queued_bookings, $non_queued_bookings);
        $updated_queued = $this->filter_bookings_by_type($updated_queued);
        $non_queued_bookings = $this->filter_bookings_by_type($non_queued_bookings);
        $result = array();
        foreach ($updated_queued as $vapp_type => $bookings) {
            $queue_time = $this->calculate_wait_time_for_queued_booking(count($bookings), $bookings, $non_queued_bookings[$vapp_type], $vapp_type);
            if ($queue_time == 0 || $this->find_next_available_slave($vapp_type)) {
                $result[$vapp_type] = 0;
            } else {
                $result[$vapp_type] = $queue_time + $spin_up_time;
            }
        }
        return $result;
    }

    function get_all_bookings()
    {
        $queued_bookings = $this->get_all_queued_bookings();
        $non_queued_bookings = $this->get_all_nonqueued_bookings();
        $queued_bookings = $this->update_queued_booking_with_queue_time($queued_bookings, $non_queued_bookings);
        $all_bookings = array_merge($non_queued_bookings,$queued_bookings);
        return $all_bookings;
    }

    function get_team_bookings_of_type_from_existing_list($team,$vapp_type,$bookings)
    {
        foreach ($bookings as $key => $booking)
        {
            if ($booking['team'] != $team || $booking['vapp_type'] != $vapp_type)
            {
                unset ($bookings[$key]);
            }
        }
        return $bookings;
    }

    function get_team_bookings_from_existing_list($team,$bookings)
    {
        foreach ($bookings as $key => $booking)
        {
            if ($booking['team'] != $team)
            {
                unset ($bookings[$key]);
            }
        }
        return $bookings;
    }

    function get_team_bookings($team)
    {
        $bookings = $this->get_all_bookings();
        return $this->get_team_bookings_from_existing_list($team,$bookings);
    }

    function get_booking_counts_and_limits()
    {
        $pooling_settings = $this->get_pooling_settings();
        $vapp_types = $pooling_settings['vapp_types'];
        $teams = $this->get_teams_and_parent();
        $bookings = $this->get_all_bookings();
        $booking_counts_and_limits= array();
        foreach ($teams as $team)
        {
            $booking_counts_and_limits[$team['team']] = array();
            foreach ($vapp_types as $vapp_type => $description)
            {
                $booking_counts_and_limits[$team['team']][$vapp_type]['booking_count'] = count($this->get_team_bookings_of_type_from_existing_list($team['team'],$vapp_type,$bookings));
                $booking_counts_and_limits[$team['team']][$vapp_type]['booking_limit'] = $this->get_team_booking_limit_for_type($team['team'],$vapp_type);
                $booking_counts_and_limits[$team['team']]['limit_type'] = $this->get_team_limit_type($team['team']);
            }
        }
        return $booking_counts_and_limits;
    }

    function get_bookings_report($startDate, $endDate)
    {
        $BookingLog = ClassRegistry::init('BookingLog');
        $pooling_settings = $this->get_pooling_settings();
        $vapp_types = $pooling_settings['vapp_types'];
        $teams = $this->get_teams_and_parent();
        $startDate  = date('Y-m-d',strtotime(str_replace("/", "-",$startDate)));
        $endDate = date('Y-m-d', strtotime(str_replace("/", "-",$endDate)));
        $team_reports = array();
        $overall_report = array();
        $bookings_filtered = $this->query('SELECT team, vapp_type, COUNT(*) as total_count, SUM(duration_seconds) as total_duration_seconds, AVG(duration_seconds) as avg_duration_seconds, SUM(canceled = 1) as total_canceled, SUM(extended = 1) as total_extended FROM booking_logs WHERE DATE(created) BETWEEN "'. $startDate . '" AND "'. $endDate .'" GROUP BY team, vapp_type');
        foreach ($teams as $team)
        {
            $team_reports[$team['team']] = array();
            $team_reports[$team['team']]['parent'] = $team['parent'];
            foreach ($vapp_types as $vapp_type => $description)
            {
                $found = false;
                foreach ($bookings_filtered as $key => $bookings)
                {
                   if($bookings['booking_logs']['team'] === $team['team'] && $bookings['booking_logs']['vapp_type'] === $vapp_type)
                   {
                      $found = true;
                      $team_reports[$team['team']]['vapp_types'][] = array('type' => $vapp_type,
                                                                        'booking_count' => $bookings[0]['total_count'],
                                                                        'booking_hours' => round($bookings[0]['total_duration_seconds'] / 3600, 1),
                                                                        'booking_hours_average' => round($bookings[0]['avg_duration_seconds'] / 3600, 1),
                                                                        'booking_canceled_count' => $bookings[0]['total_canceled'],
                                                                        'booking_extended_count' => $bookings[0]['total_extended']);
                      break;
                   }
                }
                if (!$found)
                {
                    $team_reports[$team['team']]['vapp_types'][] = array('type' => $vapp_type,
                                                                         'booking_count' => 0,
                                                                         'booking_hours' => 0,
                                                                         'booking_hours_average' => 0,
                                                                         'booking_canceled_count' => 0,
                                                                         'booking_extended_count' => 0);
                }
            }
        }
        foreach ($vapp_types as $vapp_type => $description)
        {
            $count = 0;
            $hours = 0;
            $canceled_count = 0;
            $extended_count = 0;
            foreach ($team_reports as $report => $vapps)
            {
                foreach ($vapps['vapp_types'] as $values)
                {
                   if ($values['type'] === $vapp_type)
                   {
                       $count = $count + $values['booking_count'];
                       $hours = $hours + $values['booking_hours'];
                       $canceled_count = $canceled_count + $values['booking_canceled_count'];
                       $extended_count = $extended_count + $values['booking_extended_count'];
                   }
                }
           }
           $overall_report[$vapp_type] = array('booking_count' => $count, 'booking_hours' => $hours, 'booking_hours_average' => ($hours != 0 ? round($hours / $count, 1) : 0 ), 'booking_canceled_count' => $canceled_count, 'booking_extended_count' => $extended_count);
        }
        return array('team_reports' => $team_reports, 'overall_report' => $overall_report);
    }

    function get_team_booking_counts_and_limits($team)
    {
        return $this->get_booking_counts_and_limits()[$team];
    }

    function seconds_to_hours_minutes_and_seconds($input_seconds)
    {
        $hours = floor($input_seconds / 3600);
        $minutes = floor(($input_seconds - ($hours*3600)) / 60);
        $seconds = floor($input_seconds % 60);
        $final_string = '';
        if ($hours > 0)
        {
            $hours_string = $hours . ' ' . ($hours == 1 ? 'hour' : 'hours');
            $final_string = $hours_string;
        }
        if ($minutes > 0)
        {
            $minutes_string = $minutes . ' ' . ($minutes == 1 ? 'minute' : 'minutes');
            $final_string = $final_string . ' ' . $minutes_string;
        }
        if ($seconds > 0)
        {
            $seconds_string = $seconds . ' ' . ($seconds == 1 ? 'second' : 'seconds');
            $final_string = $final_string . ' ' . $seconds_string;
        }
        return trim($final_string);
    }

    function get_script_output($server,$command)
    {
        $connection = ssh2_connect($server, 22);
        if($connection === false) {
            throw new Exception('Cant connect to server ' . $server);
        }
        $result = ssh2_auth_password($connection, "root", "shroot");
        if($result === false) {
            throw new Exception('Authentication failed connecting to server ' . $server);
        }
        $stream = ssh2_exec($connection, $command);
        stream_set_blocking($stream, true);
        $returned_output = '';
        while($buffer = fread($stream, 4096)) {
            $returned_output .= $buffer;
        }
        fclose($stream);
        return $returned_output;
    }

    function get_team_booking_limit_type_flag($team, $vapp_type)
    {
        $flag = false;
        $all_team_booking_counts = $this->get_team_booking_counts_and_limits($team);
        $team_limit_type = $this->get_team_booking_counts_and_limits($team)['limit_type'];
        $pooling_settings = $this->get_pooling_settings();
        $vapp_types = $pooling_settings['vapp_types'];
        if ($team_limit_type == "OR")
        {
            foreach ($vapp_types as $vapp_type_settings => $description)
            {
                $booking_count = $all_team_booking_counts[$vapp_type_settings]['booking_count'];
                if ($booking_count != 0)
                {
                    if($vapp_type_settings != $vapp_type)
                    {
                       $flag = true;
                       $vapp_type = $vapp_type_settings;
                       break;
                    }
                }
            }
        }
        return array('flag' => $flag, 'vapp_type' => $vapp_type);
    }

    function get_team_booking_limit_type_message($team)
    {
        $all_team_booking_counts = $this->get_team_booking_counts_and_limits($team);
        $team_limit_type = $this->get_team_booking_counts_and_limits($team)['limit_type'];
        $pooling_settings = $this->get_pooling_settings();
        $vapp_types = $pooling_settings['vapp_types'];
        $vapp_type = "";
        $message = "";
        if ($team_limit_type == "OR")
        {
            $message = "Please note you are only allowed to have one type of vApp booked at any given time";
            foreach ($vapp_types as $vapp_type_settings => $description)
            {
                $booking_count = $all_team_booking_counts[$vapp_type_settings]['booking_count'];
                if ($booking_count != 0)
                {
                    $message = "You have a " . $vapp_type_settings . " vApp type booked, so currently you are unable to book a different vApp type";
                    $vapp_type = $vapp_type_settings;
                }
            }
        }
        return array('team_limit_type' => $team_limit_type, 'vapp_type' => $vapp_type, 'message' => $message);
    }
}
?>
