<?php
// Datatable initialization
echo $this->Html->script('Vms/vapptemplatevms_datatable');
echo $this->Html->script('Vms/vapptemplate_general');
?>
<h1><?php echo $vapptemplate_name; ?></h1>
<hr />
<div id="tabs">
  <ul>
    <li><a href="#tabs-1">VMs</a></li>
    <li><?php echo $this->Html->link("vApp Diagram", array('controller' => 'Vms', 'action' => 'vapptemplate_network','vapp_template_id' => $this->passedArgs['vapp_template_id']),array('id' => 'vapp_diagram_link')); ?></li>
  </ul>
<div id="tabs-1">
<table id="vapptemplatevms_table" class="datatable">
    <thead>
        <tr>
            <th>Name</th>
            <th>Status</th>
            <th>CPUs</th>
            <th>Memory (MB)</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($vms as $vm): ?>
            <tr>
                <td><?php echo $vm['name']; ?></td>
                <td><?php echo $vm['status'] ?></td>
                <td><?php echo $vm['cpu_count']; ?></td>
                <td><?php echo $vm['memory_mb']; ?></td>
            </tr>
            <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>
