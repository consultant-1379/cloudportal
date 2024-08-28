<?php
App::uses('AppModel', 'Model');
/**
 * MigNightlyCount Model
 *
 * @property MigTeam $MigTeam
 * @property MigCountType $MigCountType
 */
class MigNightlyCount extends AppModel {

	public function teams_summary_per_ra_and_type ($ra_id,$type)
	{
                $summary=array();

                $arguments['conditions'] = array();
                $arguments['conditions']['MigTeam.mig_ra_id'] = $ra_id;
		$arguments['recursive'] = 0;
                $ra_teams = $this->MigTeam->find("all",$arguments);
                foreach ($ra_teams as $team) {

                        $arguments['conditions'] = array();
                        $arguments['conditions']['MigCountType.name'] = $type;
                        $arguments['order'] = array('date DESC');
                        $arguments['conditions']['MigNightlyCount.mig_team_id'] = $team['MigTeam']['id'];
                        $result=$this->find('first', $arguments);

                        $temp_summary=array();
                        if (isset($result["MigNightlyCount"]["count"]))
                        {
                                $temp_summary = $result["MigNightlyCount"]["count"];
                        }
                        else {
                                $temp_summary = null;
                        }
                        array_push($summary, $temp_summary);
                }
		return $summary;
	}
	public function ras_summary_per_type($type)
	{
		$summary=array();
		$ras = $this->MigTeam->MigRa->find("all");
                foreach ($ras as $ra) {
			$missing_count=0;
                        $team_summaries=$this->teams_summary_per_ra_and_type($ra['MigRa']['id'],$type);
			$ra_count=0;
                        foreach ($team_summaries as $team_summary)
                        {
				if (!is_null($team_summary))
				{
					$ra_count+=$team_summary;
				}
				else
				{
					$missing_count++;
				}
                        }
                        array_push($summary, array("count" => $ra_count,"missing" => $missing_count));
                }
		return $summary;
	}
	public $order = 'date DESC';

/**
 * Display field
 *
 * @var string
 */
	public $displayField = 'date';

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
		'date' => array(
			'date' => array(
				'rule' => array('date'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'mig_count_type_id' => array(
			'numeric' => array(
				'rule' => array('numeric'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'count' => array(
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
		'MigTeam' => array(
			'className' => 'MigTeam',
			'foreignKey' => 'mig_team_id',
			'conditions' => '',
			'fields' => '',
			'order' => 'MigTeam.name'
		),
		'MigCountType' => array(
			'className' => 'MigCountType',
			'foreignKey' => 'mig_count_type_id',
			'conditions' => '',
			'fields' => '',
			'order' => 'MigCountType.name'
		)
	);
}
