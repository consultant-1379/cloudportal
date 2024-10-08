<div class="migTeams index">
	<h2><?php echo __('Mig Teams'); ?></h2>
	<table cellpadding="0" cellspacing="0">
	<tr>
			<th><?php echo $this->Paginator->sort('id'); ?></th>
			<th><?php echo $this->Paginator->sort('name'); ?></th>
			<th><?php echo $this->Paginator->sort('mig_ra_id'); ?></th>
			<th class="actions"><?php echo __('Actions'); ?></th>
	</tr>
	<?php foreach ($migTeams as $migTeam): ?>
	<tr>
		<td><?php echo h($migTeam['MigTeam']['id']); ?>&nbsp;</td>
		<td><?php echo h($migTeam['MigTeam']['name']); ?>&nbsp;</td>
		<td>
			<?php echo $this->Html->link($migTeam['MigRa']['name'], array('controller' => 'mig_ras', 'action' => 'view', $migTeam['MigRa']['id'])); ?>
		</td>
		<td class="actions">
			<?php echo $this->Html->link(__('View'), array('action' => 'view', $migTeam['MigTeam']['id'])); ?>
			<?php echo $this->Html->link(__('Edit'), array('action' => 'edit', $migTeam['MigTeam']['id'])); ?>
			<?php echo $this->Form->postLink(__('Delete'), array('action' => 'delete', $migTeam['MigTeam']['id']), null, __('Are you sure you want to delete # %s?', $migTeam['MigTeam']['id'])); ?>
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
		<li><?php echo $this->Html->link(__('New Mig Team'), array('action' => 'add')); ?></li>
		<li><?php echo $this->Html->link(__('List Mig Ras'), array('controller' => 'mig_ras', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Ra'), array('controller' => 'mig_ras', 'action' => 'add')); ?> </li>
		<li><?php echo $this->Html->link(__('List Mig Nightly Counts'), array('controller' => 'mig_nightly_counts', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Nightly Count'), array('controller' => 'mig_nightly_counts', 'action' => 'add')); ?> </li>
		<li><?php echo $this->Html->link(__('List Mig Vcloud Mappings'), array('controller' => 'mig_vcloud_mappings', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Vcloud Mapping'), array('controller' => 'mig_vcloud_mappings', 'action' => 'add')); ?> </li>
		<li><?php echo $this->Html->link(__('List Mig Vsphere Mappings'), array('controller' => 'mig_vsphere_mappings', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Vsphere Mapping'), array('controller' => 'mig_vsphere_mappings', 'action' => 'add')); ?> </li>
	</ul>
</div>
