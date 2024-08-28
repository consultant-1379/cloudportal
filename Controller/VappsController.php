<?php

class VappsController extends AppController {

    var $components = array('Session');
    var $uses = array('Vcloud', 'Vapp', 'OrgVdc', 'Catalog');

    //public $helpers = array('Js' => array('Jquery'));

    public function beforeFilter() {
        parent::beforeFilter();
        $this->Auth->allow("import", "network", "tasks");
    }

    function isAuthorized($user) {
        if ($user['is_admin']) {
            return true;
        }

        if (in_array($this->action, array('logintest'))) {
            return true;
        }

        // Read actions
        if (in_array($this->action, array('index', 'index_api', 'quotas','newquotas', 'is_vapp_being_created_api'))) {
            $orgvdc_id = $this->passedArgs['orgvdc_id'];

            // If they have read access
            if (isset($user['permissions'][$orgvdc_id]['read_permission']) && $user['permissions'][$orgvdc_id]['read_permission']) {
                return true;
            }
        }

        // Write actions
        if (in_array($this->action, array('power', 'delete', 'edit', 'share', 'unshare', 'destroy_vapp', 'recompose_vapp', 'destroy_vapp_api', 'stop_vapp_api', 'suspend_vapp_api', 'stop_and_add_to_catalog_api', 'rename', 'recompose_vapp_api'))) {
            $orgvdc = $this->Vcloud->get_orgvdc_id_by_vapp($this->passedArgs['vapp_id']);
            $orgvdc_id = $orgvdc[0]['orgvdc_id'];

            //orgvdc admin check
            if (isset($user['permissions'][$orgvdc_id]['admin_permission']) && $user['permissions'][$orgvdc_id]['admin_permission']) {
                return true;
            }

            if (isset($user['permissions'][$orgvdc_id]['write_permission']) && $user['permissions'][$orgvdc_id]['write_permission']) {

                // So they have write permission to this orgvdc, check if they have write access to this vapp
                $this->Vapp->id = $this->passedArgs['vapp_id'];
                $db_vapp = $this->Vapp->read();

                if (in_array($this->action, array('share', 'unshare'))) {

                    if ($db_vapp['Vapp']['created_by_id'] == $user['username']) {
                        return true;
                    }
                }
                // If its not share or unshare, they can either be the vapp owner, or the vapp can be shared to allow access
                else {
                    if ($db_vapp['Vapp']['created_by_id'] == $user['username'] || $db_vapp['Vapp']['shared'] == "1") {
                        // Let them change it
                        return true;
                    }
                }
            }
        }
        //write actions for the catalog
        if (in_array($this->action, array('add', 'add_to_catalog_api', 'stop_and_add_to_catalog_api'))) {

            if (!empty($this->request->data)) {
                if (isset($this->request->data['Vapp']['catalog'])) {
                    // Get the org id from the catalog name, for the permission check
                    $org_result = $this->Vcloud->get_orgid_from_catalog($this->request->data['Vapp']['catalog']);
                    $org_id = $org_result[0]['org_id'];
                    if ($user['permissions'][$org_id]['write_permission']) {
                        return true;
                    }
                } else {
                    // Let them in, the validation should complain to them that they must set an orgvdc
                    return true;
                }
            } else {
                // Allow them to view the deploy page itself when no data comes in
                return true;
            }
        }
        // Default to not allowing them access
        return false;
    }

    function logintest() {
        $obj = "Success";
        $this->set('login', $obj);
        $this->set('_serialize', array('login'));
    }

    function destroy_vapp() {
        $vappID = $this->passedArgs['vapp_id'];
        $vapp_name = $this->Vcloud->get_vapp_name($vappID);

        $busyStatus = $this->Vcloud->is_vapp_or_any_vm_busy($vappID) ;
        if ($busyStatus != false){
            $this->Session->setFlash('This vApp "' . $vapp_name .  '" is unable to power off and delete because it is busy "' . $busyStatus . '"', 'flash_bad') ;
            $this->redirect($this->referer());
        }
        $this->Vcloud->add_vapp_to_busy_cache($vappID, 'Stopping');
        $user = $this->Auth->user();
        $app_path = dirname(APP) . "/" . basename(APP);
        $cmd = "/opt/bitnami/apache2/htdocs/lib/Cake/Console/cake -app $app_path vcloud --function=destroy_vapp --vapp_id='" . $vappID . "' --username='" . $user['username'] . "' &";
        echo Proc_Close(Proc_Open($cmd, Array(), $foo));
        $this->Session->setFlash('The vApp "' . $vapp_name . '" is now being powered off and deleted', 'flash_good');
        $this->redirect($this->referer());                                      //Redirects to the previous page
    }

    function destroy_vapp_api() {
        $user = $this->Auth->user();
        try {
            $vappID = $this->passedArgs['vapp_id'];
            $busyStatus = $this->Vcloud->is_vapp_or_any_vm_busy($vappID) ;
            if ($busyStatus != false){
                throw new Exception('Cannot delete vapp because it is in a busy state');
            }
            $this->Vcloud->destroy_vapp($vappID, $user['username']);
            $vappDetails = "Vapp has been deleted";
            $this->set('destroy_vapp_details', $vappDetails);
            $this->set('_serialize', array('destroy_vapp_details'));
        } catch (Exception $e) {
            throw new BadRequestException('Something went wrong here is the issue: ' . $e);
        }
    }

