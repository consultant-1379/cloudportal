<?php
App::uses('AppModel', 'Model');
/**
 * TaskType Model
 *
 */
class TaskType extends AppModel {

/**
 * Display field
 *
 * @var string
 */
	public $displayField = 'name';
	public $order = 'resource_points desc,name';
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
		'description' => array(
			'notempty' => array(
				'rule' => array('notempty'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'resource_points' => array(
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

	public function log($message = null,$email_administrators=false)
	{
		CakeLog::write('tasktype', $message);
		if ($email_administrators)
		{
			$Throttler = ClassRegistry::init('Throttler');
			$Throttler->email_administrators($message);
		}
	}

	public function get_task_points($task_name = "empty")
	{
		$arguments['conditions'] = array();
                $arguments['conditions']['TaskType.name'] = $task_name;
                $task_type = $this->find('first', $arguments);

                // Check that we have an entry for this in the database
                if (isset($task_type['TaskType']))
                {
                        // If we do have an entry in the database, add its points to the proposed task points
                        return $task_type['TaskType']['resource_points'];
                }
                else
                {
                        // If we don't have an entry in the databse, write about this to the log
			$this->log('ERROR: I dont have an entry for this task type in the database ' . $task_name . '. Please add it if you feel its a genuine task type',true);
			return 0;
                }
		return 0;
	}

	public function count_running_task_points() {

		// Start at 0 points
                $running_task_points=0;

		// Get the list of running tasks from the cloud
		$Vcloud = ClassRegistry::init('Vcloud');
		$running_tasks = $Vcloud->get_running_tasks("7 minutes");

		// Loop through each task from the cloud
                foreach ($running_tasks as $running_task)
                {
			// Search in or database for a task type of this name
                        $arguments['conditions'] = array();
                        $arguments['conditions']['TaskType.name'] = $running_task;
                        $task_type = $this->find('first', $arguments);

			// Check that we have an entry for this in the database
                        if (isset($task_type['TaskType']))
                        {
				// If we do have an entry in the database, add its resource points to the total
				$this->log('A task of type ' . $running_task . ' is running, adding its resource points to the count, ie ' . $task_type['TaskType']['resource_points'],false);
                                $running_task_points+=$task_type['TaskType']['resource_points'];
                        }
                        else
                        {
				// If we don't have an entry in the databse, add it to the database with points of 0 and a default description
				$this->log('ERROR: A task of type ' . $running_task . ' is running, and I havnt seen this type before, adding it as one of the available task types',true);
                                $task_type_params = array(
                                        "name" => $running_task,
                                        "description" => "Description Not Set Yet",
                                        "resource_points" => 0
                                );
                                $this->create();
                                $this->save($task_type_params);
                        }
                }

		// Return the final value
		$this->log('Counted the running task points in the cloud. It was ' . $running_task_points,false);
                return $running_task_points;
        }

	public function tasks() {
		$Vcloud = ClassRegistry::init('Vcloud');
                $Vcloud->print_tasks();
        }
}
