<?php

class SoftwareReleasesController extends AppController {

    var $name = 'SoftwareReleases';
    var $components = array('Session');

    function index() {
        $this->SoftwareRelease->recursive = 2;
        $this->set('softwareReleases', $this->SoftwareRelease->find('all'));
    }

    function add() {
        $this->set('softwareTypes', $this->SoftwareRelease->SoftwareType->find('list'));
        if (!empty($this->data)) {
            if ($this->SoftwareRelease->save($this->data)) {
                $this->Session->setFlash('Your software release has been saved.', 'flash_good');
                $this->redirect(array('action' => 'index'));
            }
        }
    }

    function delete($id) {
        if ($this->SoftwareRelease->delete($id)) {
            $this->Session->setFlash('The software release with id: ' . $id . ' has been deleted.', 'flash_good');
            $this->redirect(array('action' => 'index'));
        }
    }

    function edit($id = null) {
        $this->set('softwareTypes', $this->SoftwareRelease->SoftwareType->find('list'));
        $this->SoftwareRelease->id = $id;
        if (empty($this->data)) {
            $this->data = $this->SoftwareRelease->read();
        } else {
            if ($this->SoftwareRelease->save($this->data)) {
                $this->Session->setFlash('Your software release has been updated.', 'flash_good');
                $this->redirect(array('action' => 'index'));
            }
        }
    }

}

?>
