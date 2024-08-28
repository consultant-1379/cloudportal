<?php

class ReportsController extends AppController {

    var $uses = array('OrgVdc', 'Vcloud', 'Vapp');

    public function beforeFilter() {
        parent::beforeFilter();
        $this->Auth->allow('index','datacenter_report','vapp_report','historical_reports');
    }

    function index() {
        $this->set("title_for_layout", "Reports");
        $this->set('page_for_layout', 'reports');

        $reports = array();
        $datacenter_report = array(
            'title' => 'Generate Datacenter Report',
            'link' => 'datacenter_report',
            'details' => 'Generate a live datacenter report'
        );
        array_push($reports, $datacenter_report);
        $datacenter_report = array(
            'title' => 'Generate vApp Report',
            'link' => 'vapp_report',
            'details' => 'Generate a live vApp report'
        );
        array_push($reports, $datacenter_report);
        $historical_reports = array(
            'title' => 'Historical Reports',
            'link' => 'historical_reports',
            'details' => 'See a list of historical reports'
        );
        array_push($reports, $historical_reports);
        $this->set('reports',$reports);
    }

    function historical_reports() {
        $this->set("title_for_layout", "Historical Reports");
        $this->set('page_for_layout', 'reports');
        $reports_path = dirname(WWW_ROOT) . "/" . basename(WWW_ROOT) . "/historical_reports/";
        $reports = $this->find_files($reports_path);
        $this->set('reports',$reports);
    }

    function datacenter_report() {
        $this->set("title_for_layout", "Datacenter Report");
        $this->set('page_for_layout', 'reports');
        $params = array(
            'type' => 'adminVApp',
            'fields' => array('vdcName'),
            'generated_fields' => array('vapp_power_status')
        );
        $all_vapps = $this->Vcloud->query_service_request($params);

        $all_orgvdcs = $this->OrgVdc->find('all', array('fields' => array('vcd_id','name', 'running_tb_limit', 'stored_tb_limit', 'cpu_limit', 'memory_limit', 'ProviderVdc.name', 'ProviderVdc.new_quota_system'),'contain' => 'ProviderVdc'));

        $orgvdc_resources = $this->Vcloud->list_all_orgvdc_resourses();

        $Vapp = ClassRegistry::init('Vapp');
        $vapp_powered_on_states = $Vapp->get_powered_on_states();

        $result_array = array();

        foreach ($all_orgvdcs as &$orgvdc)
        {
            $result = array();
            $vapps_running=0;
            $vapps_not_running=0;
            foreach ($all_vapps as $vapp)
            {
                if($vapp['vdcName'] === $orgvdc['OrgVdc']['name'])
                {
                    if (in_array($vapp['vapp_power_status'], $vapp_powered_on_states))
                    {
                        $vmsrunning = true;
                    } else {
                        $vmsrunning = false;
                    }
                    if ($vmsrunning) {
                        $vapps_running++;
                    }
                    else {
                        $vapps_not_running++;
                    }
                }
            }
            $running_cpus = 0;
            $running_memory = 0;
            foreach ($orgvdc_resources as $orgvdc_resource)
            {
                if ($orgvdc_resource['orgvdc_id'] == $orgvdc['OrgVdc']['vcd_id'])
                {
                    $running_cpus = $orgvdc_resource['cpu_on_count'];
                    $running_memory = ceil($orgvdc_resource['memory_on_count'] / 1024);
                    break;
                }
            }
            $result['datacenter_name'] = $orgvdc['OrgVdc']['name'];
            $result['provider_name'] = $orgvdc['ProviderVdc']['name'];
            $result['running_cpus'] = $running_cpus;
            $result['running_cpus_quota'] = $orgvdc['OrgVdc']['cpu_limit'];
            $unused_running_cpus_quota = $result['running_cpus_quota'] - $result['running_cpus'];
            if ($unused_running_cpus_quota < 0)
            {
                $unused_running_cpus_quota = 0;
            }
            $result['unused_running_cpus_quota'] = $unused_running_cpus_quota;

            $result['running_memory'] = $running_memory;
            $result['running_memory_quota'] = $orgvdc['OrgVdc']['memory_limit'];
            $unused_running_memory_quota = $result['running_memory_quota'] - $result['running_memory'];
            if ($unused_running_memory_quota < 0)
            {
                $unused_running_memory_quota = 0;
            }
            $result['unused_running_memory_quota'] = $unused_running_memory_quota;
            $result['running_vapps'] = $vapps_running;
            $result['running_vapps_quota'] = $orgvdc['OrgVdc']['running_tb_limit'];
            $unused_running_vapps_quota = $result['running_vapps_quota'] - $result['running_vapps'];
            if ($unused_running_vapps_quota < 0)
            {
                $unused_running_vapps_quota = 0;
            }
            $result['unused_running_vapps_quota'] = $unused_running_vapps_quota;

            $result['total_vapps'] = $vapps_running + $vapps_not_running;
            $result['total_vapps_quota'] = $orgvdc['OrgVdc']['stored_tb_limit'];
            $unused_total_vapps_quota = $result['total_vapps_quota'] - $result['total_vapps'];
            if ($unused_total_vapps_quota < 0)
            {
                $unused_total_vapps_quota = 0;
            }
            $result['unused_total_vapps_quota'] = $unused_total_vapps_quota;

            if ($orgvdc['ProviderVdc']['new_quota_system'])
            {
                $result['running_vapps'] = "NA";
                $result['running_vapps_quota'] = "NA";
                $result['unused_running_vapps_quota'] = "NA";
            } else {
                $result['running_cpus'] = "NA";
                $result['running_cpus_quota'] = "NA";
                $result['unused_running_cpus_quota'] = "NA";
                $result['running_memory'] = "NA";
                $result['running_memory_quota'] = "NA";
                $result['unused_running_memory_quota'] = "NA";
            }

            array_push($result_array, $result);
        }
        $this->set('titles', array('Datacenter','Provider Name','Running CPUs','Running CPUs Quota','Unused Running CPUs Quota','Running Memory','Running Memory Quota','Unused Running Memory Quota','Running vApps', 'Running vApps Quota', 'Unused Running vApps Quota', 'Total vApps', 'Total vApps Quota', 'Unused Total vApps Quota'));
        $this->set('OrgVdcs', $result_array);
        if ($this->params['ext'] == 'csv')
        {
            $this->layout = null;
        }
        else if ($this->params['ext'] == 'json' || $this->params['ext'] == 'xml')
        {
            $this->set('_serialize', array('OrgVdcs'));
        }
    }

