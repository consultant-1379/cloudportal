<?php
App::uses('PhpReader', 'Configure');
App::import('Vendor', 'vcloud');
App::uses('CakeEmail', 'Network/Email');
include_once 'Net/SSH2.php';
class Vcloud extends AppModel

{
    var $name = 'Vcloud';
    var $useTable = false;
    public function __construct()
    {
        parent::__construct();
        $this->login();
    }

    //      public function __destruct()
    //        {
    //      global $service;
    //              $this->logout();
    //       }

    function vsphere_name_to_id($input = null)
    {
        if (strstr($input, "(")) {
            $split_bracket = explode("(", $input);
            $second_part = $split_bracket[1];
            $split_bracket2 = explode(")", $second_part);
            return "urn:vcloud:vm:" . $split_bracket2[0];
        }
        else {
            return $input;
        }
    }

    function cleanup_vapp_networks($vapp_id = null)
    {
        $retry = 2;
        for ($x = 1; $x <= $retry; $x++) {
            try {
                global $service;
                $vms = $this->list_vms_id($vapp_id);
                $used_networks = array();
                $network_count = sizeof($this->get_vapp_networks_internal($vapp_id)) + sizeof($this->get_vapp_networks_external($vapp_id));

                // For each vm

                foreach($vms as $vm) {
                    $vm_nics = $this->get_network_details_vm($vm['vm_id'], false);

                    // For each vm on the nic

                    foreach($vm_nics as $vm_nic) {

                        // Only compare them if this network isn't already in the used_network array, and if the name of it isn't none

                        if (!in_array($vm_nic['network_name'], $used_networks) && $vm_nic['network_name'] !== "none") {

                            // Add it to the used_networks array

                            array_push($used_networks, $vm_nic['network_name']);

                            // Break out of the loops if all networks are already needed

                            if ($network_count === sizeof($used_networks)) {
                                break 2;
                            }
                        }
                    }
                }

                // Only make a change if we don't have the same number of vapps before compared to after

                if ($network_count !== sizeof($used_networks)) {
                    $new_config = array();
                    $sdkVApp = $service->createSDKObj($this->get_href_from_id($vapp_id));
                    $section = $sdkVApp->getNetworkConfigSettings();
                    $configs = $section->getNetworkConfig();

                    // Now go through the network configs again and push the ones we marked to keep, into a temporary array

                    foreach($configs as $config) {
                        if (in_array($config->get_networkName() , $used_networks)) {
                            array_push($new_config, $config);
                        }
                    }

                    $section->setNetworkConfig($new_config);
                    try {

                        // Put this in the queue

                        $this->wait_in_queue("vdcUpdateVappNetworkSection");
                        $task = $sdkVApp->modifyNetworkConfigSettings($section);

                        // Inform the queue manager that the task should be in vcloud now

                        $this->task_in_vcloud("vdcUpdateVappNetworkSection");
                    }

                    catch(Exception $e) {

                        // Inform the queue manager that the task should be in vcloud now

                        $this->task_in_vcloud("vdcUpdateVappNetworkSection");
                        throw $e;

                    }

                    $this->wait_for_task($task);
                }

                break;
            }

            catch(Exception $e) {
                $this->report_exception('cleanup_vapp_network', $e, $x, $retry, "");
            }
        }
    }

    function get_running_tasks($time_ago)
    {

        // Figure out the date time object

        $objDateTime = new DateTime('NOW');
        $objDateTime = $objDateTime->sub(date_interval_create_from_date_string($time_ago));
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setPageSize(128);
        $params->setFilter("(status==running,status==queued,status==preRunning);startDate=ge=" . urlencode($objDateTime->format('Y-m-d\TH:i:s.uO')));
        $params->setFields('name');
        $type = "adminTask";
        $recs = $sdkQuery->$qm($type, $params);
        $i = 1; //
        $pages = 1;
        $params->setPage($i);
        while ($pages) {
            $taskRecords = $recs->getRecord();
            foreach($taskRecords as $taskRecord) {
                array_push($the_array, $taskRecord->get_name());
            }

            $i++;
            $params->setPage($i);
            try {
                $recs = $sdkQuery->$qm($type, $params);
            }

            catch(Exception $e) {
                $pages = null;
            }
        }

        return $the_array;
    }

    function print_tasks()
    {
        global $service;
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setPageSize(128);

        // $params->setFilter("status==running,status==queued,status==preRunning");

        $todays_date = date("Y-m-d");
        $params->setFilter("startDate=ge=" . urlencode($todays_date . 'T00:00:00.000+00:00'));
        $params->setFields('name,status,startDate,endDate,object,objectType');
        $params->setSortDesc('startDate');
        $type = "adminTask";
        $recs = $sdkQuery->$qm($type, $params);
        $i = 1; //
        $pages = 1;
        $params->setPage($i);
        while ($pages) {
            $taskRecords = $recs->getRecord();
            foreach($taskRecords as $taskRecord) {
                echo "-------------------------</br>";
                echo $taskRecord->get_name() . " " . $taskRecord->get_status() . "</br> --> " . $taskRecord->get_startDate() . " ==> " . $taskRecord->get_endDate() . "</br>";
                echo "ID: " . $this->get_id_from_href($taskRecord->get_object() , $taskRecord->get_objectType()) . "</br>";
            }

            $i++;
            $params->setPage($i);
            try {
                $recs = $sdkQuery->$qm($type, $params);
            }

            catch(Exception $e) {
                $pages = null;
            }
        }
    }

    function get_latest_task($object_id)
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setPageSize(1);
        $params->setFilter("object==" . $this->get_href_from_id($object_id));
        $params->setFields('status');
        $params->setSortDesc('startDate');
        $type = "adminTask";
        $recs = $sdkQuery->$qm($type, $params);
        $taskRecords = $recs->getRecord();
        foreach($taskRecords as $taskRecord) {
            $the_array['status'] = $taskRecord->get_status();
            $the_array['href'] = $taskRecord->get_href();
            return $the_array;
        }