    function suspend_vapp_api() {
        $user = $this->Auth->user();
        $this->Vcloud->suspend_vapp($this->passedArgs['vapp_id'], $user['username']);
        $obj = "";
        $this->set('obj', $obj);
        $this->set('_serialize', array('obj'));
    }

    function make_vapp_busy_api() {
        $vappID = $this->passedArgs['vapp_id'];
        $busyStatus = $this->passedArgs['busy_status'] ;
        try{
            if($busyStatus == "true"){
                $this->Vcloud->add_vapp_to_busy_cache($vappID, 'Made Busy');
            }
            else{
                $this->Vcloud->delete_vapp_from_busy_cache($vappID);
            }
            $obj = "";
            $this->set('obj', $obj);
            $this->set('_serialize', array('obj'));
        }
        catch (Exception $e){
            throw new Exception('Something went wrong here is the issue: ' . $e);
        }
    }

    //adds vapp to catalog using REST
    function add_to_catalog_api() {
        $user = $this->Auth->user();
        //build array for add vapp to catalog function in Vcloud
        $addCatalog_params = array(
            'vapp_id' => $this->passedArgs['vapp_id'],
            'dest_catalog_name' => $this->passedArgs['dest_catalog_name'],
            'new_vapp_template_name' => $this->passedArgs['new_vapp_template_name'],
        );

        $org_id_from_vApp = $this->Vcloud->get_org_id_by_vapp_id($addCatalog_params['vapp_id']);
        $org_result = $this->Vcloud->get_orgid_from_catalog($addCatalog_params['dest_catalog_name']);
        $org_id = $org_result[0]['org_id'];

        if (isset($org_id_from_vApp) && isset($org_id)) {
            if ($org_id_from_vApp != $org_id) {
                throw new BadRequestException('You cannot add this vApp to this Catalog as the vApp\'s location is in a different Org.');
            }
        }
        //vapp id to be assigned to variable to stop the vapp
        $vapp_id = $this->passedArgs['vapp_id'];
        try {
            //two parameters need to go here, the first one is an array(inputs) the second is username
            $vapp_details = $this->Vcloud->add_vapp_to_catalog($addCatalog_params, $user['username'], $user['email']);
        } catch (Exception $e) {
            throw new BadRequestException('Something went wrong here is the issue: ' . $e);
        }

        //first parameter setes the xml tag, the second parameter is the content inside the tags
        $this->set('vapp_template_id', $vapp_details);
        $this->set('_serialize', array('vapp_template_id'));
    }

    // function used to stop vms using REST
    //tested rest url http://atvcloud2.athtem.eei.ericsson.se:9003/Vapps/stop_vapp_api/vapp_id:urn%3Avcloud%3Avapp%3A230b0a89-5205-4b70-8546-74d1f6090a68/orgvdc_id:urn%3Avcloud%3Aorgvdc%3A04dca8c1-019a-4f27-9e57-f7031f438356.xml
    function stop_vapp_api() {
        $vapp_id = $this->passedArgs['vapp_id'];
        $user = $this->Auth->user();
        try {
            /*
             * the only issue with stop_vapp function is it requires the vms to use vmware tools
             */
            $this->Vcloud->stop_vapp($vapp_id, $user['username']);
            $obj = 'vapp sucessfully stopped';
        } catch (Exception $e) {
            throw new BadRequestException('Something went wrong here is the issue: ' . $e);
        }

        $this->set('obj', $obj);
        $this->set('_serialize', array('obj'));
    }

    function stop_and_add_to_catalog_api() {
        $user = $this->Auth->user();
        $vapp_id = $this->passedArgs['vapp_id'];
        //build array for add vapp to catalog function in Vcloud
        $addCatalog_params = array(
            'vapp_id' => $vapp_id,
            'dest_catalog_name' => $this->passedArgs['dest_catalog_name'],
            'new_vapp_template_name' => $this->passedArgs['new_vapp_template_name'],
        );

        $org_id_from_vApp = $this->Vcloud->get_org_id_by_vapp_id($addCatalog_params['vapp_id']);
        $org_result = $this->Vcloud->get_orgid_from_catalog($addCatalog_params['dest_catalog_name']);
        $org_id = $org_result[0]['org_id'];

        if (isset($org_id_from_vApp) && isset($org_id)) {
            if ($org_id_from_vApp != $org_id) {
                throw new BadRequestException('You cannot add this vApp to this Catalog as the vApp\'s location is in a different Org.');
            }
        }

        try {
            $this->Vcloud->stop_vapp($vapp_id, $user['username']);
        } catch (Exception $e)
        {
            throw new BadRequestException('Something went wrong when stopping the vapp: ' . $e);
        }
        //adds the catalog
        try {
            //two parameters need to go here, the first one is an array(inputs) the second is username
            $vapp_details = $this->Vcloud->add_vapp_to_catalog($addCatalog_params, $user['username'], $user['email']);
            //first parameter setes the xml tag, the second parameter is the content inside the tags
            $this->set('vapp_template_id', $vapp_details['tempid']);
            $this->set('_serialize', array('vapp_template_id'));
        } catch (Exception $e) {
            throw new BadRequestException('Something went wrong when adding vapp to catalog: ' . $e);
        }
    }

