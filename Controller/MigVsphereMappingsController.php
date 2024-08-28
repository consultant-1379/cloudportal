<?php
App::uses('AppController', 'Controller');
/**
 * MigVsphereMappings Controller
 *
 * @property MigVsphereMapping $MigVsphereMapping
 */
class MigVsphereMappingsController extends AppController {

	public $layout = 'migrations';

/**
 * index method
 *
 * @return void
 */
	public function index() {
		$this->MigVsphereMapping->recursive = 0;
		$this->set('migVsphereMappings', $this->paginate());
	}

/**
 * view method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function view($id = null) {
		if (!$this->MigVsphereMapping->exists($id)) {
			throw new NotFoundException(__('Invalid mig vsphere mapping'));
		}
		$options = array('conditions' => array('MigVsphereMapping.' . $this->MigVsphereMapping->primaryKey => $id));
		$this->set('migVsphereMapping', $this->MigVsphereMapping->find('first', $options));
	}

/**
 * add method
 *
 * @return void
 */
	public function add() {
		if ($this->request->is('post')) {
			$this->MigVsphereMapping->create();
			if ($this->MigVsphereMapping->save($this->request->data)) {
				$this->Session->setFlash(__('The mig vsphere mapping has been saved'));
				$this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__('The mig vsphere mapping could not be saved. Please, try again.'));
			}
		}
		$migTeams = $this->MigVsphereMapping->MigTeam->find('list');
		$this->set(compact('migTeams'));
	}

/**
 * edit method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function edit($id = null) {
		if (!$this->MigVsphereMapping->exists($id)) {
			throw new NotFoundException(__('Invalid mig vsphere mapping'));
		}
		if ($this->request->is('post') || $this->request->is('put')) {
			if ($this->MigVsphereMapping->save($this->request->data)) {
				$this->Session->setFlash(__('The mig vsphere mapping has been saved'));
				$this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__('The mig vsphere mapping could not be saved. Please, try again.'));
			}
		} else {
			$options = array('conditions' => array('MigVsphereMapping.' . $this->MigVsphereMapping->primaryKey => $id));
			$this->request->data = $this->MigVsphereMapping->find('first', $options);
		}
		$migTeams = $this->MigVsphereMapping->MigTeam->find('list');
		$this->set(compact('migTeams'));
	}

/**
 * delete method
 *
 * @throws NotFoundException
 * @throws MethodNotAllowedException
 * @param string $id
 * @return void
 */
	public function delete($id = null) {
		$this->MigVsphereMapping->id = $id;
		if (!$this->MigVsphereMapping->exists()) {
			throw new NotFoundException(__('Invalid mig vsphere mapping'));
		}
		$this->request->onlyAllow('post', 'delete');
		if ($this->MigVsphereMapping->delete()) {
			$this->Session->setFlash(__('Mig vsphere mapping deleted'));
			$this->redirect(array('action' => 'index'));
		}
		$this->Session->setFlash(__('Mig vsphere mapping was not deleted'));
		$this->redirect(array('action' => 'index'));
	}
}