    function vapp_report() {
        $this->set("title_for_layout", "vApp Report");
        $this->set('page_for_layout', 'reports');
        $params = array(
            'type' => 'adminVApp',
            'fields' => array('name', 'vdcName', 'creationDate'),
            'generated_fields' => array('vapp_power_status', 'origin_template_name', 'origin_template_id', 'vapp_id'),
            'sortAsc' => 'vdcName'
        );
        if (isset($this->passedArgs['datacenter'])) {
            $datacenter = $this->passedArgs['datacenter'];
            $this->set('datacenter',$datacenter);
            $params['filter'] = 'vdcName==' . urlencode($datacenter);
        }
        $all_vapps = $this->Vcloud->query_service_request($params);

        $params = array(
            'type' => 'adminVAppTemplate',
            'fields' => array('catalogName'),
            'generated_fields' => array('vapp_template_id'),
            'sortDesc' => 'creationDate'
        );
        $all_vapp_templates = $this->Vcloud->query_service_request($params);
        $db_vapps = $this->Vapp->find('all', array('vcd_id', 'vts_name', 'created_by_id'), array('contain' => false));
        $result_array = array();
        $vapp_resources = $this->Vcloud->list_vapps_resourses();
        foreach ($all_vapps as $vapp)
        {
            $gateway_hostname = "";
            $vapp_owner = "Administrator";
            foreach($db_vapps as $db_vapp)
            {
                if($db_vapp['Vapp']['vcd_id'] == $vapp['vapp_id'])
                {
                    $gateway_hostname = $db_vapp['Vapp']['vts_name'];

                    if ($db_vapp['Vapp']['created_by_id'] != null) {
                        $vapp_owner = $db_vapp['Vapp']['created_by_id'];
                    }
                    break;
                }
            }
            $running_cpus = 0;
            $running_memory = 0;
            foreach($vapp_resources as $vapp_resource)
            {
                if ($vapp_resource['vapp_id'] == $vapp['vapp_id'])
                {
                    $running_cpus = $vapp_resource['cpu_on_count'];
                    $running_memory = ceil($vapp_resource['memory_on_count'] / 1024);
                    break;
                }
            }
            $result = array();
            $result['datacenter_name'] = $vapp['vdcName'];
            $result['vapp_name'] = $vapp['name'];
            $result['status'] = $vapp['vapp_power_status'];
            $result['gateway'] = $gateway_hostname;
            $result['created_by'] = $vapp_owner;
            $result['creation_date'] = $vapp['creationDate'];
            $result['running_cpus'] = $running_cpus;
            $result['running_memory'] = $running_memory;
            $origin_template_id = $vapp['origin_template_id'];
            $result['origin_catalog_name'] = "unknown";
            if (strpos($origin_template_id,'urn:vcloud') !== false)
            {
                foreach ($all_vapp_templates as $vapp_template)
                {
                    if ($vapp_template['vapp_template_id'] == $origin_template_id)
                    {
                        $result['origin_catalog_name'] = $vapp_template['catalogName'];
                        break;
                    }
                }
            }
            $result['origin_template_name'] = $vapp['origin_template_name'];
            array_push($result_array, $result);
        }
        $this->set('titles', array('Datacenter','vApp Name', 'Status', 'Gateway','Created By' ,'Creation Date', 'Running CPUs', 'Running Memory', 'Origin Catalog Name', 'Original Template Name'));
        $this->set('Vapp', $result_array);
        if ($this->params['ext'] == 'csv')
        {
            $this->layout = null;
        }
        else if ($this->params['ext'] == 'json' || $this->params['ext'] == 'xml')
        {
            $this->set('_serialize', array('Vapp'));
        }
    }

    function find_files($dir) {
        $filenames = scandir($dir);
        $file_array = array();
        foreach($filenames as $filename) {
            # Don't show any files that begin with a dot
            if (strpos($filename, '.', 0) !== 0)
            {
                $filemtime = filemtime($dir.$filename);
                $result_array = array(
                    'filename' => $filename,
                    'modified_time' => $filemtime
                );
                array_push($file_array, $result_array);
            }
        }
        return $file_array;
    }
}
?>
