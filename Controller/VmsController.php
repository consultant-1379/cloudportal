<?php

class VmsController extends AppController {

    var $components = array('Session');
    var $uses = array('Vcloud', 'Vapp', 'OrgVdc');
    var $vm_id = null;
    var $vapp_id = null;
    var $authorized_value = null;

    public function beforeFilter() {
        parent::beforeFilter();
        $this->Auth->allow("set_cdrom_emulate_mode", "flip_cdrom_mode", "poweron_api", "poweroff_api", "reset_api", "check_power_status_raw_api", "list_vms_raw_api", "get_vcenter_of_vm_raw_api", "set_boot_device_api", "delete_disks_api", "tasks", "delete_internal_api", "resize_cpu_internal_api", "resize_memory_internal_api", "gateway_hostname");
	// Take in the vm_id if its set
        if (isset($this->passedArgs['vm_id']))
        {
                $this->vm_id = $this->passedArgs['vm_id'];
        }

	// For these functions we need to preprepare the vm_id
	if (in_array($this->action, array('poweron_api', 'poweroff_api', 'reset_api', 'check_power_status_raw_api', 'set_boot_device_api', 'get_vcenter_of_vm_raw_api', 'delete_internal_api', "resize_cpu_internal_api", "resize_memory_internal_api"))) {

                if (isset($this->passedArgs['vm_name']) && $this->passedArgs['vm_name'] !== "")
                {
                        $vm_name = $this->passedArgs['vm_name'];
                }
                else
                {
                        throw new BadRequestException("You must give the vm_name parameter");
                }
                // If we don't have the vm id yet, only the gateway hostname and vm string
                if (!isset($this->vm_id))
                {
                        $gateway_ip = $_SERVER['REMOTE_ADDR'];
			try {
	                        $ids=$this->Vcloud->get_ids_from_gateway_and_vm($gateway_ip,$vm_name);
			} catch (Exception $e)
			{
				throw new BadRequestException($e);
			}
                        $this->vm_id=$ids['vm_id'];
			$this->vapp_id=$ids['vapp_id'];
                }
        }
	if (in_array($this->action, array('list_vms_raw_api'))) {
		$gateway_ip = $_SERVER['REMOTE_ADDR'];
		try {
			$vapp_id_returned=$this->Vcloud->get_vapp_id_by_gateway_ip($gateway_ip);
		} catch (Exception $e)
		{
			throw new BadRequestException($e);
		}
		$this->vapp_id=$vapp_id_returned;
	}
    }

    function isAuthorized($user) {
    // Check was this is Authorized run already, as it can get run twice during rest calls
	if ($this->authorized_value != null)
	{
		return $this->authorized_value;
	}
	else
	{
		$this->authorized_value = $this->isAuthorized_internal($user);
		return $this->authorized_value;
	}
    }
    function isAuthorized_internal($user) {
        // Always let the admin do everything
        if ($user['is_admin']) {
            return true;
        }

        if (in_array($this->action, array('vapp_index', 'vapp_index_api', 'vapp_network', 'vapp_network_api', 'vapp_startup_settings_api', 'is_security_policy_set_api','vapp_metadata_api'))) {
            if (isset($this->passedArgs['orgvdc_id']))
            {
                $orgvdc_id = $this->passedArgs['orgvdc_id'];
            } else {
                $orgvdc = $this->Vcloud->get_orgvdc_id_by_vapp($this->passedArgs['vapp_id']);
                $orgvdc_id = $orgvdc[0]['orgvdc_id'];
            }
            // orgvdc read check
            if (isset($user['permissions'][$orgvdc_id]['read_permission']) && $user['permissions'][$orgvdc_id]['read_permission']) {
                return true;
            }

        }

        if (in_array($this->action, array('vapptemplate_index_api'))) {
            $vapp_template_id= $this->passedArgs['vapp_template_id'];
            $catalog_id = $this->Vcloud->get_catalog_id_by_vappTemplate($vapp_template_id);

            if (isset($user['permissions']['restrict_catalogs'])) {
                if (isset($user['permissions'][$catalog_id]['read_permission']) && $user['permissions'][$catalog_id]['read_permission']) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return true;
            }
        }

        if (in_array($this->action, array('vapptemplate_index','vapptemplate_network', 'vapptemplate_network_api', 'vapptemplate_startup_settings_api', 'disks'))) {
            return true;
        }

        // Write actions
        if (in_array($this->action, array('power', 'delete', 'edit','console','insert_media_api', 'delete_external_api'))) {
		$orgvdc = $this->Vcloud->get_orgvdc_id_by_vm($this->vm_id);
                $orgvdc_id = $orgvdc[0]['orgvdc_id'];

            // orgvdc admin check
            if (isset($user['permissions'][$orgvdc_id]['admin_permission']) && $user['permissions'][$orgvdc_id]['admin_permission']) {
                return true;
            }

            // orgvdc write check
            if (isset($user['permissions'][$orgvdc_id]['write_permission']) && $user['permissions'][$orgvdc_id]['write_permission']) {
                // So they have write permission to this orgvdc, check if they have write access to this vapp
		$vappID = $this->Vcloud->get_vapp_id_by_vm($this->vm_id);
                $this->Vapp->id = $vappID;
                $db_vapp = $this->Vapp->read();

                if ($db_vapp['Vapp']['created_by_id'] == $user['username'] || $db_vapp['Vapp']['shared'] == "1") {
                    // Let them change it
                    return true;
                }
            }
        }

        // Orgvdc admin only actions
        if (in_array($this->action, array('set_cpu_count','set_memory_mb', 'resize_cpu_external_api', 'resize_memory_external_api'))) {
            $orgvdc_id = $this->passedArgs['orgvdc_id'];

            if (isset($user['permissions'][$orgvdc_id]['admin_permission']) && $user['permissions'][$orgvdc_id]['admin_permission']) {
                return true;
            }
        }

        return false;
    }