    function tasks() {
        $this->layout = 'ajax';
        $vappID = $this->passedArgs['vapp_id'];
        $vapp_busy = $this->Vcloud->is_vapp_or_any_vm_busy($vappID);
        if ($vapp_busy === false)
        {
            $this->set('vapp_task', $this->Vcloud->get_vapp_task($vappID));
        }
        $this->set('vapp_busy', $vapp_busy);
        $this->set('vapp_id', $vappID);
    }

    function quotas() {
        $this->layout = 'ajax';
        $the_values = Cache::read('orgvdc_quota_' . $this->passedArgs['orgvdc_id'], 'orgvdc_quota_cache');
        if ($the_values === false){
                $this->OrgVdc->recursive=-1;
                $db_entry = $this->OrgVdc->find('first', array('conditions' => array('OrgVdc.vcd_id' => $this->passedArgs['orgvdc_id'])));
                if ($db_entry == null)
                {
                    $running_tb_quota = 0;
                    $stored_tb_quota = 0;
                } else {
                    $running_tb_quota = $db_entry['OrgVdc']['running_tb_limit'];
                    $stored_tb_quota = $db_entry['OrgVdc']['stored_tb_limit'];
                }
                $count = $this->Vcloud->count_running_vapps($this->passedArgs['orgvdc_id'], null);
                $the_values = array (
                        'running_tb_current' => $count['running'],
                        'running_tb_quota' => $running_tb_quota,
                        'poweredoff_tb_current' => $count['not_running'],
                        'stored_tb_quota' => $stored_tb_quota
                );
                Cache::write('orgvdc_quota_' . $this->passedArgs['orgvdc_id'], $the_values, 'orgvdc_quota_cache');
        }
        $this->set('values', $the_values);
    }

    function newquotas() {
        $cache_key = 'orgvdc_new_quota_' . $this->passedArgs['orgvdc_id'];
        $the_values = Cache::read($cache_key, 'orgvdc_quota_cache');
        if ($the_values === false){
            $orgvdc_db_entry = $this->OrgVdc->find('first', array('conditions' => array('OrgVdc.vcd_id' => $this->passedArgs['orgvdc_id']), 'fields' => array('running_tb_limit','stored_tb_limit', 'cpu_limit', 'memory_limit', 'ProviderVdc.new_quota_system'),'contain' => 'ProviderVdc'));
            if ($orgvdc_db_entry == null)
            {
                return;
            }
            $running_vapps = array();
            if (!$orgvdc_db_entry['ProviderVdc']['new_quota_system'])
            {
                $running_vapps = $this->Vcloud->count_running_vapps($this->passedArgs['orgvdc_id'], null);
            }
            $vapp_resources = $this->Vcloud->list_vapps_resourses($this->passedArgs['orgvdc_id']);
            $the_values = array(
                'vapps' => $vapp_resources,
                'datacenter_quotas' => $orgvdc_db_entry,
                'running_vapps' => $running_vapps
            );
            Cache::write($cache_key, $the_values, 'orgvdc_quota_cache');
        }
        $this->set('vapps', $the_values['vapps']);
        $this->set('datacenter_quotas', $the_values['datacenter_quotas']);
        $this->set('running_vapps', $the_values['running_vapps']);
        $this->set('_serialize', array('vapps','datacenter_quotas','running_vapps'));
    }

    /*
     * Combines list of vapps in an orgvdc with local information stored in our database, aswell as the busy state of the vapp
     */
    function prepare_vapp_list($orgvdc_id) {
        $user = $this->Auth->user();
        $vapp_list_full = $this->Vcloud->list_vapps_id($orgvdc_id);

        $db_vapps = array();
        $vapp_list = array();

        if (count($vapp_list_full) > 0)
        {
            $this->set('orgvdc_name', $vapp_list_full[0]['vdc_name']);
            $this->set("title_for_layout", $vapp_list_full[0]['vdc_name'] . " Cloud");
        }

        foreach ($vapp_list_full as $vapp) {
            $this->Vapp->id = $vapp['vapp_id'];
            $db_vapp = $this->Vapp->read(array('name', 'vcd_id', 'vts_name', 'created_by_id', 'shared', 'ip_address'));

            // Check if there is a database entry first of all
            if (isset($db_vapp['Vapp']))
            {
                // Populate the array with details from the database
                $vapp['gateway_hostname'] = $db_vapp['Vapp']['vts_name'];
                $vapp['gateway_ipaddress'] = $db_vapp['Vapp']['ip_address'];

                if ($db_vapp['Vapp']['created_by_id'] == null) {              //If the created by field iss not set in the table
                    $vapp['owner'] = "Administrator";
                } else {
                    $vapp['owner'] = $db_vapp['Vapp']['created_by_id'];     //Else get value from vCloud
                }
                $vapp['shared'] = $db_vapp['Vapp']['shared'];

            } else {
                //Populate the array with default data
                $vapp['gateway_hostname'] = "";
                $vapp['gateway_ipaddress'] = "";
                $vapp['owner'] = "Administrator";
                $vapp['shared'] = false;
            }

            // If they don't have permission to see this vApp, continue to next iteration of the loop
            if (isset($user['permissions'][$orgvdc_id]['admin_permission']) && $user['permissions'][$orgvdc_id]['admin_permission']) {
            } else if ($user['is_admin'] || $vapp['owner'] == $user['username'] || $vapp['shared'] == "1") {
            } else {
                continue;
            }
            $busy = $this->Vcloud->get_vapp_busy_status($vapp['vapp_id']);
            if ($busy != false){
                $vapp['busy'] = true;
            }
            else {
                $vapp['busy'] = false;
            }
            array_push($vapp_list, $vapp);
        }
        return $vapp_list;
    }

