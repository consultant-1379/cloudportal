<div class="migVcloudMappings form">
<?php echo $this->Form->create('MigVcloudMapping'); ?>
	<fieldset>
		<legend><?php echo __('Add Mig Vcloud Mapping'); ?></legend>
	<?php
		echo $this->Form->input('mig_team_id');
		echo $this->Form->input('spp_hostname');
		echo $this->Form->input('orgvdc_name');
	?>
	</fieldset>
<?php echo $this->Form->end(__('Submit')); ?>
</div>
<div class="actions">
	<h3><?php echo __('Actions'); ?></h3>
	<ul>

		<li><?php echo $this->Html->link(__('List Mig Vcloud Mappings'), array('action' => 'index')); ?></li>
		<li><?php echo $this->Html->link(__('List Mig Teams'), array('controller' => 'mig_teams', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Team'), array('controller' => 'mig_teams', 'action' => 'add')); ?> </li>
	</ul>
</div>