    function gateway_hostname()
    {
        $this->autoRender = false;
        $gateway_hostname = gethostbyaddr($_SERVER['REMOTE_ADDR']);
        echo explode('.', $gateway_hostname)[0];
    }

	function set_cdrom_emulate_mode()
	{
		$this->autoRender = false;
		$vm_id = $this->passedArgs['vm_id'];
		$cdromkey= $this->passedArgs['cdromkey'];
		$vcenter = $this->Vcloud->get_vcenter_of_vm($vm_id);
		$vm_id_stripped = str_replace("urn:vcloud:vm:","",$vm_id);

		$command = "/export/scripts/CLOUD/bin/vmware-vsphere-cli-distrib/apps/vm/vm_set_cdrom_emulate_mode.pl --vmname '" . $vm_id_stripped . "' --cdromkey " . $cdromkey;
                $this->Vcloud->run_vcli_command($command,$vcenter);
	}

    function flip_cdrom_mode()
    {
        $this->autoRender = false;
        $vm_id = $this->passedArgs['vm_id'];
        $vcenter = $this->Vcloud->get_vcenter_of_vm($vm_id);
        $vm_id_stripped = str_replace("urn:vcloud:vm:","",$vm_id);
        $command = "/export/scripts/CLOUD/bin/vmware-vsphere-cli-distrib/apps/vm/vm_flip_cdrom_mode.pl --vmname '" . $vm_id_stripped . "'";
        $this->Vcloud->run_vcli_command($command,$vcenter);
        $this->Vcloud->eject_first_media($vm_id);
    }

    function insert_media_api ()
    {
	$media_id=$this->passedArgs['media_id'];
	$vm_id=$this->passedArgs['vm_id'];
	try {
		$this->Vcloud->insert_media($media_id,$vm_id);
		$result = "success";
	        $this->set('result', $result);
	        $this->set('_serialize', array('result'));
	} catch (Exception $e) {
            throw new InternalErrorException('Something went wrong mounting the iso, ' . $e);
        }
    }
    function vapp_index() {
        $this->set('page_for_layout', 'home');
        if (isset($this->passedArgs['vapp_id'])) {
            $argsID = $this->passedArgs['vapp_id'];
        } else {
            $this->Session->setFlash('This is an invalid URL', 'flash_bad');     //Display Error
            $this->redirect($this->referer());
        }
	    $vapp_name="";
	    $vm_list = array();
        $vm_list_returned = $this->prepare_vm_list($argsID);
        if (count($vm_list_returned) != 0) {
		    $vm_list=$vm_list_returned;
		    $vapp_name=$vm_list[0]['vapp_name'];
        }
	    $this->set('vms', $vm_list);
	    $this->set('vapp_name', $vapp_name);
	    $this->set("title_for_layout", $vapp_name);
    }

