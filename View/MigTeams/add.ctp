<div class="migTeams form">
<?php echo $this->Form->create('MigTeam'); ?>
	<fieldset>
		<legend><?php echo __('Add Mig Team'); ?></legend>
	<?php
		echo $this->Form->input('name');
		echo $this->Form->input('mig_ra_id');
	?>
	</fieldset>
<?php echo $this->Form->end(__('Submit')); ?>
</div>
<div class="actions">
	<h3><?php echo __('Actions'); ?></h3>
	<ul>

		<li><?php echo $this->Html->link(__('List Mig Teams'), array('action' => 'index')); ?></li>
		<li><?php echo $this->Html->link(__('List Mig Ras'), array('controller' => 'mig_ras', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Ra'), array('controller' => 'mig_ras', 'action' => 'add')); ?> </li>
		<li><?php echo $this->Html->link(__('List Mig Nightly Counts'), array('controller' => 'mig_nightly_counts', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Nightly Count'), array('controller' => 'mig_nightly_counts', 'action' => 'add')); ?> </li>
		<li><?php echo $this->Html->link(__('List Mig Vcloud Mappings'), array('controller' => 'mig_vcloud_mappings', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Vcloud Mapping'), array('controller' => 'mig_vcloud_mappings', 'action' => 'add')); ?> </li>
		<li><?php echo $this->Html->link(__('List Mig Vsphere Mappings'), array('controller' => 'mig_vsphere_mappings', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Vsphere Mapping'), array('controller' => 'mig_vsphere_mappings', 'action' => 'add')); ?> </li>
	</ul>
</div>
