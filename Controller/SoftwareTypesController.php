<?php

class SoftwareTypesController extends AppController {

    var $name = 'SoftwareTypes';
    var $components = array('Session');

    function index() {
        $this->set('software_types', $this->SoftwareType->find('all'));
    }

    function add() {
        if (!empty($this->data)) {
            if ($this->SoftwareType->save($this->data)) {
                $this->Session->setFlash('Your software type has been saved.', 'flash_good');
                $this->redirect(array('action' => 'index'));
            }
        }
    }

    function delete($id) {
        if ($this->SoftwareType->delete($id)) {
            $this->Session->setFlash('The software type with id: ' . $id . ' has been deleted.', 'flash_good');
            $this->redirect(array('action' => 'index'));
        }
    }

    function edit($id = null) {
        $this->SoftwareType->id = $id;
        if (empty($this->data)) {
            $this->data = $this->SoftwareType->read();
        } else {
            if ($this->SoftwareType->save($this->data)) {
                $this->Session->setFlash('Your software type has been updated.', 'flash_good');
                $this->redirect(array('action' => 'index'));
            }
        }
    }

}

?>
