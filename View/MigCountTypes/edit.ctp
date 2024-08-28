<div class="migCountTypes form">
<?php echo $this->Form->create('MigCountType'); ?>
	<fieldset>
		<legend><?php echo __('Edit Mig Count Type'); ?></legend>
	<?php
		echo $this->Form->input('id');
		echo $this->Form->input('name');
		echo $this->Form->input('graphable_name');
	?>
	</fieldset>
<?php echo $this->Form->end(__('Submit')); ?>
</div>
<div class="actions">
	<h3><?php echo __('Actions'); ?></h3>
	<ul>

		<li><?php echo $this->Form->postLink(__('Delete'), array('action' => 'delete', $this->Form->value('MigCountType.id')), null, __('Are you sure you want to delete # %s?', $this->Form->value('MigCountType.id'))); ?></li>
		<li><?php echo $this->Html->link(__('List Mig Count Types'), array('action' => 'index')); ?></li>
		<li><?php echo $this->Html->link(__('List Mig Nightly Counts'), array('controller' => 'mig_nightly_counts', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Nightly Count'), array('controller' => 'mig_nightly_counts', 'action' => 'add')); ?> </li>
	</ul>
</div>
