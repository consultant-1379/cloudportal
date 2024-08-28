<?php

App::import('Core', 'Controller');
//App::uses('Router', 'Routing');
App::uses('CakeEmail', 'Network/Email');
App::uses('File', 'Utility');

class VcloudShell extends AppShell {

    public $uses = array('Vcloud','Booking');

    public function _welcome() {

    }

    public function main() {
        $spp_hostname = strtok(shell_exec('hostname -f'), "\n");
        $base_url = "https://" . $spp_hostname;
        if (!isset($this->params['function'])) {
            echo "You must enter a function name";
            exit(1);
        }
        if (!isset($this->params['username'])) {
            $this->params['username'] = "not_given";
            //exit(1);
        }
        if (!isset($this->params['reboot_gateway_if_necessary'])) {
            $this->params['reboot_gateway_if_necessary'] = "no";
            //exit(1);
        }
        switch ($this->params['function']) {
            case "deploy_from_catalog":
                try {
                    $error_message = "";
                    if (!isset($this->params['email'])) {
                        $dest_email = "dummy@dummy.com";
                    } else {
                        $dest_email = $this->params['email'];
                    }
                    $vapp_details = $this->Vcloud->deploy_from_catalog($this->params, $this->params['username'], $dest_email);
                } catch (Exception $e) {
                    $error_message = $e;
                    echo $e;
                }

                if (isset($this->params['email'])) {
                    $Email = new CakeEmail();
                    $Email->emailFormat('html');
                    $Email->from(array('no_reply@ericsson.com' => 'Cloud Portal'));
                    $Email->to($this->params['email']);

                    //new instance of CakeEmail class created and email attributes added 
                    if (isset($vapp_details)) {
                        $subject = "Add To Cloud Complete - " . $this->params['new_vapp_name'];
                        if (isset($vapp_details['gateway_details'])) {
                            $gateway_details = $vapp_details['gateway_details'];
                            $vpn_path = $this->Booking->create_vpn_file($gateway_details['gateway_hostname'], $gateway_details['gateway_ipaddress']);
                            $app_path = dirname(APP) . "/" . basename(APP);

                            //vapps id's assigned to variable    
                            $vms = $this->Vcloud->list_vms_id($vapp_details['vapp_id']);
                            $bool = false;
                            //check the vapps vms for uas extention
                            foreach ($vms as $vm) {
                                if (strstr($vm['name'], "uas1")) {

                                    //construct the ica file if uas1 extention found on vm on the vapp
                                    $ica_file_cex_file = $gateway_details['gateway_hostname'] . '_cex.ica';
                                    $ica_file_cex_path = $app_path . '/webroot/files/ica/' . $ica_file_cex_file;
                                    //ica content
                                    $icaFileContent = "
[Encoding]
InputEncoding=ISO8859_1

[WFClient]
Version=2
HttpBrowserAddress=" . $gateway_details['gateway_ipaddress'] .
                                            "\n" .
                                            "[ApplicationServers]" . "\n"
                                            . $gateway_details['gateway_ipaddress'] . "=

[" . $gateway_details['gateway_ipaddress'] . "]
Address=" . $gateway_details['gateway_ipaddress'] . "\n" . "
    
InitialProgram=
ClientAudio=On
AudioBandwidthLimit=2
DesiredColor=24
DesiredHRes=1280
DesiredVRes=1024
Compress=On
TransportDriver=TCP/IP
WinStationDriver=ICA 3.0
BrowserProtocol=HTTPonTCP

[Compress]
DriverName= PDCOMP.DLL
DriverNameWin16= PDCOMPW.DLL
DriverNameWin32= PDCOMPN.DLL";

                                    // The file wouldn't attach unless I created it twice, might be a bug in cakephp but it works
                                    $file = new File($ica_file_cex_path, true);
                                    $file->write($icaFileContent);
                                    $file->close();
                                    $file = new File($ica_file_cex_path, true);
                                    $file->write($icaFileContent);
                                    $file->close();
                                    $bool = true;
                                    break;
                                }
                            }

                            if ($bool == true) {
                                // Attach both files to the email if uas1 extention is true
                                $Email->attachments(array($ica_file_cex_path, $vpn_path));
                                $Email->template('deployvappicavpn', 'custom');
                                $Email->viewVars(array(
                                    'vappName' => $this->params['new_vapp_name'],
                                    'catName' => $this->params['destorgvdcname'],
                                    'vappUser' => $this->params['username'],
                                    'runtimeLease' => $vapp_details['runtimeleaseurl'],
                                    'storageLease' => $vapp_details['storageleaseurl'],
                                    'url' => $base_url,
                                    'gatewayDetails' => $gateway_details['gateway_fqhn']));
                            } else {
                                if ($this->params['destorgvdcname'] == "GTEC-MyENMlab")
                                {
                                    $Email->template('deployvappvpngtec', 'custom');
                                }
                                else {
                                    $Email->template('deployvappvpn', 'custom');
                                }
                                //if uas1 was not found only attach the vpn file
                                $Email->attachments($vpn_path);
                                $Email->viewVars(array(
                                    'vappName' => $this->params['new_vapp_name'],
                                    'catName' => $this->params['destorgvdcname'],
                                    'vappUser' => $this->params['username'],
                                    'runtimeLease' => $vapp_details['runtimeleaseurl'],
                                    'storageLease' => $vapp_details['storageleaseurl'],
                                    'url' => $base_url,
                                    'gatewayDetails' => $gateway_details['gateway_fqhn']));
                            }
                        } else {
                            //vApp not powered on
                            $Email->template('deployvappnotpoweredon', 'custom');
                            $Email->viewVars(array(
                                'vappName' => $this->params['new_vapp_name'],
                                'catName' => $this->params['destorgvdcname'],
                                'vappUser' => $this->params['username'],
                                'runtimeLease' => $vapp_details['runtimeleaseurl'],
                                'storageLease' => $vapp_details['storageleaseurl'],
                                'url' => $base_url));
                        }
                    } else {
                        $subject = "Add To Cloud Failed - " . $this->params['new_vapp_name'];
                        $Email->template('deployfromcatalogfailed', 'custom');
                        $Email->viewVars(array(
                            'vappName' => $this->params['new_vapp_name'],
                            'catName' => $this->params['destorgvdcname'],
                            'vappUser' => $this->params['username'],
                            'url' => $base_url,
                            'error' => htmlspecialchars($error_message)));
                    }
                    $Email->subject($subject);
                    $Email->send();
                }
                if (isset($vapp_details)) {
                    $this->out("NEWVAPPID " . $vapp_details['vapp_id']);
                } else {
                    exit(1);
                }
                break;
            case "add_vapp_to_catalog":
                $was_error = false;
                try {
                    $error_message = "";
                    if (!isset($this->params['email'])) {
                        $dest_email = "dummy@dummy.com";
                    } else {
                        $dest_email = $this->params['email'];
                    }
                    $output = $this->Vcloud->add_vapp_to_catalog($this->params, $this->params['username'], $dest_email);
                } catch (Exception $e) {
                    $was_error = true;
                    $error_message = $e;
                }

		if (isset($this->params['email'])) {
                //Cakelog::error('base url'.$this->params['sppurl']);
		$modifyLease=null;
		if (isset($output['leaseurl']))
		{
			$modifyLease=$output['leaseurl'];
		}
                $Email = new CakeEmail();
                $Email->emailFormat('html');
                $Email->viewVars(array(
                    'newTemplateName' => $this->params['new_vapp_template_name'],
                    'catName' => $this->params['dest_catalog_name'],
                    'newVappTemplateName' => $this->params['new_vapp_template_name'],
                    'vappUser' => $this->params['username'],
                    'modifyLease' => $modifyLease,
                    'url' => $base_url,
                    'errorMessage' => htmlspecialchars($error_message)));
                $Email->from(array('no_reply@ericsson.com' => 'Cloud Portal'));
                $Email->to($this->params['email']);
                if (isset($this->params['email'])) {
                    if (isset($output['tempid'])) {
                        $subject = $this->params['new_vapp_template_name'] . " has been sucessfully added to " . $this->params['dest_catalog_name'];
                        $Email->template('deployvapptocatalogsuccess', 'custom');
                    } else {
                        $subject = "Add To Catalog Failed - " . $this->params['new_vapp_template_name'];
                        $Email->template('deployvapptocatalogunsuccessfull', 'custom');
                    }
                    $Email->subject($subject);
                    $Email->send();
                }
		}

                if (isset($output['tempid']) && !$was_error) {
                    $this->out("NEWVAPPTEMPLATEID " . $output['tempid']);
                } else {
                    echo $error_message;
                    exit(1);
                }
                break;
            case "recompose":
                $was_error = false;
                try {
                    $error_message = "";
                    if (!isset($this->params['email'])) {
                        $dest_email = "dummy@dummy.com";
                    } else {
                        $dest_email = $this->params['email'];
                    }
                    $vms = explode(',', $this->params['vm_id']);
                    $this->Vcloud->recompose_vapp($this->params['vapp_id'], $vms, $this->params['username']);
                } catch (Exception $e) {
                    $was_error = true;
                    $error_message = $e;
                }
                if (isset($this->params['email'])) {
                    $vapp_name = $this->Vcloud->get_vapp_name($this->params['vapp_id']);
                    $orgvdc_name = $this->Vcloud->get_orgvdc_name_by_vapp($this->params['vapp_id']);
                    $vapp_ipaddress = $this->Vcloud->get_vapp_ipaddress($this->params['vapp_id']);
                    $vapp_hostname = $vapp_ipaddress[0]['hostname'];
                    if($vapp_ipaddress[0]['hostname']){
                       $vapp_hostname = "Gateway hasn't changed: " . $vapp_hostname . ".athtem.eei.ericsson.se";
                    }
                    $Email = new CakeEmail();
                    $Email->emailFormat('html');
                    $Email->viewVars(array(
                    'vappUser' => $this->params['username'],
                    'vapp' => $vapp_name,
                    'cloudName' => $orgvdc_name,
                    'url' => $base_url,
                    'gateway' => $vapp_hostname,
                    'errorMessage' => htmlspecialchars($error_message)));
                    $Email->from(array('no_reply@ericsson.com' => 'Cloud Portal'));
                    $Email->to($this->params['email']);
                    if ($was_error == false) {
                        $subject = $vapp_name . " has been successfully Recomposed";
                        $Email->template('vapprecomposesuccess', 'custom');
                    } else {
                        $subject = "Recompose was unsuccessful for " . $vapp_name;
                        $Email->template('vapprecomposeunsuccessful', 'custom');
                    }
                    $Email->subject($subject);
                    $Email->send();
                }
                break;
            case "list_vapps":
                $vapps = $this->Vcloud->list_vapps($this->params['org_name'], $this->params['org_vdc_name']);
                foreach ($vapps as $vapp) {
                    $this->out($vapp['vapp_href']);
                }
                break;
            case "list_vapps_in_orgvdc":
                $orgvdc = $this->Vcloud->get_orgvdc_id_by_name($this->params['org_vdc_name']);
                $orgvdc_id = $orgvdc[0]['orgvdc_id'];
                $vapps = $this->Vcloud->list_vapps_id($orgvdc_id);
                foreach ($vapps as $vapp) {
                    $this->out($vapp['name'] . ';' . $vapp['vapp_id']);
                }
                break;
            case "list_orgvdcs":
                $orgvdcs = $this->Vcloud->list_orgvdcs();
                foreach ($orgvdcs as $orgvdc) {
                    $this->out($orgvdc['name']);
                }
                break;
            case "count_running_vapps_in_org":
                $count = $this->Vcloud->count_running_vapps(null, $this->params['orgvdc_name'], null);
                if (isset($count['running'])) {
                    echo $count['running'];
                } else {
                    exit(1);
                }
                break;
            case "count_running_vapps_in_orgvdc":
                $count = $this->Vcloud->count_running_vapps(null, null, $this->params['org_vdc_name']);
                if (isset($count['running'])) {
                    echo $count['running'];
                } else {
                    exit(1);
                }
                break;
            case "count_hosts_in_orgvdc":
                $count = $this->Vcloud->count_hosts_in_orgvdc($this->params['org_vdc_name']);
                echo $count;
                break;
            case "count_spun_up_vapps_yesterday_in_orgvdc":
                $count = $this->Vcloud->count_spun_up_vapps_yesterday_in_orgvdc($this->params['org_vdc_name']);
                echo $count;
                break;
            case "count_spun_down_vapps_yesterday_in_orgvdc":
                $count = $this->Vcloud->count_spun_down_vapps_yesterday_in_orgvdc($this->params['org_vdc_name']);
                echo $count;
                break;
            case "list_vapp_templates_in_catalog":
                $vapptemplates = $this->Vcloud->list_vapptemplates_catalog($this->params['catalog_name']);
                foreach ($vapptemplates as $vapptemplate) {
                    $this->out($vapptemplate['vapptemplate_name'] . ";" . $vapptemplate['vapptemplate_id']);
                }
                break;
            case "list_vms_in_vapp":
                $vms = $this->Vcloud->list_vms_id($this->params['vapp_id']);
                foreach ($vms as $vm) {
                    $id_last_part = split(":", $vm['vm_id']);
                    $this->out($vm['name'] . ";", 0);
                    $this->out($vm['name'] . " (" . $id_last_part[3] . ");", 0);
                    $this->out($vm['vm_id']);
                    //$this->out($vm['vm_href']);
                }
                break;
            case "start_vapp":
                $this->Vcloud->start_vapp($this->params['vapp_id'], $this->params['username'], $this->params['reboot_gateway_if_necessary'],false);
                break;
            case "stop_vapp":
                $this->Vcloud->stop_vapp($this->params['vapp_id'], $this->params['username']);
                break;
            case "poweroff_vapp":
                $this->Vcloud->poweroff_vapp($this->params['vapp_id'], $this->params['username']);
                break;
            case "shutdown_vapp":
                $this->Vcloud->shutdown_vapp($this->params['vapp_id'], $this->params['username']);
                break;
            case "force_stop_vapp":
                $this->Vcloud->force_stop_vapp($this->params['vapp_id'], $this->params['username']);
                break;
            case "delete_vapp":
                $this->Vcloud->delete_vapp($this->params['vapp_id'], $this->params['username']);
                break;
            case "delete_vapp_template":
                $this->Vcloud->delete_vapp_template($this->params['vapp_template_id'], $this->params['username']);
                break;
            case "destroy_vapp":
                $this->Vcloud->destroy_vapp($this->params['vapp_id'], $this->params['username']);
                break;
            case "consolidate_vapp_template":
                $this->Vcloud->consolidate_vapp_template($this->params['vapp_template_id'], $this->params['username']);
                break;
	    case "check_power_vm":
                $output=$this->Vcloud->check_power_vm($this->params['vm_id']);
		$this->out($output,0);
                break;
            case "poweron_vm":
                $this->Vcloud->poweron_vm($this->params['vm_id'], $this->params['username'], $this->params['reboot_gateway_if_necessary']);
                break;
            case "poweroff_vm":
                $this->Vcloud->poweroff_vm($this->params['vm_id'], $this->params['username']);
                break;
            case "reset_vm":
                $this->Vcloud->reset_vm($this->params['vm_id'], $this->params['username']);
                break;
            case "shutdown_vm":
                $this->Vcloud->shutdown_vm($this->params['vm_id'], $this->params['username']);
                break;
            case "reboot_vm":
                $this->Vcloud->reboot_vm($this->params['vm_id'], $this->params['username']);
                break;
            case "suspend_vm":
                $this->Vcloud->suspend_vm($this->params['vm_id'], $this->params['username']);
                break;
            case "delete_vm":
                $this->Vcloud->delete_vm($this->params['vm_id'], $this->params['username']);
                break;
            case "set_memory_vm":
                $this->Vcloud->set_memory_vm($this->params['vm_id'], $this->params['memory_mb'], $this->params['username']);
                break;
            case "set_cpus_vm":
                $this->Vcloud->set_cpu_count_vm($this->params['vm_id'], $this->params['cpu_count'], $this->params['username']);
                break;
            case "set_mac_vm":
                $this->Vcloud->set_mac_vm($this->params['vm_id'], $this->params['mac_address'], $this->params['nic_no']);
                break;
            case "get_vapp_id_by_gateway_ip":
                $this->out($this->Vcloud->get_vapp_id_by_gateway_ip($this->params['gateway_ip'], $this->params['username']));
                break;
            case "get_vapp_ipaddress":
                $result = $this->Vcloud->get_vapp_ipaddress($this->params['vapp_id'],true);
                $this->out("IPADDRESS " . $result[0]['ipaddress']);
                break;
            case "get_vcenter_of_vm":
                $result = $this->Vcloud->get_vcenter_of_vm($this->params['vm_id']);
                $this->out("VCENTER " . $result);
                break;
            case "rename_vapp_template":
                $this->Vcloud->rename_vapp_template($this->params['vapp_template_id'], $this->params['new_vapp_template_name']);
                break;
            default:
                echo "You must enter a valid function name";
                exit(1);
        }
    }

