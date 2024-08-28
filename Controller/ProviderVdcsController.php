<?php

class ProviderVdcsController extends AppController {

    var $components = array('Session');
    var $uses = array('Vcloud', 'ProviderVdc', 'OrgVdc', 'MigRa');

    public function beforeFilter() {
        parent::beforeFilter();
        $this->Auth->allow("import");
    }

    function index() {
        $db_pvdcs = $this->ProviderVdc->find('all', array('contain' => false));
        $this->set('db_pvdcs', $db_pvdcs);
    }

    public function edit() {
        $this->ProviderVdc->id = $this->passedArgs['providervdc_id'];

        if (!$this->ProviderVdc->exists()) {
            throw new NotFoundException('Invalid Provider Vdc');
        }

        if ($this->request->is('post') || $this->request->is('put')) {
            if ($this->ProviderVdc->save($this->request->data)) {
                $this->Session->setFlash('The providervdc has been saved', 'flash_good');
                $this->redirect(array('action' => 'index'));
            } else {
                $this->Session->setFlash('The providervdc could not be saved. Please, try again.', 'flash_bad');
            }
        } else {
            $this->request->data = $this->ProviderVdc->find('first', array('conditions' => array('ProviderVdc.vcd_id' => $this->ProviderVdc->id),'fields' => array('name', 'new_quota_system', 'cpu_multiplier', 'memory_multiplier', 'available_cpus', 'available_memory'), 'contain' => false));
        }
    }

    function import() {
        $params = array(
            'type' => 'providerVdc',
            'fields' => array('name'),
            'generated_fields' => array('provider_vdc_id')
        );
        $pvdcs = $this->Vcloud->query_service_request($params);
        $db_pvdcs = $this->ProviderVdc->find('all', array('fields' => array('vcd_id'),'contain' => false));
        $first_ra = $this->MigRa->find('first', array('conditions' => array('name' => 'ENM'), 'contain' => false));
        if (isset($first_ra['MigRa']))
        {
            $new_quota_system_enabled_for_new_datacenters = true;
        } else {
            $new_quota_system_enabled_for_new_datacenters = false;
        }

        foreach ($pvdcs as $pvdc) {
            $already_exists_in_db = false;
            foreach ($db_pvdcs as $db_pvdc) {
                if ($db_pvdc['ProviderVdc']['vcd_id'] == $pvdc['provider_vdc_id']) {
                    $already_exists_in_db = true;
                    break;
                }
            }
            $provider_cpu_memory_totals = $this->Vcloud->get_provider_cpu_memory_totals($pvdc['name']);
            $pvdc_data = array('id' => '', 'name' => $pvdc['name'], 'vcd_id' => $pvdc['provider_vdc_id'], 'available_cpus' => $provider_cpu_memory_totals['cpus'], 'available_memory' => floor($provider_cpu_memory_totals['memory'] / 1024));
            if (!$already_exists_in_db)
            {
                $pvdc_data['new_quota_system'] = $new_quota_system_enabled_for_new_datacenters;
            }
            $this->ProviderVdc->save($pvdc_data);
        }

        $db_pvdcs = $this->ProviderVdc->find('all', array('fields' => array('vcd_id'), 'contain' => false));

        foreach ($db_pvdcs as $db_pvdc) {
            $match = 0;

            foreach ($pvdcs as $pvdc) {
                if ($db_pvdc['ProviderVdc']['vcd_id'] == $pvdc['provider_vdc_id']) {
                    $match = 1;                             //Tell the loop
                    break;
                }
            }

            if ($match == 0) {
                // Delete it from the db
                $this->OrgVdc->deleteAll(array('provider_vdc_id' => $db_pvdc['ProviderVdc']['vcd_id']));
                $this->ProviderVdc->delete($db_pvdc['ProviderVdc']['vcd_id']);
            }
        }
    }
}

?>
