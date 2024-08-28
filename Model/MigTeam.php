<?php
App::uses('AppModel', 'Model');
/**
 * MigTeam Model
 *
 * @property MigRa $MigRa
 * @property MigNightlyCount $MigNightlyCount
 * @property MigVcloudMapping $MigVcloudMapping
 * @property MigVsphereMapping $MigVsphereMapping
 */
class MigTeam extends AppModel {

	public $order = 'MigTeam.name';
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
		'mig_ra_id' => array(
			'numeric' => array(
				'rule' => array('numeric'),
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
		'MigRa' => array(
			'className' => 'MigRa',
			'foreignKey' => 'mig_ra_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
		)
	);

/**
 * hasMany associations
 *
 * @var array
 */
	public $hasMany = array(
		'MigNightlyCount' => array(
			'className' => 'MigNightlyCount',
			'foreignKey' => 'mig_team_id',
			'dependent' => false,
			'conditions' => '',
			'fields' => '',
			'order' => 'date DESC',
			'limit' => '',
			'offset' => '',
			'exclusive' => '',
			'finderQuery' => '',
			'counterQuery' => ''
		),
		'MigVcloudMapping' => array(
			'className' => 'MigVcloudMapping',
			'foreignKey' => 'mig_team_id',
			'dependent' => false,
			'conditions' => '',
			'fields' => '',
			'order' => 'spp_hostname, orgvdc_name',
			'limit' => '',
			'offset' => '',
			'exclusive' => '',
			'finderQuery' => '',
			'counterQuery' => ''
		),
		'MigVsphereMapping' => array(
			'className' => 'MigVsphereMapping',
			'foreignKey' => 'mig_team_id',
			'dependent' => false,
			'conditions' => '',
			'fields' => '',
			'order' => 'vcenter_hostname, cluster_name',
			'limit' => '',
			'offset' => '',
			'exclusive' => '',
			'finderQuery' => '',
			'counterQuery' => ''
		)
	);

}