    public function getOptionParser() {
        $parser = parent::getOptionParser();

        $parser->addOption('function', array(
            'help' => __('The function name.')
        ))->addOption('username', array(
            'help' => __('Youre username')
        ))->addOption('vapp_id', array(
            'help' => __('The vapp_id.')
        ))->addOption('vm_id', array(
            'help' => __('The vm_id.')
        ))->addOption('gateway_ip', array(
            'help' => __('The gateway ip address.')
        ))->addOption('vapp_name', array(
            'help' => __('The vapp_name.')
        ))->addOption('org_vdc_name', array(
            'help' => __('The org_vdc_name.')
        ))->addOption('org_name', array(
            'help' => __('The org_name.')
        ))->addOption('linked_clone', array(
            'help' => __('The org_vdc_name.')
        ))->addOption('new_vapp_name', array(
            'help' => __('The org_vdc_name.')
        ))->addOption('new_vapp_template_name', array(
            'help' => __('The org_vdc_name.')
        ))->addOption('vapp_template_id', array(
            'help' => __('The org_vdc_name.')
        ))->addOption('destorgvdcname', array(
            'help' => __('The org_vdc_name.')
        ))->addOption('dest_catalog_name', array(
            'help' => __('The org_vdc_name.')
        ))->addOption('catalog_name', array(
            'help' => __('The catalog_name.')
        ))->addOption('memory_mb', array(
            'help' => __('The memory size in mb.')
        ))->addOption('cpu_count', array(
            'help' => __('The cpu count.')
        ))->addOption('mac_address', array(
            'help' => __('The mac address.')
        ))->addOption('nic_no', array(
            'help' => __('The nic number to set.')
        ))->addOption('reboot_gateway_if_necessary', array(
            'help' => __('Whether to reboot the gateway, yes or no, if dhcp fails first time.')
        ))->addOption('email', array(
            'help' => __('Your email to send notifications to')
        ))->addOption('start_vapp', array(
            'help' => __('yes / no, Whether to start the vapp after its deployed')
        ))->addOption('sppurl', array(
            'help' => __('This represents the URL base name for the SPP')
        ));
        return $parser;
    }

}