    /*
    * This REST call function lists the vms in a vapp
    */
    function vapp_index_api() {
        $vapp_id = $this->passedArgs['vapp_id'];
        $vms = $this->prepare_vm_list($vapp_id);

        // Unset unnecessary information
        foreach ($vms as &$vm)
        {
            unset($vm['vapp_href']);
            unset($vm['vm_href']);
            unset($vm['vapp_name']);
            unset($vm['vapp_id']);
            unset($vm['vdc']);
            unset($vm['org']);
        }
        //$this->set('busy', $this->Vcloud->get_vapp_busy_status($vapp_id));
        $this->set('vms', $vms);
        $this->set('_serialize', 'vms');
    }

    function vapp_metadata_api() {
        $vapp_id = $this->passedArgs['vapp_id'];
        $metadata = $this->Vcloud->get_vapp_metadata_from_vapp_id($vapp_id);
        $this->set('metadata', $metadata);
        $this->set('_serialize', array('metadata'));
    }

    function vapptemplate_index_api() {
        $vapp_template_id = $this->passedArgs['vapp_template_id'];
        $vms = $vm_list = $this->Vcloud->list_vms_id($vapp_template_id);

        // Unset unnecessary information
        foreach ($vms as &$vm)
        {
            unset($vm['vapp_href']);
            unset($vm['vm_href']);
            unset($vm['vapp_name']);
            unset($vm['vapp_id']);
            unset($vm['vdc']);
            unset($vm['org']);
        }
        $this->set('vms', $vms);
        $this->set('_serialize', 'vms');
    }

    function tasks() {
        $this->layout = 'ajax';
        $vmID = $this->passedArgs['vm_id'];
        $vapp_busy = $this->Vcloud->is_vapp_or_vm_busy($vmID);
        if ($vapp_busy === false)
        {
            $this->set('vapp_task', $this->Vcloud->get_vapp_task($vmID));
        }
        $this->set('vapp_busy', $vapp_busy);
        $this->set('vapp_id', $vmID);
    }

    function vapp_network() {
        $this->set('page_for_layout', 'home');
        if (isset($this->passedArgs['vapp_id'])) {
            $argsID = $this->passedArgs['vapp_id'];
        } else {
            $this->Session->setFlash('This is an invalid URL', 'flash_bad');     //Display Error
            $this->redirect($this->referer());
        }
        $vapp_name="";
        $vm_list = array();
        $vm_list_returned = $this->Vcloud->list_vms_id_network($argsID);
        if (count($vm_list_returned) != 0) {
                $vm_list=$vm_list_returned;
                $vapp_name=$vm_list[0]['vapp_name'];
        }
        $this->set('vapp_name', $vapp_name);
        $this->set("title_for_layout", $vapp_name);
	$this->set('vms',$vm_list);
        $this->set('vapp_networks_internal',$this->Vcloud->get_vapp_networks_internal($argsID));
        $this->set('vapp_networks_external',$this->Vcloud->get_vapp_networks_external($argsID));
    }

    function vapp_network_api() {
        $argsID = $this->passedArgs['vapp_id'];
        $vms = $this->Vcloud->list_vms_id_network($argsID);
        foreach ($vms as &$vm)
        {
            unset($vm['vapp_name']);
        }
        $this->set('vm', $vms);
        $this->set('_serialize', 'vm');
    }

    function vapptemplate_network_api() {
        $argsID = $this->passedArgs['vapp_template_id'];
        $vms = $this->Vcloud->list_vms_id_network($argsID);
        foreach ($vms as &$vm)
        {
            unset($vm['vapp_name']);
        }
        $this->set('vm', $vms);
        $this->set('_serialize', 'vm');
    }

    function vapp_startup_settings_api() {
        $argsID = $this->passedArgs['vapp_id'];
        $vms = $this->Vcloud->get_vapp_startup_settings($argsID);
        $this->set('vm', $vms);
        $this->set('_serialize', 'vm');
    }

    function vapptemplate_startup_settings_api() {
        $argsID = $this->passedArgs['vapp_template_id'];
        $vms = $this->Vcloud->get_vapp_startup_settings($argsID);
        $this->set('vm', $vms);
        $this->set('_serialize', 'vm');
    }