    /*
     * List all vapp in a give org or orgvdc by passing the container type and container ID
     * Container Type = 'org' or 'vdc'
     */
    function index() {
        $this->set('page_for_layout', 'home');

        if (isset($this->passedArgs['orgvdc_id'])) {     //Check ID was passed. Prevents /vapps/index from listing everyones vApps
            $vapps = $this->prepare_vapp_list($this->passedArgs['orgvdc_id']);
            if (count($vapps) != 0) {
                $this->set('orgvdc_name', $vapps[0]['vdc_name']);
            } else {
                $orgvdc_db_entry = $this->OrgVdc->find('first', array('conditions' => array('OrgVdc.vcd_id' => $this->passedArgs['orgvdc_id']),'fields' => 'name', 'contain' => false));
                $this->set('orgvdc_name', $orgvdc_db_entry['OrgVdc']['name']);
            }
            if (($queued_vapps = Cache::read($this->passedArgs['orgvdc_id'], 'orgvdc_queued_vapp_cache')) !== false)
            {
                $this->set('queued_vapps', $queued_vapps);
            }
            $this->set('vapps', $vapps);
        } else {
            $this->Session->setFlash('This is an invalid URL', 'flash_bad');     //Display Error
            $this->redirect(array('controller' => 'OrgVdcs', 'action' => 'index'));                      //Redirects to the previous page
        }
    }

    /*
     * Rest call to list all vapps in a given orgvdc by passing the orgvdc id
     */
    function index_api() {
        $vapps = $this->prepare_vapp_list($this->passedArgs['orgvdc_id']);

        // Unset unnecessary fields
        foreach ($vapps as &$vapp)
        {
            unset($vapp['href']);
            unset($vapp['org']);
            unset($vapp['vdc']);
            unset($vapp['vdc_name']);
            unset($vapp['orgvdc_id']);
        }
        $this->set('vapps', $vapps);
        $this->set('_serialize', array('vapps'));
    }

    /*
     * Rest call to check if a vapp is either queued to create, or busy creating
     * Returns 0 if its not queued or busy creating, 1 if it is queued or busy creating
     */
    function is_vapp_being_created_api() {
        $result=false;

        // First check if its queued to create
        if (($queued_vapps = Cache::read($this->passedArgs['orgvdc_id'], 'orgvdc_queued_vapp_cache')) !== false)
        {
            foreach ($queued_vapps as $queued_vapp)
            {
                if($queued_vapp['name'] == $this->passedArgs['vapp_name'])
                {
                    $result=true;
                    break;
                }
            }
        }

        // Now check if its currently being created
        if (!$result)
        {
            $vapps = $this->prepare_vapp_list($this->passedArgs['orgvdc_id']);
            foreach ($vapps as &$vapp)
            {
                if ($vapp['name'] == $this->passedArgs['vapp_name'])
                {
                    if ($vapp['busy'])
                    {
                        $result=true;
                    }
                    break;
                }
            }
        }
        $this->set('result', $result);
        $this->set('_serialize', array('result'));
    }

    /*
     * A function to power a vApp by passing it a power function and it's ID
     * Power Options = 'start', 'stop', 'poweroff', 'force_stop', 'shutdown'
     * Please check VMware documentation for the meaning of the above
     *
     */

