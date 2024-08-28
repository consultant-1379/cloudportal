<div class="migCountTypes form">
<?php echo $this->Form->create('MigCountType'); ?>
	<fieldset>
		<legend><?php echo __('Add Mig Count Type'); ?></legend>
	<?php
		echo $this->Form->input('name');
		echo $this->Form->input('graphable_name');
	?>
	</fieldset>
<?php echo $this->Form->end(__('Submit')); ?>
</div>
<div class="actions">
	<h3><?php echo __('Actions'); ?></h3>
	<ul>

		<li><?php echo $this->Html->link(__('List Mig Count Types'), array('action' => 'index')); ?></li>
		<li><?php echo $this->Html->link(__('List Mig Nightly Counts'), array('controller' => 'mig_nightly_counts', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Nightly Count'), array('controller' => 'mig_nightly_counts', 'action' => 'add')); ?> </li>
	</ul>
</div>
