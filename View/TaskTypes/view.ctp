<div class="taskTypes view">
<h2><?php  echo __('Task Type'); ?></h2>
	<dl>
		<dt><?php echo __('Id'); ?></dt>
		<dd>
			<?php echo h($taskType['TaskType']['id']); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Name'); ?></dt>
		<dd>
			<?php echo h($taskType['TaskType']['name']); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Description'); ?></dt>
		<dd>
			<?php echo h($taskType['TaskType']['description']); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Resource Points'); ?></dt>
		<dd>
			<?php echo h($taskType['TaskType']['resource_points']); ?>
			&nbsp;
		</dd>
	</dl>
</div>
<div class="actions">
	<h3><?php echo __('Actions'); ?></h3>
	<ul>
		<li><?php echo $this->Html->link(__('Edit Task Type'), array('action' => 'edit', $taskType['TaskType']['id'])); ?> </li>
		<li><?php echo $this->Form->postLink(__('Delete Task Type'), array('action' => 'delete', $taskType['TaskType']['id']), null, __('Are you sure you want to delete # %s?', $taskType['TaskType']['id'])); ?> </li>
		<li><?php echo $this->Html->link(__('List Task Types'), array('action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Task Type'), array('action' => 'add')); ?> </li>
	</ul>
</div>