    function power() {
        $vappID = $this->passedArgs['vapp_id'];
        $action = $this->passedArgs['power_action'];

        $vapp_name = $this->Vcloud->get_vapp_name($vappID);
        $busyStatus = $this->Vcloud->is_vapp_or_any_vm_busy($vappID) ;
        if ($busyStatus != false){
            $this->Session->setFlash('This vApp "' . $vapp_name .  '" is unable to ' . $action . ' because it is busy "' . $busyStatus . '"', 'flash_bad') ;
            $this->redirect($this->referer());
        }

       if($action == "start")
       {
            $this->Vcloud->add_vapp_to_busy_cache($vappID, 'Starting');
       }
       if($action == "stop")
       {
            $this->Vcloud->add_vapp_to_busy_cache($vappID, 'Stopping');
       }
       if($action == "shutdown")
       {
            $this->Vcloud->add_vapp_to_busy_cache($vappID, 'Stopping');
       }
       if($action == "poweroff")
       {
            $this->Vcloud->add_vapp_to_busy_cache($vappID, 'Stopping');
       }

        // Start of Runtime Quota Checks
        // Inform the user if this would go over their quotas
        $allowed_power_on = true;
        if ($action == "start") {
            $orgvdc = $this->Vcloud->get_orgvdc_id_by_vapp($vappID);
            $orgvdc_id = $orgvdc[0]['orgvdc_id'];
            $orgvdc_db_entry = $this->OrgVdc->find('first', array('conditions' => array('OrgVdc.vcd_id' => $this->passedArgs['orgvdc_id']), 'fields' => array('ProviderVdc.new_quota_system'),'contain' => 'ProviderVdc'));
            if (!$orgvdc_db_entry['ProviderVdc']['new_quota_system']){
                // Only if the vapp is actually not already counted as being powered on could it effect the quota
                $vapp_powered_on_states = $this->Vapp->get_powered_on_states();
                if (!in_array($this->Vcloud->get_vapp_power_status($vappID), $vapp_powered_on_states)) {
                    // Check are we allowed power another one on
                    if (!$this->Vcloud->allowed_poweron_another_vapp($orgvdc_id)) {
                        $allowed_power_on = false;
                    }
                }
            } else {
                $allowed_power_on = $this->Vcloud->allowed_power_on_vapp_resources($orgvdc_id, $vappID);
            }
        }
        if($allowed_power_on){
            // End of Runtime Quota Checks
            $user = $this->Auth->user();
            $app_path = dirname(APP) . "/" . basename(APP);
            $cmd = "/opt/bitnami/apache2/htdocs/lib/Cake/Console/cake -app $app_path vcloud --function=" . $action . "_vapp --vapp_id='" . $vappID . "' --username='" . $user['username'] . "' &";
            echo Proc_Close(Proc_Open($cmd, Array(), $foo));                     //Call the above command from the command line to run in the background
            $this->Session->setFlash('You have requested the vApp "' . $vapp_name . '"  to ' . $action, 'flash_good');    //Pass Message
            $this->redirect($this->referer());                                      //Redirects to the previous page
        } else {
            $this->Session->setFlash('Starting this vApp "' . $vapp_name . '" would bring you over the running Resource (CPU/Memory) quota, please power off other vApps first', 'flash_bad');
            $this->Vcloud->delete_vapp_from_busy_cache($vappID);
            $this->redirect($this->referer());
        }
    }

    function delete() {
        $vappID = $this->passedArgs['vapp_id'];
        $vapp_name = $this->Vcloud->get_vapp_name($vappID);

        $busyStatus = $this->Vcloud->is_vapp_or_any_vm_busy($vappID) ;
        if ($busyStatus != false){
            $this->Session->setFlash('This vApp "' . $vapp_name .  '" is unable to delete because it is busy "' . $busyStatus . '"', 'flash_bad') ;
            $this->redirect($this->referer());
        }

        $vapp_powered_on_states = $this->Vapp->get_powered_on_states();
        if (in_array($this->Vcloud->get_vapp_power_status($vappID), $vapp_powered_on_states)) {
            $this->Session->setFlash('The vApp "' . $vapp_name . '" you tried to delete is not powered off, please power it off before deleting it.', 'flash_bad');
            $this->redirect($this->referer());
        } else {
            $user = $this->Auth->user();
            $app_path = dirname(APP) . "/" . basename(APP);
            $cmd = "/opt/bitnami/apache2/htdocs/lib/Cake/Console/cake -app $app_path vcloud --function=delete_vapp --vapp_id='" . $vappID . "' --username='" . $user['username'] . "' &";
            echo Proc_Close(Proc_Open($cmd, Array(), $foo));                     //Call the above command from the command line to run in the background
            $this->Session->setFlash('The vApp "' . $vapp_name . '" is now being deleted', 'flash_good');
            $this->redirect($this->referer());                                      //Redirects to the previous page
        }
    }

    function share() {
        $vappID = $this->passedArgs['vapp_id'];
        $params = array(
            "vcd_id" => $vappID,
            "shared" => 1
        );
        //$this->Vapp->create();
        $this->Vapp->save($params);
        $this->Session->setFlash('This vApp is now being shared', 'flash_good');
        $this->redirect($this->referer());                                      //Redirects to the previous page
    }

    function unshare() {
        $vappID = $this->passedArgs['vapp_id'];
        $params = array(
            "vcd_id" => $vappID,
            "shared" => 0
        );
        //$this->Vapp->create();
        $this->Vapp->save($params);
        $this->Session->setFlash('This vApp is no longer being shared', 'flash_good');
        $this->redirect($this->referer());                                      //Redirects to the previous page
    }

