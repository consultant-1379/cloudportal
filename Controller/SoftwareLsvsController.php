<?php

class SoftwareLsvsController extends AppController {

    var $name = 'SoftwareLsvs';
    var $components = array('Session');

    function index() {
        $this->set('software_lsvs', $this->SoftwareLsv->find('all'));
    }

    function add() {
        if (!empty($this->data)) {
            if ($this->SoftwareLsv->save($this->data)) {
                $this->Session->setFlash('Your software lsv has been saved.', 'flash_good');
                $this->redirect(array('action' => 'index'));
            }
        }
    }

    function delete($id) {
        if ($this->SoftwareLsv->delete($id)) {
            $this->Session->setFlash('The software lsv with id: ' . $id . ' has been deleted.', 'flash_good');
            $this->redirect(array('action' => 'index'));
        }
    }

    function edit($id = null) {
        $this->SoftwareLsv->id = $id;
        if (empty($this->data)) {
            $this->data = $this->SoftwareLsv->read();
        } else {
            if ($this->SoftwareLsv->save($this->data)) {
                $this->Session->setFlash('Your software lsv has been updated.', 'flash_good');
                $this->redirect(array('action' => 'index'));
            }
        }
    }

}

?>
