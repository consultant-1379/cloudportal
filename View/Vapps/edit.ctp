<?php

echo $this->Form->create('Vapp');
//echo $this->Form->input('name');
echo $this->Form->input('description');
echo $this->Form->input('vts_name');
echo $this->Form->input('ip_address');
//echo $this->Form->input('team_id', array('empty' => true));
//echo $this->Form->input('citag_id', array('empty' => true));
//echo $this->Form->input('software_type_id', array('empty' => true, 'style' => 'align:left')) . " " . $this->Html->Link('Add Software Type', array('controller' => 'SoftwareTypes', 'action' => 'add'));
//echo $this->Form->input('software_release_id', array('empty' => true));
//echo $this->Form->input('software_lsv_id', array('empty' => true));
//echo $this->Form->input('user_id', array('type' => 'hidden', 'value' => $current_user['username']));
echo $this->Form->end('Update vApp');
?>