    /*
     * A function to add a vApp to the Catalog
     *
     * ToDo: Check the vApp is powered off first, Error Management,
     */

    function add() {
        $this->set('page_for_layout', 'home');
        $user = $this->Auth->user();
        $vappID = $this->passedArgs['vapp_id'];
        $org_id_from_vApp = $this->Vcloud->get_org_id_by_vapp_id($vappID);

        $vapp_name = $this->Vcloud->get_vapp_name($vappID);
        $busyStatus = $this->Vcloud->is_vapp_or_any_vm_busy($vappID) ;
        if ($busyStatus != false){
            $this->Session->setFlash('This vApp "' . $vapp_name .  '" is unable to add to catalog because it is busy "' . $busyStatus . '"', 'flash_bad') ;
            $this->redirect($this->referer());
        }
        if (!empty($this->request->data)) {

            $this->Vcloud->add_vapp_to_busy_cache($vappID, 'Adding To Catalog');
            $this->Vapp->set($this->request->data);

            if ($this->Vapp->validates()) {

                $vapp_template_name = $this->request->data['Vapp']['name'];
                //$org_vdc_name = $this->request->data['Vapp']['orgvdc'];
                $catalog = $this->request->data['Vapp']['catalog'];
                $org_result = $this->Vcloud->get_orgid_from_catalog($catalog);
                $org_id = $org_result[0]['org_id'];

                if (isset($org_id_from_vApp) && isset($org_id)) {
                    if ($org_id_from_vApp != $org_id) {
                        $this->Session->setFlash('You cannot add "' . $vapp_name . '" vApp to this Catalog as the vApp\'s location is in a different Org.', 'flash_bad');
                        $this->redirect($this->referer());
                    }
                }
                //$description = $this->request->data['Vapp']['description'];

                if (isset($user['email']) && $user['email'] != "") {
                    $email = " --email=" . $user['email'];
                } else {
                    $email = "";
                }

                //$url = " --sppurl='" . Router::url('/', true)."' ";
                $app_path = dirname(APP) . "/" . basename(APP);
                $cmd = "/opt/bitnami/apache2/htdocs/lib/Cake/Console/cake -app $app_path vcloud --function=add_vapp_to_catalog --vapp_id='" . $vappID . "' --new_vapp_template_name='" . $vapp_template_name . "' --dest_catalog_name='" . $catalog . "' --username='" . $user['username'] . "'" . $email . " &";
                echo Proc_Close(Proc_Open($cmd, Array(), $foo));
                $this->Session->setFlash('Your vApp "' . $vapp_name . '" being added to the catalog. You will receive an email when its completed.', 'flash_good');
                $this->redirect(array('controller' => 'vappTemplates', 'action' => 'index', 'catalog_name' => $catalog, 'org_id' => $org_id));
            }
        }

        $vapp_powered_on_states = $this->Vapp->get_powered_on_states();
        if (in_array($this->Vcloud->get_vapp_power_status($vappID), $vapp_powered_on_states)) {
            $this->Session->setFlash('The vApp "' .$vapp_name . '"  you are trying to add to the catalog is not powered off, please power it off before adding it to the catalog.', 'flash_bad');
            $this->redirect($this->referer());
        }


        $catalogs = $this->Catalog->find('all', array('order' => 'Catalog.name ASC', 'conditions' => array('Catalog.org_id' => $org_id_from_vApp)));
        if (!$user['is_admin']) {
            foreach ($catalogs as $key => $catalog) {
                if (isset($user['permissions'][$catalog['Catalog']['org_id']]['write_permission']) && $user['permissions'][$catalog['Catalog']['org_id']]['write_permission']) {
                } else {
                    unset($catalogs[$key]);
                }
            }
        }
        if (count($catalogs) == 0) {
            $this->Session->setFlash('You dont have permissions to add vApps to any catalogs yet, please contact your team co-ordinator to give you access if required.', 'flash_bad');
            $this->redirect($this->referer());
        }
        $this->set('catalogs', $catalogs);
        $this->set('vapp_name', $vapp_name);
    }

    /* This is a controller function to edit the Name, Description, and meta information about a vApp.
     * Renaming the vApp needs to happen in the database and in vCD.
     * All other information in edited in the database.
     */

    function edit() {
        $this->set('page_for_layout', 'home');
        $this->Vapp->id = $this->passedArgs['vapp_id'];

        $vappID = $this->passedArgs['vapp_id'];
        $vapp_name = $this->Vcloud->get_vapp_name($vappID);
        $busyStatus = $this->Vcloud->is_vapp_or_any_vm_busy($vappID) ;
        if ($busyStatus != false){
            $this->Session->setFlash('This vApp "' . $vapp_name .  '" is unable to be edited because it is busy "' . $busyStatus . '"', 'flash_bad') ;
            $this->redirect($this->referer());
        }
        //$this->set('vapps', $this->Vcloud->list_vapps_id(null, $this->passedArgs['vapp_id']));
        //$this->set('teams', $this->Vapp->Team->find('list'));
        //$this->set('citags', $this->Vapp->Citag->find('list'));
        //$this->set('softwareTypes', $this->Vapp->SoftwareType->find('list'));
        //$this->set('softwareReleases', $this->Vapp->SoftwareRelease->find('list'));
        //$this->set('softwareLsvs', $this->Vapp->SoftwareLsv->find('list'));
        if (empty($this->data)) {
            $this->data = $this->Vapp->read();
        } else {
            if ($this->Vapp->save($this->data)) {
                $this->Session->setFlash('Your vApp "' . $vapp_name . '" has been updated!', 'flash_good');
                $this->redirect($this->referer());                      //Redirects to the previous page
            }
        }
    }

