<?php
App::uses('AppModel', 'Model');
/**
 * MigVcloudMapping Model
 *
 * @property MigTeam $MigTeam
 */
class MigVcloudMapping extends AppModel {

	public $order="spp_hostname, orgvdc_name";
/**
 * Display field
 *
 * @var string
 */
	public $displayField = 'orgvdc_name';

/**
 * Validation rules
 *
 * @var array
 */
	public $validate = array(
		'mig_team_id' => array(
			'numeric' => array(
				'rule' => array('numeric'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'spp_hostname' => array(
			'notempty' => array(
				'rule' => array('notempty'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'orgvdc_name' => array(
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
 * belongsTo associations
 *
 * @var array
 */
	public $belongsTo = array(
		'MigTeam' => array(
			'className' => 'MigTeam',
			'foreignKey' => 'mig_team_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
		)
	);
}
