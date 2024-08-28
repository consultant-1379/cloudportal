<div class="migRas view">
<h2><?php  echo __('Mig Ra'); ?></h2>
	<dl>
		<dt><?php echo __('Id'); ?></dt>
		<dd>
			<?php echo h($migRa['MigRa']['id']); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Name'); ?></dt>
		<dd>
			<?php echo h($migRa['MigRa']['name']); ?>
			&nbsp;
		</dd>
	</dl>
</div>
<div class="actions">
	<h3><?php echo __('Actions'); ?></h3>
	<ul>
		<li><?php echo $this->Html->link(__('Edit Mig Ra'), array('action' => 'edit', $migRa['MigRa']['id'])); ?> </li>
		<li><?php echo $this->Form->postLink(__('Delete Mig Ra'), array('action' => 'delete', $migRa['MigRa']['id']), null, __('Are you sure you want to delete # %s?', $migRa['MigRa']['id'])); ?> </li>
		<li><?php echo $this->Html->link(__('List Mig Ras'), array('action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Ra'), array('action' => 'add')); ?> </li>
		<li><?php echo $this->Html->link(__('List Mig Teams'), array('controller' => 'mig_teams', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Team'), array('controller' => 'mig_teams', 'action' => 'add')); ?> </li>
	</ul>
</div>
<div class="related">
	<h3><?php echo __('Related Mig Teams'); ?></h3>
	<?php if (!empty($migRa['MigTeam'])): ?>
	<table cellpadding = "0" cellspacing = "0">
	<tr>
		<th><?php echo __('Id'); ?></th>
		<th><?php echo __('Name'); ?></th>
		<th><?php echo __('Mig Ra Id'); ?></th>
		<th class="actions"><?php echo __('Actions'); ?></th>
	</tr>
	<?php
		$i = 0;
		foreach ($migRa['MigTeam'] as $migTeam): ?>
		<tr>
			<td><?php echo $migTeam['id']; ?></td>
			<td><?php echo $migTeam['name']; ?></td>
			<td><?php echo $migTeam['mig_ra_id']; ?></td>
			<td class="actions">
				<?php echo $this->Html->link(__('View'), array('controller' => 'mig_teams', 'action' => 'view', $migTeam['id'])); ?>
				<?php echo $this->Html->link(__('Edit'), array('controller' => 'mig_teams', 'action' => 'edit', $migTeam['id'])); ?>
				<?php echo $this->Form->postLink(__('Delete'), array('controller' => 'mig_teams', 'action' => 'delete', $migTeam['id']), null, __('Are you sure you want to delete # %s?', $migTeam['id'])); ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</table>
<?php endif; ?>

	<div class="actions">
		<ul>
			<li><?php echo $this->Html->link(__('New Mig Team'), array('controller' => 'mig_teams', 'action' => 'add')); ?> </li>
		</ul>
	</div>
</div>
