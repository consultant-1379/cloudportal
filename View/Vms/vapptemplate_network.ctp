<?php
echo $this->Html->css('Vms/vapp_network', null, array('inline' => false));
echo $this->Html->script('Vms/vapp_network');
?>
<h1><?php echo $vapp_name; ?></h1>
<hr />
<div id="tabs">
	<ul>
	<li><?php echo $this->Html->link("VMs", array('controller' => 'Vms', 'action' => 'vapptemplate_index', 'vapp_template_id' => $this->passedArgs['vapp_template_id']),array('id' => 'vms_tab_link')); ?></li>
	<li><a href="#tabs-2">vApp Diagram</a></li>
	</ul>
	<div id="tabs-1">
	</div>
	<div id="tabs-2" style="overflow:auto;">
		<div id="svg_container">
		<?php
			echo $this->element('network_diagram', array(
				'vms' => $vms,
				'vapp_networks_external' => $vapp_networks_external,
				'vapp_networks_internal' => $vapp_networks_internal
			));
		?>
		</div>
	</div>
</div>
