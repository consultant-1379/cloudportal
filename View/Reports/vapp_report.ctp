<?php
// Datatable initialization
echo $this->Html->script('Reports/datatable_vapp_report');

?>

<h1><?php
    if (isset($datacenter))
    {
        echo $datacenter . " vApp Report (";
        echo $this->Html->link('Download', array('controller' => 'Reports', 'action' => 'vapp_report', 'datacenter' => $datacenter, 'ext' => 'csv'));
    }
    else {
        echo "Overall vApp Report (";
        echo $this->Html->link('Download', array('controller' => 'Reports', 'action' => 'vapp_report', 'ext' => 'csv'));
    }
    echo ")";
?></h1>
<hr />
<table id="table" class="datatable">
    <thead>
        <tr>
            <th>Datacenter Name</th>
            <th>vApp Name</th>
            <th>Status</th>
            <th>Gateway</th>
            <th>Created By</th>
            <th>Creation Date</th>
            <th>Running CPUs</th>
            <th>Running Memory (GB)</th>
            <th>Origin Catalog Name</th>
            <th>Origin Template Name</th>
        </tr>
    </thead>
    <tbody>
        <?php
        foreach ($Vapp as $vapp) {
            ?>
            <tr>
                <td><?php echo $vapp['datacenter_name']; ?></td>
                <td><?php echo $vapp['vapp_name']; ?></td>
                <td><?php echo $vapp['status']; ?></td>
                <td><?php echo $vapp['gateway']; ?></td>
                <td><?php echo $vapp['created_by']; ?></td>
                <td><?php echo date('d/m/Y H:i', strtotime($vapp['creation_date'])); ?></td>
                <td><?php echo $vapp['running_cpus']; ?></td>
                <td><?php echo $vapp['running_memory']; ?></td>
                <td><?php echo $vapp['origin_catalog_name']; ?></td>
                <td><?php echo $vapp['origin_template_name']; ?></td>
            </tr>
            <?php } ?>
    </tbody>
</table>
