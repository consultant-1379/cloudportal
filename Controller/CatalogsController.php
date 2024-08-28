<?php

class CatalogsController extends AppController {

    var $name = 'Catalogs';
    var $components = array('Session');
    var $uses = array('Vcloud', 'Catalog', 'Group');

    public function beforeFilter() {
        parent::beforeFilter();
        $this->Auth->allow("import");
    }

    function isAuthorized($user) {
        return true;
    }

    function index_api($argOrgName = null) {
        try {
            $user = $this->Auth->user();
            $catalogs = $this->Catalog->find('all', array('order' => 'Catalog.name'));
            
        if (isset($user['permissions']['restrict_catalogs']) && $user['permissions']['restrict_catalogs']) {
                // ok they are restricted to at least one catalog, 
                // loop through them all to only show them the restricted ones
                foreach ($catalogs as $key => $catalog) {
                    if (isset($user['permissions'][$catalog['Catalog']['vcd_id']]["read_permission"]) && $user['permissions'][$catalog['Catalog']['vcd_id']]["read_permission"]){
      
                        }else{
                            
                        unset($catalogs[$key]);
                        }
                    }
            }
        } catch (Exception $e) {
            throw new BadRequestException('Something went wrong here is the issue: ' . $e);
        }
        $this->set('catalogs', $catalogs);
        $this->set('_serialize', array('catalogs'));
    }

    function index_by_vm_api() {
        $org = $this->Vcloud->get_org_id_by_vm($this->passedArgs['vm_id']);
        try {
            $catalogs = $this->Vcloud->list_catalogs_by_org($org);
        } catch (Exception $e) {
            throw new BadRequestException('Something went wrong here is the issue: ' . $e);
        }
        $this->set('catalogs', $catalogs);
        $this->set('_serialize', array('catalogs'));
    }
    function index() {
        $this->set('page_for_layout', 'catalogs');
        //$this->set('catalogs', $this->Catalog->find('all'));
        //$catalogs = $this->Vcloud->list_catalogs($argOrgName);
        $catalogs = $this->Catalog->find('all', array('fields' => array('name', 'vcd_id','org_id')));
        // Check if they have write access to only one catalog, to bring them to that catalog
        $write_access = 0;
        $user = $this->Auth->user();
        if (!isset($this->passedArgs['redirect'])) {
            foreach ($catalogs as $catalog) {
                if ($write_access > 1) {
                    break;
                }
                if (isset($user['permissions'][$catalog['Catalog']['org_id']]['write_permission']) && $user['permissions'][$catalog['Catalog']['org_id']]['write_permission']) {
                    $write_access++;
                    $the_write_catalog = $catalog;
                }
            }
        }

        //url variable is used as a check when the browse other catalogs link is clicked
        //$url = $this->here;
        //if ($url == '/Catalogs/index/redirect:no' && !$user['is_admin']) {
            //redirect back to index to filer unrestricted catalogs
        //    $this->redirect(array('controller' => 'Catalogs', 'action' => 'index'));
        //}

        if ($write_access == 1) {
            $this->redirect(array('controller' => 'vappTemplates', 'action' => 'index', 'catalog_name' => $the_write_catalog['Catalog']['name'], 'org_id' => $the_write_catalog['Catalog']['org_id']));
        } else {
            //check if the user is retricted to one catalog           
            if (isset($user['permissions']['restrict_catalogs']) && $user['permissions']['restrict_catalogs']) {
                // ok they are restricted to at least one catalog, 
                // loop through them all to only show them the restricted ones
                foreach ($catalogs as $key => $catalog) {
                    if (isset($user['permissions'][$catalog['Catalog']['vcd_id']]["read_permission"]) && $user['permissions'][$catalog['Catalog']['vcd_id']]["read_permission"]){
      
                        }else{
                            
                        unset($catalogs[$key]);
                        }
                    }
            }

            $this->set('dbcatalogs', $catalogs);
        }
    }

    function import() {

        $catalogs = $this->Vcloud->list_catalogs();

        foreach ($catalogs as $catalog) {
            $id = '';
            $catalog_data = array('id' => $id, 'name' => $catalog['catalog_name'], 'vcd_id' => $catalog['vcd_id'], 'org_id' => $catalog['org_id']);       //Create of values for the database

            $this->Catalog->save($catalog_data);
        }

        $db_catalogs = $this->Catalog->find('all');

        foreach ($db_catalogs as $db_catalog) {
            $match = 0;

            foreach ($catalogs as $catalog) {
                if ($db_catalog['Catalog']['vcd_id'] == $catalog['vcd_id']) {
                    $match = 1;                             //Tell the loop
                    break;
                }
            }

            if ($match == 0) {
                // Delete it from the db
                $this->Catalog->delete($db_catalog['Catalog']['vcd_id']);
            }
        }
    }
    function listWriteableCatalogs_api(){

        $user = $this->Auth->user();
        $catalogs = $this->Catalog->find('all', array('order' => 'Catalog.name ASC'));
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
        //$catalogs = "works";
        $this->set('catalogs', $catalogs);
        $this->set('_serialize', array('catalogs'));
    }

}

?>
