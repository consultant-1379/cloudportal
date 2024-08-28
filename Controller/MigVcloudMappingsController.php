<?php
App::uses('AppController', 'Controller');
/**
 * MigVcloudMappings Controller
 *
 * @property MigVcloudMapping $MigVcloudMapping
 */
class MigVcloudMappingsController extends AppController {

	public $layout = 'migrations';

/**
 * index method
 *
 * @return void
 */
	public function index() {
		$this->MigVcloudMapping->recursive = 0;
		$this->set('migVcloudMappings', $this->paginate());
	}

/**
 * view method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function view($id = null) {
		if (!$this->MigVcloudMapping->exists($id)) {
			throw new NotFoundException(__('Invalid mig vcloud mapping'));
		}
		$options = array('conditions' => array('MigVcloudMapping.' . $this->MigVcloudMapping->primaryKey => $id));
		$this->set('migVcloudMapping', $this->MigVcloudMapping->find('first', $options));
	}

/**
 * add method
 *
 * @return void
 */
	public function add() {
		if ($this->request->is('post')) {
			$this->MigVcloudMapping->create();
			if ($this->MigVcloudMapping->save($this->request->data)) {
				$this->Session->setFlash(__('The mig vcloud mapping has been saved'));
				$this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__('The mig vcloud mapping could not be saved. Please, try again.'));
			}
		}
		$migTeams = $this->MigVcloudMapping->MigTeam->find('list');
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
		if (!$this->MigVcloudMapping->exists($id)) {
			throw new NotFoundException(__('Invalid mig vcloud mapping'));
		}
		if ($this->request->is('post') || $this->request->is('put')) {
			if ($this->MigVcloudMapping->save($this->request->data)) {
				$this->Session->setFlash(__('The mig vcloud mapping has been saved'));
				$this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__('The mig vcloud mapping could not be saved. Please, try again.'));
			}
		} else {
			$options = array('conditions' => array('MigVcloudMapping.' . $this->MigVcloudMapping->primaryKey => $id));
			$this->request->data = $this->MigVcloudMapping->find('first', $options);
		}
		$migTeams = $this->MigVcloudMapping->MigTeam->find('list');
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
		$this->MigVcloudMapping->id = $id;
		if (!$this->MigVcloudMapping->exists()) {
			throw new NotFoundException(__('Invalid mig vcloud mapping'));
		}
		$this->request->onlyAllow('post', 'delete');
		if ($this->MigVcloudMapping->delete()) {
			$this->Session->setFlash(__('Mig vcloud mapping deleted'));
			$this->redirect(array('action' => 'index'));
		}
		$this->Session->setFlash(__('Mig vcloud mapping was not deleted'));
		$this->redirect(array('action' => 'index'));
	}
}
