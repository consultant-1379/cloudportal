<div class="migVsphereMappings form">
<?php echo $this->Form->create('MigVsphereMapping'); ?>
	<fieldset>
		<legend><?php echo __('Add Mig Vsphere Mapping'); ?></legend>
	<?php
		echo $this->Form->input('mig_team_id');
		echo $this->Form->input('vcenter_hostname');
		echo $this->Form->input('cluster_name');
	?>
	</fieldset>
<?php echo $this->Form->end(__('Submit')); ?>
</div>
<div class="actions">
	<h3><?php echo __('Actions'); ?></h3>
	<ul>

		<li><?php echo $this->Html->link(__('List Mig Vsphere Mappings'), array('action' => 'index')); ?></li>
		<li><?php echo $this->Html->link(__('List Mig Teams'), array('controller' => 'mig_teams', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Team'), array('controller' => 'mig_teams', 'action' => 'add')); ?> </li>
	</ul>
</div>
