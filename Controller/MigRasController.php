<?php
App::uses('AppController', 'Controller');
/**
 * MigRas Controller
 *
 * @property MigRa $MigRa
 */
class MigRasController extends AppController {

public $components = array('RequestHandler');

	public $layout = 'migrations';

	public function beforeFilter() {
                parent::beforeFilter();
                $this->Auth->allow("index","index_with_orgvdcs");
        }
/**
 * index method
 *
 * @return void
 */
	public function index() {
		$this->MigRa->recursive = 0;
		$this->set('migRas', $this->paginate());
		$this->set('_serialize', array('migRas'));
	}
/**
 * view method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function view($id = null) {
		if (!$this->MigRa->exists($id)) {
			throw new NotFoundException(__('Invalid mig ra'));
		}
		$options = array('conditions' => array('MigRa.' . $this->MigRa->primaryKey => $id));
		$this->set('migRa', $this->MigRa->find('first', $options));
	}

/*
 * This REST call lists all RAs along with associated datacenters
*/
    public function index_with_orgvdcs() {
        $this->MigRa->recursive = 1;
        $this->set('MigRas', $this->MigRa->find('all'));
        $this->set('_serialize', array('MigRas'));
    }
/**
 * add method
 *
 * @return void
 */
	public function add() {
		if ($this->request->is('post')) {
			$this->MigRa->create();
			if ($this->MigRa->save($this->request->data)) {
				$this->Session->setFlash(__('The mig ra has been saved'));
				$this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__('The mig ra could not be saved. Please, try again.'));
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
		if (!$this->MigRa->exists($id)) {
			throw new NotFoundException(__('Invalid mig ra'));
		}
		if ($this->request->is('post') || $this->request->is('put')) {
			if ($this->MigRa->save($this->request->data)) {
				$this->Session->setFlash(__('The mig ra has been saved'));
				$this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__('The mig ra could not be saved. Please, try again.'));
			}
		} else {
			$options = array('conditions' => array('MigRa.' . $this->MigRa->primaryKey => $id));
			$this->request->data = $this->MigRa->find('first', $options);
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
		$this->MigRa->id = $id;
		if (!$this->MigRa->exists()) {
			throw new NotFoundException(__('Invalid mig ra'));
		}
		$this->request->onlyAllow('post', 'delete');
		if ($this->MigRa->delete()) {
			$this->Session->setFlash(__('Mig ra deleted'));
			$this->redirect(array('action' => 'index'));
		}
		$this->Session->setFlash(__('Mig ra was not deleted'));
		$this->redirect(array('action' => 'index'));
	}
}
