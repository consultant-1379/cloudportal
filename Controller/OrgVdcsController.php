<?php

class OrgVdcsController extends AppController {

    var $components = array('Session');
    var $uses = array('Vcloud', 'OrgVdc', 'Vapp', 'MigRa', 'ProviderVdc');

    public function beforeFilter() {
        parent::beforeFilter();
        $this->Auth->allow("import","index_with_ras");
    }

    function isAuthorized($user) {
        if ($user['is_admin']) {
            return true;
        }

        // Always allow people to view the index
        if (in_array($this->action, array('index', 'index_api'))) {
            return true;
        }

        // Check before allowing them to edit, only admins can do this
        if (in_array($this->action, array('edit'))) {
            return false;
        }

        return false;
    }

    function index_api() {
        try {
            $db_orgvdcs = $this->list_filtered_orgvdcs();
        } catch (Exception $e) {
            throw new BadRequestException('Something went wrong here is the issue: ' . $e);
        }
        $this->set('db_orgvdcs', $db_orgvdcs);
        $this->set('_serialize', array('db_orgvdcs'));
    }

/*
 * This REST call returns the list of datacenters along with their associated RA
*/
    function index_with_ras() {
        $this->OrgVdc->recursive = 0;
        $this->set('OrgVdcs', $this->OrgVdc->find('all', array('fields' => array('name', 'MigRa.name'))));
        $this->set('_serialize', array('OrgVdcs'));
    }

    function list_filtered_orgvdcs() {
        $db_orgvdcs = $this->OrgVdc->find('all', array('fields' => array('name', 'running_tb_limit', 'stored_tb_limit', 'cpu_limit', 'memory_limit', 'ProviderVdc.new_quota_system', 'ProviderVdc.name', 'ProviderVdc.cpu_multiplier', 'ProviderVdc.memory_multiplier', 'ProviderVdc.available_cpus', 'ProviderVdc.available_memory', 'vcd_id', 'MigRa.name'), 'contain' => array('MigRa','ProviderVdc')));
        $user = $this->Auth->user();
        if (!$user['is_admin']) {
            foreach ($db_orgvdcs as $key => $orgvdc) {
                if (isset($user['permissions'][$orgvdc['OrgVdc']['vcd_id']]['read_permission']) && $user['permissions'][$orgvdc['OrgVdc']['vcd_id']]['read_permission']) {
                } else {
                    unset($db_orgvdcs[$key]);
                }
            }
        }
        return $db_orgvdcs;
    }

    function index() {
        $this->set("title_for_layout", "Clouds");
        $this->set('page_for_layout', 'home');

        if (isset($this->passedArgs['org_id'])) {
            $org_id = $this->passedArgs['org_id'];
        } else {
            $org_id = "";
        }

        $db_orgvdcs = $this->list_filtered_orgvdcs();
        $orgvdc_count = count($db_orgvdcs);
        if ($orgvdc_count == 1) {
            foreach ($db_orgvdcs as $db_orgvdc) {
                $this->redirect(array('controller' => 'Vapps', 'action' => 'index', 'orgvdc_id' => $db_orgvdc['OrgVdc']['vcd_id']));
                break;
            }
        }
        if ($orgvdc_count == 0) {
            $this->Session->setFlash("You don't have access to any areas of the Cloud yet, please request access and try again", 'flash_bad');
        }
        $this->set('db_orgvdcs', $db_orgvdcs);
    }

    public function edit() {
        $this->OrgVdc->id = $this->passedArgs['orgvdc_id'];

        if (!$this->OrgVdc->exists()) {
            throw new NotFoundException('Invalid Org Vdc');
        }

        $this_orgvdc = $this->OrgVdc->find('first', array('conditions' => array('OrgVdc.vcd_id' => $this->OrgVdc->id),'fields' => array('name', 'vcd_id', 'running_tb_limit', 'stored_tb_limit', 'mig_ra_id', 'cpu_limit', 'memory_limit', 'ProviderVdc.new_quota_system', 'ProviderVdc.available_cpus', 'ProviderVdc.available_memory', 'ProviderVdc.cpu_multiplier', 'ProviderVdc.memory_multiplier'),'contain' => 'ProviderVdc'));
        if ($this->request->is('post') || $this->request->is('put')) {
            if ($this->OrgVdc->save($this->request->data)) {
                $this->Session->setFlash('The orgvdc has been saved', 'flash_good');
                $this->redirect(array('action' => 'index'));
            } else {
                $this->Session->setFlash('The orgvdc could not be saved. Please, try again.', 'flash_bad');
            }
        } else {
            $this->request->data = $this_orgvdc;
        }

        $db_orgvdcs = $this->OrgVdc->find('all', array('conditions' => array('OrgVdc.provider_vdc_id' => $this_orgvdc['ProviderVdc']['vcd_id']), 'fields' => array('name', 'vcd_id', 'cpu_limit', 'memory_limit'), 'contain' => 'ProviderVdc'));

        $this->set('db_orgvdcs', $db_orgvdcs);
        $migRas = $this->MigRa->find('list');
        $this->set(compact('migRas'));
    }

    function allocation_models ()
    {
        $orgvdcs = $this->Vcloud->list_orgvdcs_pay_as_you_go();
        $this->set('orgvdcs', $orgvdcs);
    }

    function import() {
        $params = array(
            'type' => 'adminOrgVdc',
            'fields' => array('name'),
            'generated_fields' => array('org_vdc_id', 'org_vdc_vcenter', 'org_vdc_provider_vdc_id')
        );
        $orgvdcs = $this->Vcloud->query_service_request($params);

        $db_orgvdcs = $this->OrgVdc->find('all', array('fields' => array('vcd_id'),'contain' => false));

        foreach ($orgvdcs as $orgvdc) {
            $already_exists_in_db = false;
            foreach ($db_orgvdcs as $db_orgvdc) {
                if ($db_orgvdc['OrgVdc']['vcd_id'] == $orgvdc['org_vdc_id']) {
                    $already_exists_in_db = true;
                    break;
                }
            }
            $orgvdc_data = array('id' => '', 'name' => $orgvdc['name'], 'vcd_id' => $orgvdc['org_vdc_id'], 'vcenter' => $orgvdc['org_vdc_vcenter'], 'provider_vdc_id' => $orgvdc['org_vdc_provider_vdc_id']);
            $this->OrgVdc->save($orgvdc_data);
        }

        $db_orgvdcs = $this->OrgVdc->find('all', array('fields' => array('vcd_id'), 'contain' => false));

        foreach ($db_orgvdcs as $db_orgvdc) {
            $match = 0;

            foreach ($orgvdcs as $orgvdc) {
                if ($db_orgvdc['OrgVdc']['vcd_id'] == $orgvdc['org_vdc_id']) {
                    $match = 1;                             //Tell the loop
                    break;
                }
            }

            if ($match == 0) {
                // Delete it from the db
                $this->Vapp->deleteAll(array('org_vdc_id' => $db_orgvdc['OrgVdc']['vcd_id']));
                $this->OrgVdc->delete($db_orgvdc['OrgVdc']['vcd_id']);
            }
        }
    }

}

?>
