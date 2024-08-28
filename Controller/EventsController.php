<?php
App::uses('AppController', 'Controller');

class EventsController extends AppController {

    public function beforeFilter() {
        parent::beforeFilter();
        $this->Auth->allow("index");
    }

    function index() {
        $this->Event->recursive = 0;
        $arguments['fields'] = array('org_vdc_id','function_name','value_returned','created','modified','retries','OrgVdc.name','OrgVdc.mig_ra_id'); 
        $arguments['conditions'] = array();

        $data=$this->request->data;
        $fullArray = array();

        if (isset($data['dataCenters']) and !empty($data['dataCenters']))
        { 
            $dataCenterArray = array();
            foreach ($data['dataCenters'] as $dataCenter)
            {
                array_push($dataCenterArray, array('OrgVdc.name' => $dataCenter['name']));
            }
            array_push($fullArray,array('OR' => $dataCenterArray));
        }
        elseif (isset($data['devGroup']) and !empty($data['devGroup']))
        {
            $devGroupArray = array();
            foreach ($data['devGroup'] as $devGroup)
            {
                array_push($devGroupArray, array('OrgVdc.mig_ra_id' => $devGroup['id']));
            }
            array_push($fullArray,array('OR' => $devGroupArray));
        }
        if (isset($data['eventTypes']) and !empty($data['eventTypes']))
        {
            $eventTypeArray = array();
            foreach ($data['eventTypes'] as $eventType)
            {
                array_push($eventTypeArray, array('function_name' => $eventType['name']));
            }
            array_push($fullArray,array('OR' => $eventTypeArray));
        }
        if (isset($data['startTime']) and isset($data['endTime']) and !empty($data['startTime']) and !empty($data['endTime']))
        {
            $timeCheck = array();
            foreach ($data['startTime'] as $startCheckTime)
            {
                $startDate = $startCheckTime['time'];
            }
            foreach ($data['endTime'] as $endCheckTime)
            {
                $endDate = $endCheckTime['time'];
            }
            $timeCheck = array('Event.created BETWEEN ? AND ?' => array($startDate, $endDate));
            array_push($fullArray,array('OR' => $timeCheck));
        }
        $arguments['conditions']['AND'] = $fullArray;
        $arguments['conditions']['not'] = array('Event.org_vdc_id' => null, 'Event.value_returned' => null, 'OrgVdc.mig_ra_id' => null);
        $result = $this->Event->find('all', $arguments);
        $this->set('Events', $result);
        $this->set('_serialize', array('Events'));
    }
}
?>
