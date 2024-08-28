<div class="migRas form">
<?php echo $this->Form->create('MigRa'); ?>
	<fieldset>
		<legend><?php echo __('Edit Mig Ra'); ?></legend>
	<?php
		echo $this->Form->input('id');
		echo $this->Form->input('name');
	?>
	</fieldset>
<?php echo $this->Form->end(__('Submit')); ?>
</div>
<div class="actions">
	<h3><?php echo __('Actions'); ?></h3>
	<ul>

		<li><?php echo $this->Form->postLink(__('Delete'), array('action' => 'delete', $this->Form->value('MigRa.id')), null, __('Are you sure you want to delete # %s?', $this->Form->value('MigRa.id'))); ?></li>
		<li><?php echo $this->Html->link(__('List Mig Ras'), array('action' => 'index')); ?></li>
		<li><?php echo $this->Html->link(__('List Mig Teams'), array('controller' => 'mig_teams', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Team'), array('controller' => 'mig_teams', 'action' => 'add')); ?> </li>
	</ul>
</div>
