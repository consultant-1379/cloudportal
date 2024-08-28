<?php

/*
 * 
 */

class OrgsController extends AppController {

    var $components = array('Session');
    var $uses = array('Vcloud', 'Org','Catalog');

    public function beforeFilter() {
        parent::beforeFilter();
        $this->Auth->allow("import");
    }

    public function isAuthorized($user) {
        return true;
    }

    function index() {

        $orgs = $this->Vcloud->list_orgs();
        $user = $this->Auth->user();
        if (!$user['is_admin']) {
            // Check what orgs they have read permission for
            foreach ($orgs as $key => $org) {
                if (isset($user['permissions'][$org['org_id']]['read_permission']) && $user['permissions'][$org['org_id']]['read_permission']) {
                    
                } else {
                    unset($orgs[$key]);
                }
            }
        }
        $this->set('orgs', $orgs);
    }

    function import() {
        $db_orgs = $this->Org->find('all');
	$orgs = $this->Vcloud->list_orgs();
        foreach ($orgs as $org) {
            $match = 0;
            foreach ($db_orgs as $db_org) {
                if ($db_org['Org']['vcd_id'] == $org['org_id']) {
                    $match = 1;
                    break;
                }
            }
            if ($match == 1) {
                $theid = $db_org['Org']['id'];
            }
            else
            {
                $theid = '';
            }
            $org_data = array('id' => $theid,'name' => $org['name'], 'vcd_id' => $org['org_id'], 'description' => $org['display_name'], 'href' => $org['href']);
            $this->Org->save($org_data);
         }

	foreach ($db_orgs as $db_org) {
            $match = 0;

            foreach ($orgs as $org) {
                if ($db_org['Org']['vcd_id'] == $org['org_id']) {
                    $match = 1;                             //Tell the loop
                    break;
                }
            }

            if ($match == 0) {
                // Delete it from the db
		$this->Catalog->deleteAll(array('org_id' => $db_org['Org']['vcd_id']));
                $this->Org->delete($db_org['Org']['vcd_id']);
            }
        }
        //$this->Session->setFlash('Orgs from vCloud have been imported successfully!','flash_good');
        //$this->redirect(array('action' => 'index'));
    }

}

?>
