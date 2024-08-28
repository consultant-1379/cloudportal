<div class="migTeams view">
<h2><?php  echo __('Mig Team'); ?></h2>
	<dl>
		<dt><?php echo __('Id'); ?></dt>
		<dd>
			<?php echo h($migTeam['MigTeam']['id']); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Name'); ?></dt>
		<dd>
			<?php echo h($migTeam['MigTeam']['name']); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Mig Ra'); ?></dt>
		<dd>
			<?php echo $this->Html->link($migTeam['MigRa']['name'], array('controller' => 'mig_ras', 'action' => 'view', $migTeam['MigRa']['id'])); ?>
			&nbsp;
		</dd>
	</dl>
</div>
<div class="actions">
	<h3><?php echo __('Actions'); ?></h3>
	<ul>
		<li><?php echo $this->Html->link(__('Edit Mig Team'), array('action' => 'edit', $migTeam['MigTeam']['id'])); ?> </li>
		<li><?php echo $this->Form->postLink(__('Delete Mig Team'), array('action' => 'delete', $migTeam['MigTeam']['id']), null, __('Are you sure you want to delete # %s?', $migTeam['MigTeam']['id'])); ?> </li>
		<li><?php echo $this->Html->link(__('List Mig Teams'), array('action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Team'), array('action' => 'add')); ?> </li>
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
<div class="related">
	<h3><?php echo __('Related Mig Nightly Counts'); ?></h3>
	<?php if (!empty($migTeam['MigNightlyCount'])): ?>
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
		foreach ($migTeam['MigNightlyCount'] as $migNightlyCount): ?>
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
<div class="related">
	<h3><?php echo __('Related Mig Vcloud Mappings'); ?></h3>
	<?php if (!empty($migTeam['MigVcloudMapping'])): ?>
	<table cellpadding = "0" cellspacing = "0">
	<tr>
		<th><?php echo __('Id'); ?></th>
		<th><?php echo __('Mig Team Id'); ?></th>
		<th><?php echo __('Spp Hostname'); ?></th>
		<th><?php echo __('Orgvdc Name'); ?></th>
		<th class="actions"><?php echo __('Actions'); ?></th>
	</tr>
	<?php
		$i = 0;
		foreach ($migTeam['MigVcloudMapping'] as $migVcloudMapping): ?>
		<tr>
			<td><?php echo $migVcloudMapping['id']; ?></td>
			<td><?php echo $migVcloudMapping['mig_team_id']; ?></td>
			<td><?php echo $migVcloudMapping['spp_hostname']; ?></td>
			<td><?php echo $migVcloudMapping['orgvdc_name']; ?></td>
			<td class="actions">
				<?php echo $this->Html->link(__('View'), array('controller' => 'mig_vcloud_mappings', 'action' => 'view', $migVcloudMapping['id'])); ?>
				<?php echo $this->Html->link(__('Edit'), array('controller' => 'mig_vcloud_mappings', 'action' => 'edit', $migVcloudMapping['id'])); ?>
				<?php echo $this->Form->postLink(__('Delete'), array('controller' => 'mig_vcloud_mappings', 'action' => 'delete', $migVcloudMapping['id']), null, __('Are you sure you want to delete # %s?', $migVcloudMapping['id'])); ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</table>
<?php endif; ?>

	<div class="actions">
		<ul>
			<li><?php echo $this->Html->link(__('New Mig Vcloud Mapping'), array('controller' => 'mig_vcloud_mappings', 'action' => 'add')); ?> </li>
		</ul>
	</div>
</div>
<div class="related">
	<h3><?php echo __('Related Mig Vsphere Mappings'); ?></h3>
	<?php if (!empty($migTeam['MigVsphereMapping'])): ?>
	<table cellpadding = "0" cellspacing = "0">
	<tr>
		<th><?php echo __('Id'); ?></th>
		<th><?php echo __('Mig Team Id'); ?></th>
		<th><?php echo __('Vcenter Hostname'); ?></th>
		<th><?php echo __('Cluster Name'); ?></th>
		<th class="actions"><?php echo __('Actions'); ?></th>
	</tr>
	<?php
		$i = 0;
		foreach ($migTeam['MigVsphereMapping'] as $migVsphereMapping): ?>
		<tr>
			<td><?php echo $migVsphereMapping['id']; ?></td>
			<td><?php echo $migVsphereMapping['mig_team_id']; ?></td>
			<td><?php echo $migVsphereMapping['vcenter_hostname']; ?></td>
			<td><?php echo $migVsphereMapping['cluster_name']; ?></td>
			<td class="actions">
				<?php echo $this->Html->link(__('View'), array('controller' => 'mig_vsphere_mappings', 'action' => 'view', $migVsphereMapping['id'])); ?>
				<?php echo $this->Html->link(__('Edit'), array('controller' => 'mig_vsphere_mappings', 'action' => 'edit', $migVsphereMapping['id'])); ?>
				<?php echo $this->Form->postLink(__('Delete'), array('controller' => 'mig_vsphere_mappings', 'action' => 'delete', $migVsphereMapping['id']), null, __('Are you sure you want to delete # %s?', $migVsphereMapping['id'])); ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</table>
<?php endif; ?>

	<div class="actions">
		<ul>
			<li><?php echo $this->Html->link(__('New Mig Vsphere Mapping'), array('controller' => 'mig_vsphere_mappings', 'action' => 'add')); ?> </li>
		</ul>
	</div>
</div>
