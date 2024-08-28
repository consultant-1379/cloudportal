<div class="migVsphereMappings index">
	<h2><?php echo __('Mig Vsphere Mappings'); ?></h2>
	<table cellpadding="0" cellspacing="0">
	<tr>
			<th><?php echo $this->Paginator->sort('id'); ?></th>
			<th><?php echo $this->Paginator->sort('mig_team_id'); ?></th>
			<th><?php echo $this->Paginator->sort('vcenter_hostname'); ?></th>
			<th><?php echo $this->Paginator->sort('cluster_name'); ?></th>
			<th class="actions"><?php echo __('Actions'); ?></th>
	</tr>
	<?php foreach ($migVsphereMappings as $migVsphereMapping): ?>
	<tr>
		<td><?php echo h($migVsphereMapping['MigVsphereMapping']['id']); ?>&nbsp;</td>
		<td>
			<?php echo $this->Html->link($migVsphereMapping['MigTeam']['name'], array('controller' => 'mig_teams', 'action' => 'view', $migVsphereMapping['MigTeam']['id'])); ?>
		</td>
		<td><?php echo h($migVsphereMapping['MigVsphereMapping']['vcenter_hostname']); ?>&nbsp;</td>
		<td><?php echo h($migVsphereMapping['MigVsphereMapping']['cluster_name']); ?>&nbsp;</td>
		<td class="actions">
			<?php echo $this->Html->link(__('View'), array('action' => 'view', $migVsphereMapping['MigVsphereMapping']['id'])); ?>
			<?php echo $this->Html->link(__('Edit'), array('action' => 'edit', $migVsphereMapping['MigVsphereMapping']['id'])); ?>
			<?php echo $this->Form->postLink(__('Delete'), array('action' => 'delete', $migVsphereMapping['MigVsphereMapping']['id']), null, __('Are you sure you want to delete # %s?', $migVsphereMapping['MigVsphereMapping']['id'])); ?>
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
		<li><?php echo $this->Html->link(__('New Mig Vsphere Mapping'), array('action' => 'add')); ?></li>
		<li><?php echo $this->Html->link(__('List Mig Teams'), array('controller' => 'mig_teams', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Team'), array('controller' => 'mig_teams', 'action' => 'add')); ?> </li>
	</ul>
</div>