    function vapptemplate_network() {
        $this->set('page_for_layout', 'catalogs');
        if (isset($this->passedArgs['vapp_template_id'])) {
            $argsID = $this->passedArgs['vapp_template_id'];
        } else {
            $this->Session->setFlash('This is an invalid URL', 'flash_bad');     //Display Error
            $this->redirect($this->referer());
        }
        $vapp_name="";
        $vm_list = array();
        $vm_list_returned = $this->Vcloud->list_vms_id_network($argsID);
        if (count($vm_list_returned) != 0) {
                $vm_list=$vm_list_returned;
                $vapp_name=$vm_list[0]['vapp_name'];
        }
        $this->set('vapp_name', $vapp_name);
        $this->set("title_for_layout", $vapp_name);
	$this->set('vms',$vm_list);
	$this->set('vapp_networks_internal',$this->Vcloud->get_vapp_networks_internal($argsID));
	$this->set('vapp_networks_external',$this->Vcloud->get_vapp_networks_external($argsID));
    }

    function vapptemplate_index() {
        $this->set('page_for_layout', 'catalogs');
        if (isset($this->passedArgs['vapp_template_id'])) {
            $argsID = $this->passedArgs['vapp_template_id'];
        } else {
            $this->Session->setFlash('This is an invalid URL', 'flash_bad');     //Display Error
            $this->redirect($this->referer());
        }
        $vm_list = $this->Vcloud->list_vms_id($argsID);
        if (count($vm_list) != 0) {
            $this->set('vms', $vm_list);
            $this->set('vapptemplate_name', $vm_list[0]['vapp_name']);
            $this->set("title_for_layout", $vm_list[0]['vapp_name']);
        }
    }
    function power() {
        $vmID = $this->passedArgs['vm_id'];
        $action = $this->passedArgs['power_action'];
	    // Get the vApp belonging to this vm
        $admin_vm_params = array(
            'type' => "adminVM",
            'fields' => array('name','status','numberOfCpus','memoryMB'),
            'generated_fields' => array('vm_vapp_id','vm_org_vdc_id'),
            'filter' => "id==" . $vmID
        );
        $vm = $this->Vcloud->query_service_request($admin_vm_params)[0];
        $busyStatus = $this->Vcloud->is_vapp_or_vm_busy($vmID) ;
        if($busyStatus !== false) {
            $this->Session->setFlash('This vm "' . $vm['name'] . '" is unable to ' .$action . ' because it is busy "' . $busyStatus . '"' , 'flash_bad');
            $this->redirect($this->referer());
        }
        // Start of Runtime Quota Checks
        if ($action == "poweron")
        {
            if ($vm['status'] == 'POWERED_ON') {
                $this->Session->setFlash('This vm is already powered on','flash_bad');
                $this->redirect($this->referer());
            }
            $orgvdc_db_entry = $this->OrgVdc->find('first', array('conditions' => array('OrgVdc.vcd_id' => $vm['vm_org_vdc_id']), 'fields' => array('ProviderVdc.new_quota_system','memory_limit','cpu_limit'),'contain' => 'ProviderVdc'));
            if ($orgvdc_db_entry['ProviderVdc']['new_quota_system']){
                $vapp_resources = $this->Vcloud->list_vapps_resourses($vm['vm_org_vdc_id']);
                $data_center_used_memory = 0;
                $data_center_used_cpu = 0;
                foreach($vapp_resources as $vapp_resource){
                    $data_center_used_memory += $vapp_resource['memory_on_count'];
                    $data_center_used_cpu += $vapp_resource['cpu_on_count'];
                }
                $cpu_total_if_poweron = $data_center_used_cpu + $vm['numberOfCpus'];
                $memory_total_if_poweron = ($data_center_used_memory + $vm['memoryMB']) / 1024;

                if( ($cpu_total_if_poweron > $orgvdc_db_entry['OrgVdc']['cpu_limit']) || ($memory_total_if_poweron >  $orgvdc_db_entry['OrgVdc']['memory_limit']) ){
                    $this->Session->setFlash('Starting this vm would bring you over the running Resource (CPU/Memory) quota, please power off other vApps first','flash_bad');
                    $this->redirect($this->referer());
                }
            } else {
                // Only if the vapp is actually not already counted as being powered on could it effect the quota
                $vapp_powered_on_states = $this->Vapp->get_powered_on_states();
                if (!in_array($this->Vcloud->get_vapp_power_status($vm['vm_vapp_id']), $vapp_powered_on_states)){
                    // Check are we allowed power another one on
                    if (! $this->Vcloud->allowed_poweron_another_vapp($vm['vm_org_vdc_id'])){
                        $this->Session->setFlash('Starting this vm would bring you over the running vApp quota, please power off other vApps first', 'flash_bad');
                        $this->redirect($this->referer());
                    }
                }
            }
        }
        // End of Runtime Quota Checks

        if($action == "poweron")
        {
             $this->Vcloud->add_vapp_to_busy_cache($vmID, 'Starting VM');
        }
        if($action == "shutdown")
        {
             $this->Vcloud->add_vapp_to_busy_cache($vmID, 'Shutting Down VM');
        }
        if($action == "poweroff")
        {
             $this->Vcloud->add_vapp_to_busy_cache($vmID, 'Powering Off VM');
        }
        $user = $this->Auth->user();
        $app_path = dirname(APP) . "/" . basename(APP);
        $cmd = "/opt/bitnami/apache2/htdocs/lib/Cake/Console/cake -app $app_path vcloud --function=" . $action . "_vm --vm_id='" . $vmID . "' --username='" . $user['username'] . "' &";
        echo Proc_Close(Proc_Open($cmd, Array(), $foo));                     //Call the above command from the command line to run in the background
        $this->Session->setFlash('You have requested the vm to ' . $action, 'flash_good');    //Pass Message
        $this->redirect($this->referer());                                      //Redirects to the previous page
    }

