<div class="migNightlyCounts form">
<?php echo $this->Form->create('MigNightlyCount'); ?>
	<fieldset>
		<legend><?php echo __('Edit Mig Nightly Count'); ?></legend>
	<?php
		echo $this->Form->input('id');
		echo $this->Form->input('mig_team_id');
		echo $this->Form->input('date');
		echo $this->Form->input('mig_count_type_id');
		echo $this->Form->input('count');
	?>
	</fieldset>
<?php echo $this->Form->end(__('Submit')); ?>
</div>
<div class="actions">
	<h3><?php echo __('Actions'); ?></h3>
	<ul>

		<li><?php echo $this->Form->postLink(__('Delete'), array('action' => 'delete', $this->Form->value('MigNightlyCount.id')), null, __('Are you sure you want to delete # %s?', $this->Form->value('MigNightlyCount.id'))); ?></li>
		<li><?php echo $this->Html->link(__('List Mig Nightly Counts'), array('action' => 'index')); ?></li>
		<li><?php echo $this->Html->link(__('List Mig Teams'), array('controller' => 'mig_teams', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Team'), array('controller' => 'mig_teams', 'action' => 'add')); ?> </li>
		<li><?php echo $this->Html->link(__('List Mig Count Types'), array('controller' => 'mig_count_types', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Count Type'), array('controller' => 'mig_count_types', 'action' => 'add')); ?> </li>
	</ul>
</div>
