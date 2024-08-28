<?php
App::uses('AppController', 'Controller');
/**
 * MigNightlyCounts Controller
 *
 * @property MigNightlyCount $MigNightlyCount
 */

class MigNightlyCountsController extends AppController {

public $components = array('RequestHandler','Email');

	public $layout = 'migrations';
	public function beforeFilter() {
                parent::beforeFilter();
                $this->Auth->allow("teams_per_ra","by_team_and_type","by_ra_and_type","teams_summary_per_ra_and_type","ras_summary_per_type","populate_nightly_counts");
        }

/**
 * index method
 *
 * @return void
 */
	public function index() {
		$this->MigNightlyCount->recursive = 0;
		$this->set('migNightlyCounts', $this->paginate());
	}

	public function by_team_and_type () {
		$this->MigNightlyCount->recursive = 0;
		$arguments['order'] = "date ASC";
                $arguments['conditions'] = array();
		$arguments['conditions']['MigCountType.name'] = $this->passedArgs['type'];
		$arguments['conditions']['MigNightlyCount.mig_team_id'] = $this->passedArgs['team_id'];
                $this->set('counts', $this->MigNightlyCount->find('all', $arguments));
                $this->set('_serialize', array('counts'));
	}
	public function by_ra_and_type () {
		// Count the number of teams in the ra
		$count_arguments['conditions'] = array();
                $count_arguments['conditions']['MigTeam.mig_ra_id'] = $this->passedArgs['ra_id'];
		$count_arguments['recursive'] = -1;
                $team_count = $this->MigNightlyCount->MigTeam->find('count', $count_arguments);

                $this->MigNightlyCount->recursive = 0;
		$arguments['order'] = "date ASC";
                $arguments['conditions'] = array();
		$arguments['conditions']['MigCountType.name'] = $this->passedArgs['type'];
                $arguments['conditions']['MigTeam.mig_ra_id'] = $this->passedArgs['ra_id'];
		$this->MigNightlyCount->virtualFields = array('count' => 'SUM(MigNightlyCount.count)');

		// Only sum up counts that have points for each team
		$arguments['group'] = array('MigNightlyCount.date HAVING COUNT(MigNightlyCount.count) = ' . $team_count);
                $this->set('counts', $this->MigNightlyCount->find('all', $arguments));
                $this->set('_serialize', array('counts'));
        }

	public function teams_summary_per_ra_and_type () {
		$summary=array();
		$summary=$this->MigNightlyCount->teams_summary_per_ra_and_type($this->passedArgs['ra_id'],$this->passedArgs['type']);
                $this->set('summary', $summary);
                $this->set('_serialize', array('summary'));
	}
	public function ras_summary_per_type () {
		$summary=array();
		$summary=$this->MigNightlyCount->ras_summary_per_type($this->passedArgs['type']);
                $this->set('summary', $summary);
                $this->set('_serialize', array('summary'));
        }

