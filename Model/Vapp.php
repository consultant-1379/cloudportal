<?php

App::uses('AuthComponent', 'Controller/Component');

class Vapp extends AppModel {

    var $name = 'Vapp';
    //var $belongsTo = array('OrgVdc', 'SoftwareRelease', 'SoftwareType', 'SoftwareLsv', 'Citag', 'Team');
    //var $belongsTo = array('OrgVdc', 'SoftwareRelease', 'SoftwareType', 'SoftwareLsv', 'Citag', 'Team');
    //var $hasMany = 'Vms';
    //var $hasOne = 'Citag';
    var $primaryKey = 'vcd_id';
    var $validates = array(
        'name' => array(
            'vappname_rule' => array(
                'required' => true,
                'rule' => 'notEmpty',
                'message' => 'Please enter a vapp name'
            )
        ),
        'catalog' => array(
            'Please choose a catalog to add to' => array(
                'required' => true,
                'rule' => 'notEmpty',
                'message' => 'Please choose a catalog to add to'
            )
        )
    );
    function network($argsID = null, $wait_for_gateway = false, $reboot_gateway_if_necessary = "no")
    {
        $Vcloud = ClassRegistry::init("Vcloud");
        $spp_hostname=strtok(shell_exec('hostname -f'), "\n");
        Configure::load('ciportal');
        $ciportal_details = Configure::read('ciportal');

        if (isset($argsID)) {
            $vapp=null;
            $vapp['Vapp']['vcd_id']=$argsID;
            $vapps = array();
            array_push($vapps, $vapp);
        } else {
            $vapps = $this->find('all',array('conditions' => array('Vapp.vcd_id !=' => '')));
        }
        foreach ($vapps as $vapp) {
            $got_hostname = false;
            $i = 0;
            // Try 100 times, each attempt takes about 5 seconds so this shoudl gives about 10 minutes total
            while ($i <= 100) {

                // reboot the gateway as it doesn't seem to be getting its ip address
                if ($reboot_gateway_if_necessary == "yes" && $i == 240)
                {
                    $Vcloud->reboot_gateway($vapp['Vapp']['vcd_id']);
                }
                $network_info = $Vcloud->get_vapp_ipaddress($vapp['Vapp']['vcd_id'],false);
                $hostname = null;
                $ipaddress = null;
                $fqhn = null;

                if (isset($network_info[0])) {
                    if (isset($network_info[0]['hostname'])) {
                        $hostname = $network_info[0]['hostname'];
                        $got_hostname = true;
                    }
                    if (isset($network_info[0]['ipaddress'])) {
                        $ipaddress = $network_info[0]['ipaddress'];
                    }
                    if (isset($network_info[0]['fqhn'])) {
                        $fqhn = $network_info[0]['fqhn'];
                    }
                }

                if (isset($argsID)) {
                    $gateway_details = array(
                        "gateway_hostname" => $hostname,
                        "gateway_ipaddress" => $ipaddress,
                        "gateway_fqhn" => $fqhn
                    );
                }

                if (!$wait_for_gateway) {
                    break;
                }
                if ($got_hostname) {
                    break;
                }
                sleep(1);
                $i++;
            }

            $vapp_data = array('vcd_id' => $vapp['Vapp']['vcd_id'], 'ip_address' => $ipaddress, 'vts_name' => $hostname);
            $this->save($vapp_data);

            if ($got_hostname && isset($argsID))
            {
                // Create mapping on the CI Portal
                $rest_output = shell_exec("curl --request POST --insecure " . $ciportal_details['url'] . "mapGatewayToSpp/ --data 'gateway=" . $hostname  . "' --data 'spp=https://" . $spp_hostname . "/' 2>&1");
                if (!strstr($rest_output, "Created Mapping"))
                {
                    $action="populate_ciportal_gateway_spp_mapping";
                    $verbose_details = "SPP " . $spp_hostname . " was attempting to populate the CI Portal " . $ciportal_details['url'] . " with a mapping from gateway " . $hostname . " to SPP. Please run the vapp network cron manually to resync mappings. https://" . $spp_hostname . "/Vapps/network";
                    $exception = $rest_output;
                    $Vcloud->report_exception($action, $exception, 1, 1, $verbose_details);
                }
                try {
                    # Place the setupvapp start script on the gateway if required
                    $output = $this->get_script_output($ipaddress,"
                        (
                            if [[ -f /.nosetupvapp ]]
                            then
                                echo 'setupvapp not required'
                            else
                                if [[ ! `chkconfig --list setupvapp` ]]
                                then
                                    wget --no-check-certificate " . $ciportal_details['url'] . "static/setupvapp/scripts/setupvapp -O /etc/init.d/setupvapp
                                    chmod +x /etc/init.d/setupvapp
                                    chkconfig --add setupvapp
                                    chkconfig --level 2345 setupvapp on
                                    /etc/init.d/setupvapp start
                                fi
                                chkconfig --list setupvapp
                            fi
                        ) 2>&1
                    ");
                    if (!strstr($output, '2:on') && !strstr($output, 'setupvapp not required'))
                    {
                        throw new Exception('ERROR: The setupvapp script is not set to startup at boot time, see the following output for any errors: ' . $output);
                    }
                } catch (Exception $e)
                {
                    if (isset($argsID))
                    {
                        throw new Exception('Something went wrong trying to setup the gateway setupvapp script: ' . $e);
                    }
                }
            }
        }

        if (isset($argsID)) {
            return $gateway_details;
        } else {
            return true;
        }
    }

    function get_script_output($server,$command)
    {
        $connection = ssh2_connect($server, 22);
        if($connection === false) {
            throw new Exception('Cant connect to server ' . $server);
        }
        $result = ssh2_auth_password($connection, "root", "shroot");
        if($result === false) {
            throw new Exception('Authentication failed connecting to server ' . $server);
        }
        $stream = ssh2_exec($connection, $command);
        stream_set_blocking($stream, true);
        $returned_output = '';
        while($buffer = fread($stream, 4096)) {
            $returned_output .= $buffer;
        }
        fclose($stream);
        return $returned_output;
    }

    function import() {
        $i = 0;                                                         //Count the number imported.
        $vcd_count = 0;                                                 //Count the number in vCD
        $deleted = 0;                                                   //Count the number of vapps deleted from the database
        $vapps = array();                                               //Initialise array for vCloud vApps
        $Vcloud = ClassRegistry::init("Vcloud");
        $vapps = $Vcloud->list_vapps_id(null);                        //Get the list of vApps from vCloud
        $db_vapps = $this->find('all');					//Get the list of vApps from the Database
        foreach ($vapps as $vapp) {                                      //Loop through all vCloud results
            $id = '';                                                   //Blank so Database increments the ID field
            $vapp_data = array('name' => $vapp['name'], 'vcd_id' => $vapp['vapp_id'], 'href' => $vapp['href'], 'org_vdc_id' => $vapp['orgvdc_id'], 'created' => date("Y-m-d H:i:s", strtotime($vapp['creation_date'])));       //Create of values for the database
	//$vapp_data = array('name' => 'mark', 'vcd_id' => 'mark', 'href' => 'mark', 'org_vdc_id' => 'urn:vcloud:orgvdc:f1dd8751-b347-49d4-8aaa-3be5a4d9d2f7', 'created' => date("Y-m-d H:i:s", strtotime($vapp['creation_date'])));
            $match = 0;                                                 //

            if (!empty($db_vapps)) {                                      //If the database is not empty
                foreach ($db_vapps as $db_vapp) {                        //Loop through the database results
                    if ($db_vapp['Vapp']['vcd_id'] == $vapp['vapp_id']) {     //If the vApp exists already in the database
                        $match = 1;                                 //Tell the loop
                        break;
                    }
                }
                if ($match == 0) {                                        //At the end of the loop if there is no match
			//CakeLog::error($vapp['name'] . " . Saving it to the database now");
			$this->create();
			$this->save($vapp_data);                      // Add the vApps to the database
			
                    $i++;                                               // Increase imported number
                    $vcd_count++;                                       // Increase vCD Count
                    // Add an event
                    $message = "vApp " . $vapp['name'] . " has been added to the database";
                    $user = AuthComponent::user();
                    $event_data = array('user_id' => $user['username'], 'function_name' => 'vapp_import', 'message' => $message, 'object_type' => 'vApp', 'object_vcd_id' => $vapp['vapp_id'], 'event_type_id' => 1);       //Create of values for the database
                    // Write the event
                    $vapp_added_event = ClassRegistry::init('Events');
                    $vapp_added_event->create();
                    $vapp_added_event->save($event_data);
                } else {
		    //CakeLog::error($vapp['name'] . " . Not saving it to the database now");
                    $vcd_count++;                                       // Increase vCD Count
                }
            } else {                                                      //If database is empty
		$this->create();
                $this->save($vapp_data);                          //If the database is empty, add the vApp to it
                $i++;                                                   // Increase imported number
                $vcd_count++;                                           // Increase imported number
                // Add an event
                $message = "vApp " . $vapp['name'] . " has been added to the database<br>";
                $user = AuthComponent::user();
                $event_data = array('user_id' => $user['username'], 'function_name' => 'vapp_import', 'message' => $message, 'object_type' => 'vApp', 'object_vcd_id' => $vapp['vapp_id'], 'event_type_id' => 1);       //Create of values for the database
                // Write the event
                $vapp_added_event = ClassRegistry::init('Events');
                $vapp_added_event->create();
                $vapp_added_event->save($event_data);
            }
        }
        $import_variables = array();
        $import_variables['imported'] = $i;                                  //Pass imported number to view
        $import_variables['database'] = count($this->find('all'));   //Pass database count view
        $import_variables['vcd'] = $vcd_count;                               //Pass vcd number to view
        $deleted = $this->sync_database();
        $import_variables['deleted'] = $deleted;                               //Pass vcd number to view
        global $changed;
        $import_variables['changed'] = $changed;                               //Pass vcd number to view
        return $import_variables;
    }

    function sync_database() {
        $vapps = array();                                           //Initialise array for vCloud vApps
        $Vcloud = ClassRegistry::init("Vcloud");
        $vapps = $Vcloud->list_vapps_id(null);
        $db_vapps = $this->find('all',array('conditions' => array('Vapp.vcd_id !=' => '')));                      //Get the list of vApps from the Database
        $match = 0;
        $deleted = 0;
        global $changed;
        $changed = 0;

        if (count($vapps) >= 2) {                                     //If the number of vApps from vCloud is less than 10, something wrong, don't do anything
            foreach ($db_vapps as $db_vapp) {

                foreach ($vapps as $vapp) {
                    if ($db_vapp['Vapp']['vcd_id'] == $vapp['vapp_id']) {             //If the vApp exists already in the database
                        $match = 1;                                 //Tell the loop
                        if ($db_vapp['Vapp']['name'] != $vapp['name']) {
                            $vapp_data = array('vcd_id' => $vapp['vapp_id'], 'name' => $vapp['name'],'org_vdc_id' => $vapp['orgvdc_id']);
                            $this->save($vapp_data);
                            $changed++;
                        }
                        break;
                    }
                }

                if ($match == 0) {
                      $message = "vApp ".$db_vapp['Vapp']['name']." deleted from database";
		      $user=AuthComponent::user();
                      if($this->delete($db_vapp['Vapp']['vcd_id'])){
                      $deleted++;
                      // Add an event
                      $event_data = array ('user_id' => $user['username'], 'function_name' => 'vapp_sync_database', 'message' => $message,'object_type' => 'vApp','object_vcd_id' => $db_vapp['Vapp']['vcd_id'],'event_type_id' => 2);       //Create of values for the database
		      $vapp_deleted_event = ClassRegistry::init('Events');
                      $vapp_deleted_event->create();
                      $vapp_deleted_event->save($event_data);
                      }else{
                      $message = "Failed to delete vApp ".$db_vapp['Vapp']['name']." from the database";
                      $event_data = array ('user_id' => $user['username'], 'function_name' => 'vapp_sync_database', 'message' => $message,'object_type' => 'vApp','object_vcd_id' => $db_vapp['Vapp']['vcd_id'],'event_type_id' => 2);       //Create of values for the database

                      $vapp_added_event = ClassRegistry::init('Events');
                      $vapp_added_event->create();
                      $vapp_added_event->save($event_data);
                      }
                }
                $match = 0;
            }
            return $deleted;
        } else {
            $message = "Sync database was not allowed due to too few vApps in the vCloud array. Are there less than 10 vApps in vCloud?";
            $user = AuthComponent::user();
            $event_data = array('user_id' => $user['username'], 'function_name' => 'vapp_sync_database', 'message' => $message, 'object_type' => 'vApp', 'object_vcd_id' => $db_vapp['Vapp']['vcd_id'], 'event_type_id' => 2);       //Create of values for the database
            $vapp_added_event = ClassRegistry::init('Events');
            $vapp_added_event->create();
            $vapp_added_event->save($event_data);
        }
    }
    function get_powered_on_states()
    {
	return array('POWERED_ON', 'MIXED','WAITING_FOR_INPUT','PARTIALLY_POWERED_OFF');
    }

}

?>