    function import() {
        $this->set('import_variables', $this->Vapp->import());
    }

    function network() {
        $this->Vapp->network();
    }

    function rename() {

        $id = $this->passedArgs['vapp_id'];
        $vapp_name = $this->Vcloud->get_vapp_name($id);
        $new_vapp_name = $vapp_name ;
        $busyStatus = $this->Vcloud->is_vapp_or_any_vm_busy($id) ;
        if ($busyStatus != false){
            $this->Session->setFlash('This vApp "' . $vapp_name .  '" is unable to be edited because it is busy "' . $busyStatus . '"', 'flash_bad') ;
            $this->redirect($this->referer());
        }
        $vapp_details = $this->Vcloud->get_vapp_details($id);
        if (!empty($this->request->data)) {
            $this->Vapp->set($this->request->data);
            try {
                $name = $this->request->data['Vapp']['name'];
                $this->Vcloud->rename_vapp($id, $name);
                $new_vapp_name = $name ;
            } catch (Exception $e) {
                $this->Session->setFlash('Something went wrong renaming the vapp "' . $vapp_name . '"  Here is the issue: ' . $e, 'flash_bad');
                throw new InternalErrorException('Something went wrong renaming the vapp . Here is the issue: ' . $e);
            }

            $this->Session->setFlash('The vApp "' .$vapp_name . '"  has been renamed "' . $new_vapp_name . '"', 'flash_good');
            $this->redirect(array('controller' => 'vapps', 'action' => 'index', 'orgvdc_id' => $vapp_details['orgvdc_id']));
        }

        $name = $vapp_details['name'];
        $this->set('name', $name);
    }
    function recompose_vapp_api() {
        $this->recompose_vapp();
    }

