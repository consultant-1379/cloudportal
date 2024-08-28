<div class="taskTypes form">
<?php echo $this->Form->create('TaskType'); ?>
	<fieldset>
		<legend><?php echo __('Edit Task Type'); ?></legend>
	<?php
		echo $this->Form->input('id');
		echo $this->Form->input('name');
		echo $this->Form->input('description');
		echo $this->Form->input('resource_points');
	?>
	</fieldset>
<?php echo $this->Form->end(__('Submit')); ?>
</div>
<div class="actions">
	<h3><?php echo __('Actions'); ?></h3>
	<ul>

		<li><?php echo $this->Form->postLink(__('Delete'), array('action' => 'delete', $this->Form->value('TaskType.id')), null, __('Are you sure you want to delete # %s?', $this->Form->value('TaskType.id'))); ?></li>
		<li><?php echo $this->Html->link(__('List Task Types'), array('action' => 'index')); ?></li>
	</ul>
</div>
