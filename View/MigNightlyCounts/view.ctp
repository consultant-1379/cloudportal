<div class="migNightlyCounts view">
<h2><?php  echo __('Mig Nightly Count'); ?></h2>
	<dl>
		<dt><?php echo __('Id'); ?></dt>
		<dd>
			<?php echo h($migNightlyCount['MigNightlyCount']['id']); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Mig Team'); ?></dt>
		<dd>
			<?php echo $this->Html->link($migNightlyCount['MigTeam']['name'], array('controller' => 'mig_teams', 'action' => 'view', $migNightlyCount['MigTeam']['id'])); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Date'); ?></dt>
		<dd>
			<?php echo h($migNightlyCount['MigNightlyCount']['date']); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Mig Count Type'); ?></dt>
		<dd>
			<?php echo $this->Html->link($migNightlyCount['MigCountType']['name'], array('controller' => 'mig_count_types', 'action' => 'view', $migNightlyCount['MigCountType']['id'])); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Count'); ?></dt>
		<dd>
			<?php echo h($migNightlyCount['MigNightlyCount']['count']); ?>
			&nbsp;
		</dd>
	</dl>
</div>
<div class="actions">
	<h3><?php echo __('Actions'); ?></h3>
	<ul>
		<li><?php echo $this->Html->link(__('Edit Mig Nightly Count'), array('action' => 'edit', $migNightlyCount['MigNightlyCount']['id'])); ?> </li>
		<li><?php echo $this->Form->postLink(__('Delete Mig Nightly Count'), array('action' => 'delete', $migNightlyCount['MigNightlyCount']['id']), null, __('Are you sure you want to delete # %s?', $migNightlyCount['MigNightlyCount']['id'])); ?> </li>
		<li><?php echo $this->Html->link(__('List Mig Nightly Counts'), array('action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Nightly Count'), array('action' => 'add')); ?> </li>
		<li><?php echo $this->Html->link(__('List Mig Teams'), array('controller' => 'mig_teams', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Team'), array('controller' => 'mig_teams', 'action' => 'add')); ?> </li>
		<li><?php echo $this->Html->link(__('List Mig Count Types'), array('controller' => 'mig_count_types', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Count Type'), array('controller' => 'mig_count_types', 'action' => 'add')); ?> </li>
	</ul>
</div>
