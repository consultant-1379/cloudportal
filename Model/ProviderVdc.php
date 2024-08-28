<?php

class ProviderVdc extends AppModel {

    var $name = 'ProviderVdc';
    var $belongsTo = 'Org';
    var $primaryKey = 'vcd_id';

    public $hasMany = array(
        'OrgVdc' => array(
            'className' => 'OrgVdc',
            'foreignKey' => 'provider_vdc_id'
        )
    );

    public $validate = array(
        'cpu_multiplier' => array(
            'decimal_rule' => array(
                'rule' => array('decimal'),
                'message' => 'Please supply a valid decimal number between 0.0 and 99.99, with max 2 decimal places.'
            ),
            'greater_than_rule' => array(
                'rule' => array('comparison', '>', 0),
                'message' => 'Please supply a valid number thats greater than 0'
            )
        ),
        'memory_multiplier' => array(
            'decimal_rule' => array(
                'rule' => array('decimal'),
                'message' => 'Please supply a valid decimal number between 0.0 and 99.99, with max 2 decimal places.'
            ),
            'greater_than_rule' => array(
                'rule' => array('comparison', '>', 0),
                'message' => 'Please supply a valid number thats greater than 0'
            )
        ),
    );
}
?>
