<div class="migCountTypes view">
<h2><?php  echo __('Mig Count Type'); ?></h2>
	<dl>
		<dt><?php echo __('Id'); ?></dt>
		<dd>
			<?php echo h($migCountType['MigCountType']['id']); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Name'); ?></dt>
		<dd>
			<?php echo h($migCountType['MigCountType']['name']); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Graphable Name'); ?></dt>
		<dd>
			<?php echo h($migCountType['MigCountType']['graphable_name']); ?>
			&nbsp;
		</dd>
	</dl>
</div>
<div class="actions">
	<h3><?php echo __('Actions'); ?></h3>
	<ul>
		<li><?php echo $this->Html->link(__('Edit Mig Count Type'), array('action' => 'edit', $migCountType['MigCountType']['id'])); ?> </li>
		<li><?php echo $this->Form->postLink(__('Delete Mig Count Type'), array('action' => 'delete', $migCountType['MigCountType']['id']), null, __('Are you sure you want to delete # %s?', $migCountType['MigCountType']['id'])); ?> </li>
		<li><?php echo $this->Html->link(__('List Mig Count Types'), array('action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Count Type'), array('action' => 'add')); ?> </li>
		<li><?php echo $this->Html->link(__('List Mig Nightly Counts'), array('controller' => 'mig_nightly_counts', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Nightly Count'), array('controller' => 'mig_nightly_counts', 'action' => 'add')); ?> </li>
	</ul>
</div>
<div class="related">
	<h3><?php echo __('Related Mig Nightly Counts'); ?></h3>
	<?php if (!empty($migCountType['MigNightlyCount'])): ?>
	<table cellpadding = "0" cellspacing = "0">
	<tr>
		<th><?php echo __('Id'); ?></th>
		<th><?php echo __('Mig Team Id'); ?></th>
		<th><?php echo __('Date'); ?></th>
		<th><?php echo __('Mig Count Type Id'); ?></th>
		<th><?php echo __('Count'); ?></th>
		<th class="actions"><?php echo __('Actions'); ?></th>
	</tr>
	<?php
		$i = 0;
		foreach ($migCountType['MigNightlyCount'] as $migNightlyCount): ?>
		<tr>
			<td><?php echo $migNightlyCount['id']; ?></td>
			<td><?php echo $migNightlyCount['mig_team_id']; ?></td>
			<td><?php echo $migNightlyCount['date']; ?></td>
			<td><?php echo $migNightlyCount['mig_count_type_id']; ?></td>
			<td><?php echo $migNightlyCount['count']; ?></td>
			<td class="actions">
				<?php echo $this->Html->link(__('View'), array('controller' => 'mig_nightly_counts', 'action' => 'view', $migNightlyCount['id'])); ?>
				<?php echo $this->Html->link(__('Edit'), array('controller' => 'mig_nightly_counts', 'action' => 'edit', $migNightlyCount['id'])); ?>
				<?php echo $this->Form->postLink(__('Delete'), array('controller' => 'mig_nightly_counts', 'action' => 'delete', $migNightlyCount['id']), null, __('Are you sure you want to delete # %s?', $migNightlyCount['id'])); ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</table>
<?php endif; ?>

	<div class="actions">
		<ul>
			<li><?php echo $this->Html->link(__('New Mig Nightly Count'), array('controller' => 'mig_nightly_counts', 'action' => 'add')); ?> </li>
		</ul>
	</div>
</div>
