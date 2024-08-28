<?php

App::uses('AppModel', 'Model');
App::uses('CakeEmail', 'Network/Email');

/**
 * TaskType Model
 *
 */
class Throttler extends AppModel {

    public $useTable = false;
    public $transitional_task_points_cache_lock = "files/locks/throttler_locks/transitional_task_points_cache.lock";
    public $running_task_points_cache_lock = "files/locks/throttler_locks/running_task_points_cache.lock";

    public function log($message = null, $email_administrators = false) {
        CakeLog::write('throttler', $message);
        if ($email_administrators) {
            $this->email_administrators($message);
        }
    }

    public function email_administrators($message) {
        CakeLog::write('throttler', 'Sending email to administrators');
        $mail_list = array("mark.fahy@ericsson.com", "mark.a.kennedy@ericsson.com", "shane.kelly@ericsson.com", "denis.parker@ericsson.com", "ben.van.der.puil@ericsson.com");
        $email = new CakeEmail();
        $email->from(array('no_reply@ericsson.com' => 'Cloud Control Engine'));
        $email->to($mail_list);
        $email->subject('Cloud Control Engine - Something went wrong');
        $spp_hostname = strtok(shell_exec('hostname -f'), "\n");
        $email->send("The following occured on the cloud control engine of ". $spp_hostname . ". Please check it out and fix it as soon as possible\r\r" . $message);
    }

    public function propose_new_task($task_name = "not set") {
        try {
            $this->log('A new task was proposed of type ' . $task_name, false);

            if (!$this->is_throttler_enabled()) {
                $this->log('The throttler isnt enabled so allowing proposal', false);
                return true;
            }

            // Load the TaskType and ThrottlerSetting models as we need them
            $TaskType = ClassRegistry::init('TaskType');
            $ThrottlerSetting = ClassRegistry::init('ThrottlerSetting');

            /////////////////////////////////////////////////////////////////////////
            // Read the max running task points setting from the database
            $max_running_task_points_object = $ThrottlerSetting->find('first', array('conditions' => array('ThrottlerSetting.name' => "max_running_task_points")));

            // Make sure that setting exists, if not write an error to the log
            if (isset($max_running_task_points_object['ThrottlerSetting'])) {
                $max_running_task_points = $max_running_task_points_object['ThrottlerSetting']['value'];
            } else {
                $this->log('ERROR: The max_running_task_points setting isnt in the ThrottlerSetting table, check why not', true);
                $this->log('Letting a task through because of missing ThrottlerSetting', false);
                return true;
            }
            // Make sure its not set to 0 as we will get divide by zero errors later
            if ($max_running_task_points == 0) {
                $this->log('ERROR: The max_running_task_points ThrottlerSetting was set to 0, please set to something higher', true);
                $this->log('Letting a task through because of invalid ThrottlerSeting', false);
                return true;
            }
            /////////////////////////////////////////////////////////////////////////
            // Sleep to let any ongoing increases to the caches to complete
            //sleep (1);
            /////////////////////////////////////////////////////////////////////////
            // Now read the temporary local cache value used to track tasks that havn't reached vcloud yet
            /////////////////////////////////////////////////////////////////////////
            $transitional_task_points_cache = $this->read_transitional_task_points_cache();
            /////////////////////////////////////////////////////////////////////////
            /////////////////////////////////////////////////////////////////////////
            // Now read the temporary local cache value used to track tasks that have reached vcloud
            /////////////////////////////////////////////////////////////////////////
            $cached_running_task_points = $this->read_running_task_points_cache();
            /////////////////////////////////////////////////////////////////////////
            /////////////////////////////////////////////////////////////////////////
            // Lets decide whether to renew our local cache of the running task points
            /////////////////////////////////////////////////////////////////////////
            // Initially set to false
            $need_to_renew = false;

            /////////////////////////////////////////////////////////////////////////
            // Read the last cloud running task points setting from the database
            $cached_running_task_points_renewal_threshold_percent_object = $ThrottlerSetting->find('first', array('conditions' => array('ThrottlerSetting.name' => "cached_running_task_points_renewal_threshold_percent")));

            // Make sure that setting exists, if not write an error to the log
            if (isset($cached_running_task_points_renewal_threshold_percent_object['ThrottlerSetting'])) {
                $cached_running_task_points_renewal_threshold_percent = $cached_running_task_points_renewal_threshold_percent_object['ThrottlerSetting']['value'];
            } else {
                $this->log('ERROR: The cached_running_task_points_renewal_threshold_percent setting isnt in the ThrottlerSetting table, check why not', true);
                $this->log('Letting a task through because of missing ThrottlerSetting', false);
                return true;
            }

            // If the cached running task points is within our defined percentage of the max, we also should renew
            if ((($cached_running_task_points + $transitional_task_points_cache) * 100) / $max_running_task_points >= $cached_running_task_points_renewal_threshold_percent) {
                $this->log('We need to renew the cache as it has reached ' . $cached_running_task_points_renewal_threshold_percent . '% of the limit', false);
                $need_to_renew = true;
            }


            // If we have to renew it
            if ($need_to_renew) {
                $cached_running_task_points = $this->renew_cached_running_task_points();
            } else {
                $this->log('No need to renew our cache of the running task points', false);
            }
            /////////////////////////////////////////////////////////////////////////
            /////////////////////////////////////////////////////////////////////////
            // Now lets see is there room in the cloud to run the proposed task
            /////////////////////////////////////////////////////////////////////////
            // Get the task points for the proposed task
            $new_task_points = $TaskType->get_task_points($task_name);
            $this->log('The proposed task has a running task points value of ' . $new_task_points, false);

            // Add it to the cached running task points
            $proposed_running_task_points = $cached_running_task_points + $transitional_task_points_cache + $new_task_points;
            $this->log('The proposed running task points would be the currently running value of ' . $cached_running_task_points . ' + the transitional task points value of ' . $transitional_task_points_cache . ' + the proposed new task points of ' . $new_task_points . ' which comes to ' . $proposed_running_task_points, false);

            // Compare the proposed points to the max points
            $this->log('Comparing the proposed running task points ' . $proposed_running_task_points . ' to the max ' . $max_running_task_points, false);
            if ($proposed_running_task_points > $max_running_task_points) {
                // Dont let the task through
                $this->log('Not letting a task through of type ' . $task_name . '. The max running points is ' . $max_running_task_points . ' and the proposed running points would be more, ie ' . $proposed_running_task_points, false);
                return false;
            } else {
                // We are going to allow it through
                $this->log('Letting a task through of type ' . $task_name, false);

                // Let the task through
                return true;
            }

            // We should never reach here
            $this->log('ERROR: Reached the bottom of the propose_new_task function, should never get here', true);
            $this->log('Letting a task through because not sure why we got this far', false);
            return true;
        } catch (Exception $e) {
            $this->log('ERROR: An exception was thrown in the propose_new_task function. Fix the message below', true);
            $this->log($e, true);
            $this->log('Letting a task through because an exception got thrown in the propose_new_task function', false);
            return true;
        }
    }