    function poweron_api() {
        try {
            $admin_vm_params = array(
                'type' => "adminVM",
                'fields' => array('name','status','numberOfCpus','memoryMB'),
                'generated_fields' => array('vm_org_vdc_id'),
                'filter' => "id==" . $this->vm_id
            );
            $vm = $this->Vcloud->query_service_request($admin_vm_params)[0];
            if ($vm['status'] != 'POWERED_ON') {
                $orgvdc_db_entry = $this->OrgVdc->find('first', array('conditions' => array('OrgVdc.vcd_id' => $vm['vm_org_vdc_id']), 'fields' => array('ProviderVdc.new_quota_system','memory_limit','cpu_limit'),'contain' => 'ProviderVdc'));
                if ($orgvdc_db_entry['ProviderVdc']['new_quota_system']){
                    $vapp_resources = $this->Vcloud->list_vapps_resourses($vm['vm_org_vdc_id']);
                    $data_center_used_memory = 0;
                    $data_center_used_cpu = 0;
                    foreach($vapp_resources as $vapp_resource){
                        $data_center_used_memory += $vapp_resource['memory_on_count'];
                        $data_center_used_cpu += $vapp_resource['cpu_on_count'];
                    }
                    $cpu_total_if_poweron = $data_center_used_cpu + $vm['numberOfCpus'];
                    $memory_total_if_poweron = ($data_center_used_memory + $vm['memoryMB']) / 1024;

                    if( ($cpu_total_if_poweron > $orgvdc_db_entry['OrgVdc']['cpu_limit']) || ($memory_total_if_poweron >  $orgvdc_db_entry['OrgVdc']['memory_limit']) ){
                        throw new Exception('Starting this vm ' . $vm['name'] . ' would bring you over the running Resource (CPU/Memory) quota, please power off other vApps first');
                    }
                }
                $this->Vcloud->poweron_vm($this->vm_id,"rest_api_user");
            }
        }
        catch (Exception $e)
        {
            throw new BadRequestException($e);
        }
        $obj = '';
        $this->set('obj', $obj);
        $this->set('_serialize', array('obj'));
    }

    function poweroff_api() {
	try {
		$this->Vcloud->poweroff_vm($this->vm_id,"rest_api_user");
	}
	catch (Exception $e)
        {
                throw new BadRequestException($e);
        }
        $obj = '';
        $this->set('obj', $obj);
        $this->set('_serialize', array('obj'));
    }

    function reset_api() {
        try {
                $this->Vcloud->reset_vm($this->vm_id,"rest_api_user");
        }
        catch (Exception $e)
        {
                throw new BadRequestException($e);
        }
        $obj = '';
        $this->set('obj', $obj);
        $this->set('_serialize', array('obj'));
    }

