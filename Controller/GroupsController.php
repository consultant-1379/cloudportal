<?php

class GroupsController extends AppController {

    public $name = 'Groups';
    public $uses = array('Group', 'OrgVdc', 'Org', 'Catalog');
    public $components = array('Email');

    public function beforeFilter() {
        parent::beforeFilter();
        $this->Auth->allow('notify_missing_groups');
    }

    public function isAuthorized($user) {
        if ($user['is_admin']) {
            return true;
        }
        return false;
    }

    public function index() {
        $this->OrgVdc->recursive = -1;
        $this->set('orgvdcs', $this->OrgVdc->find('all'));
        $this->set('orgs', $this->Org->find('all'));
        $this->Group->recursive = 0;
        $this->set('groups', $this->Group->find('all'));
    }

    public function notify_missing_groups() {
        $this->layout = 'ajax';
        $this->OrgVdc->recursive = -1;
        $orgvdcs = $this->OrgVdc->find('all', array('order' => array('OrgVdc.name' => 'asc')));
        $groups = $this->Group->find('all');

        $mail_list = array("mark.a.kennedy@ericsson.com", "sinead.oreilly@ericsson.com", "shane.kelly@ericsson.com", "somesh.kumar@ericsson.com", "finbar.ryan@ericsson.com");

        $email_text = "";
        foreach ($orgvdcs as $orgvdc) {
            $found_match = false;
            foreach ($groups as $group) {
                if ($group['Group']['vcloudorgvdcid'] == $orgvdc['OrgVdc']['vcd_id']) {
                    $found_match = true;
                    break;
                }
            }
            if ($found_match === false) {
                $email_text = $email_text . $orgvdc['OrgVdc']['name'] . "\r\r";
            }
        }

        if ($email_text != "") {
            $email = new CakeEmail();
            $email->from(array('no_reply@ericsson.com' => 'Cloud Portal'));
            $email->to($mail_list);
            $email->subject('Cloud Portal - OrgVdcs With No Groups');
            $email->send("The following OrgVdcs have no associated LDAP Groups in the Cloud Portal " . gethostname() . ". Please associate these OrgVdcs with LDAP groups via the SPP Groups editor\r\r" . $email_text);
        }
    }

    public function add() {
        //query the catalog table for the org id's and catalogs name and set them to the catalog_details variable
        $catalog_details = $this->Catalog->find('list', array('order' => array('name' => 'asc'), 'fields' => array('Catalog.vcd_id', 'Catalog.name')));
        $this->set(compact('catalog_details'));
        $this->OrgVdc->recursive = -1;
        $this->set('orgvdcs', $this->OrgVdc->find('all', array('order' => array('OrgVdc.name' => 'asc'))));
        $this->set('orgs', $this->Org->find('all', array('order' => array('name' => 'asc'))));
        if ($this->request->is('post')) {

            if (isset($this->data['Group']['unrestricted']) && $this->data['Group']['unrestricted'] != null) {
                //comma seperate all the selected catalogs
                $comma_seperated = implode(",", $this->data['Group']['unrestricted']);
                $replacement = array('unrestricted' => $comma_seperated);
                //replace the group array with the comma seperated catalogs
                $this->request->data['Group'] = array_replace($this->request->data['Group'], $replacement);
            }
            if ($this->Group->save($this->request->data)) {
                $this->Session->setFlash('The group has been saved', 'flash_good');
                $this->redirect(array('action' => 'index'));
            } else {
                $this->Session->setFlash('The group could not be saved. Please, try again.', 'flash_bad');
            }
        }
    }

    public function edit($id = null) {
        $this->OrgVdc->recursive = -1;
        $this->set('orgvdcs', $this->OrgVdc->find('all', array('order' => array('OrgVdc.name' => 'asc'))));
        $this->set('orgs', $this->Org->find('all', array('order' => array('name' => 'asc'))));
        $this->Group->id = $id;
        if (!$this->Group->exists()) {
            throw new NotFoundException('Invalid group');
        }
        //query the catalog table for the org id's and catalogs name and set them to the catalog_details variable
        $catalog_details = $this->Catalog->find('list', array('order' => array('name' => 'asc'), 'fields' => array('Catalog.vcd_id', 'Catalog.name')));
        $this->set(compact('catalog_details'));

        $unrestricted_all = $this->Group->read();

        if (isset($unrestricted_all) && $unrestricted_all != null) {

            $unrestricted = $unrestricted_all['Group']['unrestricted'];
            $unrestricted_catalogs = explode(",", $unrestricted);
            $select_option = array();
            // for each one, set the fact that i have read access to this catalog
            foreach ($unrestricted_catalogs as $orgid) {
                array_push($select_option, $orgid);
            }
            //sets the catalogs that have already been selected in the view
            $this->set('select_option', $select_option);
        }

        if ($this->request->is('post') || $this->request->is('put')) {
            if (isset($this->data['Group']['unrestricted']) && $this->data['Group']['unrestricted'] != null) {
                //comma seperate all the new selected catalogs            
                $comma_seperated = implode(",", $this->data['Group']['unrestricted']);
                $replacement = array('unrestricted' => $comma_seperated);
                //replace the group array with the comma seperated catalogs
                $this->request->data['Group'] = array_replace($this->request->data['Group'], $replacement);
            }
            if ($this->Group->save($this->request->data)) {
                $this->Session->setFlash('The group has been saved', 'flash_good');
                $this->redirect(array('action' => 'index'));
            } else {
                $this->Session->setFlash('The group could not be saved. Please, try again.', 'flash_bad');
            }
        } else {
            $this->request->data = $this->Group->read();
        }
    }

    public function delete($id = null) {
        if ($this->request->is('get')) {
            throw new MethodNotAllowedException();
        }

        if (!$id) {
            $this->Session->setFlash('Invalid id for group', 'flash_bad');
            $this->redirect(array('action' => 'index'));
        }
        if ($this->Group->delete($id)) {
            $this->Session->setFlash('Group deleted', 'flash_good');
            $this->redirect(array('action' => 'index'));
        }
        $this->Session->setFlash('Group was not deleted', 'flash_bad');
        $this->redirect(array('action' => 'index'));
    }

}
?>

