<?php

class MigrationsController extends AppController {

	var $components = array('Session');
	var $uses = array('Migration', 'MigTeam');

	//public $helpers = array('Js' => array('Jquery'));

	public $layout = 'migrations';

	public function beforeFilter() {
		parent::beforeFilter();
		$this->Auth->allow("index");
	}

	function index() {
	}
        
}

