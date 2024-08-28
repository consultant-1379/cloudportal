<?php

class CitagsController extends AppController {

    public $helpers = array('Html', 'Form');
    var $name = 'Citag';

    function index($argOrgName = null) {

        $this->set('citags', $this->Citag->find('all'));
    }

    function view($id) {
        $this->Citag->id = $id;
        $this->set('citags', $this->Citag->read());
    }

    function add() {
        $this->set('orgVdcs', $this->Citag->OrgVdc->find('list'));
        if (!empty($this->data)) {
            if ($this->Citag->save($this->data)) {
                $this->Session->setFlash('Your CI tag has been saved.', 'flash_good');
                $this->redirect(array('action' => 'index'));
            }
        }
    }

    function edit($id = null) {
        $this->set('orgVdcs', $this->Citag->OrgVdc->find('list'));
        $this->Citag->id = $id;
        if (empty($this->data)) {
            $this->data = $this->Citag->read();
        } else {
            $user = $this->Auth->user();
            $this->data['user_id'] = $user['username'];
            if ($this->Citag->save($this->data)) {
                $this->Session->setFlash('Your CI tag has been updated.', 'flash_good');
                $this->redirect(array('action' => 'index'));
            }
        }
    }

    function delete($id) {
        if ($this->Citag->delete($id)) {
            $this->Session->setFlash('The citag with id: ' . $id . ' has been deleted.', 'flash_good');
            $this->redirect(array('action' => 'index'));
        }
    }

}

?>
