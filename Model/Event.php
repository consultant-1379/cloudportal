<?php

class Event extends AppModel {

    var $name = 'Event';
    public $order = 'created DESC';

    public $belongsTo = array(
        'OrgVdc' => array(
            'className' => 'OrgVdc',
            'foreignKey' => 'org_vdc_id',
            'conditions' => '',
            'fields' => '',
            'order' => ''
        )
    );
}

?>
