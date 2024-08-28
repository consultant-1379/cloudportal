<?php
// Datatable initialization
echo $this->Html->script('Reports/datatable_datacenter_report');

?>

<h1>Datacenter Report (<?php echo $this->Html->link('Download', array('controller' => 'Reports', 'action' => 'datacenter_report.csv')); ?>)</h1>
<hr />
<table id="table" class="datatable">
    <thead>
        <tr>
            <th>Datacenter Name</th>
            <th>Provider Name</th>
            <th>Running CPUs</th>
            <th>Running CPUs Quota</th>
            <th>Unused Running CPUs Quota</th>
            <th>Running Memory (GB)</th>
            <th>Running Memory Quota (GB)</th>
            <th>Unused Running Memory Quota (GB)</th>
            <th>Running vApps</th>
            <th>Running vApps Quota</th>
            <th>Unused Running vApps Quota</th>
            <th>Total vApps</th>
            <th>Total vApps Quota</th>
            <th>Unused Total vApps Quota</th>
        </tr>
    </thead>
    <tbody>
        <?php
        foreach ($OrgVdcs as $datacenter) {
            ?>
            <tr>
                <td><?php echo $this->Html->link($datacenter['datacenter_name'], array('controller' => 'Reports', 'action' => 'vapp_report', 'datacenter' => $datacenter['datacenter_name'])); ?></td>
                <td><?php echo $datacenter['provider_name']; ?></td>
                <td><?php echo $datacenter['running_cpus']; ?></td>
                <td><?php echo $datacenter['running_cpus_quota']; ?></td>
                <td><?php echo $datacenter['unused_running_cpus_quota']; ?></td>
                <td><?php echo $datacenter['running_memory']; ?></td>
                <td><?php echo $datacenter['running_memory_quota']; ?></td>
                <td><?php echo $datacenter['unused_running_memory_quota']; ?></td>
                <td><?php echo $datacenter['running_vapps']; ?></td>
                <td><?php echo $datacenter['running_vapps_quota']; ?></td>
                <td><?php echo $datacenter['unused_running_vapps_quota']; ?></td>
                <td><?php echo $datacenter['total_vapps']; ?></td>
                <td><?php echo $datacenter['total_vapps_quota']; ?></td>
                <td><?php echo $datacenter['unused_total_vapps_quota']; ?></td>
            </tr>
            <?php } ?>
    </tbody>
</table>
