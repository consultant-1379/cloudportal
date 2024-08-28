<div class="migNightlyCounts index">
	<h2><?php echo __('Mig Nightly Counts'); ?></h2>
	<table cellpadding="0" cellspacing="0">
	<tr>
			<th><?php echo $this->Paginator->sort('id'); ?></th>
			<th><?php echo $this->Paginator->sort('mig_team_id'); ?></th>
			<th><?php echo $this->Paginator->sort('date'); ?></th>
			<th><?php echo $this->Paginator->sort('mig_count_type_id'); ?></th>
			<th><?php echo $this->Paginator->sort('count'); ?></th>
			<th class="actions"><?php echo __('Actions'); ?></th>
	</tr>
	<?php foreach ($migNightlyCounts as $migNightlyCount): ?>
	<tr>
		<td><?php echo h($migNightlyCount['MigNightlyCount']['id']); ?>&nbsp;</td>
		<td>
			<?php echo $this->Html->link($migNightlyCount['MigTeam']['name'], array('controller' => 'mig_teams', 'action' => 'view', $migNightlyCount['MigTeam']['id'])); ?>
		</td>
		<td><?php echo h($migNightlyCount['MigNightlyCount']['date']); ?>&nbsp;</td>
		<td>
			<?php echo $this->Html->link($migNightlyCount['MigCountType']['name'], array('controller' => 'mig_count_types', 'action' => 'view', $migNightlyCount['MigCountType']['id'])); ?>
		</td>
		<td><?php echo h($migNightlyCount['MigNightlyCount']['count']); ?>&nbsp;</td>
		<td class="actions">
			<?php echo $this->Html->link(__('View'), array('action' => 'view', $migNightlyCount['MigNightlyCount']['id'])); ?>
			<?php echo $this->Html->link(__('Edit'), array('action' => 'edit', $migNightlyCount['MigNightlyCount']['id'])); ?>
			<?php echo $this->Form->postLink(__('Delete'), array('action' => 'delete', $migNightlyCount['MigNightlyCount']['id']), null, __('Are you sure you want to delete # %s?', $migNightlyCount['MigNightlyCount']['id'])); ?>
		</td>
	</tr>
<?php endforeach; ?>
	</table>
	<p>
	<?php
	echo $this->Paginator->counter(array(
	'format' => __('Page {:page} of {:pages}, showing {:current} records out of {:count} total, starting on record {:start}, ending on {:end}')
	));
	?>	</p>
	<div class="paging">
	<?php
		echo $this->Paginator->prev('< ' . __('previous'), array(), null, array('class' => 'prev disabled'));
		echo $this->Paginator->numbers(array('separator' => ''));
		echo $this->Paginator->next(__('next') . ' >', array(), null, array('class' => 'next disabled'));
	?>
	</div>
</div>
<div class="actions">
	<h3><?php echo __('Actions'); ?></h3>
	<ul>
		<li><?php echo $this->Html->link(__('New Mig Nightly Count'), array('action' => 'add')); ?></li>
		<li><?php echo $this->Html->link(__('List Mig Teams'), array('controller' => 'mig_teams', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Team'), array('controller' => 'mig_teams', 'action' => 'add')); ?> </li>
		<li><?php echo $this->Html->link(__('List Mig Count Types'), array('controller' => 'mig_count_types', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Count Type'), array('controller' => 'mig_count_types', 'action' => 'add')); ?> </li>
	</ul>
</div>