	function get_script_output($command)
	{
		$connection = ssh2_connect('atclctl1', 22);
		ssh2_auth_password($connection, "root", "shroot12");
		$stream = ssh2_exec($connection, $command);
		stream_set_blocking($stream, true);
		$returned_count = '';
		while($buffer = fread($stream, 4096)) {
			$returned_count .= $buffer;
		}
		fclose($stream);
		return $returned_count;
	}
	function populate_nightly_counts()
        {
		$mail_list = array ("mark.a.kennedy@ericsson.com","sinead.oreilly@ericsson.com","shane.kelly@ericsson.com","somesh.kumar@ericsson.com","finbar.ryan@ericsson.com");
		////////////////////////////////////////////////////////////////////
		// Alert the user about orgvdcs not in graphs
		echo "--> Searching for missing orgvdcs from the graphs";
		echo "</br>";
		$vcloud_mappings_spps = $this->MigNightlyCount->MigTeam->MigVcloudMapping->find("all",array('group' => 'MigVcloudMapping.spp_hostname'));

		$email_text = "";
		foreach ($vcloud_mappings_spps as $vcloud_mapping_spp) {
			$spp_hostname = $vcloud_mapping_spp['MigVcloudMapping']['spp_hostname'];
			echo "-> Searching for missing orgvdcs from spp " . $spp_hostname;
			echo "</br>";

			// Get the full list of orgvdcs
			$orgvdc_list = $this->get_script_output("/export/scripts/CLOUD/bin/vCloudFunctions_php.sh -f list_orgvdcs -u script -v '" . $spp_hostname . "'");
			$orgvdc_array = explode("\n", $orgvdc_list);

			// Get the full list of current vcloud mappings for this spp
			$vcloud_mappings_one_spp = $this->MigNightlyCount->MigTeam->MigVcloudMapping->find("all",array('conditions' => array('spp_hostname' => $spp_hostname)));


			foreach ($orgvdc_array as $orgvdc)
			{
				$found_it=false;
				if ($orgvdc == "" )
				{
					continue;
				}
				foreach ($vcloud_mappings_one_spp as $vcloud_mapping_one_spp)
				{
					$this_orgvdc = $vcloud_mapping_one_spp['MigVcloudMapping']['orgvdc_name'];
					echo "Comparing " . $this_orgvdc . " with " . $orgvdc;
					echo "</br>";
					if ($this_orgvdc == $orgvdc)
					{
						$found_it=true;
						break;
					}
				}
				if ($found_it==false)
				{
					echo "UH OH, couldn't find " . $orgvdc;
					echo "</br>";
					$email_text=$email_text . $orgvdc . ' from SPP ' . $spp_hostname . "\r\r";
				}
				else
				{
					echo "Found " . $orgvdc;
					echo "</br>";
				}
			}
		}
		if ($email_text != "")
		{
			$email = new CakeEmail();
			$email->from(array('no_reply@ericsson.com' => 'Cloud Migration Tracker'));
			$email->to($mail_list);
			$email->subject('Cloud Migration Tracker - OrgVdcs Missing From Graphs');
			$email->send("The following OrgVdcs are missing from the migration graphs, please associate them with one of the teams\r\r" . $email_text);
		}
		echo "--> OrgVdc search complete";
                echo "</br>";
		echo "</br>";
		////////////////////////////////////////////////////////////////////
		ini_set('memory_limit', '-1');
		$virtual_apps_type_object = $this->MigNightlyCount->MigCountType->find('first',array('conditions' => array('MigCountType.name' => "virtual_apps")));
		$virtual_machines_type_object = $this->MigNightlyCount->MigCountType->find('first',array('conditions' => array('MigCountType.name' => "virtual_machines")));
		$virtual_hosts_type_object = $this->MigNightlyCount->MigCountType->find('first',array('conditions' => array('MigCountType.name' => "virtual_hosts")));
		$virtual_apps_spun_up_type_object = $this->MigNightlyCount->MigCountType->find('first',array('conditions' => array('MigCountType.name' => "virtual_apps_spun_up")));
		$virtual_apps_spun_down_type_object = $this->MigNightlyCount->MigCountType->find('first',array('conditions' => array('MigCountType.name' => "virtual_apps_spun_down")));
		$virtual_apps_type = $virtual_apps_type_object['MigCountType']['id'];
		$virtual_machines_type = $virtual_machines_type_object['MigCountType']['id'];
		$virtual_hosts_type = $virtual_hosts_type_object['MigCountType']['id'];
		$virtual_hosts_type = $virtual_hosts_type_object['MigCountType']['id'];
		$virtual_apps_spun_up_type = $virtual_apps_spun_up_type_object['MigCountType']['id'];
		$virtual_apps_spun_down_type = $virtual_apps_spun_down_type_object['MigCountType']['id'];

		//$date=date("Y-m-d");
		// Store as yesterdays count as we run this just after midnight
		$date = date("Y-m-d", time() - 60 * 60 * 24);
		echo "Storing For Date " . $date;
		echo "</br>";
		$mig_teams=$this->MigNightlyCount->MigTeam->find("all");
		foreach ($mig_teams as $mig_team) {
			echo "</br>";
                        echo "</br>";
                        echo "</br>";
                        echo "Working on team " . $mig_team['MigTeam']['name'] . " which has id " . $mig_team['MigTeam']['id'];
                        echo "</br>";
			$powered_on_vms_count=0;
			$hosts_in_cluster_count=0;
			$powered_on_vapps_count=0;
                        $hosts_in_orgvdc_count=0;
			$vapps_spun_up_in_orgvdc_count=0;
			$vapps_spun_down_in_orgvdc_count=0;
			$vsphere_mappings = $mig_team['MigVsphereMapping'];
			$vcloud_mappings = $mig_team['MigVcloudMapping'];

			// count up the running vms
			foreach ($vsphere_mappings as $vsphere_mapping) {
				$powered_on_vms_count_returned = $this->get_script_output("/export/scripts/CLOUD/bin/count_powered_on_vms_in_cluster.sh -v " . $vsphere_mapping['vcenter_hostname'] . " -c '" . $vsphere_mapping['cluster_name'] . "'");
				$hosts_in_cluster_count_returned = $this->get_script_output("/export/scripts/CLOUD/bin/count_hosts_in_cluster.sh -v " . $vsphere_mapping['vcenter_hostname'] . " -c '" . $vsphere_mapping['cluster_name'] . "'");


				if (is_numeric($powered_on_vms_count_returned)) {
                                        echo "---> The returned value '{$powered_on_vms_count_returned}' is numeric, continuing", PHP_EOL;
                                        echo "</br>";
                                } else {
                                        echo "---> ERROR: The returned value '{$powered_on_vms_count_returned}' is NOT numeric so not storing anything for this team, please investigate, sending email and moving on", PHP_EOL;
                                        echo "</br>";
                                        $email = new CakeEmail();
                                        $email->from(array('no_reply@ericsson.com' => 'Cloud Migration Tracker'));
                                        $email->to($mail_list);
                                        $email->subject('Cloud Migration Tracker - Counting powered on vms issue');
                                        $email->send('There was a problem counting the powered on vms for team ' . $mig_team['MigTeam']['name'] . ', in vcenter ' . $vsphere_mapping['vcenter_hostname'] . ', in cluster ' . $vsphere_mapping['cluster_name'] . ', please investigate. A count value came back that doesnt look right, ie this value ' . $powered_on_vms_count_returned. ' so no data will be saved for this team');
                                        continue 2;
                                }

				if (is_numeric($hosts_in_cluster_count_returned)) {
                                        echo "---> The returned value '{$hosts_in_cluster_count_returned}' is numeric, continuing", PHP_EOL;
                                        echo "</br>";
                                } else {
                                        echo "---> ERROR: The returned value '{$hosts_in_cluster_count_returned}' is NOT numeric so not storing anything for this team, please investigate, sending email and moving on", PHP_EOL;
                                        echo "</br>";
                                        $email = new CakeEmail();
                                        $email->from(array('no_reply@ericsson.com' => 'Cloud Migration Tracker'));
                                        $email->to($mail_list);
                                        $email->subject('Cloud Migration Tracker - Counting hosts in cluster issue');
                                        $email->send('There was a problem counting the hosts in a cluster for team ' . $mig_team['MigTeam']['name'] . ', in vcenter ' . $vsphere_mapping['vcenter_hostname'] . ', in cluster ' . $vsphere_mapping['cluster_name'] . ', please investigate. A count value came back that doesnt look right, ie this value ' . $hosts_in_cluster_count_returned. ' so no data will be saved for this team');
                                        continue 2;
                                }

                                // Increment the count for this area as the data looked ok
                                $powered_on_vms_count = $powered_on_vms_count + $powered_on_vms_count_returned;
				$hosts_in_cluster_count = $hosts_in_cluster_count + $hosts_in_cluster_count_returned;

			}

			// hosts in orgvdcs are multiplied by 10000 to keep the rounding accurate, need to also do same modification to hosts in clusters so graphs show accurate info when dividing by 10000
			$hosts_in_cluster_count = $hosts_in_cluster_count * 10000;




			foreach ($vcloud_mappings as $vcloud_mapping) {
                                $powered_on_vapps_count_returned = $this->get_script_output("/export/scripts/CLOUD/bin/count_powered_on_vapps_in_orgvdc.sh -v " . $vcloud_mapping['spp_hostname'] . " -o '" . $vcloud_mapping['orgvdc_name'] . "'");
                                $hosts_in_orgvdc_count_returned = $this->get_script_output("/export/scripts/CLOUD/bin/count_hosts_in_orgvdc.sh -v " . $vcloud_mapping['spp_hostname'] . " -o '" . $vcloud_mapping['orgvdc_name']  . "'");
				$vapps_spun_up_in_orgvdc_count_returned = $this->get_script_output("/export/scripts/CLOUD/bin/count_spun_up_vapps_yesterday_in_orgvdc.sh -v " . $vcloud_mapping['spp_hostname'] . " -o '" . $vcloud_mapping['orgvdc_name']  . "'");
				$vapps_spun_down_in_orgvdc_count_returned = $this->get_script_output("/export/scripts/CLOUD/bin/count_spun_down_vapps_yesterday_in_orgvdc.sh -v " . $vcloud_mapping['spp_hostname'] . " -o '" . $vcloud_mapping['orgvdc_name']  . "'");

                                if (is_numeric($powered_on_vapps_count_returned)) {
                                        echo "---> The returned value '{$powered_on_vapps_count_returned}' is numeric, continuing", PHP_EOL;
                                        echo "</br>";
                                } else {
                                        echo "---> ERROR: The returned value '{$powered_on_vapps_count_returned}' is NOT numeric so not storing anything for this team, please investigate, sending email and moving on", PHP_EOL;
                                        echo "</br>";
                                        $email = new CakeEmail();
                                        $email->from(array('no_reply@ericsson.com' => 'Cloud Migration Tracker'));
                                        $email->to($mail_list);
                                        $email->subject('Cloud Migration Tracker - Counting powered on vapps issue');
                                        $email->send('There was a problem counting the powered on vapps for team ' . $mig_team['MigTeam']['name'] . ', in spp ' . $vcloud_mapping['spp_hostname'] . ', in orgvdc ' . $vcloud_mapping['orgvdc_name'] . ', please investigate. A count value came back that doesnt look right, ie this value ' . $powered_on_vapps_count_returned. ' so no data will be saved for this team');
                                        continue 2;
                                }

                                if (is_numeric($hosts_in_orgvdc_count_returned)) {
                                        echo "---> The returned value '{$hosts_in_orgvdc_count_returned}' is numeric, continuing", PHP_EOL;
                                        echo "</br>";
                                } else {
                                        echo "---> ERROR: The returned value '{$hosts_in_orgvdc_count_returned}' is NOT numeric so not storing anything for this team, please investigate, sending email and moving on", PHP_EOL;
                                        echo "</br>";
                                        $email = new CakeEmail();
                                        $email->from(array('no_reply@ericsson.com' => 'Cloud Migration Tracker'));
                                        $email->to($mail_list);
                                        $email->subject('Cloud Migration Tracker - Counting hosts in orgvdc issue');
                                        $email->send('There was a problem counting the hosts in an orgvdc for team ' . $mig_team['MigTeam']['name'] . ', in spp ' . $vcloud_mapping['spp_hostname'] . ', in orgvdc ' . $vcloud_mapping['orgvdc_name'] . ', please investigate. A count value came back that doesnt look right, ie this value ' . $hosts_in_orgvdc_count_returned. ' so no data will be saved for this team');
                                        continue 2;
                                }

				if (is_numeric($vapps_spun_up_in_orgvdc_count_returned)) {
                                        echo "---> The returned value '{$vapps_spun_up_in_orgvdc_count_returned}' is numeric, continuing", PHP_EOL;
                                        echo "</br>";
                                } else {
                                        echo "---> ERROR: The returned value '{$vapps_spun_up_in_orgvdc_count_returned}' is NOT numeric so not storing anything for this team, please investigate, sending email and moving on", PHP_EOL;
                                        echo "</br>";
                                        $email = new CakeEmail();
                                        $email->from(array('no_reply@ericsson.com' => 'Cloud Migration Tracker'));
                                        $email->to($mail_list);
                                        $email->subject('Cloud Migration Tracker - Counting spun up vapps in orgvdc issue');
                                        $email->send('There was a problem counting the spun up vapps in an orgvdc for team ' . $mig_team['MigTeam']['name'] . ', in spp ' . $vcloud_mapping['spp_hostname'] . ', in orgvdc ' . $vcloud_mapping['orgvdc_name'] . ', please investigate. A count value came back that doesnt look right, ie this value ' . $vapps_spun_up_in_orgvdc_count_returned . ' so no data will be saved for this team');
                                        continue 2;
                                }

				if (is_numeric($vapps_spun_down_in_orgvdc_count_returned)) {
                                        echo "---> The returned value '{$vapps_spun_down_in_orgvdc_count_returned}' is numeric, continuing", PHP_EOL;
                                        echo "</br>";
                                } else {
                                        echo "---> ERROR: The returned value '{$vapps_spun_down_in_orgvdc_count_returned}' is NOT numeric so not storing anything for this team, please investigate, sending email and moving on", PHP_EOL;
                                        echo "</br>";
                                        $email = new CakeEmail();
                                        $email->from(array('no_reply@ericsson.com' => 'Cloud Migration Tracker'));
                                        $email->to($mail_list);
                                        $email->subject('Cloud Migration Tracker - Counting spun down vapps in orgvdc issue');
                                        $email->send('There was a problem counting the spun down vapps in an orgvdc for team ' . $mig_team['MigTeam']['name'] . ', in spp ' . $vcloud_mapping['spp_hostname'] . ', in orgvdc ' . $vcloud_mapping['orgvdc_name'] . ', please investigate. A count value came back that doesnt look right, ie this value ' . $vapps_spun_down_in_orgvdc_count_returned . ' so no data will be saved for this team');
                                        continue 2;
                                }

                                // Increment the count for this area as the data looked ok
                                $powered_on_vapps_count = $powered_on_vapps_count + $powered_on_vapps_count_returned;
                                $hosts_in_orgvdc_count = $hosts_in_orgvdc_count+ $hosts_in_orgvdc_count_returned;
				$vapps_spun_up_in_orgvdc_count = $vapps_spun_up_in_orgvdc_count+ $vapps_spun_up_in_orgvdc_count_returned;
				$vapps_spun_down_in_orgvdc_count = $vapps_spun_down_in_orgvdc_count+ $vapps_spun_down_in_orgvdc_count_returned;


                        }

			// All went good
			echo "Nothing went wrong counting for this team";
			echo "</br>";
			echo "Powered on vms count: " . $powered_on_vms_count;
			echo "</br>";
			echo "Powered on vapps count: " . $powered_on_vapps_count;
			echo "</br>";
			echo "Hosts in orgvdcs count: " . $hosts_in_orgvdc_count;
			echo "</br>";
			echo "Hosts in clusters count: " . $hosts_in_cluster_count;
                        echo "</br>";
			echo "Spun up vapps count: " . $vapps_spun_up_in_orgvdc_count;
                        echo "</br>";
			echo "Spun down vapps count: " . $vapps_spun_down_in_orgvdc_count;
                        echo "</br>";
			echo "Saving the counts";
			echo "</br>";

			//////////////////////////////////////////////////////////////////////////
			// Do the individual saves
			$this->MigNightlyCount->create();
			$this->MigNightlyCount->set(array(
                                'mig_team_id' => $mig_team['MigTeam']['id'],
                                'mig_count_type_id' => $virtual_machines_type,
                                'date' => $date,
                                'count' => $powered_on_vms_count
                        ));
                        // Remove any duplicate entries incase this gets run twice in one day
                        $this->MigNightlyCount->deleteAll(array('MigNightlyCount.date' => $date, 'MigNightlyCount.mig_count_type_id' => $virtual_machines_type, 'MigNightlyCount.mig_team_id' => $mig_team['MigTeam']['id']), false);

                        // Save the object in the database
                        $this->MigNightlyCount->save();

			$this->MigNightlyCount->create();
			$this->MigNightlyCount->set(array(
                                'mig_team_id' => $mig_team['MigTeam']['id'],
                                'mig_count_type_id' => $virtual_apps_type,
                                'date' => $date,
                                'count' => $powered_on_vapps_count
                        ));
                        // Remove any duplicate entries incase this gets run twice in one day
                        $this->MigNightlyCount->deleteAll(array('MigNightlyCount.date' => $date, 'MigNightlyCount.mig_count_type_id' => $virtual_apps_type, 'MigNightlyCount.mig_team_id' => $mig_team['MigTeam']['id']), false);

                        // Save the object in the database
                        $this->MigNightlyCount->save();

			$this->MigNightlyCount->create();
			$this->MigNightlyCount->set(array(
                                'mig_team_id' => $mig_team['MigTeam']['id'],
                                'mig_count_type_id' => $virtual_hosts_type,
                                'date' => $date,
                                'count' => $hosts_in_orgvdc_count + $hosts_in_cluster_count
                        ));
                        // Remove any duplicate entries incase this gets run twice in one day
                        $this->MigNightlyCount->deleteAll(array('MigNightlyCount.date' => $date, 'MigNightlyCount.mig_count_type_id' => $virtual_hosts_type, 'MigNightlyCount.mig_team_id' => $mig_team['MigTeam']['id']), false);

                        // Save the object in the database
                        $this->MigNightlyCount->save();

                        $this->MigNightlyCount->create();
                        $this->MigNightlyCount->set(array(
                                'mig_team_id' => $mig_team['MigTeam']['id'],
                                'mig_count_type_id' => $virtual_apps_spun_up_type,
                                'date' => $date,
                                'count' => $vapps_spun_up_in_orgvdc_count
                        ));
                        // Remove any duplicate entries incase this gets run twice in one day
                        $this->MigNightlyCount->deleteAll(array('MigNightlyCount.date' => $date, 'MigNightlyCount.mig_count_type_id' => $virtual_apps_spun_up_type, 'MigNightlyCount.mig_team_id' => $mig_team['MigTeam']['id']), false);

                        // Save the object in the database
                        $this->MigNightlyCount->save();


			$this->MigNightlyCount->create();
                        $this->MigNightlyCount->set(array(
                                'mig_team_id' => $mig_team['MigTeam']['id'],
                                'mig_count_type_id' => $virtual_apps_spun_down_type,
                                'date' => $date,
                                'count' => $vapps_spun_down_in_orgvdc_count
                        ));
                        // Remove any duplicate entries incase this gets run twice in one day
                        $this->MigNightlyCount->deleteAll(array('MigNightlyCount.date' => $date, 'MigNightlyCount.mig_count_type_id' => $virtual_apps_spun_down_type, 'MigNightlyCount.mig_team_id' => $mig_team['MigTeam']['id']), false);

                        // Save the object in the database
                        $this->MigNightlyCount->save();

		}
	}
        

/**
 * view method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function view($id = null) {
		if (!$this->MigNightlyCount->exists($id)) {
			throw new NotFoundException(__('Invalid mig nightly count'));
		}
		$options = array('conditions' => array('MigNightlyCount.' . $this->MigNightlyCount->primaryKey => $id));
		$this->set('migNightlyCount', $this->MigNightlyCount->find('first', $options));
	}

/**
 * add method
 *
 * @return void
 */
	public function add() {
		if ($this->request->is('post')) {
			$this->MigNightlyCount->create();
			if ($this->MigNightlyCount->save($this->request->data)) {
				$this->Session->setFlash(__('The mig nightly count has been saved'));
				$this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__('The mig nightly count could not be saved. Please, try again.'));
			}
		}
		$migTeams = $this->MigNightlyCount->MigTeam->find('list');
		$migCountTypes = $this->MigNightlyCount->MigCountType->find('list');
		$this->set(compact('migTeams', 'migCountTypes'));
	}

