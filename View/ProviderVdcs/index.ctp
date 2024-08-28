<?php
// Datatable initialization
echo $this->Html->script('ProviderVdcs/datatable');

echo $this->Html->script('ProviderVdcs/providervdcs_rightclick');

// actions button initialization
echo $this->Html->script('Common/button_context');

?>

<h1>Provider Vdcs</h1>
<hr />
<table id="pvdcs_table" class="datatable">
    <thead>
        <tr>
            <th>Name</th>
            <th>Quota System</th>
            <th>Available CPUs</th>
            <th>CPU Multiplier</th>
            <th>Resulting CPUs Available</th>
            <th>Available Memory (GB)</th>
            <th>Memory Multiplier</th>
            <th>Resulting Memory Available (GB)</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php

        foreach ($db_pvdcs as $db_pvdc) {                //Loop through all orgvdcs in the databass
                if ($db_pvdc['ProviderVdc']['new_quota_system'])
                {
                    $quota_system = 'Running CPUs / Memory';
                } else {
                    $quota_system = 'Running vApps';
                }
            ?>

            <tr class="context-menu-one box menu-1" id="<?php echo $db_pvdc['ProviderVdc']['vcd_id']; ?>">
                <td><?php echo $db_pvdc['ProviderVdc']['name']; ?></td>
                <td><?php echo $quota_system; ?></td>
                <td><?php echo $db_pvdc['ProviderVdc']['available_cpus']; ?></td>
                <td><?php echo $db_pvdc['ProviderVdc']['cpu_multiplier']; ?></td>
                <td><?php echo floor($db_pvdc['ProviderVdc']['available_cpus'] * $db_pvdc['ProviderVdc']['cpu_multiplier']); ?></td>
                <td><?php echo $db_pvdc['ProviderVdc']['available_memory']; ?></td>
                <td><?php echo $db_pvdc['ProviderVdc']['memory_multiplier']; ?></td>
                <td><?php echo floor($db_pvdc['ProviderVdc']['available_memory'] * $db_pvdc['ProviderVdc']['memory_multiplier']); ?></td>
                <td><button class="actions" name=<?php echo $db_pvdc['ProviderVdc']['vcd_id']; ?>>Actions</button></td>
            </tr>
            <?php
        }
        ?>
    </tbody>
</table>
