<?php
App::uses('AppController', 'Controller');
/**
 * MigTeams Controller
 *
 * @property MigTeam $MigTeam
 */
class MigTeamsController extends AppController {

	public $layout = 'migrations';

	public function beforeFilter() {
                parent::beforeFilter();
                $this->Auth->allow("teams_per_ra");
        }

	public function teams_per_ra() {
                $this->MigTeam->recursive = -1;
		$ra_id = $this->passedArgs['ra_id'];
		$options = array('conditions' => array('mig_ra_id' => $ra_id));
                $this->set('migTeams', $this->MigTeam->find('all', $options));
                $this->set('_serialize', array('migTeams'));
        }
/**
 * index method
 *
 * @return void
 */
	public function index() {
		$this->MigTeam->recursive = 0;
		$this->set('migTeams', $this->paginate());
	}

/**
 * view method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function view($id = null) {
		if (!$this->MigTeam->exists($id)) {
			throw new NotFoundException(__('Invalid mig team'));
		}
		$options = array('conditions' => array('MigTeam.' . $this->MigTeam->primaryKey => $id));
		$this->set('migTeam', $this->MigTeam->find('first', $options));
	}

/**
 * add method
 *
 * @return void
 */
	public function add() {
		if ($this->request->is('post')) {
			$this->MigTeam->create();
			if ($this->MigTeam->save($this->request->data)) {
				$this->Session->setFlash(__('The mig team has been saved'));
				$this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__('The mig team could not be saved. Please, try again.'));
			}
		}
		$migRas = $this->MigTeam->MigRa->find('list');
		$this->set(compact('migRas'));
	}

/**
 * edit method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function edit($id = null) {
		if (!$this->MigTeam->exists($id)) {
			throw new NotFoundException(__('Invalid mig team'));
		}
		if ($this->request->is('post') || $this->request->is('put')) {
			if ($this->MigTeam->save($this->request->data)) {
				$this->Session->setFlash(__('The mig team has been saved'));
				$this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__('The mig team could not be saved. Please, try again.'));
			}
		} else {
			$options = array('conditions' => array('MigTeam.' . $this->MigTeam->primaryKey => $id));
			$this->request->data = $this->MigTeam->find('first', $options);
		}
		$migRas = $this->MigTeam->MigRa->find('list');
		$this->set(compact('migRas'));
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
		$this->MigTeam->id = $id;
		if (!$this->MigTeam->exists()) {
			throw new NotFoundException(__('Invalid mig team'));
		}
		$this->request->onlyAllow('post', 'delete');
		if ($this->MigTeam->delete()) {
			$this->Session->setFlash(__('Mig team deleted'));
			$this->redirect(array('action' => 'index'));
		}
		$this->Session->setFlash(__('Mig team was not deleted'));
		$this->redirect(array('action' => 'index'));
	}
}
