<?php
App::uses('AppController', 'Controller');
/**
 * TaskTypes Controller
 *
 * @property TaskType $TaskType
 */
class ThrottlersController extends AppController {

	public function beforeFilter() {
		parent::beforeFilter();
		$this->Auth->allow("propose_new_task","email_administrators");
	}

	function propose_new_task ()
	{
		$result=$this->Throttler->propose_new_task($this->passedArgs['task_name']);
		if($result)
		{
			// 146 means yes to the proposal
			// Update the transitional cache before returning yes
			$this->Throttler->increment_transitional_task_points_cache($this->passedArgs['task_name']);
			$result = "146";
		}
		else
		{
			// 147 means no to the proposal
			$result = "147";
		}

                $this->set('result', $result);
                $this->set('_serialize', array('result'));
	}

	function email_administrators()
	{
		$message = $this->request->data('message');
		$this->Throttler->email_administrators($message);
		$obj="";
		$this->set('obj', $obj);
		$this->set('_serialize', array('obj'));
	}
}