        return null;
    }

    function get_vapp_task($vapp_id = null)
    {
        global $service;
        $the_array = array();
        $the_array['vapp_id'] = $vapp_id;
        $latest_task = $this->get_latest_task($vapp_id);
        if (in_array($latest_task['status'], array(
            'error',
            'running'
        ))) {
            try {
                $running = $service->createSDKObj($latest_task['href']);
                if ($running) {
                    $task = $running->getTask();
                    $the_array['task'] = $task;
                }

                // Temporarily ignore this particular error

                if (in_array($latest_task['status'], array(
                    'error'
                )) && $task->getError()->get_message() == 'Unable to update network "null".') {
                    return null;
                }
            }

            catch(Exception $e) {
                $the_array['status'] = "";
            }
        }

        return $the_array;
    }

    function login()
    {
        global $service;

        // Load the vcd config file

        Configure::load('vcd');
        global $vcd_config;
        $vcd_config = Configure::read('VCD');
        $httpConfig = array(
            'ssl_verify_peer' => false,
            'ssl_verify_host' => false
        );
        $service = VMware_VCloud_SDK_Service::getService();

        // Check if we already have a login token

        $token = Cache::read('vcloud_login_token', 'memcache_default');
        $need_to_relogin = false;
        if ($token !== false) {
            $service->SetVcloudToken($vcd_config['hostname'], $token, $httpConfig, "30.0");
            $httpClient = $service->getHttpClient();
            $service->setHttpClient($httpClient);
            try {

                // Do a very lightweight test to see if we are actually logged in with this token

                $service->get("https://" . $vcd_config['hostname'] . "/api/session/", false);
            }

            catch(Exception $e) {
                $need_to_relogin = true;
            }
        }
        else {
            $need_to_relogin = true;
        }

        // If we need to relogin, unset the token if its set and login normally

        if ($need_to_relogin == true) {
            $service->setToken(null);
            $auth = array(
                'username' => $vcd_config['username'],
                'password' => $vcd_config['password']
            );
            $service->login($vcd_config['hostname'], $auth, $httpConfig, "30.0");
            $http = $service->getHttpClient();

            // Save the token to the cache

            Cache::write('vcloud_login_token', $http->getVcloudToken() , 'memcache_default');
        }
    }

    function logout()
    {
        global $service;
        $service->logout();
    }

    function update_org_network_gateway($vappid = null, $username = null)
    {
        $Events = ClassRegistry::init('Events');
        $event_params = array(
            "function_name" => 'update_org_network_gateway',
            "function_parameters" => $vappid,
            "object_vcd_id" => $vappid,
            "user_id" => $username
        );
        $Events->create();
        $Events->save($event_params);
        $update_org_id = $Events->id;
        global $service;
        $vms = $this->list_vms_id($vappid);
        try {
            foreach($vms as $vm) {
                if (strstr($vm['name'], "gateway")) {

                    // First find the org network

                    $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
                    $qm = "queryRecords";
                    $params = new VMware_VCloud_SDK_Query_Params();
                    $params->setFields("name");
                    $params->setFilter("vdc==" . $vm['vdc']);
                    $params->setSortDesc('name');
                    $params->setPageSize(1);
                    $type = "orgVdcNetwork";
                    $recs = $sdkQuery->$qm($type, $params);
                    $orgVdcNetworkRecords = $recs->getRecord();
                    foreach($orgVdcNetworkRecords as $orgVdcNetwork) {
                        $orgVdcNetwork_name = $orgVdcNetwork->get_name();
                    }
                    // Mostly vmware sample code below, restructured to use less sdk objects. This creates the network
                    $sdkVm = $service->createSDKObj($vm['vm_href']);
                    $sdkVApp = $service->createSDKObj($this->get_href_from_id($vappid));
                    $sdkVdc = $service->createSDKObj($vm['vdc']);
                    $sdkNet = $sdkVdc->getAvailableNetworks($orgVdcNetwork_name);
                    $sdkNet = $sdkNet[0];
                    $netRef = VMware_VCloud_SDK_Helper::createReferenceTypeObj($sdkNet->get_href(),'reference',$sdkNet->get_type(), $sdkNet->get_name());
                    $conf = $sdkNet->getConfiguration();
                    $fencemode = $conf->getFenceMode();
                    $info = new VMware_VCloud_API_OVF_Msg_Type();
                    $info->set_valueOf("Configuration parameters for logical networks");
                    $vappNetwork = new VMware_VCloud_API_VAppNetworkConfigurationType();
                    $vappNetwork->set_networkName($netRef->get_name()); //$orgVdcNetName);
                    $vappNetworkConfig = new VMware_VCloud_API_NetworkConfigurationType();
                    $vappNetworkConfig->setParentNetwork($netRef);
                    $vappNetworkConfig->setFenceMode($fencemode);
                    $vappNetwork->setConfiguration($vappNetworkConfig);
                    $section = $sdkVApp->getNetworkConfigSettings();
                    $section->setInfo($info);
                    $section->addNetworkConfig($vappNetwork);
                    $task = $sdkVApp->modifyNetworkConfigSettings($section);

                    // This throws an exception but does work, so we ignore it. SR open with vmware 13344375807

                    try {
                        $this->wait_for_task($task);
                    }

                    catch(Exception $e) {
                    }

                    $vmNetConSec = new VMware_VCloud_API_NetworkConnectionSectionType();
                    $vmNetConSec = $sdkVm->getNetworkConnectionSettings();
                    $vmNetCon = new VMware_VCloud_API_NetworkConnectionType();
                    $vmNetCon = $vmNetConSec->getNetworkConnection();
                    foreach($vmNetCon as $key => $nc) {
                        if (!strstr($vmNetCon[$key]->getMACAddress() , "00:50:56:00")) {
                            $vmNetCon[$key]->set_Network($netRef->get_name());
                            $vmNetCon[$key]->setIpAddressAllocationMode("DHCP");
                            $vmNetCon[$key]->setIsConnected(true);
                            $vmNetConSec->setNetworkConnection($vmNetCon);
                            try {
                                try {

                                    // Put this in the queue

                                    $this->wait_in_queue("vappUpdateVm");
                                    $task = $sdkVm->modifyNetworkConnectionSettings($vmNetConSec);

                                    // Inform the queue manager that the task should be in vcloud now

                                    $this->task_in_vcloud("vappUpdateVm");
                                }

                                catch(Exception $e) {

                                    // Inform the queue manager that the task should be in vcloud now

                                    $this->task_in_vcloud("vappUpdateVm");
                                    throw $e;
                                }

                                $this->wait_for_task($task);
                            }

                            catch(Exception $e) {
                                echo $e;
                                $event_params = array(
                                    "value_returned" => 1
                                );
                                $Events->id = $update_org_id;
                                $Events->save($event_params);
                                throw $e;
                                exit(1);
                            }

                            $event_params = array(
                                "value_returned" => 0
                            );
                            $Events->id = $update_org_id;
                            $Events->save($event_params);
                            break;
                        }
                    }

                    break;
                }
            }
        }

        catch(Exception $e) {
            echo $e;
            $event_params = array(
                "value_returned" => 1
            );
            $Events->id = $update_org_id;
            $Events->save($event_params);
            throw $e;
            exit(1);
        }
    }

    function set_mac_vm($vmid = null, $mac = null, $nic_no = 0)
    {
        global $service;
        $sdkVM = $service->createSDKObj($this->get_href_from_id($vmid));
        $net = $sdkVM->getNetworkConnectionSettings();
        $cons = $net->getNetworkConnection();
        foreach($cons as $ncs) {
            if ($ncs->getNetworkConnectionIndex() == $nic_no) {
                $ncs->setMACAddress($mac);
                try {
                    $task = $sdkVM->modifyNetworkConnectionSettings($net);

                    // if ($inputs['blocking'] == true) {

                    $this->wait_for_task($task);

                    // }

                }

                catch(Exception $e) {
                    echo $e;
                    throw $e;
                    exit(1);
                }

                exit(0);
            }
        }

        exit(1);
    }

    function reset_mac_gateway($vappid = null, $username = null)
    {
        $Events = ClassRegistry::init('Events');
        $event_params = array(
            "function_name" => 'reset_mac_gateway',
            "function_parameters" => $vappid,
            "object_vcd_id" => $vappid,
            "user_id" => $username
        );
        $Events->create();
        $Events->save($event_params);
        $reset_mac_id = $Events->id;
        global $service;
        $vms = $this->list_vms_id($vappid);
        foreach($vms as $vm) {
            if (strstr($vm['name'], "gateway")) {
                $sdkVM = $service->createSDKObj($vm['vm_href']);
                $net = $sdkVM->getNetworkConnectionSettings();
                $cons = $net->getNetworkConnection();
                foreach($cons as $ncs) {
                    if (!strstr($ncs->getMACAddress() , "00:50:56:00")) {
                        $ncs->setMACAddress(null);
                        try {
                            try {

                                // Put this in the queue

                                $this->wait_in_queue("vappUpdateVm");
                                $task = $sdkVM->modifyNetworkConnectionSettings($net);

                                // Inform the queue manager that the task should be in vcloud now

                                $this->task_in_vcloud("vappUpdateVm");
                            }

                            catch(Exception $e) {

                                // Inform the queue manager that the task should be in vcloud now

                                $this->task_in_vcloud("vappUpdateVm");
                                throw $e;
                            }

                            // if ($inputs['blocking'] == true) {

                            $this->wait_for_task($task);

                            // Recache the network details of the gateway as it has changed

                            $this->get_network_details_vm($vm['vm_id'], false);

                            // }

                        }

                        catch(Exception $e) {
                            echo $e;
                            $event_params = array(
                                "value_returned" => 1
                            );
                            $Events->id = $reset_mac_id;
                            $Events->save($event_params);
                            throw $e;
                            exit(1);
                        }

                        break;
                    }
                }

                break;
            }
        }

        $event_params = array(
            "value_returned" => 0
        );
        $Events->id = $reset_mac_id;
        $Events->save($event_params);
    }

    /*
    function get_vapp_id_by_vm_id($vm_id) {
    $vm_href = $this->get_href_from_id($vm_id);
    global $service;
    $the_array = array();
    $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
    $qm = "queryRecords";
    $params = new VMware_VCloud_SDK_Query_Params();
    $params->setFields('href,name,container');
    $params->setFilter("href==" . $vm_href);
    $type = "adminVM";
    $recs = $sdkQuery->$qm($type, $params);
    $pvdcRecords = $recs->getRecord();
    foreach ($pvdcRecords as $pvdc) {
    return $this->get_id_from_href($pvdc->get_container(), "vapp");
    }
    }

    */
    function get_vapp_id_by_gateway_ip($gateway_ip)
    {

        // CakeLog::write('activity', 'In get_vapp_id_by_gateway_ip with value ' . $gateway_ip);

        if (($vapp_id = Cache::read('gateway_ip_vapp_' . $gateway_ip, 'gateway_ip_vapp_cache')) === false) {

            // CakeLog::write('activity', 'Not using cache');

        }
        else {

            // CakeLog::write('activity', 'Using cached value of ' . $vapp_id);

            return $vapp_id;
        }

        global $service;
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        $vapp_href_array = array();
        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $type = "adminVApp";
        $params->setSortDesc('creationDate');
        $params->setPageSize(128); //128 is the max page size
        $params->setFields("name");
        $params->setFilter("status!=POWERED_OFF");
        $i = 1; //
        $pages = 1; //This will remain 1 once the record are returned.
        $params->setPage($i);
        $recs = $sdkQuery->$qm($type, $params);
        while ($pages) {
            $vappRecords = $recs->getRecord();
            foreach($vappRecords as $vapp) {
                array_push($vapp_href_array, array(
                    "href" => $vapp->get_href()
                ));
            }

            $i++;
            $params->setPage($i);
            try {
                $recs = $sdkQuery->$qm($type, $params);
            }

            catch(Exception $e) {
                $pages = null;
            }
        }

        $the_array = array();
        foreach($vapp_href_array as $vapp) {
            $vapp_id = $this->get_id_from_href($vapp['href'], "vapp");
            $vms = $this->list_vms_id_name_and_href($vapp_id);
            foreach($vms as $vm) {
                if (strstr($vm['name'], "gateway")) {
                    $sdkVM = $service->createSDKObj($vm['vm_href']);
                    $net = $sdkVM->getNetworkConnectionSettings();
                    $cons = $net->getNetworkConnection();
                    foreach($cons as $ncs) {
                        if (!strstr($ncs->getMACAddress() , "00:50:56:00")) {
                            $gwipaddress = $ncs->getIpAddress(null);
                            if ($gwipaddress == $gateway_ip) {

                                // Update the cache both ways

                                CakeLog::write('activity', 'Creating a mapping between ' . $gateway_ip . ' and ' . $vapp_id);
                                $this->add_gateway_ip_vapp_cache($gateway_ip, $vapp_id);
                                return $vapp_id;
                            }

                            break;
                        }
                    }

                    break;
                }
            }
        }
    }

    function rename_vapp_template($vapptemplate_id = null, $new_vapp_template_name = null)
    {
        global $service;
        $vapptemplate_href = $this->get_href_from_id($vapptemplate_id);
        $sdk_vapp_template = $service->createSDKObj($vapptemplate_href);
        if ($sdk_vapp_template->isPartOfCatalogItem()) {
            $itemsdk = $service->createSDKObj($sdk_vapp_template->getCatalogItemLink());
            $catalogitemtype = $itemsdk->getCatalogItem();
            $catalogitemtype->set_name($new_vapp_template_name);
            $itemsdk->modify($catalogitemtype);
        }

        $task = $sdk_vapp_template->modify($new_vapp_template_name);
        $this->wait_for_task($task);
    }

    function rename_vapp($vapp_id = null, $new_vapp_name = null)
    {
        global $service;
        $vapp_href = $this->get_href_from_id($vapp_id);
        $sdk_vapp = $service->createSDKObj($vapp_href);
        $task = $sdk_vapp->modify($new_vapp_name);
        $this->wait_for_task($task);
    }

    function get_vm_deployed_status($vm_id)
    {
        global $service;
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFilter('id==' . $vm_id);
        $params->setFields('isDeployed');
        $type = "adminVM";
        $recs = $sdkQuery->$qm($type, $params);
        $vmRecords = $recs->getRecord();
        foreach($vmRecords as $vm) {
            return $vm->get_isDeployed();
        }
        return null;
    }

    # This function can register a vm from a source datastore on the required vcenter
    function register_vm($datastore,$pool,$vm_folder_name,$vcenter_datacenter,$vcenter_hostname)
    {
        $command = '/export/scripts/CLOUD/bin/vmware-vsphere-cli-distrib/register_vm.pl --datacenter="' . $vcenter_datacenter . '" --vmname="' . $vm_folder_name . '" --source_datastore="' . $datastore . '" --destination_pool="' . $pool . '"';
        $register_vm_output = $this->run_vcli_command($command,$vcenter_hostname);
        $register_vm_lines = explode("\n", $register_vm_output);
        foreach ($register_vm_lines as $line)
        {
            $key = "The vm reference is:";
            if (strstr($line, $key)) {
                return str_replace($key, "", $line);
            }
        }
        throw new Exception("Couldn't find the vm reference after copying and registering the vm. " . $register_vm_output);
    }

    # This function can return a list of hosts that are connected in the given cluster, in the given vcenter
    function list_connected_hosts_in_cluster($vcenter_hostname, $vcenter_cluster)
    {
        $command = '/export/scripts/CLOUD/bin/vmware-vsphere-cli-distrib/list_connected_hosts_in_cluster.pl --cluster="' . $vcenter_cluster. '"';
        $list_output = $this->run_vcli_command($command,$vcenter_hostname);
        $list_output_lines = explode("\n", $list_output);
        $hosts = array();
        foreach ($list_output_lines as $line)
        {
            $key = "HOST: ";
            if (strstr($line, $key)) {
                $host = str_replace($key, "", $line);
                array_push($hosts, $host);
            }
        }
        return $hosts;
    }

    // This function can email the administrators with a summary report of a catalog copy operation started from this pod
    function send_cross_pod_catalog_copy_report($started_date_string, $results,$vapptemplate_name,$vapp_template_id,$vapptemplate_catalog)
    {
        $spp_shortname = strtok(shell_exec('hostname -s'), "\n");
        $spp_hostname = strtok(shell_exec('hostname -f'), "\n");
        $total_passed = 0;
        foreach ($results as $spp_url => $result)
        {
            if ($result['passed'])
            {
                $total_passed++;
            }
        }
        $total_failures = (count($results) - $total_passed);

        Configure::load('admin_email_list');
        $mail_list = Configure::read('list');
        $email = new CakeEmail();
        $email->emailFormat('html');
        $email->from(array(
            'no_reply@ericsson.com' => 'Cloud Portal'
        ));
        $email->to($mail_list);
        if ($total_failures > 0)
        {
            $success_fail_string = $total_failures . ' Failed';
        }
        else {
            $success_fail_string = $total_passed . ' Successful';
        }
        $email->subject('Cloud Portal: ' . $spp_shortname . ' - ' . $success_fail_string . ' - Cross Pod Catalog Copy Report');
        $email->template('cross_pod_catalog_copy_report', 'custom');
        $email->viewVars(array(
            'results' => $results,
            'vapptemplate_name' => $vapptemplate_name,
            'vapp_template_id' => $vapp_template_id,
            'vapptemplate_catalog' => $vapptemplate_catalog,
            'spp_hostname' => $spp_hostname,
            'started_date_string' => $started_date_string
        ));
        $email->send();
    }

    # This function starts the sync of a vApp template to other pods, and will notify administrators if it fails
    function sync_vapp_template_to_other_pods($vapp_template_id) {
        $vapptemplate_name = "<template may be deleted now>";
        $vapptemplate_catalog = $vapptemplate_name;
        $params = array(
            'type' => "adminVAppTemplate",
            'fields' => array('name','catalogName'),
            'filter' => "id==" . $vapp_template_id
        );
        $vapptemplates = $this->query_service_request($params);
        if (isset($vapptemplates[0]))
        {
            $vapptemplate = $vapptemplates[0];
            $vapptemplate_name = $vapptemplate['name'];
            $vapptemplate_catalog = $vapptemplate['catalogName'];
        } else
        {
            $vapptemplate_name = "<template may have been deleted>";
            $vapptemplate_catalog = $vapptemplate_name;
        }

        try {
            CakeLog::write('pod_sync', 'Waiting to get the lock to allow the pod sync to start');
            $pod_sync_lock_name = 'pod_sync_lock';
            $started_date_string = date('l jS F \a\t H:i T');
            $this->get_vapp_lock($pod_sync_lock_name, 14400);
            $results = $this->sync_vapp_template_to_other_pods_internal($vapp_template_id);
            $this->clear_vapp_lock($pod_sync_lock_name);
            $this->send_cross_pod_catalog_copy_report($started_date_string, $results,$vapptemplate_name,$vapp_template_id,$vapptemplate_catalog);
            return $results;
        } catch (Exception $e)
        {
            $this->clear_vapp_lock($pod_sync_lock_name);
            $verbose_details = 'There was an issue syncing the template "' . $vapptemplate_name . '" (' . $vapp_template_id . ') from catalog "' . $vapptemplate_catalog . '" to other pods';
            $this->report_exception('sync_vapp_template_to_other_pods', $e, 1, 1, $verbose_details);
            CakeLog::write('pod_sync', $verbose_details . ': ' . $e);
            throw $e;
        }
    }

    // This function runs the updatevv on a given volume on the given 3par
    function run_3par_updatevv($storage_hostname, $storage_username, $storage_password, $volume)
    {
        $updatevv_command = "updatevv -f " . $volume;
        $attempts = 90;
        for ($attempt = 1; $attempt <= $attempts; $attempt++)
        {
            $updatevv_output = $this->run_3par_command($storage_hostname, $storage_username, $storage_password, $updatevv_command);
            if (!strstr($updatevv_output, 'Updating VVs') || sizeof(explode("\n", $updatevv_output)) > 2)
            {
                $error_message = "Something went wrong during attempt " . $attempt . " of " . $attempts . " running the following command on the 3par '" . $storage_hostname . "'. Command was '" . $updatevv_command. "' and the output was '" . $updatevv_output . "'";
                CakeLog::write('pod_sync', $error_message);
                if ($attempt == $attempts)
                {
                    throw new Exception($error_message);
                }
            } else {
                return;
            }
        }
    }

    // This function runs the admitrcopyvv on the given 3par
    function run_3par_admitrcopyvv($storage_hostname, $storage_username, $storage_password, $storage_volume_master, $storage_rcfc_group_name, $storage_rcfc_target_name, $storage_rcfc_physical_volume)
    {
        $admitrcopyvv_command = "admitrcopyvv " . $storage_volume_master . ":RCFC_SNAP_PREV " . $storage_rcfc_group_name . " " . $storage_rcfc_target_name . ":" . $storage_rcfc_physical_volume;
        $attempts=90;
        for ($attempt = 1; $attempt <= $attempts; $attempt++)
        {
            $admitrcopyvv_output = $this->run_3par_command($storage_hostname, $storage_username, $storage_password, $admitrcopyvv_command);
            if ($admitrcopyvv_output != "" && !strstr($admitrcopyvv_output, "Group has already been started") && !strstr($admitrcopyvv_output, "Volume already in group " . $storage_rcfc_group_name))
            {
                $error_message = "Something went wrong during attempt " . $attempt . " of " . $attempts . " running the following command on the 3par '" . $storage_hostname . "', it should give no output if it worked. Command was '" . $admitrcopyvv_command. "' and the output was '" . $admitrcopyvv_output . "', sleeping for 20 seconds before the next attempt";
                CakeLog::write('pod_sync', $error_message);
                if ($attempt == $attempts)
                {
                    throw new Exception($error_message);
                }
                sleep(20);
            } else {
                return;
            }
        }
    }

    // This function runs the startrcopygroup on the given 3par
    function run_3par_startrcopygroup($storage_hostname, $storage_username, $storage_password, $storage_rcfc_group_name)
    {
        $startcopygroup_command = "startrcopygroup -wait " . $storage_rcfc_group_name;
        $attempts=90;
        for ($attempt = 1; $attempt <= $attempts; $attempt++)
        {
            $startcopygroup_output = $this->run_3par_command($storage_hostname, $storage_username, $storage_password, $startcopygroup_command);
            $startcopygroup_output_no_spaces = trim(preg_replace('/\s\s+/', ' ', $startcopygroup_output));
            if ($startcopygroup_output_no_spaces != "" && !strstr($startcopygroup_output, "Group has already been started") && !strstr($startcopygroup_output, "starts, task ID"))
            {
                $error_message = "Something went wrong during attempt " . $attempt . " of " . $attempts . " running the following command on the 3par '" . $storage_hostname . "', it should give no output if it worked. Command was '" . $startcopygroup_command. "' and the output was '" . $startcopygroup_output . "'";
                CakeLog::write('pod_sync', $error_message);
                if ($attempt == $attempts)
                {
                    throw new Exception($error_message);
                }
                sleep(20);
            } else {
                return;
            }
        }
    }

    // This function runs the stoprcopygroup on the given 3par
    function run_3par_stoprcopygroup($storage_hostname, $storage_username, $storage_password, $storage_rcfc_group_name)
    {
        $stoprcopygroup_command = "stoprcopygroup -f " . $storage_rcfc_group_name;
        $stoprcopygroup_output = $this->run_3par_command($storage_hostname, $storage_username, $storage_password, $stoprcopygroup_command);
        if ($stoprcopygroup_output != "")
        {
            throw new Exception("Something went wrong running the following command on the 3par '" . $storage_hostname . "', it should give no output if it worked. Command was '" . $stoprcopygroup_command. "' and the output was '" . $stoprcopygroup_output. "'");
        }
    }

    // This function runs the dismissrcopyvv on the given 3par
    function run_3par_dismissrcopyvv($storage_hostname, $storage_username, $storage_password, $storage_rcfc_group_name, $storage_volume_master)
    {
        $dismissrcopy_command = "dismissrcopyvv -f " . $storage_volume_master . " " . $storage_rcfc_group_name;
        $dismissrcopy_output = $this->run_3par_command($storage_hostname, $storage_username, $storage_password, $dismissrcopy_command);
        if (!strstr($dismissrcopy_output,"has been dismissed from group"))
        {
            throw new Exception("Something went wrong running the following command on the 3par '" . $storage_hostname . "', it should give no output if it worked. Command was '" . $dismissrcopy_command . "' and the output was '" . $dismissrcopy_output. "'");
        }
    }

    // This function can synchronize a source and destination 3par copy group
    function sync_3par_remote_copygroup($source_pod_settings, $destination_pod_settings)
    {
        $this->run_3par_admitrcopyvv($source_pod_settings['storage_hostname'], $source_pod_settings['storage_username'], $source_pod_settings['storage_password'], $source_pod_settings['storage_volume_master'],$destination_pod_settings['storage_rcfc_group_name'], $destination_pod_settings['storage_rcfc_target_name'], $destination_pod_settings['storage_rcfc_physical_volume']);
        $this->run_3par_startrcopygroup($source_pod_settings['storage_hostname'], $source_pod_settings['storage_username'], $source_pod_settings['storage_password'], $destination_pod_settings['storage_rcfc_group_name']);
        $this->run_3par_stoprcopygroup($source_pod_settings['storage_hostname'], $source_pod_settings['storage_username'], $source_pod_settings['storage_password'], $destination_pod_settings['storage_rcfc_group_name']);
        $this->run_3par_dismissrcopyvv($source_pod_settings['storage_hostname'], $source_pod_settings['storage_username'], $source_pod_settings['storage_password'], $destination_pod_settings['storage_rcfc_group_name'], $source_pod_settings['storage_volume_master']);
    }

    // This function can check if a template of a given name exists on a different pod in a given catalog
    function template_exists_on_another_pod_catalog($spp_url,$spp_username,$spp_password,$catalog_name,$vapp_template_name)
    {
        $template_exists_urls = array();

        $url = $spp_url . 'VappTemplates/index_api/catalog_name:' . urlencode($catalog_name). '/.json';
        array_push($template_exists_urls, array(
            'url' => $url
        ));

        $curl_options = array(
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $spp_username . ":" . $spp_password
        );

        $template_exists_results = $this->parallel_curl($template_exists_urls, $curl_options);

        // Check that each sync to the other pods came back with a valid response
        for ($i = 0; $i < count($template_exists_results); $i++)
        {
            $template_exists_result_object = json_decode($template_exists_results[$i],true);
            if (!isset($template_exists_result_object['vapptemplates']))
            {
                throw new Exception('Something went wrong checking if a vapp template exists on another pods catalog, see output: ' . $template_exists_results[$i]);
            }
            foreach ($template_exists_result_object['vapptemplates'] as $template)
            {
                if ($template['vapptemplate_name'] == $vapp_template_name)
                {
                    return true;
                }
            }
        }
        return false;
    }

    # This function can sync a vapp template from this pod, to other pods defined in the pod_sync config file
    function sync_vapp_template_to_other_pods_internal($vapp_template_id) {
        global $service;
        $results = array();
        try {
            Configure::load('pod_sync');
        } catch (Exception $e)
        {
            return $results;
        }

        $params = array(
            'type' => "adminVAppTemplate",
            'fields' => array('name','catalogName'),
            'filter' => "id==" . $vapp_template_id
        );
        $vapptemplates = $this->query_service_request($params);
        $vapptemplate = $vapptemplates[0];

        $pod_sync_definitions = Configure::read('pod_sync_definitions');
        if (isset($pod_sync_definitions[$vapptemplate['catalogName']]))
        {
            $vapp_template_name_regex = $pod_sync_definitions[$vapptemplate['catalogName']]['template_name_regex'];
            if ($vapp_template_name_regex) {
                if (!preg_match("/^$vapp_template_name_regex/m", $vapptemplate['name'])) {
                    CakeLog::write('pod_sync', 'The template name did not match the given template_name_regex, not going to copy this template');
                    return $results;
                } else {
                    CakeLog::write('pod_sync', 'The template name matched the given template_name_regex so going to copy this template');
                }
            } else {
                CakeLog::write('pod_sync', 'There was no template_name_regex key specified in the configuration so continuing');
            }
            CakeLog::write('pod_sync', 'Starting copy of template "' . $vapptemplate['name'] . '" from catalog "' . $vapptemplate['catalogName'] . '" across pods');
            $time_start = microtime(true);

            $all_destination_pod_settings = $pod_sync_definitions[$vapptemplate['catalogName']]['destination_pod_settings'];
            foreach($all_destination_pod_settings as $destination_pod_settings) {
                $results[$destination_pod_settings['spp_url']] = array (
                    'passed' => false,
                    'comment' => ''
                );
            }
            $required_destination_pod_settings = array();
            foreach($all_destination_pod_settings as $destination_pod_settings) {
                $template_exists_on_another_pod_catalog = false;
                $pod_down = false;
                try {
                    $template_exists_on_another_pod_catalog = $this->template_exists_on_another_pod_catalog($destination_pod_settings['spp_url'],$destination_pod_settings['spp_username'],$destination_pod_settings['spp_password'],$destination_pod_settings['catalog_name'],$vapptemplate['name']);
                } catch (Exception $e) {
                    $pod_down = true;
                }
                if ($template_exists_on_another_pod_catalog)
                {
                    $comment = 'This template already exists on this pod, not copying it to this pod again';
                    $results[$destination_pod_settings['spp_url']]['passed'] = true;
                    $results[$destination_pod_settings['spp_url']]['comment'] = $comment;
                    CakeLog::write('pod_sync', $comment . ': ' . $destination_pod_settings['spp_url']);
                } elseif ($pod_down)
                {
                    $comment = 'This pod seems to be down or having issues, not copying it to this pod';
                    $results[$destination_pod_settings['spp_url']]['passed'] = false;
                    $results[$destination_pod_settings['spp_url']]['comment'] = $comment;
                    CakeLog::write('pod_sync', $comment . ': ' . $destination_pod_settings['spp_url']);
                } else
                {
                    CakeLog::write('pod_sync','This template doesnt already exist yet on this pod, going to copy it to this pod: ' . $destination_pod_settings['spp_url']);
                    array_push($required_destination_pod_settings, $destination_pod_settings);
                }
            }
            $source_pod_settings = $pod_sync_definitions[$vapptemplate['catalogName']]['source_pod_settings'];
            CakeLog::write('pod_sync','Deploying the vApp Template from its original catalog ' . $vapptemplate['catalogName'] . ' into the source datacenter ' . $source_pod_settings['datacenter_name']);
            $deploy_params = array (
                'destorgvdcname' => $source_pod_settings['datacenter_name'],
                'vapp_template_id' => $vapp_template_id,
                'new_vapp_name' => $vapptemplate['name'],
                'linked_clone' => true,
                'start_vapp' => 'no'
            );
            $temporary_vapp = $this->deploy_from_catalog($deploy_params, 'sync', 'sync');
            $temporary_vapp_id = $temporary_vapp['vapp_id'];
            CakeLog::write('pod_sync','The vApp Template got deployed in the source datacenter, with an id of ' . $temporary_vapp_id);

            // First do the storage related steps on per source / destination pair
            foreach($all_destination_pod_settings as $destination_pod_settings) {
                $this->sync_3par_remote_copygroup($source_pod_settings, $destination_pod_settings);
            }
            $this->run_3par_updatevv($source_pod_settings['storage_hostname'], $source_pod_settings['storage_username'], $source_pod_settings['storage_password'], 'RCFC_SNAP_PREV');

            CakeLog::write('pod_sync', 'Getting the configuration settings of the source vApp');
            $source_vapp_network_settings = base64_encode(serialize($this->get_vapp_network_settings_object($temporary_vapp_id)));
            $source_vapp_startup_settings = base64_encode(serialize($this->get_vapp_startup_settings_object($temporary_vapp_id)));

            // Get all of the network settings for each vm
            $vm_details = array();
            $params = array(
                'type' => "adminVM",
                'fields' => array('name'),
                'generated_fields' => array('vm_id'),
                'filter' => "container==" . $temporary_vapp_id
            );
            $vms = $this->query_service_request($params);

            foreach ($vms as $vm)
            {
                $sdkVM = $service->createSDKObj($this->get_href_from_id($vm['vm_id']));
                $vm_network_settings = $sdkVM->getNetworkConnectionSettings();
                $vm_network_settings_encoded = base64_encode(serialize($vm_network_settings));
                array_push($vm_details, array (
                    'name' => $vm['name'],
                    'vm_id' => $vm['vm_id'],
                    'network_settings' => $vm_network_settings_encoded
                ));
            }

            // Now start the vApp creations on each of the destination pods
            $vapp_creations_time_start = microtime(true);
            CakeLog::write('pod_sync', 'Starting the vApp creation on each destination pod, follow the logs from there');
            Configure::load('user_details');
            $user_details = Configure::read('rest_call');
            $sync_from_another_pod_api_urls = array();
            foreach($required_destination_pod_settings as $destination_pod_settings) {
                $post_fields = array(
                    'destination_pod_settings' => $destination_pod_settings,
                    'new_template_name' => $vapptemplate['name'],
                    'source_vapp_network_settings' => $source_vapp_network_settings,
                    'source_vapp_startup_settings' => $source_vapp_startup_settings,
                    'vm_details' => $vm_details
                );

                $url = $destination_pod_settings['spp_url'] . 'VappTemplates/sync_vapp_from_another_pod_api/.json';
                $post = 'data=' . json_encode($post_fields);
                array_push($sync_from_another_pod_api_urls, array(
                    'url' => $url,
                    'post' => $post
                ));
            }

            $curl_options = array(
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => $user_details['username'] . ":" . $user_details['password']
            );

            $sync_from_another_pod_results = $this->parallel_curl($sync_from_another_pod_api_urls, $curl_options, true);

            // Check that each sync to the other pods came back with a valid response
            for ($i = 0; $i < count($sync_from_another_pod_results); $i++)
            {
                $sync_from_another_pod_result_object = json_decode($sync_from_another_pod_results[$i]['content'], true);
                if (!isset($sync_from_another_pod_result_object['vapptemplate']))
                {
                    $results[$required_destination_pod_settings[$i]['spp_url']]['passed'] = false;
                    $results[$required_destination_pod_settings[$i]['spp_url']]['comment'] = $sync_from_another_pod_results[$i]['content'];
                } else {
                    $single_pod_execution_time = ($vapp_creations_time_start - $time_start + $sync_from_another_pod_results[$i]['info']['total_time']);
                    $results[$required_destination_pod_settings[$i]['spp_url']]['passed'] = true;
                    $results[$required_destination_pod_settings[$i]['spp_url']]['comment'] = 'No issues found. It took ' . $this->seconds_to_hours_minutes_and_seconds($single_pod_execution_time);
                }
            }

            // Log the final results for each pod
            $found_failure = false;
            foreach ($results as $spp_url => $result)
            {
                CakeLog::write('pod_sync', 'Final Result For ' . $spp_url . ': ' . ($result['passed'] ? 'Success' : 'Fail') . ': ' . $result['comment']);
                if (!$result['passed'])
                {
                    $found_failure = true;
                }
            }

            // Only perform final steps if they all passed
            if ($found_failure)
            {
                CakeLog::write('pod_sync', 'There were failures found so not cleaning up the temporary vApp');
            } else {
                CakeLog::write('pod_sync', 'Cleaning up temporary vApp that got deployed to the source datacenter, with id of ' . $temporary_vapp_id);
                $this->delete_vapp($temporary_vapp_id, 'sync');
            }
            $time_end = microtime(true);
            $execution_time = ($time_end - $time_start);
            CakeLog::write('pod_sync', 'Finished copy of template ' . $vapptemplate['name'] . ' from catalog ' . $vapptemplate['catalogName'] . ' across pods. It took ' . $this->seconds_to_hours_minutes_and_seconds($execution_time));
        }
        return $results;
    }

    function seconds_to_hours_minutes_and_seconds($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds - ($hours*3600)) / 60);
        $seconds = floor($seconds % 60);
        return $hours . ' ' . ($hours == 1 ? 'hour' : 'hours') . ' ' . $minutes . ' ' . ($minutes == 1 ? 'minute' : 'minutes') . ' and ' . $seconds . ' ' . ($seconds == 1 ? 'second' : 'seconds');
    }

    # This function can mount a datastore onto all hosts in a given cluster
    function mount_snapshot_in_cluster($vcenter_hostname, $datastore, $vcenter_cluster)
    {
        CakeLog::write('pod_sync', 'Mounting the snapshot datastore ' . $datastore . ' on the hosts in cluster ' . $vcenter_cluster);
        $connected_hosts = $this->list_connected_hosts_in_cluster($vcenter_hostname, $vcenter_cluster);
        Configure::load('user_details');
        $user_details = Configure::read('rest_call');
        $mount_host_urls = array();
        foreach ($connected_hosts as $host)
        {
            $url = $user_details['url'] . '/VappTemplates/mount_snapshot_to_host_api/datastore:' . $datastore . '/host:' . $host . '/vcenter_hostname:' . $vcenter_hostname . '/.json';
            array_push($mount_host_urls, $url);
        }
        $curl_options = array(
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $user_details['username'] . ":" . $user_details['password']
        );
        $mount_host_results = $this->parallel_curl($mount_host_urls, $curl_options);

        // Get the results
        for ($i = 0; $i < count($connected_hosts); $i++)
        {
            $mount_host_result_object = json_decode($mount_host_results[$i], true);
            if (!isset($mount_host_result_object['result']))
            {
                throw new Exception('Something went wrong mounting the snapshot datastore on a host, see output: ' . $mount_host_results[$i]);
            }
        }
    }

    # This function can mount a datastore to a single host
    function mount_snapshot_to_host($vcenter_hostname, $datastore, $host)
    {
        CakeLog::write('pod_sync', 'Mounting the snapshot datastore ' . $datastore . ' to host ' . $host);
        $command = 'esxcli --vihost ' . $host . ' storage vmfs snapshot mount -l ' . $datastore;
        $attempts = 60;
        for ($attempt = 1; $attempt <= $attempts; $attempt++)
        {
            CakeLog::write('pod_sync', 'Checking first if the volume is already mounted to host ' . $host);
            $check_command = 'esxcli --vihost ' . $host . ' storage filesystem list';
            $output = $this->run_vcli_command($check_command,$vcenter_hostname);
            if (strstr($output, ' ' . $datastore . ' '))
            {
                CakeLog::write('pod_sync', 'This volume is already mounted to ' . $host);
                break;
            }
            $output = $this->run_vcli_command($command,$vcenter_hostname);
            if ($output != "The exit code was 0\n")
            {
                $error_message = "Mounting the snapshot datastore " . $datastore . " to host " . $host . " gave unexpected output on attempt " . $attempt . " of " . $attempts . ", here it is: " . $output;
                CakeLog::write('pod_sync', $error_message);
                if ($attempt == $attempts)
                {
                    throw new Exception($error_message);
                }
                if (strpos($output, 'No unresolved VMFS snapshots with volume label') !== false)
                {
                    CakeLog::write('pod_sync', 'The failure to mount mentioned no unresolved VMFS snapshots, rescanning the hbas on this host before the next attempt');
                    $this->rescan_hbas_on_host($vcenter_hostname, $host);
                }
                sleep(1);
            } else {
                break;
            }
        }
        CakeLog::write('pod_sync', 'Finished mounting the snapshot datastore ' . $datastore . ' to host ' . $host);
    }

    # This function can mount or unmount a datastore from the hosts its attached to, in a given vcenter
    function run_datastore_mount_operation($vcenter_hostname, $datastore, $mount)
    {
        if ($mount)
        {
            $operation = "mount";
        } else {
            $operation = "unmount";
        }
        CakeLog::write('pod_sync', 'Running a ' . $operation . ' operation on the datastore ' . $datastore . ' on all hosts its currently attached to');
        $command = '/export/scripts/CLOUD/bin/vmware-vsphere-cli-distrib/datastore_operations.pl --datastore="' . $datastore. '" --operation="' . $operation. '"';
        $this->run_vcli_command($command,$vcenter_hostname);
        CakeLog::write('pod_sync', 'Finished running a ' . $operation . ' operation on the datastore ' . $datastore);
    }

    # This function can rescan the hbas on a single host in a given vcenter
    function rescan_hbas_on_host($vcenter_hostname, $host)
    {
        CakeLog::write('pod_sync', 'Rescanning the hbas on the host ' . $host);
        $command = '/export/scripts/CLOUD/bin/vmware-vsphere-cli-distrib/rescan_hbas_on_host.pl --host="' . $host . '"';
        $this->run_vcli_command($command,$vcenter_hostname);
        CakeLog::write('pod_sync', 'Finished rescanning the hbas on the host ' . $host);
    }

    # This function can run a shell command on a given 3par
    function run_3par_command($server, $username, $password, $command)
    {
        CakeLog::write('pod_sync', 'Running this command on 3par ' . $server . ': ' . $command);
        $time_start = microtime(true);
        $command_output = $this->run_remote_shell_command_internal($server, $username, $password, $command, false, false);
        $time_end = microtime(true);
        $execution_time = ($time_end - $time_start);
        CakeLog::write('pod_sync', 'Finished running this command on 3par ' . $server . '. It took ' . $this->seconds_to_hours_minutes_and_seconds($execution_time) . ' : ' . $command);
        CakeLog::write('pod_sync', 'The output from the 3par command was "' . $command_output . '"');
        return $command_output;
    }

    // This function can run a shell command remotely. It will throw an exception if the command does not return a 0 exit code
    function run_remote_shell_command($server, $username, $password, $command)
    {
        return $this->run_remote_shell_command_internal($server, $username, $password, $command, true, true);
    }

    // This function can run a shell command remotely. It can optionally redirect standard error to standard out, and also optionally check the exit code of the command
    function run_remote_shell_command_internal($server, $username, $password, $command, $redirect_stderr, $check_exit_code)
    {
        $full_command = $command;
        if ($redirect_stderr)
        {
            $full_command = $full_command . ' 2>&1';
        }

        $connection = new Net_SSH2($server, 22, 14400);
        if($connection === false) {
            throw new Exception('Cant connect to server ' . $server);
        }
        $result = $connection->login($username, $password);
        if($result === false) {
            throw new Exception('Authentication failed connecting to server ' . $server);
        }
        $returned_output = $connection->exec($full_command);
        if ($check_exit_code)
        {
            if ($connection->getExitStatus() != 0) {
                throw new Exception("Something went wrong running the shell command on " . $server . ", heres the output: " . $returned_output);
            }
        }
        return $returned_output;
    }

    // This function can mount a snapshot datastore on the hosts in a given cluster. It runs the 3par updatevv to synchronize any changes from the source 3par datastore
    function remount_datastore($destination_pod_settings)
    {
        CakeLog::write('pod_sync', 'Starting the remount of the datastore');
        $query_params = array(
            'type' => "adminOrgVdc",
            'fields' => array('name'),
            'generated_fields' => array('org_vdc_id'),
            'filter' => 'name==' . urlencode($destination_pod_settings['datacenter_name'])
        );
        $org_vdcs = $this->query_service_request($query_params);
        $org_vdc = $org_vdcs[0];

        $vcenter_hostname = $this->get_vcenter_of_orgvdc($org_vdc['org_vdc_id']);
        $this->run_datastore_mount_operation($vcenter_hostname, $destination_pod_settings['datastore'], false);
        $this->run_3par_updatevv($destination_pod_settings['storage_hostname'], $destination_pod_settings['storage_username'], $destination_pod_settings['storage_password'], $destination_pod_settings['storage_rcfc_snap_volume']);
        $this->mount_snapshot_in_cluster($vcenter_hostname, $destination_pod_settings['datastore'], $destination_pod_settings['vcenter_cluster']);
        CakeLog::write('pod_sync', 'Finished the remount of the datastore');
    }

    // This function can import a vcenter vm as a vapp. It won't wait for the actual import to complete so its upto the user to run a wait_for_task on the resulting vApps running tasks
    function import_vcenter_vm_as_vapp_without_wait($vapp_name, $vm_name, $vm_reference, $org_vdc_ref)
    {
        global $service;
        $sdk_ext = $service->createSDKExtensionObj();
        $vim_ref = $sdk_ext->getVimServerRefs("")[0];
        $sdk_vim_server = $service->createSDKObj($vim_ref);
        $params = new VMware_VCloud_API_Extension_ImportVmAsVAppParamsType();
        $params->set_name($vapp_name);
        $params->setVmName($vm_name);
        $params->setVmMoRef($vm_reference);
        $params->set_sourceMove(true);
        $params->setVdc($org_vdc_ref);
        $attempts = 20;
        for ($attempt = 1; $attempt <= $attempts; $attempt++)
        {
            try {
                return $sdk_vim_server->importVmAsVApp($params);
            } catch (Exception $e)
            {
                $error_message = 'The import of the vm from vCenter into vCloud failed on attempt ' . $attempt . ' of ' . $attempts . ' with this error ' . $e;
                CakeLog::write('pod_sync', $error_message);
                if ($attempt == $attempts)
                {
                    throw new Exception($error_message);
                }
                sleep(30);
            }
        }
    }

    # This function creates a vApp on this pod, from vm folders and vApp settings from a source pod
    function sync_vapp_from_another_pod($destination_pod_settings,$source_vapp_network_settings,$source_vapp_startup_settings,$new_template_name,$vms)
    {
        $datacenter_name = $destination_pod_settings['datacenter_name'];
        $catalog_name = $destination_pod_settings['catalog_name'];
        $datastore = $destination_pod_settings['datastore'];
        $vcenter_datacenter = $destination_pod_settings['vcenter_datacenter'];
        $vcenter_cluster = $destination_pod_settings['vcenter_cluster'];

        $this->remount_datastore($destination_pod_settings);

        CakeLog::write('pod_sync', 'Creating a vApp template ' . $new_template_name . ' in catalog ' . $catalog_name . ' from files in datastore ' . $datastore);
        $time_start = microtime(true);
        global $service;
        $source_vapp_network_settings = unserialize(base64_decode($source_vapp_network_settings));
        $source_vapp_startup_settings = unserialize(base64_decode($source_vapp_startup_settings));

        // Get the reference to the vdc
        $query_params = array(
            'type' => "adminOrgVdc",
            'fields' => array('name'),
            'generated_fields' => array('href','org_vdc_id'),
            'filter' => 'name==' . urlencode($datacenter_name)
        );
        $org_vdcs = $this->query_service_request($query_params);
        $org_vdc = $org_vdcs[0];
        $org_vdc_ref = VMware_VCloud_SDK_Helper::createReferenceTypeObj($org_vdc['href']);
        $org_vdc_id_stripped = str_replace("urn:vcloud:orgvdc:","",$org_vdc['org_vdc_id']);
        $pool = $datacenter_name . ' (' . $org_vdc_id_stripped . ')';
        $vcenter_hostname = $this->get_vcenter_of_orgvdc($org_vdc['org_vdc_id']);

        // Start copy and register of each vm in parallel
        CakeLog::write('pod_sync', 'Registering each vm in vcenter');
        Configure::load('user_details');
        $user_details = Configure::read('rest_call');
        $register_vm_urls = array();
        foreach ($vms as &$vm)
        {
            $vm['network_settings'] = unserialize(base64_decode($vm['network_settings']));
            $vm_id_stripped = str_replace("urn:vcloud:vm:","",$vm['vm_id']);
            $vm_folder_name = $vm['name'] . ' (' . $vm_id_stripped . ')';
            $url = $user_details['url'] . '/VappTemplates/register_vm_api/datastore:' . $datastore . '/pool:' . urlencode($pool) . '/vm_folder_name:' . urlencode($vm_folder_name) . '/vcenter_datacenter:' . urlencode($vcenter_datacenter) . '/vcenter_hostname:' . $vcenter_hostname . '/.json';
            array_push($register_vm_urls, $url);
        }
        $curl_options = array(
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $user_details['username'] . ":" . $user_details['password']
        );
        $register_vm_results = $this->parallel_curl($register_vm_urls, $curl_options);

        // Get the vm ids from the results
        for ($i = 0; $i < count($vms); $i++)
        {
            $register_vm_result_object = json_decode($register_vm_results[$i], true);
            if (isset($register_vm_result_object['vm_id']))
            {
                $vms[$i]['vm_reference'] = $register_vm_result_object['vm_id'];
            } else {
                throw new Exception('Something went wrong registering one of the vms, see output: ' . $register_vm_results[$i]);
            }
        }

        $temporary_vapp_ids = array();
        $source_vapps = array();
        $import_tasks = array();

        CakeLog::write('pod_sync', 'Importing each vm into a temporary vApp');
        // Create a temporary vApp out of each vm in parallel
        for ($x = 0; $x < count($vms); $x++)
        {
            $vapp_name = $new_template_name . '_for_' . $catalog_name . ' ' . $vms[$x]['name'] . '_only';
            $temporary_vapp = $this->import_vcenter_vm_as_vapp_without_wait($vapp_name, $vms[$x]['name'], $vms[$x]['vm_reference'], $org_vdc_ref);
            $tasks = $temporary_vapp->getTasks()->getTask();
            if ($tasks) {
                $task = $tasks[0];
                array_push($import_tasks, $task);
            }

            $source_vapp_ref = VMware_VCloud_SDK_Helper::createReferenceTypeObj($temporary_vapp->get_href());
            $sourced_items_params = new VMware_VCloud_API_SourcedCompositionItemParamType();
            $sourced_items_params->setSource($source_vapp_ref);
            $sourced_items_params->set_sourceDelete(true);
            array_push($source_vapps,$sourced_items_params);
            array_push($temporary_vapp_ids,$temporary_vapp->get_id());
        }
        foreach ($import_tasks as $task)
        {
            $this->wait_for_task($task);
        }

        CakeLog::write('pod_sync', 'Composing a vApp from all of the temporary vApps');
        // Join the temporary vApps together into one vApp using a compose operation
        $sdk_org_vdc = $service->createSDKObj(str_replace('/api/admin/vdc/','/api/vdc/', $org_vdc['href']));
        $compose_params = new VMware_VCloud_API_ComposeVAppParamsType();
        $compose_params->setSourcedItem($source_vapps);
        $compose_params->set_name($new_template_name . '_for_' . $catalog_name);
        $composed_vapp = $sdk_org_vdc->composeVApp($compose_params);
        $tasks = $composed_vapp->getTasks()->getTask();
        if ($tasks) {
            $task = $tasks[0];
            $this->wait_for_task($task);
        }

        $sdk_composed_vapp = $service->createSDKObj($composed_vapp->get_href());

        CakeLog::write('pod_sync', 'Applying the startup settings to the vapp');
        $task = $sdk_composed_vapp->modifyStartupSettings($source_vapp_startup_settings);
        $this->wait_for_task($task);

        CakeLog::write('pod_sync', 'Applying the network settings to the vapp');
        $task = $sdk_composed_vapp->modifyNetworkConfigSettings($source_vapp_network_settings);
        $this->wait_for_task($task);

        // Find all of the vms in the new vapp
        $admin_vm_params = array(
            'type' => "adminVM",
            'fields' => array('name'),
            'generated_fields' => array('vm_id'),
            'filter' => 'container==' . $composed_vapp->get_href()
        );
        $vms_in_new_vapp = $this->query_service_request($admin_vm_params);
        foreach ($vms_in_new_vapp as $vm_in_new_vapp)
        {
            foreach ($vms as &$vm)
            {
                if($vm['name'] == $vm_in_new_vapp['name'])
                {
                    $vm['new_vm_id'] = $vm_in_new_vapp['vm_id'];
                    continue;
                }
            }
        }

        // Import each original vms settings
        foreach ($vms as &$vm)
        {
            CakeLog::write('pod_sync', 'Applying the network settings to the ' . $vm['name'] . ' vm');
            // If its the gateway, temporarily set its public interface onto an internal network to avoid errors
            if (strstr($vm['name'], "gateway")) {
                $network_for_external_nic = "";
                $vmNetCon = $vm['network_settings']->getNetworkConnection();
                foreach($vmNetCon as $key => $value) {
                    if (strstr($vmNetCon[$key]->getMACAddress() , "00:50:56:00")) {
                        $network_for_external_nic = $vmNetCon[$key]->get_Network();
                        break;
                    }
                }
                foreach($vmNetCon as $key => $value) {
                    if (!strstr($vmNetCon[$key]->getMACAddress() , "00:50:56:00")) {
                        $vmNetCon[$key]->set_Network($network_for_external_nic);
                        $vm['network_settings']->setNetworkConnection($vmNetCon);
                        break;
                    }
                }
            }
            $sdk_vm = $service->createSDKObj($this->get_href_from_id($vm['new_vm_id']));
            $task = $sdk_vm->modifyNetworkConnectionSettings($vm['network_settings']);
            $this->wait_for_task($task);
        }

        // Add back to the catalog
        CakeLog::write('pod_sync', 'Adding the built vApp to the catalog');
        $add_params = array(
            'vapp_id' => $composed_vapp->get_id(),
            'dest_catalog_name' => $catalog_name,
            'new_vapp_template_name' => $new_template_name
        );
        $vapptemplate_details = $this->add_vapp_to_catalog($add_params, 'sync', 'sync');

        // Delete the temporary vApp on this destination pod
        CakeLog::write('pod_sync', 'Cleaning up temporary vApp that got added to the catalog, with id of ' . $composed_vapp->get_id());
        $this->delete_vapp($composed_vapp->get_id(), 'sync');

        // Delete the other temporary vApps that were used to import the vms in parallel
        foreach ($temporary_vapp_ids as $vapp_id)
        {
            CakeLog::write('pod_sync', 'Cleaning up temporary vApp with id of ' . $vapp_id);
            $this->delete_vapp($vapp_id, 'sync');
        }
        $this->run_datastore_mount_operation($vcenter_hostname, $datastore, false);
        $time_end = microtime(true);
        $execution_time = ($time_end - $time_start);
        CakeLog::write('pod_sync', 'Finished creating a vApp template ' . $new_template_name . ' in catalog ' . $catalog_name . ' from files in datastore ' . $datastore . '. It took ' . $this->seconds_to_hours_minutes_and_seconds($execution_time));
        return $vapptemplate_details;
    }

    # This function can execute many curl operations in parallel and wait for their results to be returned
    function parallel_curl($data_array, $options = array(), $return_curl_info = false) {
        $curl_handles = array();
        $results = array();
        $multi_handle = curl_multi_init();

        // Loop through $data and create curl handles
        // then add them to the multi-handle
        foreach ($data_array as $id => $data) {

            $curl_handles[$id] = curl_init();

            if (is_array($data) && !empty($data['url']))
            {
                $url = $data['url'];
            } else {
                $url = $data;
            }
            curl_setopt($curl_handles[$id], CURLOPT_URL,            $url);
            curl_setopt($curl_handles[$id], CURLOPT_HEADER,         0);
            curl_setopt($curl_handles[$id], CURLOPT_RETURNTRANSFER, 1);

            if (is_array($data) && (!empty($data['post']))) {
                curl_setopt($curl_handles[$id], CURLOPT_POST,       1);
                curl_setopt($curl_handles[$id], CURLOPT_POSTFIELDS, $data['post']);
            }

            if (!empty($options)) {
                curl_setopt_array($curl_handles[$id], $options);
            }

            curl_multi_add_handle($multi_handle, $curl_handles[$id]);
        }

        // Execute the handles
        $running = null;
        do {
            curl_multi_exec($multi_handle, $running);
        } while($running > 0);

        // Get content and remove handles
        foreach($curl_handles as $id => $handle) {
            $content = curl_multi_getcontent($handle);
            if ($return_curl_info)
            {
                $results[$id] = array();
                $results[$id]['info'] = curl_getinfo($handle);
                $results[$id]['content'] = $content;
            } else {
                $results[$id] = $content;
            }
            curl_multi_remove_handle($multi_handle, $handle);
        }

        curl_multi_close($multi_handle);

        return $results;
    }

    # This function returns the network settings object from a vApp, with the parent networks rest to null
    function get_vapp_network_settings_object($vapp_id)
    {
        global $service;
        $sdkSource = $service->createSDKObj($this->get_href_from_id($vapp_id));
        $network_config_settings = $sdkSource->getNetworkConfigSettings();
        $configs = $network_config_settings->getNetworkConfig();

        foreach($configs as $config) {
            $configuration = $config->getConfiguration();
            $configuration->setParentNetwork(null);
            $config->setConfiguration($configuration);
        }

        return $network_config_settings;
    }

    # This function returns the startup settings object of a vApp or a vApp template
    function get_vapp_startup_settings_object($vapp_id)
    {
        global $service;
        $vapp_id_updated = str_replace("vapptemplate", "vapp", $vapp_id);
        $sdkSource = $service->createSDKObj($this->get_href_from_id($vapp_id_updated));
        return $sdkSource->getStartupSettings();
    }

    function recompose_vapp($dest_vapp_id = null, $source_vm_id_array = null, $username = null)
    {
        try {
            $orgvdc = $this->get_orgvdc_id_by_vapp($dest_vapp_id);
            $orgvdc_id = $orgvdc[0]['orgvdc_id'];
            $Events = ClassRegistry::init('Events');
            $event_params = array(
                "function_name" => 'recompose_vapp',
                "function_parameters" => "dest_vapp_id=" . $dest_vapp_id . " vms=" . implode(",",$source_vm_id_array),
                "object_vcd_id" => $dest_vapp_id,
                "org_vdc_id" => $orgvdc_id,
                "user_id" => $username
            );
            $Events->create();
            $Events->save($event_params);
            $recompose_vapp_id = $Events->id;

            $this->add_vapp_to_busy_cache($dest_vapp_id, 'Recomposing');
            global $service;
            $dest_vapp_href = $this->get_href_from_id($dest_vapp_id);
            $sdkVApp = $service->createSDKObj($dest_vapp_href);
            $original_dest_vapp_power_state = $this->get_vapp_power_status($dest_vapp_id);
            $vms_in_destination_vapp = $this->list_vms_id_name_and_href($dest_vapp_id);
            $source_vapptemplate_id = $this->get_vapp_id_by_vm($source_vm_id_array[0]);
            $vapp_template_name = $this->get_vapp_template_name($source_vapptemplate_id);
            $vapp_meta = $this->set_metadata_for_id($dest_vapp_id,"vapp.latestTemplate.name",$vapp_template_name) ;
            $source_vapptemplate_href = $this->get_href_from_id($source_vapptemplate_id);
            $sdkVAppTemplate = $service->createSDKObj($source_vapptemplate_href);
            $sdkVAppTemplateStartSettings = $sdkVAppTemplate->getStartupSettings();

            $source_vms_array = array();
            $source_vm_network_settings_array = array();
            $undeploy_tasklist = array();
            $startup_settings_vms_array = array();
            $recompose_params = new VMware_VCloud_API_RecomposeVAppParamsType();

            foreach ($source_vm_id_array as $source_vm_id)
            {
                $source_vm_href = $this->get_href_from_id($source_vm_id);
                $source_vm_name = $this->get_vm_name($source_vm_id);
                $source_vm_ref = VMware_VCloud_SDK_Helper::createReferenceTypeObj($source_vm_href);
                $sdkVMSource = $service->createSDKObj($source_vm_href);
                array_push($startup_settings_vms_array, $source_vm_name);

                # Remember network settings for each VM
                $source_vm_network_settings = $sdkVMSource->getNetworkConnectionSettings();
                $source_vm_network_settings_array[$source_vm_name] = $source_vm_network_settings;

                # Poweroff and delete the original vms
                foreach ($vms_in_destination_vapp as $destination_vm)
                {
                    array_push($startup_settings_vms_array, $destination_vm['name']);

                    if ($destination_vm['name'] === $source_vm_name)
                    {
                        $sdkVMDest = $service->createSDKObj($destination_vm['vm_href']);

                        # Poweroff / undeploy the vm before deleting it
                        if ($this->get_vm_deployed_status($sdkVMDest->getId()))
                        {
                            $undeploy_params = new VMware_VCloud_API_UndeployVAppParamsType();
                            $undeploy_params->setUndeployPowerAction("powerOff");
                            $task = $sdkVMDest->undeploy($undeploy_params);
                            array_push($undeploy_tasklist, $task);
                        }
                        $recompose_params->addDeleteItem($sdkVMDest->getVmRef());
                        break;
                    }
                }

                # Import the vm into the sourceItems list
                $sourced_items_params = new VMware_VCloud_API_SourcedCompositionItemParamType();
                $sourced_items_params->setSource($source_vm_ref);
                $sourced_items_params->set_sourceDelete(false);
                array_push($source_vms_array,$sourced_items_params);
            }

            foreach ($undeploy_tasklist as $task)
            {
                $this->wait_for_task($task);
            }

            # First recompose to delete old VMs
            $task = $sdkVApp->recompose($recompose_params);
            $this->wait_for_task($task);

            # Remove unused items in the templates startup settings to avoid errors in the second recompose
            $original_vm_startup_items = $sdkVAppTemplateStartSettings->getItem();
            $modified_vm_startup_items = array();
            foreach ($original_vm_startup_items as $original_vm_startup_item)
            {
                if (in_array($original_vm_startup_item->get_anyAttributes()['id'], $startup_settings_vms_array))
                {
                    array_push($modified_vm_startup_items, $original_vm_startup_item);
                }
            }

            if (sizeof($original_vm_startup_items) != sizeof($modified_vm_startup_items))
            {
                $sdkVAppTemplateStartSettings->setItem($modified_vm_startup_items);
            }

            # Second recompose to add new VMs
            $recompose_params = new VMware_VCloud_API_RecomposeVAppParamsType();
            $instantiate_params = new VMware_VCloud_API_InstantiationParamsType();
            $instantiate_params->setSection(array($sdkVAppTemplateStartSettings));
            $recompose_params->setInstantiationParams($instantiate_params);
            $recompose_params->setSourcedItem($source_vms_array);
            $task = $sdkVApp->recompose($recompose_params);
            $this->wait_for_task($task);

            # Fix lost mac addresses
            $vms_in_destination_vapp_after_recompose = $this->list_vms_id_name_and_href($dest_vapp_id);
            foreach ($vms_in_destination_vapp_after_recompose as $destination_vm)
            {
                foreach ($source_vm_network_settings_array as $source_vm_name => $source_vm_network_settings)
                {
                    if ($destination_vm['name'] === $source_vm_name)
                    {
                        $sdkVM = $service->createSDKObj($destination_vm['vm_href']);
                        $task = $sdkVM->modifyNetworkConnectionSettings($source_vm_network_settings);
                        $this->wait_for_task($task);
                        break;
                    }
                }
            }
            $this->reset_leases($dest_vapp_id);
            $this->delete_vapp_from_busy_cache($dest_vapp_id);
        }
        catch(Exception $e)
        {
            $this->delete_vapp_from_busy_cache($dest_vapp_id);
            $Events->id = $recompose_vapp_id;
            $event_params = array(
                "value_returned" => 1
            );
            $Events->save($event_params);
            throw $e;
        }
        $Events->id = $recompose_vapp_id;
        $event_params = array(
            "value_returned" => 0
        );
        $Events->save($event_params);

        # Start the vApp if the vApp was originally in a state that is classed as powered onon
        $Vapp = ClassRegistry::init('Vapp');
        $vapp_powered_on_states = $Vapp->get_powered_on_states();
        if (in_array($original_dest_vapp_power_state, $vapp_powered_on_states))
        {
            # Decide whether to start the vApp the fast way or not
            $template_details = $this->get_vapp_template_details($source_vapptemplate_id);
            $source_catalog_name = $template_details['catalog_name'];
            $fast_start_catalogs = $this->get_fast_start_catalogs();
            if (in_array($source_catalog_name, $fast_start_catalogs)) {
                $fast_start = true;
            }
            else {
                $fast_start = false;
            }
            $this->start_vapp($dest_vapp_id,$username,"no", $fast_start);
        }
    }

    function reboot_gateway($vappid = null)
    {
        global $service;
        $vms = $this->list_vms_id_name_and_href($vappid);
        foreach($vms as $vm) {
            if (strstr($vm['name'], "gateway")) {
                $this->reboot_vm($this->get_id_from_href($vm['vm_href'], "vm"));
                break;
            }
        }
    }

    function get_fast_start_catalogs()
    {
        return array(
            "Master-Templates",
            "Templates"
        );
    }

    function vapp_hostname_by_ip($gwipaddress)
    {
        $the_array = array();
        $gwhostname = gethostbyaddr($gwipaddress);
        if (!strstr($gwhostname, "atvts")) {
            $gwhostname = null;
            $gwipaddress = null;
            $gwfqhn = null;
        }
        else {
            $gwfqhn = $gwhostname;
            $gwhostname_split = split('\.', $gwhostname);
            $gwhostname = $gwhostname_split[0];
        }

        array_push($the_array, array(
            "ipaddress" => $gwipaddress,
            "hostname" => $gwhostname,
            "fqhn" => $gwfqhn
        ));
        return $the_array;
    }

    function get_vapp_ipaddress($vappid = null, $usecache = true)
    {

        // $cache_string = ($usecache) ? 'true' : 'false';
        // CakeLog::write('activity', 'In get_vapp_ipaddress with values ' . $vappid . " and usecache" . $cache_string);

        if ($usecache === false || ($value = Cache::read('gateway_ip_vapp_' . $vappid, 'gateway_ip_vapp_cache')) === false) {

            // CakeLog::write('activity', 'Not using the cache');

        }
        else {

            // CakeLog::write('activity', 'Found something in the cache with value ' . $value);

            return $this->vapp_hostname_by_ip($value);
        }

        global $service;
        $vms = $this->list_vms_id_name_and_href($vappid);
        foreach($vms as $vm) {
            if (strstr($vm['name'], "gateway")) {
                $sdkVM = $service->createSDKObj($vm['vm_href']);
                $net = $sdkVM->getNetworkConnectionSettings();
                $cons = $net->getNetworkConnection();
                $gwhostname = 'none';
                foreach($cons as $ncs) {
                    if (!strstr($ncs->getMACAddress() , "00:50:56:00")) {
                        $gwipaddress = $ncs->getIpAddress(null);

                        // CakeLog::write('activity', 'Got its ip address of ' . $gwipaddress);

                        if ($gwipaddress) {
                            $vapp_hostname_details = $this->vapp_hostname_by_ip($gwipaddress);

                            // CakeLog::write('activity', 'The hostname came back as ' . $vapp_hostname_details[0]['hostname']);

                            if ($vapp_hostname_details[0]['hostname'] !== null) {
                                $details = $this->get_vm_power_and_name($vm['vm_href']);

                                // If its powered on, update the latest cache

                                if ($details['status'] === 'POWERED_ON') {

                                    // CakeLog::write('activity', 'Its powered on');

                                    $this->add_gateway_ip_vapp_cache($gwipaddress, $vappid);
                                }

                                return $vapp_hostname_details;
                            }
                        }

                        break;
                    }
                }

                break;
            }
        }

        // If we are refreshing the cache, then clear it out now that theres no valid mapping

        if ($usecache === false) {

            // Invalidate the cache here if we made it this far
            // CakeLog::write('activity', 'Deleting the cache because no valid data was found for gateway_ip_vapp_' . $vappid);

            Cache::delete('gateway_ip_vapp_' . $vappid);
        }

        return null;
    }

    // # Add a new mapping to the cache, between ip and vapp id

    function add_gateway_ip_vapp_cache($input1, $input2)
    {
        $this->delete_gateway_ip_vapp_cache($input1);
        $this->delete_gateway_ip_vapp_cache($input2);

        // CakeLog::write('activity', 'Caching new mapping between ' . $input1 . ' and ' . $input2);

        Cache::write('gateway_ip_vapp_' . $input1, $input2, 'gateway_ip_vapp_cache');
        Cache::write('gateway_ip_vapp_' . $input2, $input1, 'gateway_ip_vapp_cache');
    }

    // # Remove a mapping to the cache, between ip and vapp id

    function delete_gateway_ip_vapp_cache($input)
    {

        // CakeLog::write('activity', 'Invalidating cache for ' . $input);

        if (($prev_value = Cache::read('gateway_ip_vapp_' . $input, 'gateway_ip_vapp_cache')) === false) {
        }
        else {

            // CakeLog::write('activity', 'Previous mapping existed which will be deleted now ' . $input . " and " . $prev_value);

            Cache::delete('gateway_ip_vapp_' . $input);
            Cache::delete('gateway_ip_vapp_' . $prev_value);
        }
    }

    function wait_in_queue($task_name = null)
    {

        // Ask the throttler to put this in the queue and return when ready

        $Throttler = ClassRegistry::init('Throttler');
        $Throttler->wait_in_queue($task_name);
    }

    function task_in_vcloud($task_name = null)
    {

        // Ask the throttler to put this in the queue and return when ready

        $Throttler = ClassRegistry::init('Throttler');
        $Throttler->task_in_vcloud($task_name);
    }

    function get_provider_id_from_orgvdc ($orgvdc_id = null)
    {
        global $service;
        $OrgVdc = ClassRegistry::init('OrgVdc');

        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields("providerVdc");
        $params->setFilter("id==" . $orgvdc_id);
        $type = "adminOrgVdc";
        $recs = $sdkQuery->$qm($type, $params);
        $orgRecords = $recs->getRecord();
        foreach($orgRecords as $orgvdc) {
            return $this->get_id_from_href($orgvdc->get_providerVdc(),"providervdc");
        }
    }

    function deploy_from_catalog($inputs = null, $username, $email)
    {
        // Add an event
        // Figure out the orgvdc
        $orgvdc = $this->get_orgvdc_id_by_name($inputs['destorgvdcname']);
        if (!isset($orgvdc[0])) {
            throw new Exception("Can't seem to find an orgvdc of this name " . $inputs['destorgvdcname']);
        }

        $orgvdc_id = $orgvdc[0]['orgvdc_id'];

        $message = "RetryError: " ;
        $retryAttempts = 0;

        $Events = ClassRegistry::init('Events');
        $deploy_event = ClassRegistry::init('Events');
        $event_params = array(
            "function_name" => 'deploy_from_catalog',
            "function_parameters" => "vapp_template_id=" . $inputs["vapp_template_id"] . " new_vapp_name=" . $inputs['new_vapp_name'] . " linked_clone=" . $inputs['linked_clone'] . " destorgvdcname=" . $inputs['destorgvdcname'] . " start_vapp=" . $inputs['start_vapp'],
            "object_vcd_id" => $inputs["vapp_template_id"],
            "user_id" => $username,
            "org_vdc_id" => $orgvdc_id
        );
        $Events->create();
        $Events->save($event_params);
        $deploy_event_id = $Events->id;

        //this try catch is to save event state
        try
        {
        // Prepare variables for the queued vapp cache

        $date_object = date("Y-m-d H:i:s");

        // Add to the vapp details to the queued cache, so that we can show it while its in the queue

        $queued_vapp_cache_key = $orgvdc_id;
        $queued_vapp_cache_sub_key = $inputs['new_vapp_name'] . strtotime($date_object);
        $cached_value = array (
            'name' => $inputs['new_vapp_name'],
            'creation_date' => $date_object,
            'created_by_id' => $username
        );

        // Figure out if we need to get a lock to prevent deploying into the same cluster twice from the same template simultaneously (vmware bug open)

        $source_provider_id = $this->get_provider_id_from_orgvdc($this->get_orgvdc_id_by_vapptemplate($inputs["vapp_template_id"]));
        $destination_provider_id = $this->get_provider_id_from_orgvdc($orgvdc_id);
        $need_cluster_lock = false;
        $have_cluster_lock = false;

        // Add this vapp into the queued vapp cache, so it appears on the UI earlier

        $this->add_orgvdc_queued_vapp_cache($queued_vapp_cache_key,$queued_vapp_cache_sub_key, $cached_value);

        // If the destination and source provider arn't the same, then we need to get the lock

        if ($destination_provider_id !== $source_provider_id)
        {
            $need_cluster_lock = true;
            $cluster_lock_name = $inputs["vapp_template_id"] . "_" . $destination_provider_id;
        }
        $quota_error = false ;
        $retry = 2;
        $retry_overall = 3;
        for ($y = 1; $y <= $retry_overall; $y++) {
            try {
                $runtimeleaseurl = null;
                $storageleaseurl = null;

                // Put this in the queue

                try {
                    global $service;
                    $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

                    // Get ref to vapp template

                    $vapp_template_href = $this->get_href_from_id($inputs["vapp_template_id"]);
                    $sdk_vapp_template = $service->createSDKObj($vapp_template_href);
                    $vapp_template_reference = $sdk_vapp_template->getVappTemplateRef();
                    $vappparams = new VMware_VCloud_API_InstantiateVAppTemplateParamsType();
                    $vappparams->set_deploy(false);
                    $vappparams->setDescription($sdk_vapp_template->getVAppTemplate()->getDescription());
                    $vappparams->setIsSourceDelete(false);
                    $vappparams->set_linkedClone($inputs["linked_clone"]);
                    $vappparams->set_name($inputs['new_vapp_name']);
                    $vappparams->set_powerOn(false);
                    $vappparams->setSource($vapp_template_reference);

                    // /////////////////////////////////////////////////////
                    // Find the href of the org vdc

                    $qm = "queryRecords";
                    $params = new VMware_VCloud_SDK_Query_Params();
                    $params->setFields("name");
                    $params->setFilter("name==" . urlencode($inputs['destorgvdcname']));
                    $type = "adminOrgVdc";
                    $recs = $sdkQuery->$qm($type, $params);
                    $pvdcRecords = $recs->getRecord();
                    foreach($pvdcRecords as $pvdc) {
                        $vdc_href = str_replace("admin/", "", $pvdc->get_href());
                        break;
                    }

                    $sdkVdc = $service->createSDKObj($vdc_href);

                    for ($x = 1; $x <= $retry; $x++) {
                        try {
                            try {

                                // Add this vapp into the queued vapp cache, so it appears on the UI earlier. Adding again here incase we are retrying and need to keep adding

                                $this->add_orgvdc_queued_vapp_cache($queued_vapp_cache_key,$queued_vapp_cache_sub_key, $cached_value);

                                // Get the lock to prevent simultaneous deploys from a template, to another cluster

                                if ($need_cluster_lock)
                                {
                                    $this->get_vapp_lock($inputs["vapp_template_id"] . "_" . $destination_provider_id, 14400);
                                    $have_cluster_lock=true;
                                }

                                // Put this in the queue

                                $this->wait_in_queue("vdcInstantiateVapp");
                                $vApp = $sdkVdc->instantiateVAppTemplate($vappparams);
                                $new_vapp_id = $vApp->get_id();

                                // Take it out of the queued cache
                                $this->add_vapp_to_busy_cache($new_vapp_id, 'Creating');
                                $this->delete_orgvdc_queued_vapp_cache($queued_vapp_cache_key,$queued_vapp_cache_sub_key);

                                // Inform the queue manager that the task should be in vcloud now

                                $this->task_in_vcloud("vdcInstantiateVapp");
                                break;
                            }

                            catch(Exception $e) {

                                // Clear the cluster lock
                                $message = $message . ",Failed to instantiate Vapp" ;
                                if ($need_cluster_lock && $have_cluster_lock)
                                {
                                    $this->clear_vapp_lock($cluster_lock_name);
                                    $have_cluster_lock = false;
                                }

                                // Take it out of the queued cache

                                $this->delete_orgvdc_queued_vapp_cache($queued_vapp_cache_key,$queued_vapp_cache_sub_key);

                                // Inform the queue manager that the task should be in vcloud now

                                $this->task_in_vcloud("vdcInstantiateVapp");
                                throw $e;
                            }
                        }

                        catch(Exception $e) {
                            $check_exists = "The VCD entity " . $inputs['new_vapp_name'] . " already exists";
                            if (strpos($e, $check_exists) !== FALSE) {
                                $message = $message . ",already exists" ;
                                throw $e;
                            }
                            if($quota_error) {
                                $message = $message . ",cannot create this vapp due to quota" ;
                                throw $e;
                            }

                            $catalog_details = $this->get_vapp_template_details($inputs["vapp_template_id"]);
                            $verbose_details = $username . " was attempting to deploy a new vapp called '" . $inputs['new_vapp_name'] . "' to datacenter '" . $inputs['destorgvdcname'] . "' from vapp template '" . $catalog_details['name'] . "' (" . $inputs["vapp_template_id"] . ") from catalog '" . $catalog_details["catalog_name"] . "'";
                            $message = $message . ",failed to instantiate vapp" ;
                            $retryAttempts++ ;
                            $this->report_exception('instantiateVAppTemplate', $e, $x, $retry, $verbose_details);
                            if ($x == $retry) {

                                // Here we also want to make sure that mail is sent with failure ..
                                if (isset($new_vapp_id)) {
                                    $this->delete_vapp_from_busy_cache($new_vapp_id);
                                }
                                throw $e;
                            }
                        }
                    }

                    // $vApp = $service->refetch($vApp);

                    $Vapp = ClassRegistry::init('Vapp');
                    $params = array(
                        "vcd_id" => $new_vapp_id,
                        "created_by_id" => $username,
                        "name" => $inputs['new_vapp_name'],
                        "org_vdc_id" => $orgvdc_id
                    );
                    $Vapp->create();
                    try {
                        $Vapp->save($params);

                        // Attach Leases to the vApp -START

                        if (isset($email) && $email != "") {
                            $DCNoLease = Configure::read('DCNoLease');
                            if (!in_array($inputs['destorgvdcname'], $DCNoLease)) {
                                $vappdetails = $Vapp->find('first', array(
                                    'conditions' => array(
                                        'Vapp.vcd_id' => $Vapp->id
                                    ) ,
                                    'recursive' => - 1
                                ));
                                $Yama = Configure::read('Yama');
                                $spp_hostname = strtok(shell_exec('hostname -f'), "\n");
                                $url = 'http://' . $spp_hostname . ':8080' . $Yama['VAPP'];
                                $runtimeLease = array(
                                    "Lease" => array(
                                        "machine_type_id" => "2",
                                        "host_id" => $vappdetails['Vapp']['id'],
                                        "lease_type_id" => "2",
                                        "remainders" => "1",
                                        "emails" => $email
                                    )
                                );
                                $storageLease = array(
                                    "Lease" => array(
                                        "machine_type_id" => "2",
                                        "host_id" => $vappdetails['Vapp']['id'],
                                        "lease_type_id" => "1",
                                        "remainders" => "1",
                                        "emails" => $email
                                    )
                                );

                                // Runtime Lease

                                Configure::load('user_details');
                                $user_details = Configure::read('yama');
                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, $url);
                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                                curl_setopt($ch, CURLOPT_USERPWD, $user_details['username'] . ':' . $user_details['password']);
                                curl_setopt($ch, CURLOPT_PROXY, '');
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                                    'Content-Type: application/json',
                                ));
                                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($runtimeLease));
                                $content = curl_exec($ch);
                                $response = curl_getinfo($ch);
                                curl_close($ch);
                                $resout = json_decode($content);

                                if ($response['http_code'] == 200) $runtimeleaseurl = $resout->{'url'};
                                else CakeLog::write('error', 'Problem in attaching the Runtime Lease for the vApp' . $response['http_code'] . 'content' . $content);

                                // Storage Lease

                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, $url);
                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                                curl_setopt($ch, CURLOPT_USERPWD, $user_details['username'] . ':' . $user_details['password']);
                                curl_setopt($ch, CURLOPT_PROXY, '');
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                                    'Content-Type: application/json',
                                ));
                                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($storageLease));
                                $content = curl_exec($ch);
                                $response = curl_getinfo($ch);
                                curl_close($ch);
                                $resout = json_decode($content);

                                if ($response['http_code'] == 200) $storageleaseurl = $resout->{'url'};
                                else CakeLog::write('error', 'Problem in attaching the Storage Lease for the vApp' . $response['http_code'] . 'content' . $content);
                            }
                        } //Attach Leases to the vApp -END
                    }

                    catch(Exception $e) {
                        $message = $message . ",failed saving deploy" ;
                        CakeLog::error("ERROR: Was trying to save the deploy_from_catalog created_by_id but there was an exception, here it is: " . $e);
                    }

                    // Wait for the task to finish

                    $tasks = $vApp->getTasks()->getTask();
                    if ($tasks) {
                        $task = $tasks[0];
                        try {
                            $this->wait_for_task($task);
                        }

                        catch(Exception $e) {
                            if (strpos($e, 'validation error : may not be null') !== FALSE) {
                                // Try for 2 minutes to see does it come out of UNRESOLVED state and continue
                                for ($x = 0; $x <= 60; $x++) {

                                    // Check if its no longer in the UNRESOLVED state, if so recheck the wait for task
                                    $power_status = $this->get_vapp_power_status($new_vapp_id);
                                    if ($power_status != "UNRESOLVED") {
                                        $this->wait_for_task($task);
                                        break;
                                    }

                                    sleep(2);
                                }
                            }
                            else {
                                throw $e;
                            }
                        }
                    }
                    $this->wait_for_power_state($new_vapp_id, "POWERED_OFF");

                    // Clear the cluster lock

                    if ($need_cluster_lock && $have_cluster_lock)
                    {
                        $this->clear_vapp_lock($cluster_lock_name);
                        $have_cluster_lock = false;
                    }
                    break;
                } // end of block try
                catch(Exception $e) {
                    // Clear the cluster lock
                    $message = $message . ",deploy from catalog failure" ;
                    if ($need_cluster_lock && $have_cluster_lock)
                    {
                        $this->clear_vapp_lock($cluster_lock_name);
                        $have_cluster_lock = false;
                    }

                    // Inform the queue manager that the task should be in vcloud now
                    // echo $e;

                    throw $e;
                }
            } // end of try for deploy_from_catalog
            catch(Exception $e) {
                $check_exists = "The VCD entity " . $inputs['new_vapp_name'] . " already exists";
                if (strpos($e, $check_exists) !== FALSE) {
                    throw $e;
                }
                $retryAttempts++;
                if (isset($new_vapp_id)) {
                    $catalog_details = $this->get_vapp_template_details($inputs["vapp_template_id"]);
                    $verbose_details = $username . " was attempting to deploy a new vapp called '" . $inputs['new_vapp_name'] . "' to datacenter '" . $inputs['destorgvdcname'] . "' from vapp template '" . $catalog_details['name'] . "' (" . $inputs["vapp_template_id"] . ") from catalog '" . $catalog_details["catalog_name"] . "'";
                    $this->report_exception('deploy_from_catalog', $e, $y, $retry_overall, $verbose_details);
                }

                if ($y == ($retry_overall - 1)) {

                    // Wait before retrying the third time

                    CakeLog::write('retry', 'Sleeping now for 60 seconds before retrying again');
                    sleep(60);
                }

                if ($y == $retry_overall) {

                    // Take it out of the queued cache

                    $this->delete_orgvdc_queued_vapp_cache($queued_vapp_cache_key,$queued_vapp_cache_sub_key);

                    // Here we also want to make sure that mail is sent with failure ..
                    if (isset($new_vapp_id)) {
                        $this->delete_vapp_from_busy_cache($new_vapp_id);
                    }
                    throw $e;
                }
                else {
                    try {
                        // Cleanup before retry
                        if (isset($new_vapp_id)) {
                            $this->delete_vapp($new_vapp_id, "admin-retry");
                            $new_vapp_id = null;
                        }
                    }
                    catch(Exception $deleteEx) {
                        // Take it out of the queued cache
                        $this->delete_orgvdc_queued_vapp_cache($queued_vapp_cache_key,$queued_vapp_cache_sub_key);
                        // Throw the original Exception back and NOT the $deleteEx
                        $this->delete_vapp_from_busy_cache($new_vapp_id);
                        $catalog_details = $this->get_vapp_template_details($inputs["vapp_template_id"]);
                        $verbose_details = $username . " was attempting to delete a failed vapp called '" . $inputs['new_vapp_name'] . "' to datacenter '" . $inputs['destorgvdcname'] . "' from vapp template '" . $catalog_details['name'] . "' (" . $inputs["vapp_template_id"] . ") from catalog that didn't succeed '" . $catalog_details["catalog_name"] . "'";
                        $message = $message . ",failed to instantiate vapp" ;
                        $retryAttempts++ ;
                        $this->report_exception('delete on a failed vapp', $e, 1, 1, $verbose_details);
                        throw $e;
                    }
                }
            }
        } // end of for loop(y) of retries
        for ($x = 1; $x <= $retry; $x++) {
            try {
                $this->update_org_network_gateway($new_vapp_id, $username);
                break;
            }

            catch(Exception $e) {
                $catalog_details = $this->get_vapp_template_details($inputs["vapp_template_id"]);
                $verbose_details = $username . " was attempting to deploy a new vapp called '" . $inputs['new_vapp_name'] . "' to datacenter '" . $inputs['destorgvdcname'] . "' from vapp template '" . $catalog_details['name'] . "' (" . $inputs["vapp_template_id"] . ") from catalog '" . $catalog_details["catalog_name"] . "'";
                $message = $message . ",failed updating org network" ;
                $retryAttempts++;
                $this->report_exception('update_org_network', $e, $x, $retry, $verbose_details);
                // Here we also want to make sure that mail is sent with failure ..
                if($x == $retry)
                {
                    $this->delete_vapp_from_busy_cache($new_vapp_id);
                    throw $e;
                }
              }
           }

        $this->cleanup_vapp_networks($new_vapp_id);
        for ($x = 1; $x <= $retry; $x++) {
            try {
                $this->reset_mac_gateway($new_vapp_id, $username);
                break;
            }

            catch(Exception $e) {
                $message = $message . ",failed to reset mac gateway" ;
                $catalog_details = $this->get_vapp_template_details($inputs["vapp_template_id"]);
                $verbose_details = $username . " was attempting to deploy a new vapp called '" . $inputs['new_vapp_name'] . "' to datacenter '" . $inputs['destorgvdcname'] . "' from vapp template '" . $catalog_details['name'] . "' (" . $inputs["vapp_template_id"] . ") from catalog '" . $catalog_details["catalog_name"] . "'";
                $retryAttempts++ ;
                $this->report_exception('reset_mac_gateway', $e, $x, $retry, $verbose_details);
                if ($x == $retry) {
                    // Here we also want to make sure that mail is sent with failure ..
                    $this->delete_vapp_from_busy_cache($new_vapp_id);
                    throw $e;
                }
            }
        }
        }
        catch(Exception $e)
        {
            $Events->id = $deploy_event_id;
            $event_params = array(
                "message" => $message,
                "value_returned" => 1,
                "retries" => $retryAttempts
            );
            $Events->save($event_params);
            throw $e;
        }
        $Events->id = $deploy_event_id;
        $event_params = array(
            "message" => $message,
            "value_returned" => 0,
            "retries" => $retryAttempts
        );
        $Events->save($event_params);

        $gateway_details = null;
        if ($inputs['start_vapp'] == "yes") {
            $fast_start_catalogs = $this->get_fast_start_catalogs();
            if (in_array($this->get_vapp_template_details($inputs["vapp_template_id"]) ['catalog_name'], $fast_start_catalogs)) {
                $fast_start = true;
            }
            else {
                $fast_start = false;
            }
            try{
                 $gateway_details = $this->start_vapp($new_vapp_id, $username, "yes", $fast_start);
                 $this->add_vapp_to_busy_cache($new_vapp_id, 'Creating');
            }
            catch(Exception $e){
                $this->delete_vapp_from_busy_cache($new_vapp_id);
                throw $e;
            }
        }

        $this->delete_vapp_from_busy_cache($new_vapp_id);
        $return_params = array(
            "vapp_id" => $new_vapp_id,
            "gateway_details" => $gateway_details,
            "runtimeleaseurl" => $runtimeleaseurl,
            "storageleaseurl" => $storageleaseurl);

        return $return_params;
    } // end of function deploy_from_catalog

    // One cache per orgvdc. Each cache is an array of objects containing the name, owner etc of the vapp in the cache.

    function add_orgvdc_queued_vapp_cache($key, $sub_key, $new_value)
    {
        // Get a lock to prevent simultaneous access to the same cache

        $this->get_vapp_lock("orgvdc_queued_vapp_cache_lock_" . $key, 120);

        // If theres nothing in the cache yet, create an empty array to start with

        if (($value = Cache::read($key, 'orgvdc_queued_vapp_cache')) === false) {
            $value = array();
        }

        // Add a value to the array

        $value[$sub_key] = $new_value;
        Cache::write($key, $value, 'orgvdc_queued_vapp_cache');

        // Remove the lock

        $this->clear_vapp_lock("orgvdc_queued_vapp_cache_lock_" . $key);
    }

    function delete_orgvdc_queued_vapp_cache($key,$sub_key)
    {
        // Get a lock to prevent simultaneous access to the same cache

        $this->get_vapp_lock("orgvdc_queued_vapp_cache_lock_" . $key, 120);

        // If the cache isnt empty, unset the key

        if (($value = Cache::read($key, 'orgvdc_queued_vapp_cache')) === false) {
        } else {
            unset ($value[$sub_key]);
        }

        // Figure out whether to delete the cache altogether or write the updated value

        if ($value === false || sizeof($value) === 0)
        {
            Cache::delete($key,'orgvdc_queued_vapp_cache');
        }
        else {
            Cache::write($key, $value, 'orgvdc_queued_vapp_cache');
        }

        // Remove the lock

        $this->clear_vapp_lock("orgvdc_queued_vapp_cache_lock_" . $key);
    }

    function add_vapp_to_busy_cache($key, $status){
        // Checking the cache
        Cache::write($key,  $status, 'busy_vapp_cache');
    }

    function delete_vapp_from_busy_cache($key){
        // Checking the cache
        if (($value = Cache::read($key, 'busy_vapp_cache')) !== false) {
            Cache::delete($key,'busy_vapp_cache');
        }

    }

    function get_vapp_busy_status($key){
        $status = false;
        // Checking the cache
        if (($value = Cache::read($key, 'busy_vapp_cache')) !== false) {
             $status = $value;
        }
        return $status;
    }

    function get_vm_busy_status($key){
        $status = false;
        // Checking the cache
        if (($value = Cache::read($key, 'busy_vapp_cache')) !== false) {
             $status = $value;
        }
        return $status;
    }

    function is_vapp_or_any_vm_busy($vappID)
    {
        $status = $this->get_vapp_busy_status($vappID);
        if($status !== false)
        {
            return $status ;
        }

        $vms = $this->list_vms_id($vappID) ;
        foreach($vms as $vm)
        {
            $status = $this->get_vm_busy_status($vm['vm_id']) ;

            if($status !== false)
            {
                return $status ;
            }
        }
        return false ;
    }

    function is_vapp_or_vm_busy($vmID)
    {
        $vappID = $this->get_vapp_id_by_vm($vmID);
        $status = $this->get_vapp_busy_status($vappID) ;
        if($status !== false)
        {
            return $status ;
        }

        $status = $this->get_vm_busy_status($vmID) ;
        if($status !== false)
        {
            return $status ;
        }
        return false ;
    }

    function add_vapp_to_catalog($inputs = null, $username = null, $email)
    {
        try
        {
            $this->add_vapp_to_busy_cache($inputs["vapp_id"], 'Adding To Catalog');
            $vapp_details = $this->add_vapp_to_catalog_internal($inputs, $username, $email);
            $this->delete_vapp_from_busy_cache($inputs["vapp_id"]);
        } catch(Exception $e) {
            $this->delete_vapp_from_busy_cache($inputs["vapp_id"]);
            throw $e;
        }

        try {
            $pod_sync_config_loaded = false;
            try {
                Configure::load('pod_sync');
                $pod_sync_definitions = Configure::read('pod_sync_definitions');
                $pod_sync_config_loaded = true;
            } catch (Exception $e)
            {
            }
            if ($pod_sync_config_loaded)
            {
                if (isset($pod_sync_definitions[$inputs['dest_catalog_name']])) {
                    Configure::load('user_details');
                    $user_details = Configure::read('rest_call');
                    exec("curl -i -u " . $user_details['username'] . ":" . $user_details['password'] . " --insecure " . $user_details['url'] . "/VappTemplates/sync_vapp_template_to_other_pods_api/vapp_template_id:" . $vapp_details['tempid'] . "/.xml > /dev/null 2>&1 &");
                }
            }
        } catch(Exception $e) {
            CakeLog::write('debug', 'There was an exception when syncing into other pods. Heres the exception ' . $e);
        }

        try {
            Configure::load('catalog_sync');
            $catalog_sync_definitions = Configure::read('catalog_sync_definitions');
            if (isset($catalog_sync_definitions[$inputs['dest_catalog_name']])) {
                foreach($catalog_sync_definitions[$inputs['dest_catalog_name']] as $catalog_sync_definition) {
                    try {
                        Configure::load('user_details');
                        $user_details = Configure::read('rest_call');
                        exec("curl -i -u " . $user_details['username'] . ":" . $user_details['password'] . " --insecure " . $user_details['url'] . "/VappTemplates/deploy_api/vapp_template_id:" . $vapp_details['tempid']  . "/new_vapp_name:sync_" . $inputs['new_vapp_template_name'] . "/datacenter:" . $catalog_sync_definition . "/poweron:no/linked_clone:true/.xml > /dev/null 2>&1 &");
                    } catch(Exception $e) {
                       CakeLog::write('debug', 'There was an exception when deploying this vapp to the sync datacenter, moving on to the next datacenter. Heres the exception ' . $e);

                    }
                }
            }
        } catch(Exception $e) {
            CakeLog::write('debug', 'There was an exception when syncing into datacenters. Heres the exception ' . $e);
        }
        return $vapp_details;
    }

    function add_vapp_to_catalog_internal($inputs = null, $username = null, $email)
    {
        $power_status = $this->get_vapp_power_status($inputs["vapp_id"]);
        if ($power_status !== "POWERED_OFF")
        {
            throw new Exception("The vApp must be in a POWERED_OFF state before it can be added to the catalog");
        }
        $retryAttempts = 0;
        // Add an event
        $message = "RetryError: " ;
        $orgvdc = $this->get_orgvdc_id_by_vapp($inputs["vapp_id"]);
        $orgvdc_id = $orgvdc[0]['orgvdc_id'];
        $Events = ClassRegistry::init('Events');
        $event_params = array(
            "function_name" => 'add_vapp_to_catalog',
            "function_parameters" => "vapp_id=" . $inputs["vapp_id"] . " new_vapp_template_name=" . $inputs['new_vapp_template_name'] . " dest_catalog_name=" . $inputs['dest_catalog_name'],
            "object_vcd_id" => $inputs["vapp_id"],
            "user_id" => $username,
            "org_vdc_id" => $orgvdc_id,
        );
        $Events->create();
        $Events->save($event_params);
        $add_vapp_id = $Events->id;
        $leaseurl = null;
        global $service;
        try {
            // Check if a vapp template of this name already exists in the catalog
            $url_encoded_catalog = urlencode($inputs['dest_catalog_name']);
            $org = $this->get_orgid_from_catalog($url_encoded_catalog);
            $admin_catalog_params = array(
                'type' => "adminVAppTemplate",
                'fields' => array('name'),
                'sortDesc' => 'creationDate',
                'filter' => "isExpired==false;org==" . $org[0]['org_id'] . ";(catalogName==" . $url_encoded_catalog . ",isInCatalog==false)"
            );
            $vapptemplates = $this->query_service_request($admin_catalog_params);
            foreach ($vapptemplates as $vapptemplate)
            {
                if ($vapptemplate['name'] === $inputs['new_vapp_template_name'])
                {
                    $message = $message . ",Vapp template with name exists" ;
                    throw new Exception("ERROR: A vapp template with this name '" . $inputs['new_vapp_template_name'] . "' already exists");
                }
            }
            $this->cleanup_vapp_networks($inputs["vapp_id"]);

            // Find the original vapp
            $vapp_href = $this->get_href_from_id($inputs['vapp_id']);
            $vappSDK = $service->createSDKObj($vapp_href);
            $vdcSDK = $service->createSDKObj($vappSDK->getVdcRef());

            // Find the destination catalog
            $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
            $qm = "queryRecords";
            $params = new VMware_VCloud_SDK_Query_Params();
            $params->setFields("name");
            $params->setFilter("name==" . $url_encoded_catalog);
            $type = "adminCatalog";
            $recs = $sdkQuery->$qm($type, $params);
            $pvdcRecords = $recs->getRecord();
            foreach($pvdcRecords as $pvdc) {
                $dest_catalog_href = $pvdc->get_href();
                break;
            }

            if (!isset($dest_catalog_href)) {
                throw new Exception("Couldn't seem to find the catalog called " . $inputs['dest_catalog_name']);
            }

            $catalogSDK = $service->createSDKObj($dest_catalog_href);

                // Create the params
            $captureparams = new VMware_VCloud_API_CaptureVAppParamsType();
            $captureparams->setSource($vappSDK->getVAppRef());
            $captureparams->set_name($inputs['new_vapp_template_name']);
            $captureparams->setDescription($vappSDK->getVApp()->getDescription());
            $retry = 3;
            for ($x = 1; $x <= $retry; $x++) {
                // Outer retry try catch
                try {
                    try {
                        // Put this in the queue
                        $this->wait_in_queue("vdcCaptureTemplate");
                        $new_vapp_template = $vdcSDK->captureVApp($captureparams);
                        // Inform the queue manager that the task should be in vcloud now
                        $this->task_in_vcloud("vdcCaptureTemplate");
                    }
                    catch(Exception $e) {
                        // Inform the queue manager that the task should be in vcloud now
                        $this->task_in_vcloud("vdcCaptureTemplate");
                        throw $e;
                    }

                    $tasks = $new_vapp_template->getTasks()->getTask();
                    if ($tasks) {
                        $task = $tasks[0];
                        $this->wait_for_task($task);
                    }
                    break;
                } catch (Exception $exception)
                {
                    $message = $message . ", Couldn't add vApp to catalog";
                    $verbose_details = $username . " was attempting to add a vApp called '" . $vappSDK->getVApp()->get_name() . "' (" . $inputs["vapp_id"] .") in datacenter '" . $vappSDK->getVdc()->get_name() . "' to catalog '" . $inputs['dest_catalog_name'] . "' as '" . $inputs['new_vapp_template_name'] . "'";
                    $retryAttempts++;
                    $this->report_exception('add_vapp_to_catalog', $exception, $x, $retry, $verbose_details);
                    if ($x == ($retry- 1)) {
                        // Wait before retrying the third time
                        CakeLog::write('retry', 'Sleeping now for 60 seconds before retrying add_vapp_to_catalog again');
                        sleep(60);
                    }
                    if ($x == $retry) {
                        throw $exception;
                    }
                }
            }

            // Add it to the destination catalog now

            $catalog_item_type = new VMware_VCloud_API_CatalogItemType();
            $catalog_item_type->setDescription($vappSDK->getVApp()->getDescription());
            $catalog_item_type->setEntity(VMware_VCloud_SDK_Helper::createReferenceTypeObj($new_vapp_template->get_href()));
            $catalog_item_type->set_name($inputs['new_vapp_template_name']);
            $new_catalog_item = $catalogSDK->addCatalogItem($catalog_item_type);

            if (isset($email) && $email != "") {
                // Attach Leases to the vAppTemplate -START

                $CATNoLease = Configure::read('CATNoLease');
                if (!in_array($inputs['dest_catalog_name'], $CATNoLease)) {
                    $Yama = Configure::read('Yama');

                    // $url = 'http://atvcloud2.athtem.eei.ericsson.se:9011/leases/addhost.json';

                    $spp_hostname = strtok(shell_exec('hostname -f'), "\n");
                    $url = 'http://' . $spp_hostname . ':8080' . $Yama['CATVAPP'];

                    $storageLease = array(
                        "Lease" => array(
                            "machine_type_id" => "4",
                            "host" => array(
                                "Vapptemplate" => array(
                                    "name" => $new_vapp_template->get_name() ,
                                    "templateid" => $new_vapp_template->get_id()
                                )
                            ) ,
                            "lease_type_id" => "4",
                            "remainders" => "1",
                            "emails" => $email
                        )
                    );
                    Configure::load('user_details');
                    $user_details = Configure::read('yama');
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                    curl_setopt($ch, CURLOPT_USERPWD, $user_details['username'] . ":" . $user_details['password']);
                    curl_setopt($ch, CURLOPT_PROXY, '');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        'Content-Type: application/json',
                    ));
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($storageLease));
                    $content = curl_exec($ch);
                    $response = curl_getinfo($ch);
                    curl_close($ch);
                    $resout = json_decode($content);

                    if ($response['http_code'] == 200) $leaseurl = $resout->{'url'};
                    else CakeLog::write('error', 'Problem in checking the vapptemplate exist' . $response['http_code'] . 'content' . $content);
                }
                    // Attach Leases to the vAppTemplate -END

            }
        }
        catch(Exception $e)
        {
            $message = $message . ",failed to add to catalog" ;
            $Events->id = $add_vapp_id;
            $event_params = array(
                    "value_returned" => 1,
                    "message" => $message,
                    "retries" => $retryAttempts
            );
            $Events->save($event_params);
            throw $e ;

        }

        $Events->id = $add_vapp_id;
        $event_params = array(
            "value_returned" => 0,
            "message" => $message,
            "retries" => $retryAttempts
        );
        $Events->save($event_params);
        $catvappdetails = array(
            'tempid' => $new_vapp_template->get_id() ,
            'leaseurl' => $leaseurl
        );

        // return $new_vapp_template->get_id();

        return $catvappdetails;
    }

    function wait_for_task($task)
    {
        global $service;
        $result = $service->waitForTask($task, 200000, 1);
        $error_object = $result->getError();
        if ($error_object) {
            $error_output = "An error occured during a vcloud task, see output below." . "\n" . $error_object->get_message() . "\n";
            throw new Exception($error_output);
        }

        /*
        $result->get_operationName()
        $result->get_status()
        $result->get_tagName()
        $result->get_operation()
        $result->getProgress()
        $result->getParams()
        $result->getDescription()
        $error_object->get_majorErrorCode()
        $error_object->get_message()
        $error_object->get_minorErrorCode()
        $error_object->get_stackTrace()
        $error_object->get_tagName()
        $error_object->get_vendorSpecificErrorCode()
        */
    }

    function get_href_from_id($vcloud_id)
    {
        global $service;
        global $vcd_config;
        $entity_url = "https://" . $vcd_config['hostname'] . "/api/entity";
        $entity = $service->get($entity_url . "/" . $vcloud_id, "", true);

        // $entity = $service->get(","",true);
        // echo $entity->get_id();
        // echo $entity->get_name();
        // echo "</br>";
        // echo $entity->get_id();
        // echo "</br>";
        // echo $entity->get_tagName();
        // echo "</br>";

        $links = $entity->getLink();
        foreach($links as $link) {
            return $link->get_href();

            // echo "</br>";
            // echo $link->get_type();
            // echo "</br>";
            // echo $link->get_name();
            // echo "</br>";
            // echo $link->get_id();
            // echo "</br>";
            // echo $link->get_rel();
            // echo "</br>";
            // echo $link->get_tagName();
            // echo "</br>";

        }
    }

    function get_vapp_lock($vapp_id = null, $timeout = 600)
    {
        $app_path = dirname(APP) . "/" . basename(APP);
        $LOCKFILE = "files/locks/vapp_locks/" . $vapp_id;
        $cmd = $app_path . "/webroot/Locker/get_lock.sh -f " . $LOCKFILE . " -p 1234 -t $timeout -r yes 2>&1";
        $output = shell_exec($cmd);
    }

    function clear_vapp_lock($vapp_id = null)
    {
        $app_path = dirname(APP) . "/" . basename(APP);
        $LOCKFILE = "files/locks/vapp_locks/" . $vapp_id;
        $cmd = $app_path . "/webroot/Locker/clear_lock.sh -f " . $LOCKFILE . " -p 1234";
        $output = shell_exec($cmd);
    }

    function poweron_vm($vm_id = null, $username = null, $reboot_gateway_if_necessary = "no")
    {
        $result = $this->get_vm_power_and_name($vm_id);
        if ($result['status'] != 'POWERED_ON') {
            $retry = 3;
            for ($x = 1; $x <= $retry; $x++) {
                try {
                    $this->add_vapp_to_busy_cache($vm_id, 'Starting VM');
                    // Start the actual power on attempt
                    # sleep for testcase goes here #
                    $this->poweron_vm_internal($vm_id, $username, $reboot_gateway_if_necessary);
                    break;
                }
                catch(Exception $e) {
                    $this->delete_vapp_from_busy_cache($vm_id);
                    $insufficient_resources = "resources in the parent resource pool are insufficient for the operation";
                    if (strpos($e, $insufficient_resources) !== FALSE) {
                        throw $e;
                    }
                    if(strpos($e, "Cannot start this vm as doing so would exceed the runtime quota") !== FALSE) {
                        throw $e;
                    }
                    $vm_details = $this->get_vm_power_and_name($vm_id);
                    $vapp_id = $this->get_vapp_id_by_vm($vm_id);
                    $vapp_name = $this->get_vapp_name($vapp_id);
                    $orgvdc_name = $this->get_orgvdc_name_by_vapp($vapp_id);
                    $verbose_details = $username . " was attempting to power on a vm called '" . $vm_details['name'] . "' (" . $vm_id .") in vapp '" . $vapp_name . "' (" . $vapp_id . ") in datacenter '" . $orgvdc_name . "'";
                    $this->report_exception('poweron_vm', $e, $x, $retry, $verbose_details);
                    if ($x == ($retry- 1)) {

                        // Wait before retrying the third time

                        CakeLog::write('retry', 'Sleeping now for 60 seconds before retrying poweron_vm again');
                        sleep(60);
                    }
                    if ($x == $retry) {

                        // Here we also want to make sure that mail is sent with failure ..

                        throw $e;
                    }
                }
            }
            try{
               $this->set_security_policy_vm($vm_id);
               $this->delete_vapp_from_busy_cache($vm_id);
            }
            catch(Exception $e)
            {
               $this->delete_vapp_from_busy_cache($vm_id);
               throw $e ;
            }
        }
        else {
               $this->delete_vapp_from_busy_cache($vm_id);
        }
    }

    function poweron_vm_internal($vm_id = null, $username = null, $reboot_gateway_if_necessary = "no")
    {
        $vm_id = $this->vsphere_name_to_id($vm_id);

        // Get the power status and name using the query service

        $result = $this->get_vm_power_and_name($vm_id);
        if ($result['status'] != 'POWERED_ON') {
            global $service;

            // Start of Runtime Quota Checks
            // Get the vApp belonging to this vm

            $vapp_id = $this->get_vapp_id_by_vm($vm_id);
            $vapp_power_status = $this->get_vapp_power_status($vapp_id) ;
            $clear_lock_needed = false ;
            $vm_href = $this->get_href_from_id($vm_id);
            $sdkVm = $service->createSDKObj($vm_href);
            try {

                // Get a lock file so that multiple operations that might make the vapp busy, don't happen at the same time
                if($vapp_power_status == "POWERED_OFF"){
                    $this->get_vapp_lock($vapp_id);
                    $clear_lock_needed = true ;
                }
                try {

                    // Put this in the queue

                    $this->wait_in_queue("vappDeploy");
                    $task = $sdkVm->powerOn();

                    // Inform the queue manager that the task should be in vcloud now

                    $this->task_in_vcloud("vappDeploy");
                }

                catch(Exception $e) {

                    // Inform the queue manager that the task should be in vcloud now

                    $this->task_in_vcloud("vappDeploy");
                    throw $e;
                }

                $this->wait_for_task($task);
                if($clear_lock_needed)
                {
                    $this->clear_vapp_lock($vapp_id);
                }
            }

            catch(Exception $e) {
                if($clear_lock_needed)
                {
                    $this->clear_vapp_lock($vapp_id);
                }
                throw $e;
            }
        }
        $this->wait_for_power_state($vm_id, "POWERED_ON");
        if (strstr($result['name'], "gateway")) {
            if (!isset($vapp_id)) {
                $vapp_id = $this->get_vapp_id_by_vm($vm_id);
            }

            $saved_vapp = ClassRegistry::init('Vapp');
            return $saved_vapp->network($vapp_id, true, $reboot_gateway_if_necessary);
        }
    }

    function delete_vm($vm_id = null, $username = null)
    {
        $vm_id = $this->vsphere_name_to_id($vm_id);
        $this->add_vapp_to_busy_cache($vm_id, 'Deleting VM');
        global $service;

        $orgvdc = $this->get_orgvdc_id_by_vm($vm_id);
        $orgvdc_id = $orgvdc[0]['orgvdc_id'];
        $vapp_id = $this->get_vapp_id_by_vm($vm_id);
        $orgvdc_name = $this->get_orgvdc_name_by_vapp($vapp_id);

        $Events = ClassRegistry::init('Events');
        $event_params = array(
            "function_name" => 'delete_vm',
            "function_parameters" => "destorgvdcname=" . $orgvdc_name . " " . $vapp_id . " " . $vm_id,
            "object_vcd_id" => $vm_id,
            "org_vdc_id" => $orgvdc_id,
            "user_id" => $username,
         );
        $Events->create();
        $Events->save($event_params);
        $delete_vm_id = $Events->id;
        $vm_href = $this->get_href_from_id($vm_id);
        $sdkVm = $service->createSDKObj($vm_href);
        $status = $this->get_vm_power_status($vm_id);
        if($status == "PARTIALLY_POWERED_OFF"){
          try{
             try{
                 $params = new VMware_VCloud_API_UndeployVAppParamsType();
                 $params->setUndeployPowerAction("default");
                 // Put this in the queue
                 $this->wait_in_queue("jobUndeploy");
                 $task = $sdkVm->undeploy($params);

                 // Inform the queue manager that the task should be in vcloud now
                 $this->task_in_vcloud("jobUndeploy");
             }
             catch(Exception $e) {
                 $this->task_in_vcloud("jobUndeploy");
                 throw $e;
             }
             $this->wait_for_task($task);
          }catch(Exception $e) {
                $Events->id = $delete_vm_id;
                $event_params = array(
                  "value_returned" => 1,
                  "message" => "Delete VM Failure - Error During Undeploy"
                );
               $Events->save($event_params);
               $this->delete_vapp_from_busy_cache($vm_id);
               throw $e;
          }
        }
        try{
            try {

               // Put this in the queue
               $this->wait_in_queue("vdcRecomposeVapp");
               $task = $sdkVm->delete();

               // Inform the queue manager that the task should be in vcloud now
               $this->task_in_vcloud("vdcRecomposeVapp");
           }
            catch(Exception $e) {
             $this->task_in_vcloud("vdcRecomposeVapp");
             throw $e;

           }
          $this->wait_for_task($task);
        }catch(Exception $e) {
             $Events->id = $delete_vm_id;
             $event_params = array(
                "value_returned" => 1,
                "message" => "Delete VM Failure - Error During Deletion"
             );
             $Events->save($event_params);
             $this->delete_vapp_from_busy_cache($vm_id);
             throw $e;
         }
        $Events->id = $delete_vm_id;
        $event_params = array(
            "value_returned" => 0
        );
        $Events->save($event_params);
        $this->delete_vapp_from_busy_cache($vm_id);
    }

    function poweroff_vm($vm_id = null, $username = null)
    {
        $retry = 3;
        for ($x = 1; $x <= $retry; $x++) {
            try {
                $this->add_vapp_to_busy_cache($vm_id, 'Powering Off VM');
                // Start the actual power off attempt
                # sleep for testcase goes here #
                $this->poweroff_vm_internal($vm_id, $username);
                $this->delete_vapp_from_busy_cache($vm_id);
                break;
            }

            catch(Exception $e) {
                $this->delete_vapp_from_busy_cache($vm_id);
                $vm_details = $this->get_vm_power_and_name($vm_id);
                $vapp_id = $this->get_vapp_id_by_vm($vm_id);
                $vapp_name = $this->get_vapp_name($vapp_id);
                $orgvdc_name = $this->get_orgvdc_name_by_vapp($vapp_id);
                $verbose_details = $username . " was attempting to power off a vm called '" . $vm_details['name'] . "' (" . $vm_id .") in vapp '" . $vapp_name . "' (" . $vapp_id . ") in datacenter '" . $orgvdc_name . "'";
                $this->report_exception('poweroff_vm', $e, $x, $retry, $verbose_details);
                if ($x == ($retry- 1)) {

                    // Wait before retrying the third time

                    CakeLog::write('retry', 'Sleeping now for 60 seconds before retrying poweroff_vm again');
                    sleep(60);
                }
                if ($x == $retry) {

                    // Here we also want to make sure that mail is sent with failure ..

                    throw $e;
                }
            }
        }
    }

    function poweroff_vm_internal($vm_id = null, $username = null)
    {
        $vm_id = $this->vsphere_name_to_id($vm_id);
        $result = $this->get_vm_power_and_name($vm_id);
        if ($result['status'] != 'POWERED_OFF') {
            global $service;
            $vapp_id = $this->get_vapp_id_by_vm($vm_id);

            // $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

            $vm_href = $this->get_href_from_id($vm_id);
            $sdkVApp = $service->createSDKObj($vm_href);
            try {

                // Get a lock file so that multiple operations that might make the vapp busy, don't happen at the same time

                //$this->get_vapp_lock($vapp_id);
                try {

                    // Put this in the queue

                    $this->wait_in_queue("vappPowerOff");
                    $task = $sdkVApp->powerOff();

                    // Inform the queue manager that the task should be in vcloud now

                    $this->task_in_vcloud("vappPowerOff");
                }

                catch(Exception $e) {

                    // Inform the queue manager that the task should be in vcloud now

                    $this->task_in_vcloud("vappPowerOff");
                    throw $e;
                }

                $this->wait_for_task($task);
                //$this->clear_vapp_lock($vapp_id);
            }

            catch(Exception $e) {
                //$this->clear_vapp_lock($vapp_id);
                throw $e;
            }
        }
        $this->wait_for_power_state($vm_id, "POWERED_OFF");
    }

    function wait_for_power_state($object_id,$expected_state)
    {
        // Wait until VM is in the right state. This is a workaround that can be removed when issue is resolved
        if (strstr($object_id,"vapp"))
        {
            $working_with_vapp=true;
            $object_type="vapp";
        } else {
            $working_with_vapp=false;
            $object_type="vm";
        }
        $went_into_right_state=false;
        $wasnt_in_right_state_immediately=false;
        $attempts=300;
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            if ($working_with_vapp)
            {
                $actual_state = $this->get_vapp_power_status($object_id);
            } else {
                $result = $this->get_vm_power_and_name($object_id);
                $actual_state = $result['status'];
            }
            if ($actual_state == $expected_state)
            {
                $went_into_right_state=true;
                $attempts_it_took=$attempt;
                break;
            }
            else
            {
                $wasnt_in_right_state_immediately=true;
                sleep(1);
            }
        }
        if (!$went_into_right_state)
        {
            $exception="The " . $object_type . " should be in state " . $expected_state . " but vCloud Director reports its in state " . $actual_state . " after " . $attempts . " seconds. The SPP won't wait any longer";
            throw new Exception($exception);
        }
        if ($wasnt_in_right_state_immediately)
        {
            if ($working_with_vapp)
            {
                $vapp_id = $object_id;
                $exception_type = 'wait_for_vapp_power_state';
                $extra_vm_details = "";
            } else
            {
                $vapp_id = $this->get_vapp_id_by_vm($object_id);
                $exception_type = 'wait_for_vm_power_state';
                $details = $this->get_vm_power_and_name($object_id);
                $extra_vm_details = "vm called '" . $details['name'] . "' (" . $object_id .") in ";
            }

            $vapp_name = $this->get_vapp_name($vapp_id);
            $orgvdc_name = $this->get_orgvdc_name_by_vapp($vapp_id);
            $verbose_details = "SPP was waiting for a " . $extra_vm_details . "vapp '" . $vapp_name . "' (" . $vapp_id . ") in datacenter '" . $orgvdc_name . "' to be in the correct state " . $expected_state . ". It wasn't in the expected state immediately after the action completed.";
            $exception="The " . $object_type . " took " . $attempts_it_took . " seconds to appear in the correct state. Therefore this issue didn't effect the user but should be investigated.";
            $this->report_exception($exception_type, $exception, 1, 1, $verbose_details);
        }
    }

    function reset_vm($vm_id = null, $username = null)
    {
        $retry = 3;
        for ($x = 1; $x <= $retry; $x++) {
            try {
               $this->add_vapp_to_busy_cache($vm_id, 'Resetting VM');
                // Start the actual reset attempt
                $this->reset_vm_internal($vm_id, $username);
                $this->delete_vapp_from_busy_cache($vm_id);
                break;
            }

            catch(Exception $e) {
                $this->delete_vapp_from_busy_cache($vm_id);
                $vm_details = $this->get_vm_power_and_name($vm_id);
                $not_powered_on = "The requested operation could not be executed since VM &quot;" . $vm_details['name'] . "&quot; is not powered on";
                if (strpos($e, $not_powered_on) !== FALSE) {
                    throw $e;
                }
                $vapp_id = $this->get_vapp_id_by_vm($vm_id);
                $vapp_name = $this->get_vapp_name($vapp_id);
                $orgvdc_name = $this->get_orgvdc_name_by_vapp($vapp_id);
                $verbose_details = $username . " was attempting to reset a vm called '" . $vm_details['name'] . "' (" . $vm_id .") in vapp '" . $vapp_name . "' (" . $vapp_id . ") in datacenter '" . $orgvdc_name . "'";
                $this->report_exception('reset_vm', $e, $x, $retry, $verbose_details);
                if ($x == ($retry- 1)) {

                    // Wait before retrying the third time

                    CakeLog::write('retry', 'Sleeping now for 60 seconds before retrying reset_vm again');
                    sleep(60);
                }
                if ($x == $retry) {

                    // Here we also want to make sure that mail is sent with failure ..
                    throw $e;
                }
            }
        }
    }

    function reset_vm_internal($vm_id = null, $username = null)
    {
        $vm_id = $this->vsphere_name_to_id($vm_id);
        global $service;

        // $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        $vm_href = $this->get_href_from_id($vm_id);
        $sdkVApp = $service->createSDKObj($vm_href);
        $task = $sdkVApp->reset();
        $this->wait_for_task($task);
    }

    function shutdown_vm($vm_id = null)
    {
        $vm_id = $this->vsphere_name_to_id($vm_id);
        $result = $this->get_vm_power_and_name($vm_id);
        if ($result['status'] != 'POWERED_OFF') {
            global $service;
            $this->add_vapp_to_busy_cache($vm_id, 'Shutting Down VM');
            // $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
            $vm_href = $this->get_href_from_id($vm_id);
            $sdkVApp = $service->createSDKObj($vm_href);
            try {

                // Put this in the queue

                $this->wait_in_queue("vappShutdownGuest");
                $task = $sdkVApp->shutdown();

                // Inform the queue manager that the task should be in vcloud now

                $this->task_in_vcloud("vappShutdownGuest");
            }

            catch(Exception $e) {

                // Inform the queue manager that the task should be in vcloud now
                $this->delete_vapp_from_busy_cache($vm_id);
                $this->task_in_vcloud("vappShutdownGuest");
                throw $e;
            }
            try
            {
                $this->wait_for_task($task);
                $this->delete_vapp_from_busy_cache($vm_id);
            }
            catch(Exception $e)
            {
                $this->delete_vapp_from_busy_cache($vm_id);
            }
        }
        else {
               $this->delete_vapp_from_busy_cache($vm_id);
        }

    }

    function reboot_vm($vm_id = null)
    {
        $vm_id = $this->vsphere_name_to_id($vm_id);
        global $service;

        // $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        $this->add_vapp_to_busy_cache($vm_id, 'Rebooting VM');
        $vm_href = $this->get_href_from_id($vm_id);
        $sdkVApp = $service->createSDKObj($vm_href);
        $task = $sdkVApp->reboot();
        try
        {
            $this->wait_for_task($task);
            $this->delete_vapp_from_busy_cache($vm_id);
        }
        catch(Exception $e)
        {
            $this->delete_vapp_from_busy_cache($vm_id);
        }
    }

    function suspend_vm($vm_id = null)
    {
        $vm_id = $this->vsphere_name_to_id($vm_id);
        global $service;

        // $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        $this->add_vapp_to_busy_cache($vm_id, 'Suspending VM');
        $vm_href = $this->get_href_from_id($vm_id);
        $sdkVApp = $service->createSDKObj($vm_href);
        $task = $sdkVApp->suspend();
        try
        {
            $this->wait_for_task($task);
            $this->delete_vapp_from_busy_cache($vm_id);
        }
        catch(Exception $e)
        {
            $this->delete_vapp_from_busy_cache($vm_id);
        }
    }

    function start_vapp($vapp_id = null, $username = null, $reboot_gateway_if_necessary = "no", $fast_start = false)
    {
        $this->add_vapp_to_busy_cache($vapp_id, 'Starting');
        $power_status = $this->get_vapp_power_status($vapp_id);
        if ($power_status === "POWERED_ON")
        {
            $this->delete_vapp_from_busy_cache($vapp_id);
            return;
        }

        $gatewayDetails = null ;
        $retry = 3;
        $retryAttempts = 0 ;
        $message = "RetryError: " ;

        $orgvdc = $this->get_orgvdc_id_by_vapp($vapp_id);
        $orgvdc_id = $orgvdc[0]['orgvdc_id'];
        $orgvdc_name = $this->get_orgvdc_name_by_vapp($vapp_id);

        $Events = ClassRegistry::init('Events');
        $event_params = array(
            "function_name" => 'start_vapp',
            "function_parameters" => "destorgvdcname=" . $orgvdc_name . " " . $vapp_id,
            "object_vcd_id" => $vapp_id,
            "org_vdc_id" => $orgvdc_id,
            "user_id" => $username,
         );
        $Events->create();
        $Events->save($event_params);
        $start_vapp_id = $Events->id;
        try
        {
            for ($x = 1; $x <= $retry; $x++) {
                try {
                    // Start the actual power on attempt
                    $gatewayDetails = $this->start_vapp_internal($vapp_id, $username, $reboot_gateway_if_necessary, $fast_start);
                    break;
                }

                catch(Exception $e) {
                    $insufficient_resources = "resources in the parent resource pool are insufficient for the operation";
                    if (strpos($e, $insufficient_resources) !== FALSE) {
                        throw $e;
                    }
                    if(strpos($e, "Cannot start this vApp as doing so would exceed the runtime quota") !== FALSE) {
                        throw $e;
                    }
                    $vapp_name = $this->get_vapp_name($vapp_id);
                    $orgvdc_name = $this->get_orgvdc_name_by_vapp($vapp_id);
                    $verbose_details = $username . " was attempting to start a vapp called '" . $vapp_name . "' (" . $vapp_id .") in datacenter '" . $orgvdc_name . "'";
                    $message = $message . ", Couldn't start vapp" ;
                    $retryAttempts ++ ;
                    $this->report_exception('start_vapp', $e, $x, $retry, $verbose_details);
                    if ($x == ($retry- 1)) {

                        // Wait before retrying the third time

                        CakeLog::write('retry', 'Sleeping now for 60 seconds before retrying start_vapp again');
                        sleep(60);
                    }
                    if ($x == $retry) {
                         $Events->id = $start_vapp_id;
                        // Here we also want to make sure that mail is sent with failure ..
                         $event_params = array(
                             "value_returned" => 1,
                             "retries" => $retryAttempts,
                             "message" => $message
                         );
                         $Events->save($event_params);
                         throw $e;
                    }
                }
            }
        }
        catch(Exception $e)
        {
            $this->delete_vapp_from_busy_cache($vapp_id);
            throw $e ;
        }
        $Events->id = $start_vapp_id;
        $event_params = array(
            "value_returned" => 0,
            "retries" => $retryAttempts,
            "message" => $message
        );
        $Events->save($event_params);
        $this->delete_vapp_from_busy_cache($vapp_id);
        return $gatewayDetails ;
    }

    function start_vapp_internal($vapp_id = null, $username = null, $reboot_gateway_if_necessary = "no", $fast_start = false)
    {
        // Add an event
        $orgvdc_name = $this->get_orgvdc_name_by_vapp($vapp_id);
        global $service;
        try {
            $need_to_run_start_vapp = true;

            // Fast start

            if ($fast_start) {
                $need_to_run_start_vapp = false;
                try {
                    $vms = $this->list_vms_id($vapp_id);
                    foreach($vms as $vm) {
                        if (strstr($vm['name'], "gateway")) {
                            $this->poweron_vm($vm['vm_id'], $username, "no");
                            break;
                        }
                    }

                    foreach($vms as $vm) {
                        if (!strstr($vm['name'], "gateway")) {
                            $this->poweron_vm($vm['vm_id'], $username, "no");
                        }
                    }
                }

                catch(Exception $e) {
                    $need_to_run_start_vapp = true;
                }

                // End of fast start

            }

            if ($need_to_run_start_vapp) {
                $params = new VMware_VCloud_API_DeployVAppParamsType();
                $params->set_powerOn(true);
                $params->set_deploymentLeaseSeconds(null);
                $vapp_ref = $this->get_href_from_id($vapp_id);
                $sdkVApp = $service->createSDKObj($vapp_ref);
                try {

                    // Put this in the queue

                    $this->wait_in_queue("vappDeploy");
                    $task = $sdkVApp->deploy($params);

                    // Inform the queue manager that the task should be in vcloud now

                    $this->task_in_vcloud("vappDeploy");
                }

                catch(Exception $e) {

                    // Inform the queue manager that the task should be in vcloud now

                    $this->task_in_vcloud("vappDeploy");
                    throw $e;
                }

                $this->wait_for_task($task);
            }
            $this->wait_for_power_state($vapp_id, "POWERED_ON");
        }

        catch(Exception $e) {
            throw $e;
        }

        $this->set_security_policy_vapp($vapp_id);
        $saved_vapp = ClassRegistry::init('Vapp');
        $gateway_details = $saved_vapp->network($vapp_id, true, $reboot_gateway_if_necessary);
        if ($gateway_details['gateway_hostname'] == null) {
            throw new Exception("The vapp didn't seem to get its gateway hostname correctly, please contact the CLOUD team");
        }

        return $gateway_details;
    }

    function does_media_exist($catalog_name, $item_name)
    {
        $media_list = $this->list_media_in_catalog($catalog_name);
        foreach($media_list as $media) {
            if ($item_name == $media['name']) {
                return true;

                // throw new Exception("An iso with the name " . $item_name . " already exists in catalog " . $catalog_name);

            }
        }

        return false;
    }

    function eject_first_media($vm_id)
    {
        $org = $this->get_org_id_by_vm($vm_id);
        $catalogs = $this->list_catalogs_by_org($org);
        foreach ($catalogs as $catalog)
        {
            $medias = $this->list_media_in_catalog($catalog['name']);
            foreach ($medias as $media)
            {
                $this->eject_media($media['media_id'],$vm_id);
                return;
            }
        }
    }

    function eject_media($media_id = null, $vm_id = null)
    {
        global $service;
        try {
            $sdkVm = $service->createSDKObj($this->get_href_from_id($vm_id));
            $sdkMedia = $service->createSDKObj($this->get_href_from_id($media_id));
            $params = new VMware_VCloud_API_MediaInsertOrEjectParamsType();
            $params->setMedia($sdkMedia->getMediaRef());
            $task = $sdkVm->ejectMedia($params);
            $this->wait_for_task($task);
        }

        catch(Exception $e) {
            throw $e;
        }
    }

    function insert_media($media_id = null, $vm_id = null)
    {
        global $service;
        try {
            $sdkVm = $service->createSDKObj($this->get_href_from_id($vm_id));
            $sdkMedia = $service->createSDKObj($this->get_href_from_id($media_id));
            $params = new VMware_VCloud_API_MediaInsertOrEjectParamsType();
            $params->setMedia($sdkMedia->getMediaRef());
            $task = $sdkVm->insertMedia($params);
            $this->wait_for_task($task);
        }

        catch(Exception $e) {
            throw $e;
        }
    }

    function upload_local_iso($catalog_name = null, $file_path = null, $item_name)
    {

        // Check if it already exists first

        if ($this->does_media_exist($catalog_name, $item_name)) {
            throw new Exception("An iso with the name " . $item_name . " already exists in catalog " . $catalog_name);
        }

        // Get the catalog details

        global $service;
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields("org");
        $params->setFilter("name==" . urlencode($catalog_name));
        $type = "adminCatalog";
        $recs = $sdkQuery->$qm($type, $params);
        $pvdcRecords = $recs->getRecord();
        foreach($pvdcRecords as $pvdc) {
            $dest_catalog_href = $pvdc->get_href();
            $org_href = $pvdc->get_org();
            break;
        }

        $catalogSDK = $service->createSDKObj($dest_catalog_href);

        // Get details of the orgvdc

        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields("org");
        $params->setFilter("org==" . urlencode($org_href));
        $type = "adminOrgVdc";
        $recs = $sdkQuery->$qm($type, $params);
        $pvdcRecords = $recs->getRecord();
        foreach($pvdcRecords as $pvdc) {
            $vdc_href = str_replace("admin/", "", $pvdc->get_href());
            break;
        }

        $sdkVdc = $service->createSDKObj($vdc_href);

        // Create the media object

        $mediaType = new VMware_VCloud_API_MediaType();
        $mediaType->set_imageType("iso");
        $mediaType->set_name($item_name);
        $mediaType->set_size(filesize($file_path));

        // Upload the file to vcloud director

        try {
            $mediaType = $sdkVdc->uploadIsoMedia($file_path, $mediaType);
            $tasks = $mediaType->getTasks()->getTask();
            if ($tasks) {
                $task = $tasks[0];
                $this->wait_for_task($task);
            }
        }

        catch(Exception $e) {
            throw $e;
        }

        // Now put it in the catalog so its visible on the UI

        try {
            $catalog_item_type = new VMware_VCloud_API_CatalogItemType();
            $catalog_item_type->setDescription("the description");
            $catalog_item_type->setEntity(VMware_VCloud_SDK_Helper::createReferenceTypeObj($mediaType->get_href()));
            $catalog_item_type->set_name($item_name);
            $catalogSDK->addCatalogItem($catalog_item_type);
        }

        catch(Exception $e) {

            // Delete the old one as its useless now that adding to catalog failed

            $sdkMedia = $service->createSDKObj($mediaType);
            $task = $sdkMedia->delete();
            $this->wait_for_task($task);
            throw $e;
        }
    }

    function delete_vapp($vapp_id = null, $username = null)
    {
        $orgvdc_name = $this->get_orgvdc_name_by_vapp($vapp_id);

        //Add an event

        $Events = ClassRegistry::init('Events');
        $event_params = array(
            "function_name" => 'delete_vapp',
            "function_parameters" => "destorgvdcname=" . $orgvdc_name . " " . $vapp_id,
            "object_vcd_id" => $vapp_id,
            "user_id" => $username
        );
        $Events->create();
        $Events->save($event_params);
        $delete_vapp_id = $Events->id;
        global $service;
        try {
            $this->add_vapp_to_busy_cache($vapp_id, 'Deleting');
            $vapp_ref = $this->get_href_from_id($vapp_id);
            $sdkVApp = $service->createSDKObj($vapp_ref);
            try {

                // Put this in the queue

                $this->wait_in_queue("vdcDeleteVapp");
                $task = $sdkVApp->delete();

                // Inform the queue manager that the task should be in vcloud now

                $this->task_in_vcloud("vdcDeleteVapp");
            }

            catch(Exception $e) {

                // Inform the queue manager that the task should be in vcloud now

                $this->task_in_vcloud("vdcDeleteVapp");
                throw $e;
            }

            $this->wait_for_task($task);
        }

        catch(Exception $e) {
            $event_params = array(
                "value_returned" => 1
            );
            $Events->id = $delete_vapp_id;
            $Events->save($event_params);

            // echo $e;
            $this->delete_vapp_from_busy_cache($vapp_id);
            throw $e;
            exit(1);
        }

        $event_params = array(
            "value_returned" => 0
        );
        $Events->id = $delete_vapp_id;
        $Events->save($event_params);
        $this->delete_vapp_from_busy_cache($vapp_id);
    }

    function delete_vapp_template($vapp_template_id = null, $username = null)
    {

        // Add an event

        $Events = ClassRegistry::init('Events');
        $event_params = array(
            "function_name" => 'delete_vapp_template',
            "function_parameters" => $vapp_template_id,
            "object_vcd_id" => $vapp_template_id,
            "user_id" => $username
        );
        $Events->create();
        $Events->save($event_params);
        $delete_template_id = $Events->id;
        global $service;
        try {
            $vapp_template_ref = $this->get_href_from_id($vapp_template_id);
            $sdkVApp = $service->createSDKObj($vapp_template_ref);
            try {

                // Put this in the queue
                // This also kicks off the main task called commonPurgeDeletedItem

                $this->wait_in_queue("vdcDeleteTemplate");
                if ($sdkVApp->isPartOfCatalogItem()) {
                    $task = $service->createSDKObj($sdkVApp->getCatalogItemLink())->delete();
                }else{
                    $task = $service->delete($vapp_template_ref, 202);
                }
                // Inform the queue manager that the task should be in vcloud now

                $this->task_in_vcloud("vdcDeleteTemplate");
            }

            catch(Exception $e) {

                // Inform the queue manager that the task should be in vcloud now

                $this->task_in_vcloud("vdcDeleteTemplate");
                throw $e;
            }
            if($task){
               $this->wait_for_task($task);
            }
        }

        catch(Exception $e) {
            $event_params = array(
                "value_returned" => 1
            );
            $Events->id = $delete_template_id;
            $Events->save($event_params);

            // echo $e;

            throw $e;
            exit(1);
        }

        $event_params = array(
            "value_returned" => 0
        );
        $Events->id = $delete_template_id;
        $Events->save($event_params);
    }

    function destroy_vapp($vapp_id = null, $username = null)
    {
        try{
            $this->poweroff_vapp($vapp_id, $username);
        }catch(Exception $e) {
            // Do nothing as the Vapp wasn't powered on before use.
        }
        $this->delete_vapp($vapp_id, $username);
    }

    function stop_vapp($vapp_id = null, $username = null)
    {
        // If its already powered off, exit
        $power_status = $this->get_vapp_power_status($vapp_id);
        $this->add_vapp_to_busy_cache($vapp_id, 'Stopping');
        if ($power_status === "POWERED_OFF")
        {
            $this->delete_vapp_from_busy_cache($vapp_id);
            return;
        }
        $orgvdc_name = $this->get_orgvdc_name_by_vapp($vapp_id);

        // Add an event

        $message = "RetryError: " ;

        $orgvdc = $this->get_orgvdc_id_by_vapp($vapp_id);
        $orgvdc_id = $orgvdc[0]['orgvdc_id'];

        $Events = ClassRegistry::init('Events');
        $event_params = array(
            "function_name" => 'stop_vapp',
            "function_parameters" => "destorgvdcname=" . $orgvdc_name . " " . $vapp_id,
            "object_vcd_id" => $vapp_id,
            "user_id" => $username,
            "org_vdc_id" => $orgvdc_id
        );
        $Events->create();
        $Events->save($event_params);
        $stop_vapp_id = $Events->id;
        global $service;
        $params = new VMware_VCloud_API_UndeployVAppParamsType();
        $params->setUndeployPowerAction("default");
        try {
            $vapp_ref = $this->get_href_from_id($vapp_id);
            $sdkVApp = $service->createSDKObj($vapp_ref);
            $task = $sdkVApp->undeploy($params);
            $this->wait_for_task($task);
            $this->wait_for_power_state($vapp_id, "POWERED_OFF");
        }

        catch(Exception $e) {
            $message = $message . ",Failed to stop Vapp" ;
            $event_params = array(
                "value_returned" => 1,
                "message" => $message
            );
            $Events->id = $stop_vapp_id;
            $Events->save($event_params);

            // echo $e;

            $this->delete_vapp_from_busy_cache($vapp_id);
            throw $e;

            // exit(1);

        }
        $event_params = array(
            "value_returned" => 0,
            "message" => $message
        );
        $Events->id = $stop_vapp_id;
        $Events->save($event_params);
        $this->delete_vapp_from_busy_cache($vapp_id);
    }

    function poweroff_vapp($vapp_id = null, $username = null)
    {
        // If its already powered off, exit
        $power_status = $this->get_vapp_power_status($vapp_id);
        $this->add_vapp_to_busy_cache($vapp_id, 'Stopping');
        if ($power_status === "POWERED_OFF")
        {
            $this->delete_vapp_from_busy_cache($vapp_id);
            return;
        }
        $message = "RetryError: " ;

        $orgvdc = $this->get_orgvdc_id_by_vapp($vapp_id);
        $orgvdc_id = $orgvdc[0]['orgvdc_id'];
        $Events = ClassRegistry::init('Events');

        $event_params = array(
            "function_name" => 'poweroff_vapp',
            "function_parameters" => $vapp_id,
            "object_vcd_id" => $vapp_id,
            "user_id" => $username,
            "org_vdc_id" => $orgvdc_id
        );
        $Events->create();
        $Events->save($event_params);
        $poweroff_vapp_id = $Events->id;
        global $service;
        $params = new VMware_VCloud_API_UndeployVAppParamsType();
        $params->setUndeployPowerAction("powerOff");
        $vapp_ref = $this->get_href_from_id($vapp_id);
        $sdkVApp = $service->createSDKObj($vapp_ref);
        try {
            try {

                //Add to busy cache

                // Put this in the queue
                $this->wait_in_queue("vappUndeployPowerOff");
                $task = $sdkVApp->undeploy($params);

                // Inform the queue manager that the task should be in vcloud now

                $this->task_in_vcloud("vappUndeployPowerOff");
            }

            catch(Exception $e) {

                // Inform the queue manager that the task should be in vcloud now

                $this->task_in_vcloud("vappUndeployPowerOff");
                throw $e;
            }

            $this->wait_for_task($task);
            $this->wait_for_power_state($vapp_id, "POWERED_OFF");
        }

        catch(Exception $e) {
            $message = $message . ",failed hard power off" ;
            $event_params = array(
                "value_returned" => 1,
                "message" => $message
            );
            $Events->id = $poweroff_vapp_id;
            $Events->save($event_params);
            $this->delete_vapp_from_busy_cache($vapp_id);
            throw $e;
        }

        $event_params = array(
            "value_returned" => 0,
            "message" => $message
        );
        $Events->id = $poweroff_vapp_id;
        $Events->save($event_params);
        $this->delete_vapp_from_busy_cache($vapp_id);
    }

    function suspend_vapp($vapp_id = null, $username = null)
    {
        $Events = ClassRegistry::init('Events');
        $event_params = array(
            "function_name" => 'suspend_vapp',
            "function_parameters" => $vapp_id,
            "object_vcd_id" => $vapp_id,
            "user_id" => $username
        );
        $Events->create();
        $Events->save($event_params);
        $suspend_vapp_id = $Events->id;
        global $service;
        $this->add_vapp_to_busy_cache($vapp_id, 'Stopping');
        $params = new VMware_VCloud_API_UndeployVAppParamsType();
        $params->setUndeployPowerAction("suspend");
        $vapp_ref = $this->get_href_from_id($vapp_id);
        $sdkVApp = $service->createSDKObj($vapp_ref);
        try {
            try {

                // Put this in the queue
                $this->wait_in_queue("vappUndeploySuspend");
                $task = $sdkVApp->undeploy($params);

                // Inform the queue manager that the task should be in vcloud now

                $this->task_in_vcloud("vappUndeploySuspend");
            }

            catch(Exception $e) {

                // Inform the queue manager that the task should be in vcloud now

                $this->task_in_vcloud("vappUndeploySuspend");
                throw $e;
            }

            $this->wait_for_task($task);
        }

        catch(Exception $e) {
            echo $e;
            $event_params = array(
                "value_returned" => 1
            );
            $Events->id = $suspend_vapp_id;
            $Events->save($event_params);
            $this->delete_vapp_from_busy_cache($vapp_id);
            throw $e;
            exit(1);
        }

        $event_params = array(
            "value_returned" => 0
        );
        $Events->id = $suspend_vapp_id;
        $Events->save($event_params);
        $this->delete_vapp_from_busy_cache($vapp_id);
    }

    function shutdown_vapp($vapp_id = null, $username = null)
    {
        // If its already powered off, exit
        $this->add_vapp_to_busy_cache($vapp_id, 'Stopping');
        $power_status = $this->get_vapp_power_status($vapp_id);
        if ($power_status === "POWERED_OFF")
        {
            $this->delete_vapp_from_busy_cache($vapp_id);
            return;
        }

        $message = "RetryError: " ;

        $orgvdc = $this->get_orgvdc_id_by_vapp($vapp_id);
        $orgvdc_id = $orgvdc[0]['orgvdc_id'];
        $Events = ClassRegistry::init('Events');
        $event_params = array(
            "function_name" => 'shutdown_vapp',
            "function_parameters" => $vapp_id,
            "object_vcd_id" => $vapp_id,
            "user_id" => $username,
            "org_vdc_id" => $orgvdc_id,
        );
        $Events->create();
        $Events->save($event_params);
        $shutdown_vapp_id = $Events->id;
        global $service;
        $params = new VMware_VCloud_API_UndeployVAppParamsType();
        $params->setUndeployPowerAction("shutdown");
        try {
            $vapp_ref = $this->get_href_from_id($vapp_id);
            $sdkVApp = $service->createSDKObj($vapp_ref);
            try {

                // Put this in the queue

                $this->wait_in_queue("vappUndeployPowerOff");
                $task = $sdkVApp->undeploy($params);

                // Inform the queue manager that the task should be in vcloud now

                $this->task_in_vcloud("vappUndeployPowerOff");
            }

            catch(Exception $e) {

                // Inform the queue manager that the task should be in vcloud now

                $this->task_in_vcloud("vappUndeployPowerOff");
                throw $e;
            }

            $this->wait_for_task($task);
        }

        catch(Exception $e) {
            $message = $message . ",failed to shutdown vapp" ;
            echo $e;
            $event_params = array(
                "value_returned" => 1,
                "message" => $message
            );
            $Events->id = $shutdown_vapp_id;
            $Events->save($event_params);
            $this->delete_vapp_from_busy_cache($vapp_id);
            throw $e;
            exit(1);
        }

        $event_params = array(
            "value_returned" => 0,
            "message" => $message
        );
        $Events->id = $shutdown_vapp_id;
        $Events->save($event_params);
        $this->delete_vapp_from_busy_cache($vapp_id);
    }

    function force_stop_vapp($vapp_id = null, $username = null)
    {
        // If its already powered off, exit
        $power_status = $this->get_vapp_power_status($vapp_id);
        $this->add_vapp_to_busy_cache($vapp_id, 'Stopping');
        if ($power_status === "POWERED_OFF")
        {
            $this->delete_vapp_from_busy_cache($vapp_id);
            return;
        }

        $message = "RetryError: " ;

        $orgvdc = $this->get_orgvdc_id_by_vapp($vapp_id);
        $orgvdc_id = $orgvdc[0]['orgvdc_id'];
        $Events = ClassRegistry::init('Events');
        $event_params = array(
            "function_name" => 'force_stop_vapp',
            "function_parameters" => $vapp_id,
            "object_vcd_id" => $vapp_id,
            "user_id" => $username,
            "org_vdc_id" => $orgvdc_id,
        );
        $Events->create();
        $Events->save($event_params);
        $force_stop_id = $Events->id;
        global $service;
        $params = new VMware_VCloud_API_UndeployVAppParamsType();
        $params->setUndeployPowerAction("force");
        try {
            $vapp_ref = $this->get_href_from_id($vapp_id);
            $sdkVApp = $service->createSDKObj($vapp_ref);
            $task = $sdkVApp->undeploy($params);
            $this->wait_for_task($task);
        }

        catch(Exception $e) {
            $message = $message . ",failed to force stop vapp" ;
            $event_params = array(
                "value_returned" => 1,
                "message" => $message
            );
            $Events->id = $force_stop_id;
            $Events->save($event_params);

            $this->delete_vapp_from_busy_cache($vapp_id);
            // echo $e;

            throw $e;
            exit(1);
        }

        $event_params = array(
            "value_returned" => 0,
            "message" => $message
        );
        $Events->id = $force_stop_id;
        $Events->save($event_params);
        $this->delete_vapp_from_busy_cache($vapp_id);
    }

    function allowed_poweron_another_vapp($orgvdc_id = null)
    {
        $OrgVdc = ClassRegistry::init('OrgVdc');
        $db_entry = $OrgVdc->find('first', array(
            'conditions' => array(
                'OrgVdc.vcd_id' => $orgvdc_id
            )
        ,'contain' => false));
        if ($this->count_running_vapps($orgvdc_id, null, null) ['running'] + 1 > $db_entry['OrgVdc']['running_tb_limit']) {
            return false;
        }
        else {
            return true;
        }
    }

    function allowed_created_another_vapp($orgvdc_id = null)
    {
        $OrgVdc = ClassRegistry::init('OrgVdc');
        $db_entry = $OrgVdc->find('first', array(
            'conditions' => array(
                'OrgVdc.vcd_id' => $orgvdc_id
            )
        ,'contain' => false));
        $vapp_counts = $this->count_running_vapps($orgvdc_id, null, null);

        // adding both running and not_running from db will give total number of vapps
        if ($vapp_counts['running'] + $vapp_counts['not_running'] + 1 > $db_entry['OrgVdc']['stored_tb_limit']) {
            return false;
        }
        else {
            return true;
        }
    }

    function get_orgvdc_name_from_id($orgvdc_id)
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // /////////////////////////////////////////////////////
        // Find the href of the org vdc

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields("name,href");
        return $this->get_href_from_id($orgvdc_id);
        $params->setFilter("href==" . $this->get_href_from_id($orgvdc_id));
        $type = "adminOrgVdc";
        $recs = $sdkQuery->$qm($type, $params);
        $pvdcRecords = $recs->getRecord();
        foreach($pvdcRecords as $pvdc) {
            return $pvdc->get_name();
        }
    }

    function is_exists($id)
    {
        global $service;
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields("name");
        $params->setFilter("href==" . $this->get_href_from_id($id));
        $type = "adminVAppTemplate";
        $recs = $sdkQuery->$qm($type, $params);
        $records = $recs->getRecord();
        return !empty($records);
    }

    function find_vapp_href_by_name($vapp_name, $orgvdc_name)
    {
        global $service;
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // Find the href of the org vdc

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields("name");
        $params->setFilter("name==" . $orgvdc_name);
        $type = "adminOrgVdc";
        $recs = $sdkQuery->$qm($type, $params);
        $pvdcRecords = $recs->getRecord();
        foreach($pvdcRecords as $pvdc) {
            $vdc_href = $pvdc->get_href();
        }

        // /////////////////////////////////////////////////////
        // / Find the vapps under this org and orgvdc

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields("name,status,ownerName,org,vdc,creationDate");
        $params->setFilter("name==" . $vapp_name . ";vdc==" . $vdc_href);
        $type = "adminVApp";
        $recs = $sdkQuery->$qm($type, $params);
        $pvdcRecords = $recs->getRecord();
        foreach($pvdcRecords as $pvdc) {
            return $pvdc->get_href();
        }
    }

    function list_vapps($orgName, $vdcName)
    {
        // $this->login();

        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // Find the href of the org

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields("name");
        $params->setFilter("name==" . $orgName);
        $type = "organization";
        $recs = $sdkQuery->$qm($type, $params);
        $pvdcRecords = $recs->getRecord();
        foreach($pvdcRecords as $pvdc) {
            $org_href = $pvdc->get_href();
            break;
        }

        // /////////////////////////////////////////////////////
        // Find the href of the org vdc

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields("name");
        $params->setFilter("name==" . $vdcName);
        $type = "adminOrgVdc";
        $recs = $sdkQuery->$qm($type, $params);
        $pvdcRecords = $recs->getRecord();
        foreach($pvdcRecords as $pvdc) {
            $vdc_href = $pvdc->get_href();
            break;
        }

        // /////////////////////////////////////////////////////
        // / Find the vapps under this org and orgvdc

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields("name,status,ownerName,org,vdc,creationDate");
        $params->setFilter("org==" . $org_href . ";vdc==" . $vdc_href);
        $type = "adminVApp";
        $recs = $sdkQuery->$qm($type, $params);
        $pvdcRecords = $recs->getRecord();
        foreach($pvdcRecords as $pvdc) {

            // $vms = $this->list_vms2($pvdc->get_name(), $vdcName);
            // echo $service->get($pvdc->get_href(),"",true)->get_status();
            // echo "</br>";
            // return;

            array_push($the_array, array(
                "vapp_name" => $pvdc->get_name() ,
                "status" => "get from db",
                "vapp_href" => $pvdc->get_href() ,
                "orgvdc_name" => $vdcName,
                "creation_date" => $pvdc->get_creationDate()
            ));
        }

        return $the_array;
    }

    function count_spun_up_vapps_yesterday_in_orgvdc($orgvdc_name = null)
    {
        $Events = ClassRegistry::init('Event');
        $arguments['conditions'] = array();
        $arguments['conditions']['Event.function_name'] = "start_vapp";
        $arguments['conditions']['Event.function_parameters LIKE'] = "%destorgvdcname=" . $orgvdc_name . " %";
        $arguments['conditions']['DATE(Event.created)'] = date("Y-m-d", time() - 60 * 60 * 24);
        $arguments['conditions']['Event.value_returned'] = "0";
        return $Events->find("count", $arguments);
    }

    function count_spun_down_vapps_yesterday_in_orgvdc($orgvdc_name = null)
    {
        $Events = ClassRegistry::init('Event');
        $arguments['conditions'] = array();
        $arguments['conditions']['Event.function_name'] = "stop_vapp";
        $arguments['conditions']['Event.function_parameters LIKE'] = "%destorgvdcname=" . $orgvdc_name . " %";
        $arguments['conditions']['DATE(Event.created)'] = date("Y-m-d", time() - 60 * 60 * 24);
        $arguments['conditions']['Event.value_returned'] = "0";
        return $Events->find("count", $arguments);
    }

    function get_vcenter_of_vm($vm_id = null)
    {
        $vm_id = $this->vsphere_name_to_id($vm_id);
        global $service;
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields("vc");
        $params->setFilter("id==" . $vm_id);
        $type = "adminVM";
        $recs = $sdkQuery->$qm($type, $params);
        $provRecords = $recs->getRecord();
        $vc = null;
        foreach($provRecords as $providervdc) {
            $vc = $providervdc->get_vc();
        }

        if (is_null($vc))
        {
            throw new Exception("A VM with id " . $vm_id . " could not be found");
        }

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields("url");
        $params->setFilter("href==" . $vc);
        $type = "virtualCenter";
        $recs = $sdkQuery->$qm($type, $params);
        $provRecords = $recs->getRecord();
        foreach($provRecords as $providervdc) {
            $url = $providervdc->get_url();
        }

        $without_https = preg_replace("/https:\/\//", "", $url);
        $without_sdk = preg_replace("/:.*/", "", $without_https);
        return $without_sdk;
    }

    # This function returns the vcenter that this vapp belongs to, by finding the vcenter of one of its vms
    function get_vcenter_of_vapp($vapp_id = null)
    {
        global $service;
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields('name');
        $params->setPageSize(1);
        $params->setFilter("container==" . $vapp_id);
        $type = "adminVM";
        $recs = $sdkQuery->$qm($type, $params);
        $pvdcRecords = $recs->getRecord();
        foreach($pvdcRecords as $pvdc) {
            $vm_id = $this->get_id_from_href($pvdc->get_href() , "vm");
            return $this->get_vcenter_of_vm($vm_id);
        }
        return "";
    }

    # This function returns the vcenter that this datacenter belongs to. If its already stored in the database return that.
    # If its not stored in the database, we get all datacenters belonging to its provider, and search through those
    # If none of them are in the database we resort to finding the vcenter of any vm in any of the datacenters
    # If no local database entry exists and no vms exist in any datacenters we return null
    function get_vcenter_of_orgvdc($orgvdc_id = null)
    {
        # See do we already have it in the db
        $OrgVdc = ClassRegistry::init('OrgVdc');
        $orgvdc_db_entry = $OrgVdc->find('first', array('fields' => array('vcenter'), 'conditions' => array('OrgVdc.vcd_id' => $orgvdc_id), 'contain' => false));
        if(isset($orgvdc_db_entry['OrgVdc']['vcenter']) && $orgvdc_db_entry['OrgVdc']['vcenter'] != "")
        {
            return $orgvdc_db_entry['OrgVdc']['vcenter'];
        }

        $provider_id = $this->get_provider_id_from_orgvdc ($orgvdc_id);
        $orgvdcs_in_provider = $this->list_orgvdcs_in_provider($provider_id);
        $orgvdc_db_entries = $OrgVdc->find('all', array('fields' => array('vcd_id', 'vcenter'), 'contain' => false ));

        foreach($orgvdcs_in_provider as $orgvdc)
        {
            foreach ($orgvdc_db_entries as $db_orgvdc)
            {
                if ($orgvdc['orgvdc_id'] == $db_orgvdc['OrgVdc']['vcd_id'])
                {
                    if($db_orgvdc['OrgVdc']['vcenter'] != "")
                    {
                        return $db_orgvdc['OrgVdc']['vcenter'];
                    }
                }
            }
        }

        foreach($orgvdcs_in_provider as $orgvdc)
        {
            $vm_id_from_orgvdc = $this->one_vm_from_orgvdc($orgvdc['orgvdc_id']);
            if (!is_null($vm_id_from_orgvdc))
            {
                return $this->get_vcenter_of_vm($vm_id_from_orgvdc);
            }
        }

        return null;
    }

    # This function returns the id of one vm in the datacenter
    # This is used in functions like get_vcenter_of_orgvdc where we sometimes want to find one vm in the datacenter
    function one_vm_from_orgvdc($orgvdc_id = null)
    {
        global $service;
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields("name");
        $params->setFilter("vdc==" . $orgvdc_id);
        $params->setPageSize(1);
        $type = "adminVM";
        $recs = $sdkQuery->$qm($type, $params);
        $vmRecords = $recs->getRecord();
        foreach($vmRecords as $vm) {
            return $this->get_id_from_href($vm->get_href() , "vm");
        }
        return null;
    }

    # This function returns an array of datacenter ids in the provider
    function list_orgvdcs_in_provider($provider_id= null)
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setPageSize(128);
        $params->setFields('name');
        $params->setFilter('isSystemVdc==false;providerVdc==' . $provider_id);
        $type = "adminOrgVdc";
        $i = 1; //
        $pages = 1; //This will remain 1 once the record are returned.
        $params->setPage($i);
        $recs = $sdkQuery->$qm($type, $params);
        while ($pages) {
            $orgVdcRecords = $recs->getRecord();
            foreach($orgVdcRecords as $orgVdc) {
                array_push($the_array, array(
                    "orgvdc_id" => $this->get_id_from_href($orgVdc->get_href() , "orgvdc")
                ));
            }

            $i++;
            $params->setPage($i);
            try {
                $recs = $sdkQuery->$qm($type, $params);
            }

            catch(Exception $e) {
                $pages = null;
            }
        }
        return $the_array;
    }
    function count_hosts_in_orgvdc($orgvdc_name = null)
    {
        global $service;
        $OrgVdc = ClassRegistry::init('OrgVdc');

        // Find the orgvdc itself to get its provider name

        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields("providerVdcName");
        $params->setFilter("name==" . urlencode($orgvdc_name));
        $type = "adminOrgVdc";
        $recs = $sdkQuery->$qm($type, $params);
        $orgRecords = $recs->getRecord();
        if (!$orgRecords) {
            echo "ERROR: Couldn't find an org vdc of this name " . $orgvdc_name;
            return 1;
        }

        foreach($orgRecords as $orgvdc) {

            // $orgvdc_href=$orgvdc->get_href();

            $orgvdc_providerVdcName = $orgvdc->get_providerVdcName();

            // $orgvdc_mb = $orgvdc->get_memoryAllocationMB ();
            // echo "Orgvdc allocated mb: " . $orgvdc->get_memoryAllocationMB ();
            // echo "\n";
            // echo $orgvdc->get_memoryLimitMB ();
            // echo "\n";

        }

        // Find all of the orgvdcs in that provider

        $quota_total = 0;
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields("name");
        $params->setFilter("isSystemVdc==false;providerVdcName==" . $orgvdc_providerVdcName);
        $type = "adminOrgVdc";
        $recs = $sdkQuery->$qm($type, $params);
        $orgRecords = $recs->getRecord();
        if (!$orgRecords) {
            echo "ERROR: Couldn't find an org vdc belonging to this provider vdc " . $orgvdc_providerVdcName;
            return 1;
        }

        foreach($orgRecords as $orgvdc) {
            $orgvdc_name_loop = $orgvdc->get_name();
            $db_orgvdcs = $OrgVdc->find('first', array(
                'conditions' => array(
                    'OrgVdc.name' => $orgvdc_name_loop
                )
            ,'contain' => false));

            // echo "Checking quota for " . $orgvdc_name_loop;

            if (isset($db_orgvdcs['OrgVdc'])) {
                $running_quota = $db_orgvdcs['OrgVdc']['running_tb_limit'];

                // if ($running_quota == 0) {
                // }

                if ($orgvdc_name_loop == $orgvdc_name) {
                    $orgvdc_quota = $running_quota;
                }

                $quota_total+= $running_quota;

                // echo "Increasing quota to " . $quota_total;

            }
            else {
                echo "ERROR: There is no database entry found for this orgvdc, so I can't read its running quota, please check why not\n";
                exit(1);
            }
        }

        if ($quota_total == 0) {
            echo "ERROR: No quota is set for any orgvdcs in the providervdc " . $orgvdc_providerVdcName . ", please check out why not\n";
            exit(1);
        }

        $sdkExt = $service->createSDKExtensionObj();
        $vdcrefs = $sdkExt->getVMWProviderVdcRefs($orgvdc_providerVdcName);
        $vdcref = $vdcrefs[0];
        $vmwprovidervdcob = $service->createSDKObj($vdcref);
        $providerobj = $vmwprovidervdcob->getVMWProviderVdc();
        $hostRefs = $providerobj->getHostReferences();
        $refis = $hostRefs->getHostReference();
        $providervdc_blades = count($hostRefs->getHostReference());

        // echo "Provider vdc blades: " . $providervdc_blades;
        // Do the calculations

        $quota_percentage = $orgvdc_quota / $quota_total;

        // echo "Quota percentage: " . $quota_percentage;

        $blade_count_decimal = $quota_percentage * $providervdc_blades;

        // echo "Blade count decimal: " . $blade_count_decimal;

        $blade_count_rounded = round($blade_count_decimal * 10000);

        // echo "Blade count rounded: " . $blade_count_rounded;

        return $blade_count_rounded;
    }

    function get_provider_cpu_memory_totals($provider_name)
    {
        global $service;
        $sdkExt = $service->createSDKExtensionObj();
        $vdcrefs = $sdkExt->getVMWProviderVdcRefs($provider_name);
        $vdcref = $vdcrefs[0];
        $vmwprovidervdcob = $service->createSDKObj($vdcref);
        $providerobj = $vmwprovidervdcob->getVMWProviderVdc();
        $hostRefs = $providerobj->getHostReferences();
        $host_references = $hostRefs->getHostReference();
        $cpu_total = 0;
        $memory_total = 0;
        foreach ($host_references as $host)
        {
            $host_obj = $service->createSdkObj($host);
            $cpu_total += $host_obj->getHost()->getNumOfCpusLogical();
            $memory_total += $host_obj->getHost()->getMemTotal();
        }
        $result_array = array();
        $result_array['cpus'] = $cpu_total;
        $result_array['memory'] = $memory_total;
        return $result_array;
    }

    function get_vapp_power_status($vapp_id = null)
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // Find the href of the org vdc

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFilter('id==' . $vapp_id);
        $params->setFields('status,isDeployed');
        $type = "adminVApp";
        $recs = $sdkQuery->$qm($type, $params);
        $vappRecords = $recs->getRecord();
        foreach($vappRecords as $vapp) {
            return $this->create_vapp_status_string($vapp->get_status() , $vapp->get_isDeployed());
        }

        return null;
    }

    function get_vapp_name($vapp_id = null)
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // Find the href of the org vdc

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFilter('id==' . $vapp_id);
        $params->setFields('name');
        $type = "adminVApp";
        $recs = $sdkQuery->$qm($type, $params);
        $vappRecords = $recs->getRecord();
        foreach($vappRecords as $vapp) {
            return $vapp->get_name();
        }

        return null;
    }

    function get_vapp_template_name($vapp_template_id = null)
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        // /////////////////////////////////////////////////////
        // Find the vapp template name

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFilter('id==' . $vapp_template_id);
        $params->setFields('name');
        $type = "adminVAppTemplate";
        $recs = $sdkQuery->$qm($type, $params);
        $vappTemplateRecords = $recs->getRecord();
        foreach($vappTemplateRecords as $vappTemplate) {
            return $vappTemplate->get_name();
        }
        return null;
    }

    function get_vapp_metadata_from_vapp_id($vapp_id = null){
        global $service ;
        $vapp_href = $this->get_href_from_id($vapp_id);
        $sdkVApp = $service->createSDKObj($vapp_href);
        $vappMetadata = $sdkVApp->getMetaData()->getMetaDataEntry();
        $metadata_array= array() ;
        foreach($vappMetadata as $vappMetadataEntry)
        {
            $metadata_array[$vappMetadataEntry->getKey()] = $vappMetadataEntry->getTypedValue()->getValue() ;
        }
        return $metadata_array;
    }

    function set_metadata_for_id($vapp_id = null, $key = null, $value = null){
        global $service ;
        $vapp_href = $this->get_href_from_id($vapp_id);
        $sdkVObj = $service->createSDKObj($vapp_href);
        $metavalue = new VMWare_VCloud_API_MetadataValueType();
        $metadataStringValue = new VMware_VCloud_API_MetadataStringValue();
        $metadataStringValue->setValue($value);
        $metavalue->setTypedValue($metadataStringValue);
        $setvdobj = $sdkVObj->setMetadataByKey($key, $metavalue);

        return $setvdobj;
    }

    function get_vapp_origin_template_id($vapp_id)
    {
        $key = 'vapp_origin_template_id_' . $vapp_id;
        $store = 'short_day';
        if (($value = Cache::read($key, $store)) === false) {
        }
        else {
            return $value;
        }

        $metadata = $this->get_vapp_metadata_from_vapp_id($vapp_id);
        if (isset($metadata['vapp.origin.id']) && $metadata['vapp.origin.id'] != "")
        {
            $value = $metadata['vapp.origin.id'];
            if (strpos($value,'urn:vcloud') === false)
            {
                $value = 'urn:vcloud:vapptemplate:' . $value;
            }
        }
        else
        {
            $value = "unknown";
        }
        Cache::write($key, $value, $store);
        return $value;
    }

    function get_vapp_origin_template_name($vapp_id)
    {
        $key = 'vapp_origin_template_name_' . $vapp_id;
        $store = 'short_day';
        if (($value = Cache::read($key, $store)) === false) {
        }
        else {
            return $value;
        }
        $metadata = $this->get_vapp_metadata_from_vapp_id($vapp_id);
        if (isset($metadata['vapp.origin.name']))
        {
            $value = $metadata['vapp.origin.name'];
        }
        else
        {
            $value = "unknown";
        }
        Cache::write($key, $value, $store);
        return $value;
    }

    function query_service_request($params)
    {
        global $service;
        $result_array = array();
        $qparams = new VMware_VCloud_SDK_Query_Params();
        $type = $params['type'];

        # Define normal query services fields that are required by the various generated fields
        $generated_fields_mappings = array(
            'vapp_power_status' => array('status','isDeployed'),
            'org_vdc_provider_vdc_id' => array('providerVdc'),
            'vapp_org_id' => array('org'),
            'vapp_org_vdc_id' => array('vdc'),
            'vm_vapp_id' => array('container'),
            'vm_org_vdc_id' => array('vdc')
        );

        if(isset($params['filter']))
        {
            $qparams->setFilter($params['filter']);
        }

        if (isset($params['sortAsc']))
        {
            $qparams->setSortAsc($params['sortAsc']);
        } else if (isset($params['sortDesc']))
        {
            $qparams->setSortDesc($params['sortDesc']);
        }

        $other_required_fields = array();

        # Populate the other required fields based on the required generated fields
        if(isset($params['generated_fields']))
        {
            $generated_fields=$params['generated_fields'];
            foreach ($generated_fields as $field)
            {
                if(isset($generated_fields_mappings[$field]))
                {
                    foreach($generated_fields_mappings[$field] as $generated_fields_mapping)
                    {
                        if(!in_array($generated_fields_mapping, $params['fields']) && !in_array($generated_fields_mapping, $other_required_fields))
                        {
                            array_push($other_required_fields, $generated_fields_mapping);
                        }
                    }
                }
            }
        } else
        {
            $generated_fields=array();
        }

        if(isset($params['fields']))
        {
            $field_string = "";
            foreach (array_merge($params['fields'], $other_required_fields) as $field)
            {
                $field_string = $field_string . $field . ",";
            }
            $field_string = rtrim($field_string, ',');
            $qparams->setFields($field_string);
        }

        $i = 1;
        $pages = 1;
        $qparams->setPageSize(128);
        $qparams->setPage($i);
        $qm = "queryRecords";
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        $recs = $sdkQuery->$qm($type, $qparams);
        while ($pages) {
            $records = $recs->getRecord();
            foreach($records as $r)
            {
                $record_array = array();

                foreach ($generated_fields as $field)
                {
                    switch($field)
                    {
                        case 'vm_id':
                            $result = $this->get_id_from_href($r->get_href() , 'vm');
                            break;
                        case 'vm_vapp_id':
                            $result = $this->get_id_from_href($r->get_container() , 'vapp');
                            break;
                        case 'vm_org_vdc_id':
                            $result = $this->get_id_from_href($r->get_vdc(), 'orgvdc');
                            break;
                        case 'vapp_id':
                            $result = $this->get_id_from_href($r->get_href() , 'vapp');
                            break;
                        case 'vapp_vcenter':
                            $result = $this->get_vcenter_of_vapp($this->get_id_from_href($r->get_href(),'vapp'));
                            break;
                        case 'vapp_org_id':
                            $result = $this->get_id_from_href($r->get_org(),"org");
                            break;
                        case 'vapp_org_vdc_id':
                            $result = $this->get_id_from_href($r->get_vdc(),"orgvdc");
                            break;
                        case 'vapp_template_id':
                            $result = $this->get_id_from_href($r->get_href() , 'vapptemplate');
                            break;
                        case 'org_vdc_id':
                            $result = $this->get_id_from_href($r->get_href() , 'orgvdc');
                            break;
                        case 'org_vdc_vcenter':
                            $result = $this->get_vcenter_of_orgvdc($this->get_id_from_href($r->get_href(),'orgvdc'));
                            break;
                        case 'org_vdc_provider_vdc_id':
                            $result = $this->get_id_from_href($r->get_providerVdc(),"providervdc");
                            break;
                        case 'provider_vdc_id':
                            $result = $this->get_id_from_href($r->get_href() , "providervdc");
                            break;
                        case 'vapp_power_status':
                            $result = $this->create_vapp_status_string($r->get_status() , $r->get_isDeployed());
                            break;
                        case 'origin_template_name':
                            $result = $this->get_vapp_origin_template_name($this->get_id_from_href($r->get_href(),'vapp'));
                            break;
                        case 'origin_template_id':
                            $result = $this->get_vapp_origin_template_id($this->get_id_from_href($r->get_href(),'vapp'));
                            break;
                        case 'href':
                            $result = $r->get_href();
                            break;
                        default:
                            throw new Exception("ERROR: Theres no generated field available called " . $field);
                    }
                    $record_array[$field] = $result;
                }

                foreach ($params['fields'] as $field)
                {
                    $function_name = 'get_' . $field;
                    $result = $r->{$function_name}();
                    $record_array[$field] = $result;
                }

                array_push($result_array, $record_array);
            }
            if (sizeof($records) < 128)
            {
                break;
            }
            $i++;
            $qparams->setPage($i);
            try {
                $recs = $sdkQuery->$qm($type, $qparams);
            }
            catch(Exception $e) {
                $pages = null;
            }
        }
        return $result_array;
    }

    function count_running_vapps($orgvdc_id = null, $org_name = null, $orgvdc_name = null)
    {
        global $service;

        // Get an array of power states that count as being on, for use later on

        $Vapp = ClassRegistry::init('Vapp');
        $vapp_powered_on_states = $Vapp->get_powered_on_states();
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // / Find the vapps under this org and orgvdc

        if ($org_name) {
            $qm = "queryRecords";
            $params = new VMware_VCloud_SDK_Query_Params();
            $params->setFields("name");
            $params->setFilter("name==" . $org_name);
            $type = "organization";
            $recs = $sdkQuery->$qm($type, $params);
            $orgRecords = $recs->getRecord();
            if (!$orgRecords) {
                echo "ERROR: Couldn't find an org of this name " . $org_name;
                return 1;
            }

            foreach($orgRecords as $org) {
                $org_href = $org->get_href();
            }
        }

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        if (isset($orgvdc_id)) {
            $params->setFilter("vdc==" . $orgvdc_id);
        }
        else if (isset($org_href)) {
            $params->setFilter("org==" . $org_href);
        }
        else if (isset($orgvdc_name)) {
            $params->setFilter("vdcName==" . urlencode($orgvdc_name));
        }
        else {
            return;
        }

        $params->setFields("status,isDeployed");
        $type = "adminVApp";
        $params->setPageSize(128); //128 is the max page size
        $i = 1; //
        $pages = 1; //This will remain 1 once the record are returned.
        $params->setPage($i);
        $recs = $sdkQuery->$qm($type, $params);
        $vapps_not_running = 0;
        $vapps_running = 0;
        while ($pages) {
            $vappRecords = $recs->getRecord();
            foreach($vappRecords as $vapp) {
                $vapp_status = $this->create_vapp_status_string($vapp->get_status() , $vapp->get_isDeployed());
                $vmsrunning = false;
                if (in_array($vapp_status, $vapp_powered_on_states)) {
                    $vmsrunning = true;
                }

                /*
                $vms = $this->list_vms_id_status($this->get_id_from_href($vapp->get_href(), "vapp"));
                $vmsrunning = false;
                foreach ($vms as $vm) {
                if ($vm['status'] == "POWERED_ON") {
                $vmsrunning = true;
                break;
                }
                }

                */
                if ($vmsrunning) {
                    $vapps_running++;
                }
                else {
                    $vapps_not_running++;
                }
            }

            if (count($vappRecords) < 128) {
                $pages = null;
            }
            else {
                $i++;
                $params->setPage($i);
                try {
                    $recs = $sdkQuery->$qm($type, $params);
                }

                catch(Exception $e) {

                    // echo 'Caught exception: ',  $e->getMessage(), "\n";

                    $pages = null;
                }
            }
        }

        $the_array['running'] = $vapps_running;
        $the_array['not_running'] = $vapps_not_running;
        return $the_array;
    }

    function delete_media($media_id = null, $username = null)
    {

        // $orgvdc_name=$this->get_orgvdc_name_by_vapp($vapp_id);
        // Add an event

        $Events = ClassRegistry::init('Events');
        $event_params = array(
            "function_name" => 'delete_media',
            "function_parameters" => $media_id,
            "object_vcd_id" => $media_id,
            "user_id" => $username
        );
        $Events->create();
        $Events->save($event_params);
        $delete_media_id = $Events->id;
        global $service;
        try {
            $media_ref = $this->get_href_from_id($media_id);
            $sdkMedia = $service->createSDKObj($media_ref);
            $task = $sdkMedia->delete();
            $this->wait_for_task($task);
        }

        catch(Exception $e) {
            $event_params = array(
                "value_returned" => 1
            );
            $Events->id = $delete_media_id;
            $Events->save($event_params);
            throw $e;
        }

        $event_params = array(
            "value_returned" => 0
        );
        $Events->id = $delete_media_id;
        $Events->save($event_params);
    }

    function list_media_in_catalog($catalog_name = null)
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $type = "adminMedia";

        // $params->setSortAsc('creationDate');

        $params->setPageSize(128); //128 is the max page size
        $params->setFields("name,status,creationDate,storageB");
        $params->setFilter('catalogName==' . urlencode($catalog_name));
        $i = 1; //
        $pages = 1; //This will remain 1 once the record are returned.
        $params->setPage($i);
        $recs = $sdkQuery->$qm($type, $params);
        while ($pages) {
            $mediaRecords = $recs->getRecord();
            foreach($mediaRecords as $media) {
                array_push($the_array, array(
                    "media_id" => $this->get_id_from_href($media->get_href() , "media") ,
                    "name" => $media->get_name() ,
                    "status" => $media->get_status() ,
                    "creation_date" => $media->get_creationDate() ,
                    "storage_used" => $media->get_storageB()
                ));
            }

            $i++;
            $params->setPage($i);
            try {
                $recs = $sdkQuery->$qm($type, $params);
            }

            catch(Exception $e) {

                // echo 'Caught exception: ',  $e->getMessage(), "\n";

                $pages = null;
            }
        }

        return $the_array;
    }

    function get_orgid_by_mediaid($media_id = null)
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $type = "adminMedia";
        $params->setFields("org");
        $params->setFilter('id==' . $media_id);
        $recs = $sdkQuery->$qm($type, $params);
        $mediaRecords = $recs->getRecord();
        foreach($mediaRecords as $media) {
            return ($this->get_id_from_href($media->get_org() , "org"));
        }

        return "blank";
    }

    // Add the partially powered off status if the vapp is deployed

    function create_vapp_status_string($status = "", $isDeployed = false)
    {
        if ($status == "POWERED_OFF" && $isDeployed) {
            $final_status = "PARTIALLY_POWERED_OFF";
        }
        else {
            $final_status = $status;
        }

        return $final_status;
    }

    # This function can be used to call the security policy vcli perl script, to check or set the security settings on the vm
    # It returns false if the settings arn't set, and true if they are set
    function security_policy_vm($vm_id, $operation)
    {
        $vcenter = $this->get_vcenter_of_vm($vm_id);
        $vm_id_stripped = str_replace("urn:vcloud:vm:","",$vm_id);
        $command = "/export/scripts/CLOUD/bin/vmware-vsphere-cli-distrib/apps/vm/security_policy.pl --vmname " . $vm_id_stripped . " --op " . $operation;
        $result = $this->run_vcli_command($command,$vcenter);
        if (strstr($result,"Security Policy Status: Incorrect"))
        {
            return false;
        }
        elseif (strstr($result,"Security Policy Status: Correct"))
        {
            return true;
        }
        else
        {
            throw new Exception("Got a value back that I didn't expect, when trying to run the security policy checker: " . $result);
        }
    }

    # This is a wrapper function around security_policy_vm, which checks the security policy on a vm
    # It returns a boolean indicating whether the security policy settings are set or not on the vm
    function check_security_policy_vm($vm_id)
    {
        return $this->security_policy_vm($vm_id, "check");
    }

    # This is a wrapper function around security_policy_vm, which sets the security policy on a vm
    # It contains the logic to make sure its only run against specific vms
    function set_security_policy_vm($vm_id)
    {
        $vm_details = $this->get_vm_power_and_name($vm_id);
        $security_policy_vms = array(
            'master_cloud-svc-1',
            'master_sc-1',
            'master_pl-3',
            'master_db1'
        );
        if ($vm_details['status'] === 'POWERED_ON' && (in_array($vm_details['name'], $security_policy_vms)))
        {
                $this->security_policy_vm($vm_id, "set");
        }
    }

    # This function loops through vms in a vapp, and returns an array of their names and security status
    function check_security_policy_status_vapp($vapp_id)
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields('name,status');
        $params->setFilter("container==" . $vapp_id);
        $params->setSortAsc('name');
        $params->setPageSize(128);
        $type = "adminVM";
        $recs = $sdkQuery->$qm($type, $params);
        $vmRecords = $recs->getRecord();
        foreach($vmRecords as $vm) {
            if ($vm->get_status() === 'POWERED_ON')
            {
                $vm_id = $this->get_id_from_href($vm->get_href() , 'vm');
                $security_policy_set=$this->check_security_policy_vm($vm_id);
            } else {
                $security_policy_set=false;
            }
            array_push($the_array, array(
                "name" => $vm->get_name(),
                "security_policy_set" => $security_policy_set
            ));
        }

        return $the_array;
    }

    # This function loops through vms in a vapp, and sets the security policy on the vms
    function set_security_policy_vapp($vapp_id)
    {
        global $service;
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields('name');
        $params->setFilter("container==" . $vapp_id);
        $params->setPageSize(128);
        $type = "adminVM";
        $recs = $sdkQuery->$qm($type, $params);
        $vmRecords = $recs->getRecord();
        foreach($vmRecords as $vm) {
            $vm_id = $this->get_id_from_href($vm->get_href() , 'vm');
            $this->set_security_policy_vm($vm_id);
        }
    }

    function list_vapps_id($containerID)
    {
        $filter = "";
        if ($containerID) {
            $filter = "vdc==" . $containerID;
        }
        $admin_vapp_params = array(
            'type' => "adminVApp",
            'fields' => array('name','status','vdc','vdcName','org','creationDate','numberOfVMs','isDeployed'),
            'generated_fields' => array('href'),
            'sortDesc' => 'creationDate',
            'filter' => $filter
            );
        $vappRecords = $this->query_service_request($admin_vapp_params);

        $the_array = array();
        foreach($vappRecords as $vapp) {
            $status = $this->create_vapp_status_string($vapp['status'] , $vapp['isDeployed']);
            $vapp_id = $this->get_id_from_href($vapp['href'], "vapp");
            array_push($the_array, array(
                "name" => $vapp['name'] ,
                "status" => $status,
                "href" => $vapp['href'] ,
                "vdc" => $vapp['vdc'] ,
                "vdc_name" => $vapp['vdcName'],
                "org" => $vapp['org'],
                "creation_date" => $vapp['creationDate'],
                "number_of_vms" => $vapp['numberOfVMs'],
                "orgvdc_id" => $this->get_id_from_href($vapp['vdc'], "orgvdc"),
                "vapp_id" => $vapp_id
            ));
        }
        return $the_array;
    }

    function list_vapptemplates($orgName, $vdcName)
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // Find the href of the org

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields("name");
        $params->setFilter("name==" . $orgName);
        $type = "organization";
        $recs = $sdkQuery->$qm($type, $params);
        $pvdcRecords = $recs->getRecord();
        foreach($pvdcRecords as $pvdc) {
            $org_href = $pvdc->get_href();
            break;
        }

        // /////////////////////////////////////////////////////
        // Find the href of the org vdc

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields("name");
        $params->setFilter("name==" . $vdcName);
        $type = "adminOrgVdc";
        $recs = $sdkQuery->$qm($type, $params);
        $pvdcRecords = $recs->getRecord();
        foreach($pvdcRecords as $pvdc) {
            $vdc_href = $pvdc->get_href();
            break;
        }

        // /////////////////////////////////////////////////////
        // / Find the vapps under this org and orgvdc

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();

        // $params->setFields("name,status,ownerName,org,vdc,creationDate");

        $params->setFilter("org==" . $org_href . ";vdc==" . $vdc_href);
        $type = "adminVAppTemplate";
        $recs = $sdkQuery->$qm($type, $params);
        $pvdcRecords = $recs->getRecord();
        foreach($pvdcRecords as $pvdc) {

            // $vms = $this->list_vms2($pvdc->get_name(), $vdcName);
            // echo $service->get($pvdc->get_href(),"",true)->get_status();
            // echo "</br>";
            // return;
            // echo $service->get($pvdc->get_href(),"",false);
            // return;

            array_push($the_array, array(
                "vapptemplate_name" => $pvdc->get_name() ,
                "status" => "",
                "vapptemplate_href" => $pvdc->get_href() ,
                "orgvdc_name" => $vdcName,
                "creation_date" => $pvdc->get_creationDate()
            ));
        }

        return $the_array;
    }

    function list_catalogs_by_org($orgid = null)
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // / Find the catalog under the given org.

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFilter("org==" . $this->get_href_from_id($orgid));
        $params->setFields('name');
        $params->setPageSize(128);
        $type = "adminCatalog";
        $recs = $sdkQuery->$qm($type, $params);
        $i = 1;
        $pages = 1;
        $params->setPage($i);
        while ($pages) {
            $catRecords = $recs->getRecord();
            foreach($catRecords as $cat) {
                array_push($the_array, array(
                    "name" => $cat->get_name()
                ));
            }

            $i++;
            $params->setPage($i);
            try {
                $recs = $sdkQuery->$qm($type, $params);
            }

            catch(Exception $e) {
                $pages = null;
            }
        }

        return $the_array;
    }

    function get_catalog_id_by_name($catalogName)
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // / Find the catalog under the given org.

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFilter("name==" . urlencode($catalogName));
        $params->setFields("name");
        $type = "adminCatalog";
        $recs = $sdkQuery->$qm($type, $params);
        $catRecords = $recs->getRecord();
        foreach($catRecords as $cat) {
            return $this->get_id_from_href($cat->get_href() , 'catalog');
        }

        return null;
    }

    function list_catalogs($orgName = "")
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // / Find the catalog under the given org.

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        if ($orgName != '') {
            $params->setFilter("orgName=='" . $orgName . "'");
        }

        $params->setFields('org,name');
        $params->setPageSize(128);
        $type = "adminCatalog";
        $recs = $sdkQuery->$qm($type, $params);
        $i = 1;
        $pages = 1;
        $params->setPage($i);
        while ($pages) {
            $catRecords = $recs->getRecord();
            foreach($catRecords as $cat) {
                array_push($the_array, array(
                    "catalog_name" => $cat->get_name() ,

                    // "is_published" => $cat->get_isPublished(),
                    // "number_of_templates" => $cat->get_numberOfTemplates(),
                    // "org_href" => $cat->get_org(),

                    "org_id" => $this->get_id_from_href($cat->get_org() , "org") ,
                    "vcd_id" => $this->get_id_from_href($cat->get_href() , "catalog")

                    // "organisation" => $cat->get_orgName(),
                    // "owner_name" => $cat->get_ownerName(),
                    // "creation_date" => date('d/m/Y H:i', strtotime($cat->get_creationDate()))

                ));
            }

            $i++;
            $params->setPage($i);
            try {
                $recs = $sdkQuery->$qm($type, $params);
            }

            catch(Exception $e) {
                $pages = null;
            }
        }

        return $the_array;
    }

    function get_orgid_from_catalog($catalogName)
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // / Find the catalog under the given org.

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFilter("name==" . urlencode($catalogName));
        $params->setFields("org,name");
        $type = "adminCatalog";
        $recs = $sdkQuery->$qm($type, $params);
        $catRecords = $recs->getRecord();
        foreach($catRecords as $cat) {
            array_push($the_array, array(
                "org_id" => $this->get_id_from_href($cat->get_org() , "org")
            ));
        }

        return $the_array;
    }

    function list_vapptemplates_catalog($catalogName)
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // / Find the vapps under catalog

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();

        // $params->setFields("name,status,ownerName,org,vdc,creationDate");

        $params->setFilter("catalogName==" . urlencode($catalogName));
        $params->setSortDesc('creationDate');
        $params->setPageSize(128); //128 is the max page size
        $i = 1; //
        $pages = 1;
        $params->setPage($i);
        $type = "adminVAppTemplate";
        $recs = $sdkQuery->$qm($type, $params);
        while ($pages) {
            $pvdcRecords = $recs->getRecord();
            foreach($pvdcRecords as $pvdc) {

                // $vms = $this->list_vms2($pvdc->get_name(), $vdcName);
                // echo $service->get($pvdc->get_href(),"",true)->get_status();
                // echo "</br>";
                // return;
                // echo $service->get($pvdc->get_href(),"",false);
                // return;

                array_push($the_array, array(
                    "vapptemplate_name" => $pvdc->get_name() ,
                    "status" => $pvdc->get_status() ,
                    "vapptemplate_href" => $pvdc->get_href() ,
                    "orgvdc_name" => $pvdc->get_vdcName() ,
                    "owner_name" => $pvdc->get_ownerName() ,
                    "creation_date" => date('d/m/Y H:i', strtotime($pvdc->get_creationDate())) ,
                    "vapptemplate_id" => $this->get_id_from_href($pvdc->get_href() , "vapptemplate") ,
                    "orgvdc_id" => $this->get_id_from_href($pvdc->get_vdc() , "orgvdc")
                ));
            }

            $i++;
            $params->setPage($i);
            try {
                $recs = $sdkQuery->$qm($type, $params);
            }

            catch(Exception $e) {

                // echo 'Caught exception: ',  $e->getMessage(), "\n";

                $pages = null;
            }
        }

        return $the_array;
    }

    // A function to power on an object Vm or vApp by passing it's URL

    function power_on_object($vm_href)
    {
        global $service;
        $sdkVapps = $service->createSDKObj($vm_href);
        $sdkVapps->powerOn();
        return 1;
    }

    function list_vms($vapp_name, $orgvdc_name)
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // Find the href of the org vdc

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields("name");
        $params->setFilter("name==" . $orgvdc_name);
        $type = "adminOrgVdc";
        $recs = $sdkQuery->$qm($type, $params);
        $pvdcRecords = $recs->getRecord();
        foreach($pvdcRecords as $pvdc) {
            $vdc_href = $pvdc->get_href();
        }

        // /////////////////////////////////////////////////////
        // Find the details of the vms

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields("name,vc,numberOfCpus,container,containerName,org,status,memoryMB,vdc");
        $params->setFilter("containerName==" . $vapp_name . ";vdc==" . $vdc_href);
        $type = "adminVM";
        $recs = $sdkQuery->$qm($type, $params);
        $pvdcRecords = $recs->getRecord();
        foreach($pvdcRecords as $pvdc) {

            // echo $pvdc->get_name() . "\t" . $pvdc->get_datastoreName() . "\n";
            // $status="2";

            array_push($the_array, array(
                "name" => $pvdc->get_name() ,
                "status" => $pvdc->get_status() ,
                "vm_href" => $pvdc->get_href() ,
                "vapp_href" => $pvdc->get_container() ,
                "cpu_count" => $pvdc->get_numberOfCpus() ,
                "memory_mb" => $pvdc->get_memoryMB() ,
                "vapp_name" => $pvdc->get_containerName()
            ));
        }

        return $the_array;
    }

    function list_orgs()
    {
        global $service;
        $the_array = array();

        // $this->login();

        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // Find the href of the org vdc

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setSortAsc('name');
        $params->setPageSize(128);
        $type = "organization";
        $recs = $sdkQuery->$qm($type, $params);
        $i = 1;
        $pages = 1;
        $params->setPage($i);
        while ($pages) {
            $orgRecords = $recs->getRecord();
            foreach($orgRecords as $org) {
                array_push($the_array, array(
                    "href" => $org->get_href() ,
                    "name" => $org->get_name() ,
                    "display_name" => $org->get_displayName() ,
                    "catalogs" => $org->get_numberOfCatalogs() ,
                    "vapps" => $org->get_numberOfVApps() ,
                    "vdcs" => $org->get_numberOfVdcs() ,
                    "deploy_vm_quota" => $org->get_deployedVMQuota() ,
                    "stored_vm_quota" => $org->get_storedVMQuota() ,
                    "org_id" => $this->get_id_from_href($org->get_href() , "org")
                ));
            }

            $i++;
            $params->setPage($i);
            try {
                $recs = $sdkQuery->$qm($type, $params);
            }

            catch(Exception $e) {
                $pages = null;
            }
        }

        return $the_array;
    }

    function list_orgvdcs($orgName = null)
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // Find the href of the org vdc

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setSortAsc('name');

        // if ($orgName != null) {
        //    $params->setFilter('org==' . $orgName);
        // }

        $params->setPageSize(128);
        $params->setFields('name');
        $params->setFilter('isSystemVdc==false');
        $type = "adminOrgVdc";
        $i = 1; //
        $pages = 1; //This will remain 1 once the record are returned.
        $params->setPage($i);
        $recs = $sdkQuery->$qm($type, $params);
        while ($pages) {
            $orgVdcRecords = $recs->getRecord();
            foreach($orgVdcRecords as $orgVcd) {
                $orgvdc_id = $this->get_id_from_href($orgVcd->get_href() , "orgvdc");
                array_push($the_array, array(
                    "name" => $orgVcd->get_name() ,
                    "orgvdc_id" => $orgvdc_id,
                    "vcenter" => $this->get_vcenter_of_orgvdc($orgvdc_id)
                ));
            }

            $i++;
            $params->setPage($i);
            try {
                $recs = $sdkQuery->$qm($type, $params);
            }

            catch(Exception $e) {
                $pages = null;
            }
        }

        return $the_array;
    }

    function list_orgvdcs_pay_as_you_go()
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setSortAsc('name');
        $params->setPageSize(128);
        $params->setFields('name');
        $params->setFilter('isSystemVdc==false');
        $type = "adminOrgVdc";
        $i = 1; //
        $pages = 1; //This will remain 1 once the record are returned.
        $params->setPage($i);
        $recs = $sdkQuery->$qm($type, $params);
        while ($pages) {
            $orgVdcRecords = $recs->getRecord();
            foreach($orgVdcRecords as $orgVcd) {

                // Create an sdk object
                $orgvdcSDK= $service->createSDKObj($orgVcd->get_href());

                // Create an admin sdk version of this
                $adminVdc = $orgvdcSDK->getAdminVdc();

                // Push the name, and allocation details into the array to be returned
                array_push($the_array, array(
                    "name" => $orgVcd->get_name(),
                    "allocationmodel" => $adminVdc->getAllocationModel(),
                    "resourceguaranteedmemory" => $adminVdc->getResourceGuaranteedMemory(),
                    "resourceguaranteedcpu" => $adminVdc->getResourceGuaranteedCpu()
                ));
            }

            $i++;
            $params->setPage($i);
            try {
                $recs = $sdkQuery->$qm($type, $params);
            }

            catch(Exception $e) {
                $pages = null;
            }
        }
        return $the_array;
    }

    function get_orgvdc_id_by_name($orgVdcName = null)
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // Find the href of the org vdc

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFilter('name==' . urlencode($orgVdcName));
        $params->setFields('name');
        $type = "adminOrgVdc";
        $recs = $sdkQuery->$qm($type, $params);
        $orgVdcRecords = $recs->getRecord();
        foreach($orgVdcRecords as $orgVcd) {
            array_push($the_array, array(
                "orgvdc_id" => $this->get_id_from_href($orgVcd->get_href() , "orgvdc")
            ));
        }

        return $the_array;
    }

    function set_cpu_count_vm($vm_id = null, $cpu_count = null, $username = null)
    {
        $vm_id = $this->vsphere_name_to_id($vm_id);
        $this->add_vapp_to_busy_cache($vm_id, 'Resizing VM CPU');
        global $service;

        $orgvdc = $this->get_orgvdc_id_by_vm($vm_id);
        $orgvdc_id = $orgvdc[0]['orgvdc_id'];
        $vapp_id = $this->get_vapp_id_by_vm($vm_id);
        $orgvdc_name = $this->get_orgvdc_name_by_vapp($vapp_id);

        $Events = ClassRegistry::init('Events');
        $event_params = array(
            "function_name" => 'set_cpu_count_vm',
            "function_parameters" => "destorgvdcname=" . $orgvdc_name . " " . $vapp_id . " " . $vm_id,
            "object_vcd_id" => $vm_id,
            "org_vdc_id" => $orgvdc_id,
            "user_id" => $username,
         );
        $Events->create();
        $Events->save($event_params);
        $set_cpu_count_vm_id = $Events->id;

        $vm_href = $this->get_href_from_id($vm_id);
        $sdkVm = $service->createSDKObj($vm_href);
        try {
             $cpu_obj = $sdkVm->getVirtualCpu();
             $cpu_string = new VMware_VCloud_API_OVF_cimString($cpu_count);
             $cpu_obj->setVirtualQuantity($cpu_string);
             # sleep for resize cpu testcase goes here #
             $task = $sdkVm->modifyVirtualCpu($cpu_obj);
             $this->wait_for_task($task);
         } catch(Exception $e) {
             $Events->id = $set_cpu_count_vm_id;
             $event_params = array(
                "value_returned" => 1,
                "message" => "VM Failure - Error During Resizing CPU"
             );
             $Events->save($event_params);
             $this->delete_vapp_from_busy_cache($vm_id);
             throw $e;
         }
        $Events->id = $set_cpu_count_vm_id;
        $event_params = array(
            "value_returned" => 0
        );
        $Events->save($event_params);
        $this->delete_vapp_from_busy_cache($vm_id);
    }

    function set_memory_vm($vm_id = null, $memory_mb = null, $username = null)
    {
        $vm_id = $this->vsphere_name_to_id($vm_id);
        $this->add_vapp_to_busy_cache($vm_id, 'Resizing VM Memory');
        global $service;

        $orgvdc = $this->get_orgvdc_id_by_vm($vm_id);
        $orgvdc_id = $orgvdc[0]['orgvdc_id'];
        $vapp_id = $this->get_vapp_id_by_vm($vm_id);
        $orgvdc_name = $this->get_orgvdc_name_by_vapp($vapp_id);

        $Events = ClassRegistry::init('Events');
        $event_params = array(
            "function_name" => 'set_memory_vm',
            "function_parameters" => "destorgvdcname=" . $orgvdc_name . " " . $vapp_id . " " . $vm_id,
            "object_vcd_id" => $vm_id,
            "org_vdc_id" => $orgvdc_id,
            "user_id" => $username,
         );
        $Events->create();
        $Events->save($event_params);
        $set_memory_vm_id = $Events->id;

        $vm_href = $this->get_href_from_id($vm_id);
        $sdkVm = $service->createSDKObj($vm_href);

        try{
            $mem_obj = $sdkVm->getVirtualMemory();
            $vq = $mem_obj->getVirtualQuantity(); // get quantity
            $vq->set_valueOf($memory_mb); // set to the new value
            $mem_obj->setVirtualQuantity($vq);
            # sleep for resize memory testcase goes here #
            $task = $sdkVm->modifyVirtualMemory($mem_obj);
            $this->wait_for_task($task);
        } catch(Exception $e) {
             $Events->id = $set_memory_vm_id;
             $event_params = array(
                "value_returned" => 1,
                "message" => "VM Failure - Error During Resizing Memory"
             );
             $Events->save($event_params);
             $this->delete_vapp_from_busy_cache($vm_id);
             throw $e;
         }
        $Events->id = $set_memory_vm_id;
        $event_params = array(
            "value_returned" => 0
        );
        $Events->save($event_params);
        $this->delete_vapp_from_busy_cache($vm_id);

    }


    function get_orgvdc_id_by_vapp($vapp_id = null)
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // Find the href of the org vdc

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFilter('id==' . $vapp_id);
        $params->setFields('vdc');
        $type = "adminVApp";
        $recs = $sdkQuery->$qm($type, $params);
        $orgVdcRecords = $recs->getRecord();
        foreach($orgVdcRecords as $orgVcd) {
            array_push($the_array, array(
                "orgvdc_id" => $this->get_id_from_href($orgVcd->get_vdc() , "orgvdc")
            ));
        }

        return $the_array;
    }

    function get_orgvdc_id_by_vapptemplate($vapptemplate_id = null)
    {
        global $service;
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFilter('id==' . $vapptemplate_id);
        $params->setFields('vdc');
        $type = "adminVAppTemplate";
        $recs = $sdkQuery->$qm($type, $params);
        $orgVdcRecords = $recs->getRecord();
        foreach($orgVdcRecords as $orgVcd) {
            return $this->get_id_from_href($orgVcd->get_vdc() , "orgvdc");
        }
    }

    function get_orgvdc_name_by_vapp($vapp_id = null)
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // $vapp_href = $this->get_href_from_id($vapp_id);
        // /////////////////////////////////////////////////////
        // Find the href of the org vdc

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFilter('id==' . $vapp_id);
        $params->setFields('vdcName');
        $type = "adminVApp";
        $recs = $sdkQuery->$qm($type, $params);
        $orgVdcRecords = $recs->getRecord();
        foreach($orgVdcRecords as $orgVcd) {
            return $orgVcd->get_vdcName();
        }
    }

    function get_orgvdc_id_by_vm($vm_id = null)
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // Find the href of the org vdc

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFilter('id==' . $vm_id);
        $params->setFields('vdc');
        $type = "adminVM";
        $recs = $sdkQuery->$qm($type, $params);
        $orgVdcRecords = $recs->getRecord();
        foreach($orgVdcRecords as $orgVcd) {
            array_push($the_array, array(
                "orgvdc_id" => $this->get_id_from_href($orgVcd->get_vdc() , "orgvdc")
            ));
        }

        return $the_array;
    }

    function get_org_id_by_vm($vm_id = null)
    {
        global $service;
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // Find the href of the org vdc

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFilter('id==' . $vm_id);
        $params->setFields('org');
        $type = "adminVM";
        $recs = $sdkQuery->$qm($type, $params);
        $orgVdcRecords = $recs->getRecord();
        foreach($orgVdcRecords as $orgVcd) {
            return $this->get_id_from_href($orgVcd->get_org() , "org");
        }

        return null;
    }

    function get_vapp_id_by_vm($vm_id = null)
    {
        global $service;
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // Find the href of the org vdc

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFilter('id==' . $vm_id);
        $params->setFields('container');
        $type = "adminVM";
        $recs = $sdkQuery->$qm($type, $params);
        $vappRecords = $recs->getRecord();
        foreach($vappRecords as $vapp) {
            return $this->get_id_from_href($vapp->get_container() , "vapp");
        }

        return null;
    }

    function get_catalog_id_by_vappTemplate($vappTemplate_id = null)
    {
        global $service;
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // Find the href of the org vdc

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFilter('id==' . $vappTemplate_id);
        $params->setFields('catalog');
        $type = "adminVAppTemplate";
        $recs = $sdkQuery->$qm($type, $params);
        $vappRecords = $recs->getRecord();
        foreach($vappRecords as $vapptemplate) {
            return $this->get_id_from_href($vapptemplate->get_catalog() , "catalog");
        }

        return null;
    }

    function get_id_from_href($href = null, $type = null)
    {
        return "urn:vcloud:" . $type . ":" . VMware_VCloud_SDK_Helper::getUuidByUrl($href);
    }

    function consolidate_vapp($vapp_id)
    {
        global $service;

        $params = array(
            'type' => "adminVM",
            'fields' => array('name'),
            'generated_fields' => array('href'),
            'filter' => "container==" . $vapp_id
        );
        $vms = $this->query_service_request($params);
        $tasks = array();
        foreach ($vms as $vm)
        {
            $sdkVm = $service->createSDKObj($vm['href']);
            $task = $sdkVm->consolidate();
            array_push($tasks, $task);
        }

        foreach($tasks as $task)
        {
            $this->wait_for_task($task);
        }
    }

    function consolidate_vapp_template($vapptemplateid = null, $username = null)
    {
        global $service;
        $vms = $this->list_vms_id($vapptemplateid);
        foreach($vms as $vm) {
            $sdkVm = $service->createSDKObj($vm['vm_href']);
            try {

                // Put this in the queue

                $this->wait_in_queue("vappConsolidateVm");
                $task = $sdkVm->consolidate();

                // Inform the queue manager that the task should be in vcloud now

                $this->task_in_vcloud("vappConsolidateVm");
            }

            catch(Exception $e) {

                // Inform the queue manager that the task should be in vcloud now

                $this->task_in_vcloud("vappConsolidateVm");
                throw $e;
            }

            $this->wait_for_task($task);
        }
    }

    function get_vapp_template_details($vapp_template_id)
    {
        global $service;
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields('name,catalogName,org');
        $type = "adminVAppTemplate";
        $params->setFilter('id==' . $vapp_template_id);
        $recs = $sdkQuery->$qm($type, $params);
        $vapptemplateRecords = $recs->getRecord();
        foreach($vapptemplateRecords as $vapptemplate) {
            return array(
                "name" => $vapptemplate->get_name() ,
                "org_id" => $this->get_id_from_href($vapptemplate->get_org() , "org") ,
                "catalog_name" => $vapptemplate->get_catalogName()
            );
        }

        return null;
    }

    function get_org_id_by_vapp_id($vapp_id)
    {
        global $service;
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields('org');
        $type = "adminVApp";
        $params->setFilter('id==' . $vapp_id);
        $recs = $sdkQuery->$qm($type, $params);
        $vappRecords = $recs->getRecord();
        foreach($vappRecords as $vapp) {
            return $this->get_id_from_href($vapp->get_org() , "org");
        }

        return null;
    }

    function get_vapp_details($vapp_id)
    {
        global $service;
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields('name,vdc');
        $type = "adminVApp";
        $params->setFilter('id==' . $vapp_id);
        $recs = $sdkQuery->$qm($type, $params);
        $vappRecords = $recs->getRecord();
        foreach($vappRecords as $vapp) {
            return array(
                "name" => $vapp->get_name() ,
                "orgvdc_id" => $this->get_id_from_href($vapp->get_vdc() , "orgvdc")
            );
        }

        return null;
    }

    function aasort(&$array, $key)
    {
        $sorter = array();
        $ret = array();
        reset($array);
        foreach($array as $ii => $va) {
            $sorter[$ii] = $va[$key];
        }

        asort($sorter);
        foreach($sorter as $ii => $va) {
            $ret[$ii] = $array[$ii];
        }

        $array = $ret;
    }

    function get_vapp_networks_external($vapp_id = null)
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields('name');
        $type = "adminVAppNetwork";
        $params->setFilter('vApp==' . $vapp_id . ';isIpScopeInherited==1');
        $recs = $sdkQuery->$qm($type, $params);
        $vappRecords = $recs->getRecord();
        foreach($vappRecords as $vapp) {
            array_push($the_array, array(
                "name" => $vapp->get_name()
            ));
        }

        return $the_array;
    }

    function get_vapp_networks_internal($vapp_id = null)
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);
        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields('name');
        $type = "adminVAppNetwork";
        $params->setFilter('vApp==' . $vapp_id . ';isIpScopeInherited==0');
        $recs = $sdkQuery->$qm($type, $params);
        $vappRecords = $recs->getRecord();
        foreach($vappRecords as $vapp) {
            array_push($the_array, array(
                "name" => $vapp->get_name()
            ));
        }

        $this->aasort($the_array, 'name');
        return $the_array;
    }

    function get_network_details_vm($vmid = null, $usecache = true)
    {
        if ($usecache === false || ($value = Cache::read('network_details_vm_' . $vmid, 'network_details_vm_cache')) === false) {
        }
        else {
            return $value;
        }

        global $service;
        $sdkVM = $service->createSDKObj($this->get_href_from_id($vmid));
        $net = $sdkVM->getNetworkConnectionSettings();
        $cons = $net->getNetworkConnection();
        $the_array = array();
        foreach($cons as $ncs) {
            array_push($the_array, array(
                "network_name" => $ncs->get_network() ,
                "nic_no" => $ncs->getNetworkConnectionIndex() ,
                "ipaddress" => $ncs->getIpAddress() ,
                "macaddress" => $ncs->getMacAddress()
            ));
        }

        $this->aasort($the_array, 'nic_no');
        Cache::write('network_details_vm_' . $vmid, $the_array, 'network_details_vm_cache');
        return $the_array;
    }

    function get_disk_details_vm($vmid = null)
    {

        // if (($value = Cache::read('disk_details_vm_' . $vmid,'disk_details_vm_cache')) === false) {
        //        }
        // else
        // {
        //         return $value;
        // }

        global $service;
        $sdkVM = $service->createSDKObj($this->get_href_from_id($vmid));
        $disks = $sdkVM->getVirtualDisks();
        $items = $disks->getItem();
        $the_array = array();
        $controller_no = "X";
        foreach($items as $item) {
            if (6 == $item->getResourceType()->get_valueOf()) {
                $controller_no = $item->getAddress()->get_valueOf();
            }

            $resources = $item->getHostResource();
            foreach($resources as $resource) {
                $attributes = $resource->get_anyAttributes();
                array_push($the_array, array(
                    'controller_no' => $controller_no,
                    'disk_no' => $item->getAddressOnParent()->get_valueOf() ,
                    'disk_capacity_mb' => $attributes['capacity']
                ));
            }
        }

        // Cache::write('disk_details_vm_' . $vmid,$the_array,'disk_details_vm_cache');

        return $the_array;
    }

    # This function deletes all disks on a vm, except for those specified as the ones you wish to keep
    # The format of the disks to keep parameter is, multiple disks/controllers seperated by commas.
    # Each disk/controller is seperated by a colon
    # ie. <controller>:<disk>,<controller>:disk>,<controller>:disk>
    # eg. 0:0,1:3,1:4,1:13
    function delete_disks_vm($vmid = null, $disks_to_keep = "")
    {
        # Parse the disks to keep string into an array that can be easily queried later
        $disk_and_controller_array = array();
        $disk_split = explode(",", $disks_to_keep);
        foreach ($disk_split as $disk)
        {
            $disk_controller_split = explode(":", $disk);
            $disk_and_controller_array[$disk_controller_split[0]][$disk_controller_split[1]] = true;
        }

        global $service;
        $sdkVM = $service->createSDKObj($this->get_href_from_id($vmid));
        $disks = $sdkVM->getVirtualDisks();
        $items = $disks->getItem();
        $controller_no = "X";
        $made_changes = false;
        foreach($items as $key => &$item) {
            if (6 == $item->getResourceType()->get_valueOf()) {
                $controller_no = $item->getAddress()->get_valueOf();
            }
            $resources = $item->getHostResource();
            foreach($resources as $resource) {
                $disk_no = $item->getAddressOnParent()->get_valueOf();
                if (!isset($disk_and_controller_array[$controller_no][$disk_no]))
                {
                    # We want to delete this disk so unset it
                    unset($items[$key]);
                    $made_changes = true;
                }
            }
        }
        # If any disks were removed, make the change and wait for the task to complete
        if ($made_changes)
        {
            $disks->setItem($items);
            $task = $sdkVM->modifyVirtualDisks($disks);
            $this->wait_for_task($task);
        }
    }

    function get_vapp_startup_settings($vapp_id)
    {
        global $service;
        $the_array = array();
        $vapp_id_updated = str_replace("vapptemplate", "vapp", $vapp_id);
        $sdkVApp = $service->createSDKObj($this->get_href_from_id($vapp_id_updated));
        $sdkVAppStartSettings = $sdkVApp->getStartupSettings();
        $vm_startup_items = $sdkVAppStartSettings->getItem();

        foreach ($vm_startup_items as $vm_startup_item)
        {
            $attributes = $vm_startup_item->get_anyAttributes();
            array_push($the_array, array(
                "name" => $attributes['id'],
                "order" => $attributes['order'],
                "stop_delay" => $attributes['stopDelay'],
                "stop_action" => $attributes['stopAction'],
                "start_delay" => $attributes['startDelay'],
                "start_action" => $attributes['startAction']
            ));
        }
        return $the_array;
    }

    function list_vms_id_network($parent_id)
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // Find the details of the vms

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields('containerName,name');
        if (isset($parent_id)) {
            $params->setFilter("container==" . $parent_id);
        }

        $params->setSortAsc('name');
        $params->setPageSize(128);
        $type = "adminVM";
        $recs = $sdkQuery->$qm($type, $params);
        $pvdcRecords = $recs->getRecord();
        foreach($pvdcRecords as $pvdc) {
            $vm_id = $this->get_id_from_href($pvdc->get_href() , "vm");

            // $vm_id = $service->createSDKObj($pvdc->get_href())->getVm()->get_id();
            // $vmSDK = $service->createSDKObj($pvdc->get_href())->getScreenThumbnailImage("/opt/bitnami/apache2/htdocs/app/webroot/test.png");
            // debug($vmSDK);

            array_push($the_array, array(
                "network_details" => $this->get_network_details_vm($vm_id, true) ,
                "name" => $pvdc->get_name() ,
                "vapp_name" => $pvdc->get_containerName()

                // "thumbnail" => $vm_thumb

            ));
        }

        return $the_array;
    }

    function run_vcli_command($command = "", $vcenter = "")
    {
        Configure::load('vcli_config');
        $vcli_settings = Configure::read('vcli_settings');
        $connection = ssh2_connect($vcli_settings['hostname'], 22);
        ssh2_auth_password($connection, $vcli_settings['username'], $vcli_settings['password']);
        $full_command = "/export/scripts/CLOUD/bin/run_vcli_command.sh -r '" . $command . "' -v " . $vcenter;
        $stream = ssh2_exec($connection, $full_command . ';EXIT_CODE=$?;echo "The exit code was $EXIT_CODE"');
        stream_set_blocking($stream, true);
        $returned_value = '';
        while ($buffer = fread($stream, 4096)) {
            $returned_value.= $buffer;
        }

        fclose($stream);
        if (!strstr($returned_value, "The exit code was 0")) {
            throw new Exception("Something went wrong running the vcli command, heres the output: " . $returned_value);
        }
        return $returned_value;
    }

    function get_ids_from_gateway_and_vm($gateway_ip, $search_string)
    {
        $vapp_id = $this->get_vapp_id_by_gateway_ip($gateway_ip);
        if ($vapp_id == null) {
            throw new Exception("Couldnt find a vapp with this ip " . $gateway_ip);
        }

        $vms = $this->list_vms_id($vapp_id);
        $found_id = null;
        $found_count = 0;
        foreach($vms as $vm) {
            if (strstr($vm['name'], $search_string)) {
                $found_id = $vm['vm_id'];
                $found_count++;
            }
        }

        if ($found_count === 0) {
            throw new Exception("Couldnt find a vm matching this string");
        }
        elseif ($found_count > 1) {
            throw new Exception("Found multiple vms matching this string");
        }

        return array(
            "vapp_id" => $vapp_id,
            "vm_id" => $found_id
        );
    }

    function get_vm_power_status($vm_id)
    {
        global $service;
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // Find the details of the vm

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFilter('id==' . $vm_id);
        $params->setFields('status,isDeployed');
        $type = "adminVM";
        $recs = $sdkQuery->$qm($type, $params);
        $vmRecords = $recs->getRecord();
        foreach($vmRecords as $vm) {
            return $this->create_vapp_status_string($vm->get_status() , $vm->get_isDeployed());
        }
        return null;
    }

    function check_power_vm($vm_id)
    {
        $vm_id = $this->vsphere_name_to_id($vm_id);
        global $service;
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // Find the details of the vms

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields('status');
        $params->setFilter("id==" . $vm_id);
        $type = "adminVM";
        $recs = $sdkQuery->$qm($type, $params);
        $vmRecords = $recs->getRecord();
        foreach($vmRecords as $vm) {
            return $vm->get_status();
        }
    }

    function get_vm_power_and_name($vm_id)
    {
        global $service;
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // Find the details of the vms

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields('name,status');
        $params->setFilter("id==" . $vm_id);
        $type = "adminVM";
        $recs = $sdkQuery->$qm($type, $params);
        $vmRecords = $recs->getRecord();
        foreach($vmRecords as $vm) {
            return array(
                "name" => $vm->get_name() ,
                "status" => $vm->get_status() ,
            );
        }
    }

    function get_vm_name($vm_id)
    {
        global $service ;
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // Find the details of the vms

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields('name');
        $params->setFilter("id==" . $vm_id);
        $type = "adminVM";
        $recs = $sdkQuery->$qm($type, $params);
        $vmRecords = $recs->getRecord();
        foreach($vmRecords as $vm) {
            return $vm->get_name() ;
        }
    }

    function list_vms_id($parent_id)
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // Find the details of the vms

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields('name,status,container,numberOfCpus,memoryMB,containerName,org,vdc');
        if (isset($parent_id)) {
            $params->setFilter("container==" . $parent_id);
        }

        $params->setSortAsc('name');
        $params->setPageSize(128);
        $type = "adminVM";
        $recs = $sdkQuery->$qm($type, $params);
        $pvdcRecords = $recs->getRecord();
        foreach($pvdcRecords as $pvdc) {
            $vm_id = $this->get_id_from_href($pvdc->get_href() , "vm");
            // $vm_id = $service->createSDKObj($pvdc->get_href())->getVm()->get_id();
            // $vmSDK = $service->createSDKObj($pvdc->get_href())->getScreenThumbnailImage("/opt/bitnami/apache2/htdocs/app/webroot/test.png");
            // debug($vmSDK);

            array_push($the_array, array(
                "name" => $pvdc->get_name() ,
                "status" => $pvdc->get_status() ,
                "vm_href" => $pvdc->get_href() ,
                "vm_id" => $vm_id,
                "vapp_href" => $pvdc->get_container() ,
                "cpu_count" => $pvdc->get_numberOfCpus() ,
                "memory_mb" => $pvdc->get_memoryMB() ,
                "vapp_name" => $pvdc->get_containerName() ,
                "org" => $pvdc->get_org() ,
                "vapp_id" => $this->get_id_from_href($pvdc->get_container() , "vapp") ,
                "vdc" => $pvdc->get_vdc()

                // "thumbnail" => $vm_thumb

            ));
        }

        return $the_array;
    }

    function get_console_details($vm_id)
    {
        global $service;
        global $vcd_config;
        global $spp_hostname;
        $spp_hostname = php_uname('n');
        try {
            $sdkVM = $service->createSDKObj($this->get_href_from_id($vm_id));
            $screenTokens = $sdkVM->getScreenTicketTokens();
        }

        catch(Exception $e) {
            return null;
        }

        $screenTokens['vapp_name'] = $sdkVM->getContainerVApp()->get_name();
        $screenTokens['vapp_id'] = $sdkVM->getContainerVApp()->get_id();
        $screenTokens['vm_name'] = $sdkVM->getVm()->get_name();
        $screenTokens['vmrc_exe_url'] = "https://" . $spp_hostname . "/files/vmrc/VMware-ClientIntegrationPlugin-6.2.0.exe";
        $screenTokens['vmrc_exe_url_linux_x86_64'] = "https://" . $spp_hostname . "/files/vmrc/VMware-ClientIntegrationPlugin-6.2.0.x86_64.bundle";
        $screenTokens['vmrc_exe_url_linux_i386'] = "https://" . $spp_hostname . "/files/vmrc/VMware-ClientIntegrationPlugin-6.2.0.i386.bundle";
        return $screenTokens;
    }

    function list_vms_id_name_and_href($parent_id)
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // Find the details of the vms

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFields("name");
        if (isset($parent_id)) {
            $params->setFilter("container==" . $parent_id);
        }

        $params->setSortAsc('name');
        $params->setPageSize(128);
        $type = "adminVM";
        $recs = $sdkQuery->$qm($type, $params);
        $pvdcRecords = $recs->getRecord();
        foreach($pvdcRecords as $pvdc) {

            // $vm_id =  $this->get_id_from_href($pvdc->get_href(),"vm");
            // $vm_id = $service->createSDKObj($pvdc->get_href())->getVm()->get_id();
            // $vmSDK = $service->createSDKObj($pvdc->get_href())->getScreenThumbnailImage("/opt/bitnami/apache2/htdocs/app/webroot/test.png");
            // debug($vmSDK);

            array_push($the_array, array(
                "name" => $pvdc->get_name() ,
                "vm_href" => $pvdc->get_href()
                // "thumbnail" => $vm_thumb

            ));
        }

        return $the_array;
    }

    function list_vms_id_status($parent_id)
    {
        global $service;
        $the_array = array();
        $sdkQuery = VMware_VCloud_SDK_Query::getInstance($service);

        // /////////////////////////////////////////////////////
        // Find the details of the vms

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();

        // $params->setFields("name,vc,numberOfCpus,containerName,org,status,memoryMB,vdc");

        if (isset($parent_id)) {
            $params->setFilter("container==" . $parent_id);
        }

        $params->setSortAsc('name');
        $params->setPageSize(128);
        $params->setFields('status');
        $type = "adminVM";
        $recs = $sdkQuery->$qm($type, $params);
        $pvdcRecords = $recs->getRecord();
        foreach($pvdcRecords as $pvdc) {

            // $vm_id = $service->createSDKObj($pvdc->get_href())->getVm()->get_id();
            // $vmSDK = $service->createSDKObj($pvdc->get_href())->getScreenThumbnailImage("/opt/bitnami/apache2/htdocs/app/webroot/test.png");
            // debug($vmSDK);

            array_push($the_array, array(
                "status" => $pvdc->get_status()
            ));
        }

        return $the_array;
    }

    function get_vapp_status($vapp_id)
    {

        // /////////////////////////////////////////////////////
        // / Find the vapp by it's id

        $qm = "queryRecords";
        $params = new VMware_VCloud_SDK_Query_Params();
        $params->setFilter("vapp==" . $vapp_id);
        $type = "adminVApp";
        $recs = $sdkQuery->$qm($type, $params);
        $vappRecords = $recs->getRecord();
        foreach($vappRecords as $vapp) {

            // $vms = $this->list_vms2($pvdc->get_name(), $vdcName);

            $status = $service->get($vapp->get_href() , "", true)->get_status();
        }

        return $status;
    }

    function report_exception($action, $exception, $current_try, $max_retries, $verbose_details)
    {
        $spp_shortname = strtok(shell_exec('hostname -s'), "\n");
        $spp_hostname = strtok(shell_exec('hostname -f'), "\n");
        $message = "Cloud Portal: " . $spp_hostname . ". Attempt " . $current_try . " of " . $max_retries . ". An exception was thrown calling " . $action . "\n\nDetails: " . $verbose_details . "\n\nHere is the exception information:\n" . $exception;
        CakeLog::write('retry', $message);

        // Read email list from config file

        Configure::load('admin_email_list');
        $mail_list = Configure::read('list');
        $email = new CakeEmail();
        $email->from(array(
            'no_reply@ericsson.com' => 'Cloud Portal'
        ));
        $email->to($mail_list);
        $email->subject('Cloud Portal: ' . $spp_shortname . ' - ' . $action . ' failed - Attempt ' . $current_try . " of " . $max_retries);
        $email->send($message);
    }

    function reset_leases($vapp_id){
        $Yama = Configure::read('Yama');
        Configure::load('user_details');
        $user_details = Configure::read('yama');
        $spp_hostname = strtok(shell_exec('hostname -f'), "\n");
        $url = 'http://' . $spp_hostname . ':8080' . $Yama['VAPP-Reset'];
        $runtimeLease = array(
             "Lease" => array(
                   "host_id" => $vapp_id,
                   "lease_type_id" => "2"
              )
        );
        $storageLease = array(
             "Lease" => array(
                   "host_id" => $vapp_id,
                   "lease_type_id" => "1"
             )
        );
        // Runtime Lease
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $user_details['username'] . ':' . $user_details['password']);
        curl_setopt($ch, CURLOPT_PROXY, '');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($runtimeLease));
        $content = curl_exec($ch);
        $response = curl_getinfo($ch);
        curl_close($ch);
        $resout = json_decode($content);

        if ($response['http_code'] != 200){
             CakeLog::write('error', 'Problem in Resetting the Runtime Lease for the vApp ' . $response['http_code'] . ' content ' . $content);
        }
        // Storage Lease
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $user_details['username'] . ':' . $user_details['password']);
        curl_setopt($ch, CURLOPT_PROXY, '');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                  'Content-Type: application/json',
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($storageLease));
        $content = curl_exec($ch);
        $response = curl_getinfo($ch);
        curl_close($ch);
        $resout = json_decode($content);

        if ($response['http_code'] != 200) {
             CakeLog::write('error', 'Problem in Resetting the Storage Lease for the vApp ' . $response['http_code'] . ' content ' . $content);
        }
    }

    function list_vapps_resourses($ID=null)
    {
        $filter = "";
        if(isset($ID)){
            if(strstr($ID, "orgvdc")){
                $filter = "isVAppTemplate==false";
                $filter = $filter . ';vdc==' . $ID;

            } elseif(strstr($ID, "vapptemplate")){
                $filter = $filter . 'container==' . $ID;
            } else {
                $filter = "isVAppTemplate==false";
                $filter = $filter . ';container==' . $ID;
            }
        }
        $admin_vm_params = array(
            'type' => "adminVM",
            'fields' => array('status','numberOfCpus','memoryMB','container'),
            'filter' => $filter
            );
        $all_vms_in_dataCenter = $this->query_service_request($admin_vm_params);

        $vapp_hrefs = array();
        foreach ($all_vms_in_dataCenter as $vm)
        {
            if(!in_array($vm['container'], $vapp_hrefs, true))
            {
                array_push($vapp_hrefs, $vm['container']);
            }
        }
        $result = array();
        foreach($vapp_hrefs as $vapp_href) {
            $cpuOnCount = 0;
            $cpuTotal = 0;
            $memoryOnCount = 0;
            $memoryTotal = 0;
            foreach($all_vms_in_dataCenter as $vm){
                if ($vm['container'] == $vapp_href) {
                    if ($vm['status'] == "POWERED_ON"){
                        $cpuOnCount += $vm['numberOfCpus'];
                        $memoryOnCount += $vm['memoryMB'];
                    }
                    $cpuTotal += $vm['numberOfCpus'];
                    $memoryTotal += $vm['memoryMB'];
                }
            }
            array_push($result, array(
                "vapp_id" => $this->get_id_from_href($vapp_href, "vapp"),
                "cpu_on_count" => $cpuOnCount,
                "cpu_total" => $cpuTotal,
                "memory_on_count" => $memoryOnCount,
                "memory_total" => $memoryTotal
            ));
        }
        return $result;
    }

    function list_vapptemplates_resourses($catalog_name)
    {
        $admin_vm_params = array(
            'type' => "adminVM",
            'fields' => array('numberOfCpus','memoryMB','container'),
            'filter' => "catalogName==" . urlencode($catalog_name)
        );
        $all_vms_in_catalog = $this->query_service_request($admin_vm_params);

        $vapptemplate_hrefs = array();
        foreach ($all_vms_in_catalog as $vm)
        {
            if(!in_array($vm['container'], $vapptemplate_hrefs, true))
            {
                array_push($vapptemplate_hrefs, $vm['container']);
            }
        }
        $result = array();
        foreach($vapptemplate_hrefs as $vapptemplate_href) {
            $cpuTotal = 0;
            $memoryTotal = 0;
            foreach($all_vms_in_catalog as $vm){
                if ($vm['container'] == $vapptemplate_href) {
                    $cpuTotal += $vm['numberOfCpus'];
                    $memoryTotal += $vm['memoryMB'];
                }
            }
            array_push($result, array(
                "vapp_template_id" => $this->get_id_from_href($vapptemplate_href, "vapptemplate"),
                "cpu_total" => $cpuTotal,
                "memory_total" => $memoryTotal
            ));
        }
        return $result;
    }

    function list_all_orgvdc_resourses()
    {
        $admin_vm_params = array(
            'type' => "adminVM",
            'fields' => array('status','numberOfCpus','memoryMB','vdc'),
            'filter' => "isVAppTemplate==false"
            );
        $all_vms = $this->query_service_request($admin_vm_params);
        $orgvdc_hrefs = array();
        foreach ($all_vms as $vm)
        {
            if(!in_array($vm['vdc'], $orgvdc_hrefs, true))
            {
                array_push($orgvdc_hrefs, $vm['vdc']);
            }
        }
        $result = array();
        foreach($orgvdc_hrefs as $orgvdc_href) {
            $cpuOnCount = 0;
            $cpuTotal = 0;
            $memoryOnCount = 0;
            $memoryTotal = 0;
            foreach($all_vms as $vm){
                if ($vm['vdc'] == $orgvdc_href) {
                    if ($vm['status'] == "POWERED_ON"){
                        $cpuOnCount += $vm['numberOfCpus'];
                        $memoryOnCount += $vm['memoryMB'];
                    }
                    $cpuTotal += $vm['numberOfCpus'];
                    $memoryTotal += $vm['memoryMB'];
                }
            }
            array_push($result, array(
                "orgvdc_id" => $this->get_id_from_href($orgvdc_href, "orgvdc"),
                "cpu_on_count" => $cpuOnCount,
                "cpu_total" => $cpuTotal,
                "memory_on_count" => $memoryOnCount,
                "memory_total" => $memoryTotal
            ));
        }
        return $result;
    }

    function list_vm_resourses($vm_id)
    {
        $admin_vm_params = array(
            'type' => "adminVM",
            'fields' => array('status','numberOfCpus','memoryMB'),
            'filter' => "id==" . $vm_id
        );
        $all_vms = $this->query_service_request($admin_vm_params);
        $cpuOnCount = 0;
        $cpuTotal = 0;
        $memoryOnCount = 0;
        $memoryTotal = 0;
        foreach($all_vms as $vm){
            if ($vm['status'] == "POWERED_ON"){
                $cpuOnCount += $vm['numberOfCpus'];
                $memoryOnCount += $vm['memoryMB'];
            }
            $cpuTotal += $vm['numberOfCpus'];
            $memoryTotal += $vm['memoryMB'];
        }
        return array(
            "cpu_on_count" => $cpuOnCount,
            "cpu_total" => $cpuTotal,
            "memory_on_count" => $memoryOnCount,
            "memory_total" => $memoryTotal
        );
    }

    /*
    * Get status on starting a cloud instance in a datacenter to establisg can instance be started within datacenter resourses
    */
    function allowed_power_on_vapp_resources($orgvdc_id, $vappID){

        $vapp_resources = $this->list_vapps_resourses($orgvdc_id);
        $vapp = $this->list_vapps_resourses($vappID);
        $vapp_total_cpu_required = ($vapp[0]['cpu_total'] - $vapp[0]['cpu_on_count']);
        $vapp_total_memory_required = ($vapp[0]['memory_total'] - $vapp[0]['memory_on_count']);
        $data_center_used_memory = 0;
        $data_center_used_cpu = 0;
        foreach($vapp_resources as $vapp_resource){
            $data_center_used_memory += $vapp_resource['memory_on_count'];
            $data_center_used_cpu += $vapp_resource['cpu_on_count'];
        }

        $cpu_total_if_poweron = ($data_center_used_cpu + $vapp_total_cpu_required);
        $memory_total_if_poweron = ($data_center_used_memory + $vapp_total_memory_required) / 1024;

        $OrgVdc = ClassRegistry::init('OrgVdc');
        $db_entry = $OrgVdc->find('first', array('conditions' => array('OrgVdc.vcd_id' => $orgvdc_id), 'fields' => array('cpu_limit','memory_limit'),'contain' => false));

        if( ($cpu_total_if_poweron > $db_entry['OrgVdc']['cpu_limit']) || ($memory_total_if_poweron >  $db_entry['OrgVdc']['memory_limit']) ){
            return false;
        }
        return true;
    }
}

?>
