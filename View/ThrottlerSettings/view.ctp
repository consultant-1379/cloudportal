<div class="throttlerSettings view">
<h2><?php  echo __('Throttler Setting'); ?></h2>
	<dl>
		<dt><?php echo __('Id'); ?></dt>
		<dd>
			<?php echo h($throttlerSetting['ThrottlerSetting']['id']); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Name'); ?></dt>
		<dd>
			<?php echo h($throttlerSetting['ThrottlerSetting']['name']); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Value'); ?></dt>
		<dd>
			<?php echo h($throttlerSetting['ThrottlerSetting']['value']); ?>
			&nbsp;
		</dd>
		<dt><?php echo __('Modified'); ?></dt>
		<dd>
			<?php echo h($throttlerSetting['ThrottlerSetting']['modified']); ?>
			&nbsp;
		</dd>
	</dl>
</div>
<div class="actions">
	<h3><?php echo __('Actions'); ?></h3>
	<ul>
		<li><?php echo $this->Html->link(__('Edit Throttler Setting'), array('action' => 'edit', $throttlerSetting['ThrottlerSetting']['id'])); ?> </li>
		<li><?php echo $this->Form->postLink(__('Delete Throttler Setting'), array('action' => 'delete', $throttlerSetting['ThrottlerSetting']['id']), null, __('Are you sure you want to delete # %s?', $throttlerSetting['ThrottlerSetting']['id'])); ?> </li>
		<li><?php echo $this->Html->link(__('List Throttler Settings'), array('action' => 'index')); ?> </li>
		<li><?php echo $this->Html->link(__('New Throttler Setting'), array('action' => 'add')); ?> </li>
	</ul>
</div>
