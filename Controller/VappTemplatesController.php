<?php

class VappTemplatesController extends AppController {

    var $components = array('Session');
    var $uses = array('Vcloud', 'VappTemplate', 'OrgVdc');

    public function beforeFilter() {
        parent::beforeFilter();
        $this->Auth->allow("tasks");
    }

    function isAuthorized($user) {
        if ($user['is_admin']) {
            return true;
        }

        if (in_array($this->action, array('index', 'index_api'))) {
            $catalog_id = $this->Vcloud->get_catalog_id_by_name($this->passedArgs['catalog_name']);
            if (isset($user['permissions']['restrict_catalogs'])) {
                if (isset($user['permissions'][$catalog_id]['read_permission']) && $user['permissions'][$catalog_id]['read_permission']) {
                    return true;
                }
            } else {
                return true;
            }
        }

        if (in_array($this->action, array('deploy_api'))) {

            $vappTemplateId = $this->passedArgs['vapp_template_id'];
            $catalog_id = $this->Vcloud->get_catalog_id_by_vappTemplate($vappTemplateId);

            if (isset($user['permissions']['restrict_catalogs'])) {
                if (isset($user['permissions'][$catalog_id]['read_permission']) && $user['permissions'][$catalog_id]['read_permission']) {
                    //return true;
                } else {
                    return false;
                }
            }


            $orgvdc = $this->Vcloud->get_orgvdc_id_by_name($this->passedArgs['datacenter']);
            $orgvdc_id = $orgvdc[0]['orgvdc_id'];

            if (isset($user['permissions'][$orgvdc_id]['write_permission']) && $user['permissions'][$orgvdc_id]['write_permission']) {
                return true;
            }
        }

        if (in_array($this->action, array('deploy'))) {

            if (!empty($this->request->data)) {
                // Get the org vdc id from the org vdc name, for the permission check
                if (isset($this->request->data['VappTemplate']['orgvdc'])) {
                    $orgvdc = $this->Vcloud->get_orgvdc_id_by_name($this->request->data['VappTemplate']['orgvdc']);
                    $orgvdc_id = $orgvdc[0]['orgvdc_id'];
                    if ($user['permissions'][$orgvdc_id]['write_permission']) {
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

        if (in_array($this->action, array('delete', 'delete_api', 'rename','rename_api'))) {

            if (isset($user['permissions'][$this->passedArgs['org_id']]['write_permission']) && $user['permissions'][$this->passedArgs['org_id']]['write_permission']) {
                return true;
            }
        }

        // Default to not allowing them access
        return false;
    }

    function rename() {
        $id = $this->passedArgs['vapp_template_id'];
        $vapp_template_details = $this->Vcloud->get_vapp_template_details($id);
        if (!empty($this->request->data)) {
            $this->VappTemplate->set($this->request->data);
            try {
                $name = $this->request->data['VappTemplate']['name'];
                $this->Vcloud->rename_vapp_template($id, $name);
            } catch (Exception $e) {
                $this->Session->setFlash('Something went wrong renaming the vapp template. Here is the issue: ' . $e, 'flash_bad');
                throw new InternalErrorException('Something went wrong renaming the vapp template. Here is the issue: ' . $e);
            }
            $this->Session->setFlash('This vApp template has been renamed', 'flash_good');
            $this->redirect(array('controller' => 'vappTemplates', 'action' => 'index', 'catalog_name' => $vapp_template_details['catalog_name'], 'org_id' => $vapp_template_details['org_id']));
        }

        $name = $vapp_template_details['name'];
        $this->set('name', $name);
    }

    function rename_api(){
        $user = $this->Auth->user();
        $this->Vcloud->rename_vapp_template($this->passedArgs['vapp_template_id'],$this->passedArgs['vapp_template_new_name']);
        $obj = "";
        $this->set('obj', $obj);
        $this->set('_serialize', array('obj'));
    }

    function delete() {

        $vapptemplateID = $this->passedArgs['vapp_template_id'];
        $user = $this->Auth->user();
        $app_path = dirname(APP) . "/" . basename(APP);
        $cmd = "/opt/bitnami/apache2/htdocs/lib/Cake/Console/cake -app $app_path vcloud --function=delete_vapp_template --vapp_template_id='" . $vapptemplateID . "' --username=" . $user['username'] . " &";
        echo Proc_Close(Proc_Open($cmd, Array(), $foo));                     //Call the above command from the command line to run in the background
        $this->Session->setFlash('This template is now being deleted', 'flash_good');
        $this->redirect($this->referer());                                      //Redirects to the previous page
    }

    function delete_api() {
        $user = $this->Auth->user();
        $this->Vcloud->delete_vapp_template($this->passedArgs['vapp_template_id'], $user['username']);
        $obj = "";
        $this->set('obj', $obj);
        $this->set('_serialize', array('obj'));
    }

    function is_exists() {
        if ($this->Vcloud->is_exists($this->passedArgs['vapp_template_id'])) {
            $status = "true";
            $this->set('Status', $status);
            $this->set('_serialize', array('Status'));
        } else {
            throw new BadRequestException('Catalogued vApp does not exist');
//                    $status="false";
//                    $this->set('Status', $status);
//                    $this->set('_serialize', array('Status'));
        }
    }

    function index_api() {
        try {
            $argCatalog = urlencode($this->passedArgs['catalog_name']);
            $org = $this->Vcloud->get_orgid_from_catalog($this->passedArgs['catalog_name']);
            $params= array(
                'type' => "adminVAppTemplate",
                'fields' => array('name','status','creationDate'),
                'generated_fields' => array('vapp_template_id'),
                'sortDesc' => 'creationDate',
                'filter' => "isExpired==false;org==" . $this->Vcloud->get_href_from_id($org[0]['org_id']) . ";(catalogName==" . urlencode($this->passedArgs['catalog_name']) . ",isInCatalog==false)"
            );
            $vapptemplates = $this->Vcloud->query_service_request($params);

            // Make backwards compatible with frields from previous implementation of rest call
            $vapptemplates = array_map(function($vapptemplate) {
                return array (
                    'vapptemplate_name' => $vapptemplate['name'],
                    'status' => $vapptemplate['status'],
                    'creation_date' => date('d/m/Y H:i', strtotime($vapptemplate['creationDate'])),
                    'vapptemplate_id' => $vapptemplate['vapp_template_id']
                );
            }, $vapptemplates);
        } catch (Exception $e) {
            throw new BadRequestException('Something went wrong here is the issue: ' . $e);
        }
        $this->set('vapptemplates', $vapptemplates);
        $this->set('_serialize', array('vapptemplates'));
    }

    function index($inputs = null) {
        $this->set('page_for_layout', 'catalogs');
        $this->set("title_for_layout", $this->passedArgs['catalog_name'] . " Catalog");
        $catalogName = $this->passedArgs['catalog_name'];
        $org_id = $this->passedArgs['org_id'];

        $params= array(
            'type' => "adminVAppTemplate",
            'fields' => array('name','status','creationDate'),
            'generated_fields' => array('vapp_template_id'),
            'sortDesc' => 'creationDate',
            'filter' => "isExpired==false;org==" . $this->Vcloud->get_href_from_id($org_id) . ";(catalogName==" . urlencode($this->passedArgs['catalog_name']) . ",isInCatalog==false)"
        );
        $vapptemplates = $this->Vcloud->query_service_request($params);
        $vapptemplate_resources = $this->Vcloud->list_vapptemplates_resourses($catalogName);

        foreach ($vapptemplates as &$vapptemplate)
        {
            foreach ($vapptemplate_resources as $vapptemplate_resource)
            {
                if ($vapptemplate['vapp_template_id'] == $vapptemplate_resource['vapp_template_id'])
                {
                    $vapptemplate['cpu_total'] = $vapptemplate_resource['cpu_total'];
                    $vapptemplate['memory_total'] = $vapptemplate_resource['memory_total'];
                    break;
                }
            }
        }
        $this->set('vapptemplates', $vapptemplates);
    }

    function sync_vapp_template_to_other_pods_api() {
        try {
            $result = $this->Vcloud->sync_vapp_template_to_other_pods($this->passedArgs['vapp_template_id']);
        } catch (Exception $e)
        {
            throw new BadRequestException('Something went wrong here is the issue: ' . $e);
        }
        $this->set('result', $result);
        $this->set('_serialize', array('result'));
    }

    function sync_vapp_from_another_pod_api()
    {
        try {
            $inputs = json_decode($_POST['data'],true);
            $destination_pod_settings = $inputs['destination_pod_settings'];
            $new_template_name = $inputs['new_template_name'];
            $source_vapp_network_settings = $inputs['source_vapp_network_settings'];
            $source_vapp_startup_settings = $inputs['source_vapp_startup_settings'];
            $vm_details = $inputs['vm_details'];
            $vapp_template = $this->Vcloud->sync_vapp_from_another_pod($destination_pod_settings,$source_vapp_network_settings,$source_vapp_startup_settings,$new_template_name,$vm_details);
        } catch (Exception $e)
        {
            throw new BadRequestException('Something went wrong here is the issue: ' . $e);
        }
        $this->set('vapptemplate', $vapp_template);
        $this->set('_serialize', array('vapptemplate'));
    }

    function register_vm_api ()
    {
        try {
            $datastore = $this->passedArgs['datastore'];
            $pool = $this->passedArgs['pool'];
            $vm_folder_name = $this->passedArgs['vm_folder_name'];
            $vcenter_datacenter = $this->passedArgs['vcenter_datacenter'];
            $vcenter_hostname = $this->passedArgs['vcenter_hostname'];
            $vm_id = $this->Vcloud->register_vm($datastore,$pool,$vm_folder_name,$vcenter_datacenter,$vcenter_hostname);
        } catch (Exception $e)
        {
            throw new BadRequestException('Something went wrong here is the issue: ' . $e);
        }
        $this->set('vm_id', $vm_id);
        $this->set('_serialize', array('vm_id'));
    }

    function mount_snapshot_to_host_api()
    {
        try {
            $datastore = $this->passedArgs['datastore'];
            $host = $this->passedArgs['host'];
            $vcenter_hostname = $this->passedArgs['vcenter_hostname'];
            $this->Vcloud->mount_snapshot_to_host($vcenter_hostname, $datastore, $host);
        } catch (Exception $e)
        {
            throw new BadRequestException('Something went wrong here is the issue: ' . $e);
        }
        $this->set('result', 'success');
        $this->set('_serialize', array('result'));
    }

    function deploy_api() {
        $user = $this->Auth->user();
        $vapp_template_name = '';
        $vapp_template_orgvdc = '';
        $vapp_template_powerOn = '';
        $deploy_params = array(
            'vapp_template_id' => $this->passedArgs['vapp_template_id'],
            'new_vapp_name' => $this->passedArgs['new_vapp_name'],
            'linked_clone' => $this->passedArgs['linked_clone'],
            'destorgvdcname' => $this->passedArgs['datacenter'],
            'start_vapp' => $this->passedArgs['poweron']
        );
        $orgvdc = $this->Vcloud->get_orgvdc_id_by_name($deploy_params['destorgvdcname']);
        $orgvdc_id = $orgvdc[0]['orgvdc_id'];
        try {
            // Start of Runtime Quota Checks
            $orgvdc_db_entry = $this->OrgVdc->find('first', array('conditions' => array('OrgVdc.vcd_id' => $orgvdc_id), 'fields' => array('ProviderVdc.new_quota_system'),'contain' => 'ProviderVdc'));
            $vapptemplateID = $this->passedArgs['vapp_template_id'];
            if ($this->passedArgs['poweron'] == "yes") {
                if ($orgvdc_db_entry['ProviderVdc']['new_quota_system'])
                {
                    if (!$this->Vcloud->allowed_power_on_vapp_resources($orgvdc_id, $vapptemplateID)) {
                        throw new BadRequestException('Starting this vApp would bring you over the running Resource (CPU/Memory) quota, please power off other vApps first');
                    }
                } else {
                    // Check are we allowed power another one on
                    if (!$this->Vcloud->allowed_poweron_another_vapp($orgvdc_id)) {
                        throw new BadRequestException('Choosing to power on this new vApp would bring you over the running vApp quota, please power off other vApps first');
                    }
                }
            }
            if (!$this->Vcloud->allowed_created_another_vapp($orgvdc_id)) {
                throw new BadRequestException('Creating this vApp would bring you over the Total vApps quota, please delete other vApps first');
            }

            # Check for deploying across vcenters
            $vcenter_of_vapp_template = $this->Vcloud->get_vcenter_of_vapp($vapptemplateID);
            $vcenter_of_orgvdc = $this->Vcloud->get_vcenter_of_orgvdc($orgvdc_id);
            if(($vcenter_of_orgvdc != null) && ($vcenter_of_vapp_template != $vcenter_of_orgvdc))
            {
                throw new BadRequestException('You cannot deploy a vapp from one vcenter to another. (ie Catalog is in ' . $vcenter_of_vapp_template . ' and cloud area is in ' . $vcenter_of_orgvdc . '). Please seek support if you believe this to be incorrect');
            }
            if (strpos($this->passedArgs['datacenter'], "sync_") !== false){
               $vapp_details = $this->Vcloud->deploy_from_catalog($deploy_params, "sync", "sync");
            } else {
               $vapp_details = $this->Vcloud->deploy_from_catalog($deploy_params, $user['username'], $user['email']);
            }
        } catch (Exception $e) {
            throw new BadRequestException('Something went wrong here is the issue: ' . $e);
        }

        $this->set('vapp_details', $vapp_details);
        $this->set('_serialize', array('vapp_details'));
    }

    function deploy() {
        $this->set('page_for_layout', 'catalogs');
        $user = $this->Auth->user();

        if (!empty($this->request->data)) {

            $this->VappTemplate->set($this->request->data);

            if ($this->VappTemplate->validates()) {

                $vapptemplateID = $this->passedArgs['vapp_template_id'];
                $vapp_name = $this->request->data['VappTemplate']['name'];
                $trimmed_vapp_name = trim($vapp_name);
                $org_vdc_name = $this->request->data['VappTemplate']['orgvdc'];
                $orgvdc = $this->Vcloud->get_orgvdc_id_by_name($this->request->data['VappTemplate']['orgvdc']);
                $orgvdc_id = $orgvdc[0]['orgvdc_id'];
                $orgvdc_db_entry = $this->OrgVdc->find('first', array('conditions' => array('OrgVdc.vcd_id' => $orgvdc_id), 'fields' => array('ProviderVdc.new_quota_system'),'contain' => 'ProviderVdc'));
                if ($this->request->data['VappTemplate']['powerOn']) {
                    $start = "yes";
                } else {
                    $start = "no";
                }
                // Start of Runtime Quota Checks
                if ($orgvdc_db_entry['ProviderVdc']['new_quota_system'] && $start == "yes"){
                    $result = $this->Vcloud->allowed_power_on_vapp_resources($orgvdc_id, $vapptemplateID);
                    if(!$result){
                        $this->Session->setFlash('Starting this vApp "' . $trimmed_vapp_name . '" would bring you over the running Resource (CPU/Memory) quota, please power off other vApps first', 'flash_bad');
                        $this->redirect($this->referer());
                    }
                } elseif (!$orgvdc_db_entry['ProviderVdc']['new_quota_system'] && $start == "yes"){
                    // Check are we allowed power another one on
                    if (!$this->Vcloud->allowed_poweron_another_vapp($orgvdc_id)) {
                        $this->Session->setFlash('Choosing to power on this new vApp would bring you over the running vApp quota, please power off other vApps first', 'flash_bad');
                        $this->redirect($this->referer());
                    }
                }

                if (isset($user['email']) && $user['email'] != "") {
                    $email = " --email=" . $user['email'];
                } else {
                    $email = "";
                }

                // End of Runtime Quota Checks
                //checks if another vapp can be created
                if (!$this->Vcloud->allowed_created_another_vapp($orgvdc_id)) {
                    $this->Session->setFlash('Creating this vApp "' . $trimmed_vapp_name . '" would bring you over the Total vApps quota, please delete other vApps first', 'flash_bad');
                    $this->redirect($this->referer());
                }

                # Check for deploying across vcenters
                if (!$user['is_admin'])
                {
                    $vcenter_of_vapp_template = $this->Vcloud->get_vcenter_of_vapp($vapptemplateID);
                    $vcenter_of_orgvdc = $this->Vcloud->get_vcenter_of_orgvdc($orgvdc_id);
                    if(($vcenter_of_orgvdc != null) && ($vcenter_of_vapp_template != $vcenter_of_orgvdc))
                    {
                        $this->Session->setFlash('You cannot deploy a vapp from one vcenter to another. (ie Catalog is in ' . $vcenter_of_vapp_template . ' and cloud area is in ' . $vcenter_of_orgvdc . '). Please seek support if you believe this to be incorrect', 'flash_bad');
                        $this->redirect($this->referer());
                    }
                }
                $app_path = dirname(APP) . "/" . basename(APP);
                $cmd = "/opt/bitnami/apache2/htdocs/lib/Cake/Console/cake -app $app_path vcloud --function=deploy_from_catalog --linked_clone=true --new_vapp_name='" . $trimmed_vapp_name . "' --vapp_template_id='" . $vapptemplateID . "' --destorgvdcname=" . $org_vdc_name . " --start_vapp=" . $start . " --username=" . $user['username'] . $email . "&";

                echo Proc_Close(Proc_Open($cmd, Array(), $foo));
                $this->Session->setFlash('Your vApp is now being added to your cloud. You will receive an email when its ready.', 'flash_good');
                $this->redirect(array('controller' => 'vapps', 'action' => 'index', 'orgvdc_id' => $orgvdc_id));
            }
        }

        // The form either was submitted and didn't validate, or it wasn't submitted
        //$orgvdcs = $this->Vcloud->list_orgvdcs("");
        $orgvdcs = $this->OrgVdc->find('all', array('fields' => array('name', 'vcd_id', 'vcenter'), 'order' => 'OrgVdc.name ASC','contain' => false));

        $user = $this->Auth->user();
        if (!$user['is_admin']) {
            foreach ($orgvdcs as $key => $orgvdc) {
                if (isset($user['permissions'][$orgvdc['OrgVdc']['vcd_id']]['write_permission']) && $user['permissions'][$orgvdc['OrgVdc']['vcd_id']]['write_permission']) {
                    
                } else {
                    unset($orgvdcs[$key]);
                }
            }
        }

        if (count($orgvdcs) == 0) {
            $this->Session->setFlash('You dont have permission to add vApps to any areas of the Cloud yet, please contact your team co-ordinator to give you access if required.', 'flash_bad');
            $this->redirect($this->referer());
        }
        $this->set('orgvdcs', $orgvdcs);
    }

    //this REST API is accessed by Yamaapp/
    function catalog_details_from_vapptemplateid() {
        try {
            $catalog = $this->Vcloud->get_vapp_template_details($this->passedArgs['template_id']);
        } catch (Exception $e) {
            throw new BadRequestException('Something went wrong here is the issue: ' . $e);
        }
        $this->set('catalog_details', $catalog);
        $this->set('_serialize', array('catalog_details'));
    }

    function tasks() {
        $this->layout = 'ajax';
        $vappTemplateID = $this->passedArgs['vapp_template_id'];
        $this->set('vapp_task', $this->Vcloud->get_vapp_task($vappTemplateID));
    }
}
?>
