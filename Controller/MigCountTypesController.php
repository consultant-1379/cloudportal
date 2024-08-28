<?php
App::uses('AppController', 'Controller');
/**
 * MigCountTypes Controller
 *
 * @property MigCountType $MigCountType
 */
class MigCountTypesController extends AppController {

	public $layout = 'migrations';

/**
 * index method
 *
 * @return void
 */
	public function index() {
		$this->MigCountType->recursive = 0;
		$this->set('migCountTypes', $this->paginate());
	}

/**
 * view method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function view($id = null) {
		if (!$this->MigCountType->exists($id)) {
			throw new NotFoundException(__('Invalid mig count type'));
		}
		$options = array('conditions' => array('MigCountType.' . $this->MigCountType->primaryKey => $id));
		$this->set('migCountType', $this->MigCountType->find('first', $options));
	}

/**
 * add method
 *
 * @return void
 */
	public function add() {
		if ($this->request->is('post')) {
			$this->MigCountType->create();
			if ($this->MigCountType->save($this->request->data)) {
				$this->Session->setFlash(__('The mig count type has been saved'));
				$this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__('The mig count type could not be saved. Please, try again.'));
			}
		}
	}

/**
 * edit method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function edit($id = null) {
		if (!$this->MigCountType->exists($id)) {
			throw new NotFoundException(__('Invalid mig count type'));
		}
		if ($this->request->is('post') || $this->request->is('put')) {
			if ($this->MigCountType->save($this->request->data)) {
				$this->Session->setFlash(__('The mig count type has been saved'));
				$this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__('The mig count type could not be saved. Please, try again.'));
			}
		} else {
			$options = array('conditions' => array('MigCountType.' . $this->MigCountType->primaryKey => $id));
			$this->request->data = $this->MigCountType->find('first', $options);
		}
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
		$this->MigCountType->id = $id;
		if (!$this->MigCountType->exists()) {
			throw new NotFoundException(__('Invalid mig count type'));
		}
		$this->request->onlyAllow('post', 'delete');
		if ($this->MigCountType->delete()) {
			$this->Session->setFlash(__('Mig count type deleted'));
			$this->redirect(array('action' => 'index'));
		}
		$this->Session->setFlash(__('Mig count type was not deleted'));
		$this->redirect(array('action' => 'index'));
	}
}
