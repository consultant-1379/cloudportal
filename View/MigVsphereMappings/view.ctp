<div class="migVsphereMappings view">
<h2><?php  echo __('Mig Vsphere Mapping'); ?></h2>
	<dl>
		<dt><?php echo __('Id'); ?></dt>
		<dd>
			<?php echo h($migVsphereMapping['MigVsphereMapping']['id']); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Mig Team'); ?></dt>
		<dd>
			<?php echo $this->Html->link($migVsphereMapping['MigTeam']['name'], array('controller' => 'mig_teams', 'action' => 'view', $migVsphereMapping['MigTeam']['id'])); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Vcenter Hostname'); ?></dt>
		<dd>
			<?php echo h($migVsphereMapping['MigVsphereMapping']['vcenter_hostname']); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Cluster Name'); ?></dt>
		<dd>
			<?php echo h($migVsphereMapping['MigVsphereMapping']['cluster_name']); ?>
			&nbsp;
		</dd>
	</dl>
</div>
<div class="actions">
	<h3><?php echo __('Actions'); ?></h3>
	<ul>
		<li><?php echo $this->Html->link(__('Edit Mig Vsphere Mapping'), array('action' => 'edit', $migVsphereMapping['MigVsphereMapping']['id'])); ?> </li>
		<li><?php echo $this->Form->postLink(__('Delete Mig Vsphere Mapping'), array('action' => 'delete', $migVsphereMapping['MigVsphereMapping']['id']), null, __('Are you sure you want to delete # %s?', $migVsphereMapping['MigVsphereMapping']['id'])); ?> </li>
		<li><?php echo $this->Html->link(__('List Mig Vsphere Mappings'), array('action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Vsphere Mapping'), array('action' => 'add')); ?> </li>
		<li><?php echo $this->Html->link(__('List Mig Teams'), array('controller' => 'mig_teams', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Team'), array('controller' => 'mig_teams', 'action' => 'add')); ?> </li>
	</ul>
</div>
