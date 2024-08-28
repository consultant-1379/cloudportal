<?php
App::uses('AppModel', 'Model');
/**
 * MigRa Model
 *
 * @property MigTeam $MigTeam
 */
class MigRa extends AppModel {

	public $order='name';
/**
 * Display field
 *
 * @var string
 */
	public $displayField = 'name';

/**
 * Validation rules
 *
 * @var array
 */
	public $validate = array(
		'name' => array(
			'notempty' => array(
				'rule' => array('notempty'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
	);

	//The Associations below have been created with all possible keys, those that are not needed can be removed

/**
 * hasMany associations
 *
 * @var array
 */
	public $hasMany = array(
		'MigTeam' => array(
			'className' => 'MigTeam',
			'foreignKey' => 'mig_ra_id',
			'dependent' => false,
			'conditions' => '',
			'fields' => '',
			'order' => 'name',
			'limit' => '',
			'offset' => '',
			'exclusive' => '',
			'finderQuery' => '',
			'counterQuery' => ''
		),
        'OrgVdc' => array(
            'className' => 'OrgVdc',
            'foreignKey' => 'mig_ra_id',
            'dependent' => false,
            'conditions' => '',
            'fields' => 'name',
            'order' => 'name',
            'limit' => '',
            'offset' => '',
            'exclusive' => '',
            'finderQuery' => '',
            'counterQuery' => ''
        ),
	);

}
