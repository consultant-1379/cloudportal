<?php
App::uses('AppController', 'Controller');
/**
 * ThrottlerSettings Controller
 *
 * @property ThrottlerSetting $ThrottlerSetting
 */
class ThrottlerSettingsController extends AppController {

	public $layout = "controlengine";
/**
 * index method
 *
 * @return void
 */
	public function index() {
		$this->ThrottlerSetting->recursive = 0;
		$this->set('throttlerSettings', $this->paginate());
	}

/**
 * view method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function view($id = null) {
		if (!$this->ThrottlerSetting->exists($id)) {
			throw new NotFoundException(__('Invalid throttler setting'));
		}
		$options = array('conditions' => array('ThrottlerSetting.' . $this->ThrottlerSetting->primaryKey => $id));
		$this->set('throttlerSetting', $this->ThrottlerSetting->find('first', $options));
	}

/**
 * add method
 *
 * @return void
 */
	public function add() {
		if ($this->request->is('post')) {
			$this->ThrottlerSetting->create();
			if ($this->ThrottlerSetting->save($this->request->data)) {
				$this->Session->setFlash(__('The throttler setting has been saved'));
				$this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__('The throttler setting could not be saved. Please, try again.'));
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
		if (!$this->ThrottlerSetting->exists($id)) {
			throw new NotFoundException(__('Invalid throttler setting'));
		}
		if ($this->request->is('post') || $this->request->is('put')) {
			if ($this->ThrottlerSetting->save($this->request->data)) {
				$this->Session->setFlash(__('The throttler setting has been saved'));
				$this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__('The throttler setting could not be saved. Please, try again.'));
			}
		} else {
			$options = array('conditions' => array('ThrottlerSetting.' . $this->ThrottlerSetting->primaryKey => $id));
			$this->request->data = $this->ThrottlerSetting->find('first', $options);
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
		$this->ThrottlerSetting->id = $id;
		if (!$this->ThrottlerSetting->exists()) {
			throw new NotFoundException(__('Invalid throttler setting'));
		}
		$this->request->onlyAllow('post', 'delete');
		if ($this->ThrottlerSetting->delete()) {
			$this->Session->setFlash(__('Throttler setting deleted'));
			$this->redirect(array('action' => 'index'));
		}
		$this->Session->setFlash(__('Throttler setting was not deleted'));
		$this->redirect(array('action' => 'index'));
	}
}