    function get_running_task_points() {
        $TaskType = ClassRegistry::init('TaskType');
        $currently_running_task_points = $TaskType->count_running_task_points();
        return $currently_running_task_points;
    }

    function renew_cached_running_task_points() {
        try {
            $this->get_running_task_points_cache_lock();

            // Get the currently running task points from the cloud
            $this->log('Renewing our cache of the running task points', false);
            $currently_running_task_points = $this->get_running_task_points();

            $this->log('Saving the currently running task points in the cloud value of ' . $currently_running_task_points, false);

            $this->write_running_task_points_cache($currently_running_task_points);
            $this->clear_running_task_points_cache_lock();
        } catch (Exception $e) {
            // Always clear the lock
            $this->clear_running_task_points_cache_lock();
            $this->log('ERROR: An exception was thrown in the renew_cached_running_task_points function. Fix the message below', true);
            $this->log($e, true);
        }
        return $currently_running_task_points;
    }

    public function is_throttler_enabled() {
        $ThrottlerSetting = ClassRegistry::init('ThrottlerSetting');
        $throttler_enabled_object = $ThrottlerSetting->find('first', array('conditions' => array('ThrottlerSetting.name' => "throttler_enabled")));

        // Make sure that setting exists, if not write an error to the log
        if (isset($throttler_enabled_object['ThrottlerSetting'])) {
            $throttler_enabled = $throttler_enabled_object['ThrottlerSetting']['value'];
        } else {
            $this->log('ERROR: The throttler_enabled setting isnt in the ThrottlerSetting table, check why not', true);
            $this->log('Defaulting to throttler off', false);
            return false;
        }
        if ($throttler_enabled == 1) {
            return true;
        } else {
            return false;
        }
    }