    # This REST function will attempt to delete all disks on the specified vm, except for those that are specified to keep
    # Please see the delete_disks_vm function in the Vcloud model for formatting of this value
    function delete_disks_api() {
        try {
            $this->Vcloud->delete_disks_vm($this->vm_id,$this->passedArgs["disks_to_keep"]);
        }
        catch (Exception $e)
        {
            throw new BadRequestException($e);
        }
        $obj = '';
        $this->set('obj', $obj);
        $this->set('_serialize', array('obj'));
    }

    function check_power_status_raw_api () {
	$this->autoRender = false;
        try {
                $power_status = $this->Vcloud->check_power_vm($this->vm_id);
        }
        catch (Exception $e)
        {
                throw new BadRequestException($e);
        }
	echo $power_status;
    }

    function get_vcenter_of_vm_raw_api () {
	$this->autoRender = false;
        try {
		$result = $this->Vcloud->get_vcenter_of_vm($this->vm_id);
        }
        catch (Exception $e)
        {
                throw new BadRequestException($e);
        }
	echo "VCENTER " . $result;
    }

    function list_vms_raw_api () {
	$this->autoRender = false;
	try {
		$vms = $this->Vcloud->list_vms_id($this->vapp_id);
	}
        catch (Exception $e)
        {
                throw new BadRequestException($e);
        }
	foreach ($vms as $vm) {
		$id_last_part = split(":", $vm['vm_id']);
		echo $vm['name'] . ";";
		echo $vm['name'] . " (" . $id_last_part[3] . ");";
		echo $vm['vm_id'];
		echo "</br>";
	}
    }

    function set_boot_device_api() {
	try {
		$vcenter = $this->Vcloud->get_vcenter_of_vm($this->vm_id);
		$vm_id_stripped = str_replace("urn:vcloud:vm:","",$this->vm_id);
		if (isset($this->passedArgs['boot_devices']) && $this->passedArgs['boot_devices'] !== "")
		{
			$boot_devices = $this->passedArgs['boot_devices'];
		}
		else
		{
			throw new Exception("You must give the boot_devices parameter");
		}
		$command = "/export/scripts/CLOUD/bin/BootOrder.pl --vmname '" . $vm_id_stripped . "' --bootWith allow:" . $boot_devices;
		$this->Vcloud->run_vcli_command($command,$vcenter);
	}
	catch (Exception $e)
	{
		throw new BadRequestException($e);
	}
	$obj = '';
	$this->set('obj', $obj);
        $this->set('_serialize', array('obj'));
    }

    function delete_internal_api() {
        try {
            $status = $this->Vcloud->get_vm_power_status($this->vm_id);
            if($status == "POWERED_ON"){
                throw new Exception("You must have the vm powered off before you delete it");
            }
            $user = $this->Auth->user();
            $username = null;
            if($user == null){
                $username = "rest_api_user";
            }else{
                $username = $user['username'];
            }
            $this->Vcloud->delete_vm($this->vm_id, $username);
        }
        catch (Exception $e)
        {
            throw new BadRequestException($e);
        }
        $obj = '';
        $this->set('obj', $obj);
        $this->set('_serialize', array('obj'));
    }

    function delete_external_api() {
        try {
            if($status == "POWERED_ON"){
                throw new Exception("You must have the vm powered off before you delete it");
            }
            $user = $this->Auth->user();
            $this->Vcloud->delete_vm($this->vm_id, $user['username']);
        }
        catch (Exception $e)
        {
            throw new BadRequestException($e);
        }
        $obj = '';
        $this->set('obj', $obj);
        $this->set('_serialize', array('obj'));
    }

