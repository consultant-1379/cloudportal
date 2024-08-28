<?php
echo $this->Form->create('SoftwareRelease');
echo $this->Form->input('name');
echo $this->Form->input('software_type_id');
echo $this->Form->end('Add Software Release');
?>