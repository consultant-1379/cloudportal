<?php
echo $this->Form->create('Citag');
echo $this->Form->input('name');
echo $this->Form->input('org_vdc_id');
echo $this->Form->input('user_id', array('type' => 'hidden', 'value' => $current_user['username']));
echo $this->Form->end('Create Ci Tag');
?>