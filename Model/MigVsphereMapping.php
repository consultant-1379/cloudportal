<?php
App::uses('AppModel', 'Model');
/**
 * MigVsphereMapping Model
 *
 * @property MigTeam $MigTeam
 */
class MigVsphereMapping extends AppModel {

	public $order="vcenter_hostname, cluster_name";
	
/**
 * Display field
 *
 * @var string
 */
	public $displayField = 'cluster_name';

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
			'notempty' => array(
				'rule' => array('notempty'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'vcenter_hostname' => array(
			'notempty' => array(
				'rule' => array('notempty'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'cluster_name' => array(
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
