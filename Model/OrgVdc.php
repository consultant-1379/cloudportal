<?php

class OrgVdc extends AppModel {

    var $name = 'OrgVdc';
    var $primaryKey = 'vcd_id';
    public $order = 'OrgVdc.name';
    public $actsAs = array('Containable');

    public $belongsTo = array(
        'MigRa' => array(
            'className' => 'MigRa',
            'foreignKey' => 'mig_ra_id',
            'conditions' => '',
            'fields' => '',
            'order' => ''
        ),
        'ProviderVdc' => array(
            'className' => 'ProviderVdc',
            'foreignKey' => 'provider_vdc_id',
            'conditions' => '',
            'fields' => '',
            'order' => ''
        )
    );

    public $hasMany = array(
        'event' => array(
            'className' => 'event',
            'foreignKey' => 'org_vdc_id',
            'dependent' => false,
            'conditions' => '',
            'fields' => '',
            'order' => '',
            'limit' => '',
            'offset' => '',
            'exclusive' => '',
            'finderQuery' => '',
            'counterQuery' => ''
        )
    );

    public $validate = array(
        'cpu_limit' => array(
            'rule' => array('numeric')
        ),
        'memory_limit' => array(
            'rule' => array('numeric')
        ),
        'running_tb_limit' => array(
            'rule' => array('numeric')
        ),
        'stored_tb_limit' => array(
            'rule' => array('numeric')
        ),
    );
}

?>