    public function wait_in_queue($task_name = null) {

        $SPP_URL = "";

        if (!isset($_SERVER['HTTPS'])) {
            $_SERVER['HTTPS'] = "";
            if (!isset($_SERVER['SERVER_NAME'])) {
                $_SERVER['SERVER_NAME'] = "";
            }
            $SPP_URL = 'http://' . $_SERVER['SERVER_NAME'];
        } else {
            $_SERVER['HTTP'] = "";
            if (!isset($_SERVER['SERVER_NAME'])) {
                $_SERVER['SERVER_NAME'] = "";
            }
            $SPP_URL = 'https://' . $_SERVER['SERVER_NAME'];
        }

        // If the throttler isn't enabled, dont do anything
        if (!$this->is_throttler_enabled()) {
            return;
        }
        // Define the path to the app directory
        $app_path = dirname(APP) . "/" . basename(APP);

        // Define the path to the wait in queue shell script
        $cmd = $app_path . "/webroot/Throttler/wait_in_queue.sh -t" . $task_name . " -u " . $SPP_URL . " 2>&1";

        // Run the script, which blocks until its completed
        $this->log('Adding an item into the queue of type ' . $task_name, false);
        $output = shell_exec($cmd);
        $this->log($output, false);
        $this->log('Item removed from the queue of type ' . $task_name . ', its output is above', false);
    }

    public function get_transitional_task_points_cache_lock() {
        $this->get_lock($this->transitional_task_points_cache_lock);
    }

    public function clear_transitional_task_points_cache_lock() {
        $this->clear_lock($this->transitional_task_points_cache_lock);
    }

    public function get_running_task_points_cache_lock() {
        $this->get_lock($this->running_task_points_cache_lock);
    }

    public function clear_running_task_points_cache_lock() {
        $this->clear_lock($this->running_task_points_cache_lock);
    }

    function get_lock ($LOCKFILE=null)
    {
	$this->log('Waiting to get the lock ' . $LOCKFILE, false);
        $app_path = dirname(APP) . "/" . basename(APP);
        $cmd = $app_path . "/webroot/Locker/get_lock.sh -f " . $LOCKFILE . " -p 1234 -t 600 -r yes 2>&1";
        $output = shell_exec($cmd);
	$this->log('Got the lock ' . $LOCKFILE, false);
    }

    function clear_lock ($LOCKFILE=null)
    {
	$this->log('Clearing the lock ' . $LOCKFILE, false);
        $app_path = dirname(APP) . "/" . basename(APP);
        $cmd = $app_path . "/webroot/Locker/clear_lock.sh -f " . $LOCKFILE . " -p 1234";
        $output = shell_exec($cmd);
	$this->log('Lock cleared ' . $LOCKFILE, false);
    }

    public function task_in_vcloud($task_name = null) {
        // If the throttler isn't enabled, dont do anything
        if (!$this->is_throttler_enabled()) {
            return;
        }
        $this->increment_running_task_points_cache($task_name);
        $this->decrement_transitional_task_points_cache($task_name);
    }

    public function read_transitional_task_points_cache_raw() {
        $this->log("Reading transitional task points cache value", false);
        if (($value = Cache::read('transitional_task_points_cache', 'memcache_transitional_task_points_cache')) === false) {
            $this->log("transitional_task_points_cache is not set, returning 0", false);
            $value = 0;
        } else {
            if ($value < 0) {
                $this->log('WARNING: The transitional task points cache had a negative value of ' . $value . ', please check how this can be happening', true);
                $value = 0;
            }
            $this->log("transitional_task_points_cache is set to " . $value, false);
        }
        return $value;
    }

    public function read_transitional_task_points_cache() {
        try {
            $this->get_transitional_task_points_cache_lock();
            $this->log("Reading transitional task points cache value", false);
            if (($value = Cache::read('transitional_task_points_cache', 'memcache_transitional_task_points_cache')) === false) {
                $this->log("transitional_task_points_cache is not set, setting it to 0", false);
                $value = 0;
                Cache::write('transitional_task_points_cache', 0, 'memcache_transitional_task_points_cache');
            } else {
                if ($value < 0) {
                    $this->log('WARNING: The transitional task points cache had a negative value of ' . $value . ', please check how this can be happening', true);
                    $value = 0;
                }
                $this->log("transitional_task_points_cache is set to " . $value, false);
            }
            $this->clear_transitional_task_points_cache_lock();
        } catch (Exception $e) {
            $this->clear_transitional_task_points_cache_lock();
            $this->log('ERROR: An exception was thrown in the read_transitional_task_points_cache function. Fix the message below', true);
            $this->log($e, true);
        }
        return $value;
    }

    public function read_running_task_points_cache_raw() {
        $this->log("Reading running task points cache value", false);
        if (($value = Cache::read('running_task_points_cache', 'memcache_running_task_points_cache')) === false) {
            $this->log("running_task_points_cache is not set, returning the current running task points", false);
            $value = $this->get_running_task_points();
        } else {
            $this->log("running_task_points_cache is set to " . $value, false);
        }
        return $value;
    }

