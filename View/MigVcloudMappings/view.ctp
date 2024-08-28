<div class="migVcloudMappings view">
<h2><?php  echo __('Mig Vcloud Mapping'); ?></h2>
	<dl>
		<dt><?php echo __('Id'); ?></dt>
		<dd>
			<?php echo h($migVcloudMapping['MigVcloudMapping']['id']); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Mig Team'); ?></dt>
		<dd>
			<?php echo $this->Html->link($migVcloudMapping['MigTeam']['name'], array('controller' => 'mig_teams', 'action' => 'view', $migVcloudMapping['MigTeam']['id'])); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Spp Hostname'); ?></dt>
		<dd>
			<?php echo h($migVcloudMapping['MigVcloudMapping']['spp_hostname']); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Orgvdc Name'); ?></dt>
		<dd>
			<?php echo h($migVcloudMapping['MigVcloudMapping']['orgvdc_name']); ?>
			&nbsp;
		</dd>
	</dl>
</div>
<div class="actions">
	<h3><?php echo __('Actions'); ?></h3>
	<ul>
		<li><?php echo $this->Html->link(__('Edit Mig Vcloud Mapping'), array('action' => 'edit', $migVcloudMapping['MigVcloudMapping']['id'])); ?> </li>
		<li><?php echo $this->Form->postLink(__('Delete Mig Vcloud Mapping'), array('action' => 'delete', $migVcloudMapping['MigVcloudMapping']['id']), null, __('Are you sure you want to delete # %s?', $migVcloudMapping['MigVcloudMapping']['id'])); ?> </li>
		<li><?php echo $this->Html->link(__('List Mig Vcloud Mappings'), array('action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Vcloud Mapping'), array('action' => 'add')); ?> </li>
		<li><?php echo $this->Html->link(__('List Mig Teams'), array('controller' => 'mig_teams', 'action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Mig Team'), array('controller' => 'mig_teams', 'action' => 'add')); ?> </li>
	</ul>
</div>
