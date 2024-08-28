<?php

class VappTemplate extends AppModel {

    var $name = 'VappTemplate';
    //var $belongsTo = 'Catalog';
    //var $hasMany = 'Vms';
    var $primaryKey = 'vcd_id';
    var $useTable = false;
    var $validate = array(
        'name' => array(
            'vappname_rule' => array(
                'required' => true,
                'rule' => 'notEmpty',
                'message' => 'Please enter a vApp name'
            )
        ),
        'orgvdc' => array(
            'Please choose a cloud to add to' => array(
                'required' => true,
                'rule' => 'notEmpty',
                'message' => 'Please choose a cloud to add to'
            )
        )
    );

}

?>
