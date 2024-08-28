<?php

class SupportController extends AppController {

    var $components = array('Session');

    //public $helpers = array('Js' => array('Jquery'));

    public function beforeFilter() {
        parent::beforeFilter();
        $this->Auth->allow("index");
    }

    function index() {
        $this->set('page_for_layout', "support");
    }

}

?>