/**
 * edit method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function edit($id = null) {
		if (!$this->MigNightlyCount->exists($id)) {
			throw new NotFoundException(__('Invalid mig nightly count'));
		}
		if ($this->request->is('post') || $this->request->is('put')) {
			if ($this->MigNightlyCount->save($this->request->data)) {
				$this->Session->setFlash(__('The mig nightly count has been saved'));
				$this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__('The mig nightly count could not be saved. Please, try again.'));
			}
		} else {
			$options = array('conditions' => array('MigNightlyCount.' . $this->MigNightlyCount->primaryKey => $id));
			$this->request->data = $this->MigNightlyCount->find('first', $options);
		}
		$migTeams = $this->MigNightlyCount->MigTeam->find('list');
		$migCountTypes = $this->MigNightlyCount->MigCountType->find('list');
		$this->set(compact('migTeams', 'migCountTypes'));
	}

/**
 * delete method
 *
 * @throws NotFoundException
 * @throws MethodNotAllowedException
 * @param string $id
 * @return void
 */
	public function delete($id = null) {
		$this->MigNightlyCount->id = $id;
		if (!$this->MigNightlyCount->exists()) {
			throw new NotFoundException(__('Invalid mig nightly count'));
		}
		$this->request->onlyAllow('post', 'delete');
		if ($this->MigNightlyCount->delete()) {
			$this->Session->setFlash(__('Mig nightly count deleted'));
			$this->redirect(array('action' => 'index'));
		}
		$this->Session->setFlash(__('Mig nightly count was not deleted'));
		$this->redirect(array('action' => 'index'));
	}
}