    function recompose_vapp() {
        if ($this->params['ext'] == 'json' || $this->params['ext'] == 'xml')
        {
            $rest = true;
        }
        else
        {
            $rest = false;
        }
        try {
            if (!$rest)
            {
                $this->set('page_for_layout', 'home');
            }
            $user = $this->Auth->user();
            $vapp_id = $this->passedArgs['vapp_id'];
            $busyStatus = $this->Vcloud->is_vapp_or_any_vm_busy($vapp_id) ;
            if ($busyStatus != false){
                throw new Exception('This vApp is unable to add to be recomposed because it is busy "' . $busyStatus . '"');
            }
            $params= array(
                'type' => "adminVApp",
                'fields' => array('name'),
                'generated_fields' => array('vapp_org_vdc_id','vapp_vcenter','vapp_power_status'),
                'filter' => "id==" . $vapp_id
            );
            $vapp = $this->Vcloud->query_service_request($params)[0];

            $vms_data = null;
            if ($rest)
            {
                $data=$this->request->data;
                if (!isset($data['vms']))
                {
                    throw new Exception('You must give the list of vms to be copied into the vApp');
                }
                $vms_data = array();
                foreach ($data['vms'] as $vm)
                {
                    if(!isset($vm['id']))
                    {
                        throw new Exception('You must give the list of vm id you want to use');
                    }
                    array_push($vms_data, $vm['id']);
                }
            } else if (!empty($this->request->data)) {
                $data=$this->request->data;
                $vms_data = $data['recompose']['vms'];
            }
            if (isset($vms_data))
            {
                $vm_ids = array();
                foreach($vms_data as $vm)
                {
                    array_push($vm_ids, $vm);
                }
                if (count($vm_ids) == 0){
                    throw new Exception('You must select vm(s) to recompose for "' . $vapp['name'] . '"');
                }

                $perform_quota_check = false;
                $orgvdc_db_entry = $this->OrgVdc->find('first', array('conditions' => array('OrgVdc.vcd_id' => $vapp['vapp_org_vdc_id']), 'fields' => array('ProviderVdc.new_quota_system','cpu_limit','memory_limit'),'contain' => 'ProviderVdc'));
                if ($orgvdc_db_entry['ProviderVdc']['new_quota_system']){
                    $vapp_powered_on_states = $this->Vapp->get_powered_on_states();
                    if (in_array($vapp['vapp_power_status'], $vapp_powered_on_states))
                    {
                        $perform_quota_check = true;
                    }
                }

                if ($perform_quota_check)
                {
                    $dest_name_resource_map = array();
                    $params = array(
                        'type' => "adminVM",
                        'fields' => array('name','numberOfCpus','memoryMB'),
                        'filter' => "container==" . $vapp_id
                    );
                    $all_vms_in_vapp = $this->Vcloud->query_service_request($params);
                    foreach ($all_vms_in_vapp as $vm)
                    {
                        $dest_name_resource_map[$vm['name']] = array (
                            'cpu_total' => $vm['numberOfCpus'],
                            'memory_total' => $vm['memoryMB']
                        );
                    }
                }

               # Cross vCenter and vm not being the gateway restriction
               foreach ($vm_ids as $vm_id)
               {
                    $vcenter_of_vm = $this->Vcloud->get_vcenter_of_vm($vm_id);
                    if(($vapp['vapp_vcenter'] != null) && ($vapp['vapp_vcenter']  != $vcenter_of_vm))
                    {
                        throw new Exception('You cannot recompose a vapp with vms from another vcenter. (ie vApp is in ' . $vapp['vapp_vcenter'] . ' and vm is in ' . $vcenter_of_vm . '). Please seek support if you believe this to be incorrect');
                    }
                    $vm_name = $this->Vcloud->get_vm_name($vm_id);
                    if (strstr($vm_name, 'gateway'))
                    {
                        throw new Exception('You cannot recompose a vApp with the gateway vm as its not supported');
                    }
                    if ($perform_quota_check)
                    {
                        $vm_resources = $this->Vcloud->list_vm_resourses($vm_id);
                        $dest_name_resource_map[$vm_name] = array (
                            'cpu_total' => $vm_resources['cpu_total'],
                            'memory_total' => $vm_resources['memory_total']
                        );
                    }
                }

                if ($perform_quota_check)
                {
                    $vapp_resources = $this->Vcloud->list_vapps_resourses($vapp['vapp_org_vdc_id']);
                    $data_center_used_memory = 0;
                    $data_center_used_cpu = 0;
                    foreach($vapp_resources as $vapp_resource){
                        $data_center_used_memory += $vapp_resource['memory_on_count'];
                        $data_center_used_cpu += $vapp_resource['cpu_on_count'];
                    }
                    $vapp_total_cpu_after_recompose = 0;
                    $vapp_total_memory_after_recompose = 0;
                    foreach ($dest_name_resource_map as $vm){
                        $vapp_total_cpu_after_recompose+=$vm['cpu_total'];
                        $vapp_total_memory_after_recompose+=$vm['memory_total'];
                    }
                    $dest_vapp_resources = $this->Vcloud->list_vapps_resourses($vapp_id)[0];
                    $cpu_total_if_recompose = $data_center_used_cpu - $dest_vapp_resources['cpu_on_count'] + $vapp_total_cpu_after_recompose;
                    $memory_total_if_recompose = ($data_center_used_memory - $dest_vapp_resources['memory_on_count'] + $vapp_total_memory_after_recompose) / 1024;

                    if( ($cpu_total_if_recompose > $orgvdc_db_entry['OrgVdc']['cpu_limit']) || ($memory_total_if_recompose >  $orgvdc_db_entry['OrgVdc']['memory_limit']) ){
                        throw new Exception('Recomposing this vApp "' . $vapp['name'] . '" would bring you over the running Resource (CPU/Memory) quota, please power off other vApps first');
                    }
                }
                if ($rest)
                {
                    $this->Vcloud->recompose_vapp($vapp_id, $vm_ids, $user['username']);
                    $this->set('recompose_vapp_details', "Vapp has been recomposed");
                    $this->set('_serialize', array('recompose_vapp_details'));
                } else {
                    if (isset($user['email']) && $user['email'] != "") {
                        $email = " --email=" . $user['email'];
                    } else {
                        $email = "";
                    }

                    $app_path = dirname(APP) . "/" . basename(APP);
                    $vm_ids_string = implode(',', $vm_ids);
                    $cmd = "/opt/bitnami/apache2/htdocs/lib/Cake/Console/cake -app $app_path vcloud --function=recompose --vapp_id='" . $vapp_id . "' --vm_id='" . $vm_ids_string . "' --username='" . $user['username'] . "'" . $email . " &";
                    echo Proc_Close(Proc_Open($cmd, Array(), $foo));
                    $this->Session->setFlash('Your vApp "' . $vapp['name'] . '" is being recomposed. You will receive an email when its completed.', 'flash_good');
                    $this->redirect(array('controller' => 'vapps', 'action' => 'index', 'orgvdc_id' => $vapp['vapp_org_vdc_id']));
                }
            }
            if (!$rest)
            {
                $catalogs = $this->Catalog->find('all', array('order' => 'Catalog.name ASC', 'fields' => array('name')));
                if (count($catalogs) == 0) {
                    throw new Exception('You dont have permissions to use any catalogs yet for recomposing, please contact your team co-ordinator to give you access if required.');
                }
                $this->set('catalogs', $catalogs);
                $this->set('vapp_name', $vapp['name']);
            }
        } catch (Exception $e)
        {
            if ($rest)
            {
                throw new BadRequestException('Something went wrong here is the issue: ' . $e);
            }
            else
            {
                $this->Session->setFlash($e->getMessage(), 'flash_bad');
                $this->redirect($this->referer());
            }
        }
    }
}
?>