    public function read_running_task_points_cache() {
        try {
            $this->get_running_task_points_cache_lock();

            $this->log("Reading running task points cache value", false);
            if (($value = Cache::read('running_task_points_cache', 'memcache_running_task_points_cache')) === false) {
                $this->log("running_task_points_cache is not set, setting as the current running task points", false);
                $value = $this->get_running_task_points();
                Cache::write('running_task_points_cache', intval($value), 'memcache_running_task_points_cache');
            } else {
                $this->log("running_task_points_cache set to " . $value, false);
            }
            $this->clear_running_task_points_cache_lock();
        } catch (Exception $e) {
            // Always clear the lock
            $this->clear_running_task_points_cache_lock();
            $this->log('ERROR: An exception was thrown in the read_running_task_points_cache function. Fix the message below', true);
            $this->log($e, true);
        }
        return $value;
    }

    public function write_transitional_task_points_cache($new_value) {
        if ($new_value < 0) {
            $this->log('WARNING: Was about to set the transitional_task_points_cache value to a negative one of ' . $new_value . '. If this keeps happening please check why. Setting it back to 0.', true);
            Cache::write('transitional_task_points_cache', 0, 'memcache_transitional_task_points_cache');
        } else {
            Cache::write('transitional_task_points_cache', intval($new_value), 'memcache_transitional_task_points_cache');
        }
    }

    public function write_running_task_points_cache($new_value) {
        if ($new_value < 0) {
            $this->log('WARNING: Was about to set the running_task_points_cache value to a negative one of ' . $new_value . '. If this keeps happening please check why. Setting it back to 0.', true);
            Cache::write('running_task_points_cache', 0, 'memcache_running_task_points_cache');
        } else {
            Cache::write('running_task_points_cache', intval($new_value), 'memcache_running_task_points_cache');
        }
    }

    public function increment_transitional_task_points_cache($task_name = null) {
        try {
            $this->get_transitional_task_points_cache_lock();

            $TaskType = ClassRegistry::init('TaskType');
            $task_points = $TaskType->get_task_points($task_name);
            $this->log('Adding ' . $task_name . ' to the transitional task points cache with value of ' . $task_points, false);

            $prev_value = $this->read_transitional_task_points_cache_raw();
            $new_value = $prev_value + $task_points;
            $this->write_transitional_task_points_cache($new_value);

            $this->log('transitional_task_points_cache was ' . $prev_value);
            $this->log('transitional_task_points_cache is now ' . $new_value);

            $this->clear_transitional_task_points_cache_lock();
        } catch (Exception $e) {
            $this->clear_transitional_task_points_cache_lock();
            $this->log('ERROR: An exception was thrown in the increment_transitional_task_points_cache function. Fix the message below', true);
            $this->log($e, true);
        }
    }

    public function decrement_transitional_task_points_cache($task_name = null) {
        try {
            $this->get_transitional_task_points_cache_lock();

            $TaskType = ClassRegistry::init('TaskType');
            $task_points = $TaskType->get_task_points($task_name);
            $this->log('Removing ' . $task_name . ' from the transitional task points cache with value of ' . $task_points . ' as its in vcloud now', false);

            $prev_value = $this->read_transitional_task_points_cache_raw();
            $new_value = $prev_value - $task_points;
            $this->write_transitional_task_points_cache($new_value);

            $this->log('transitional_task_points_cache was ' . $prev_value);
            $this->log('transitional_task_points_cache is now ' . $new_value);

            $this->clear_transitional_task_points_cache_lock();
        } catch (Exception $e) {
            $this->clear_transitional_task_points_cache_lock();
            $this->log('ERROR: An exception was thrown in the decrement_transitional_task_points_cache function. Fix the message below', true);
            $this->log($e, true);
        }
    }

    public function increment_running_task_points_cache($task_name = null) {
        try{
            $this->get_running_task_points_cache_lock();

            $TaskType = ClassRegistry::init('TaskType');
            $task_points = $TaskType->get_task_points($task_name);
            $this->log('Adding ' . $task_name . ' to the running task points cache with value of ' . $task_points, false);

            $prev_value = $this->read_running_task_points_cache_raw();
            $new_value = $prev_value + $task_points;
            $this->write_running_task_points_cache($new_value);

            $this->log('running_task_points_cache was ' . $prev_value);
            $this->log('running_task_points_cache is now ' . $new_value);
            $this->clear_running_task_points_cache_lock();
        } catch (Exception $e) {
            // Always clear the lock
            $this->clear_running_task_points_cache_lock();
            $this->log('ERROR: An exception was thrown in the increment_running_task_points_cache function. Fix the message below', true);
            $this->log($e, true);
        }
    }

}