    function delete() {
        $user = $this->Auth->user();
        $vm_id = $this->passedArgs['vm_id'];
        $app_path = dirname(APP) . "/" . basename(APP);
        $busyStatus = $this->Vcloud->is_vapp_or_vm_busy($vm_id) ;
        $vm_name = $this->Vcloud->get_vm_name($vm_id);
        if($busyStatus !== false) {
            $this->Session->setFlash('This vm "' . $vm_name . '" is unable to delete because it is busy "' . $busyStatus . '"' , 'flash_bad');
            $this->redirect($this->referer());
        }
        $this->Vcloud->add_vapp_to_busy_cache($vm_id, 'DeleteVM');
        $cmd = "/opt/bitnami/apache2/htdocs/lib/Cake/Console/cake -app $app_path vcloud --function=delete_vm --vm_id='" . $vm_id . "' --username='" . $user['username'] . "' &";
        echo Proc_Close(Proc_Open($cmd, Array(), $foo));                     //Call the above command from the command line to run in the background
        $this->Session->setFlash('This vm "' . $vm_name . '" is now being deleted', 'flash_good');
        $this->redirect($this->referer());                                      //Redirects to the previous page
    }


    function resize_cpu(){
       try {
             $busyStatus = $this->Vcloud->is_vapp_or_vm_busy($this->vm_id);
             $vm_name = $this->Vcloud->get_vm_name($this->vm_id);
             if($busyStatus !== false) {
                throw new Exception('This vm "' . $vm_name . '" is unable to resize cpu because it is busy "' . $busyStatus . '"');
             }
             $user = $this->Auth->user();
             $username = null;
             if($user == null){
                $username = "rest_api_user";
             } else {
                $username = $user['username'];
             }
             if (isset($this->passedArgs['cpu_count']) && $this->passedArgs['cpu_count'] !== ""){
                 $cpu_count = $this->passedArgs['cpu_count'];
             } else {
                 throw new Exception("You must have cpu_count set");
             }
             if (isset($this->passedArgs['hot_add']) && $this->passedArgs['hot_add'] !== ""){
                $hot_add = $this->passedArgs['hot_add'];
                $hot_add = strtolower($hot_add);
                if($hot_add != "yes"){
                    $status = $this->Vcloud->get_vm_power_status($this->vm_id);
                    if($status == "POWERED_ON"){
                       throw new Exception("You must have the vm off before you can change the cpu size");
                    }
                }
             } else {
                  throw new Exception("You must have hot_add set: yes or no");
             }
             $this->Vcloud->set_cpu_count_vm($this->vm_id, $cpu_count, $username);
        } catch (Exception $e) {
            throw new BadRequestException($e);
        }
        $obj = '';
        $this->set('obj', $obj);
        $this->set('_serialize', array('obj'));
    }

    function resize_cpu_internal_api() {
       $this->resize_cpu();
    }

    function resize_cpu_external_api() {
        $this->resize_cpu();
    }

    function set_cpu_count() {
        $user = $this->Auth->user();
        $vm_id = $this->passedArgs['vm_id'];
        $busyStatus = $this->Vcloud->is_vapp_or_vm_busy($vm_id);
        $vm_name = $this->Vcloud->get_vm_name($vm_id);
        if($busyStatus !== false) {
            $this->Session->setFlash('This vm "' . $vm_name . '" is unable to resize cpu because it is busy "' . $busyStatus . '"' , 'flash_bad');
            $this->redirect($this->referer());
        }
        $this->Vcloud->add_vapp_to_busy_cache($vm_id, 'Resizing VM CPU');
        $cpu_count = $this->passedArgs['cpu_count'];
        try{
            $this->Vcloud->set_cpu_count_vm($vm_id, $cpu_count, $user['username']);
        }catch (Exception $e){
            $this->Session->setFlash('This vm "' . $vm_name . '" is unable to resize cpu because of following: "' . $e . '"' , 'flash_bad');
            $this->redirect($this->referer());
        }
        $this->Session->setFlash('This vms cpu count has been updated', 'flash_good');
        $this->redirect($this->referer());                                      //Redirects to the previous page
    }
    function disks () {
	$vm_id = $this->passedArgs['vm_id'];
	$disk_details = $this->Vcloud->get_disk_details_vm($vm_id);
	$this->set("disk_details", $disk_details);
    }
    function console() {
	$this->layout = 'console';
        $vm_id = $this->passedArgs['vm_id'];
	$console_details = $this->Vcloud->get_console_details($vm_id);
	if ($console_details == null)
	{
		$this->Session->setFlash('Please make sure the VM is powered on before connecting to its console.', 'flash_bad');
		$this->redirect($this->referer());
	}
	$this->Vapp->id = $console_details['vapp_id'];
	$db_vapp = $this->Vapp->read();
	if (isset($db_vapp['Vapp']) && strstr($db_vapp['Vapp']['vts_name'], "atvts"))
	{
		$vapp_part=$db_vapp['Vapp']['vts_name'] . " - ";
	}
	else
	{
		$vapp_part="";
	}
	$this->set("full_vmid", $this->passedArgs['vm_id']);
	$this->set("title_for_layout", $vapp_part . $console_details['vapp_name'] . " - " . $console_details['vm_name']);
	$this->set("console_details", $console_details);
    }

    function resize_memory(){
      try {
             $busyStatus = $this->Vcloud->is_vapp_or_vm_busy($this->vm_id);
             $vm_name = $this->Vcloud->get_vm_name($this->vm_id);
             if($busyStatus !== false) {
                throw new Exception('This vm "' . $vm_name . '" is unable to resize memory because it is busy "' . $busyStatus . '"');
             }
             $user = $this->Auth->user();
             $username = null;
             if($user == null){
                $username = "rest_api_user";
             } else {
                $username = $user['username'];
             }
             if (isset($this->passedArgs['memory_mb']) && $this->passedArgs['memory_mb'] !== ""){
                 $memory_mb = $this->passedArgs['memory_mb'];
             } else {
                 throw new Exception("You must have memory_mb set");
             }
             if (isset($this->passedArgs['hot_add']) && $this->passedArgs['hot_add'] !== ""){
                $hot_add = $this->passedArgs['hot_add'];
                $hot_add = strtolower($hot_add);
                if($hot_add != "yes"){
                    $status = $this->Vcloud->get_vm_power_status($this->vm_id);
                    if($status == "POWERED_ON"){
                       throw new Exception("You must have the vm off before you can change the memory size");
                    }
                }
             } else {
                  throw new Exception("You must have hot_add set: yes or no");
             }
             $this->Vcloud->set_memory_vm($this->vm_id, $memory_mb, $username);
        } catch (Exception $e) {
            throw new BadRequestException($e);
        }
        $obj = '';
        $this->set('obj', $obj);
        $this->set('_serialize', array('obj'));
    }

    function resize_memory_internal_api() {
       $this->resize_memory();
    }

    function resize_memory_external_api() {
        $this->resize_memory();
    }

    function set_memory_mb() {
        $user = $this->Auth->user();
        $vm_id = $this->passedArgs['vm_id'];
        $busyStatus = $this->Vcloud->is_vapp_or_vm_busy($vm_id);
        $vm_name = $this->Vcloud->get_vm_name($vm_id);
        if($busyStatus !== false) {
            $this->Session->setFlash('This vm "' . $vm_name . '" is unable to resize memory because it is busy "' . $busyStatus . '"' , 'flash_bad');
            $this->redirect($this->referer());
        }
        $this->Vcloud->add_vapp_to_busy_cache($vm_id, 'Resizing VM Memory');
        $memory_mb = $this->passedArgs['memory_mb'];
        try {
            $this->Vcloud->set_memory_vm($vm_id, $memory_mb, $user['username']);
        }catch (Exception $e){
            $this->Session->setFlash('This vm "' . $vm_name . '" is unable to resize memory because of following: "' . $e . '"' , 'flash_bad');
            $this->redirect($this->referer());
        }
        $this->Session->setFlash('This vms memory has been updated', 'flash_good');
        $this->redirect($this->referer());                                      //Redirects to the previous page
    }

    # This REST function will return an xml containing a list of vms in the vapp, with the vms name and an indication of the vms security policy settings
    # If the security policy settings are set, it shows 1, if not it shows 0
    function is_security_policy_set_api() {
        $security_policy_status = $this->Vcloud->check_security_policy_status_vapp($this->passedArgs['vapp_id']);
        $this->set('vm', $security_policy_status);
        $this->set('_serialize', array('vm'));
    }

    function prepare_vm_list($vapp_id) {
        $vm_list_full = $this->Vcloud->list_vms_id($vapp_id);
        $vm_list = array();

        foreach ($vm_list_full as $vm) {
            $vm_id = $vm['vm_id'];
            $busy = $this->Vcloud->is_vapp_or_vm_busy($vm_id);
            if($busy != false){
               $vm['busy'] = true;
            }
            else {
               $vm['busy'] = false;
            }
            array_push($vm_list, $vm);
        }
        return $vm_list;
    }
}

?>
