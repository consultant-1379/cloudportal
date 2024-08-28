<?php
// Datatable initialization
echo $this->Html->script('OrgVdcs/datatable');

echo $this->Html->script('OrgVdcs/orgvdcs_rightclick');

// actions button initialization
echo $this->Html->script('Common/button_context');

// Double click stuff
echo $this->Html->script('OrgVdcs/orgvdc_dclick.js');
?>

<?php
if ($current_user["is_admin"]) {
    echo "<div id='is_admin' style='display:none;'></div>";
}
?>
<h1>Clouds</h1>
<hr />
<table id="orgvdcs_table" class="datatable">
    <thead>
        <tr>
            <th>Name</th>
            <?php
                if ($current_user['is_admin'])
                {
            ?>
                    <th>Provider Quota System</th>
                    <th>Provider</th>
                    <th>Provider Memory(GB)/CPU Ratio</th>
                    <th>OrgVdc Memory(GB)/CPU Ratio</th>
            <?php
                }
            ?>
            <th>Running CPUs Quota</th>
            <th>Running Memory Quota (GB)</th>
            <th>Running vApp Quota</th>
            <th>Total vApp Quota</th>
            <th>RA</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php

        foreach ($db_orgvdcs as $db_orgvdc) {                //Loop through all orgvdcs in the databass
            $running_tb_limit = $db_orgvdc['OrgVdc']['running_tb_limit'];
            $stored_vm_limit = $db_orgvdc['OrgVdc']['stored_tb_limit'];
                $providervdc_cpu_limit = $db_orgvdc['ProviderVdc']['available_cpus'] * $db_orgvdc['ProviderVdc']['cpu_multiplier'];
                $providervdc_memory_limit = $db_orgvdc['ProviderVdc']['available_memory'] * $db_orgvdc['ProviderVdc']['memory_multiplier'];

                if ($db_orgvdc['ProviderVdc']['new_quota_system'])
                {
                    $quota_system = 'Running CPUs / Memory';
                    $running_vapps_limit = "NA";
                    $orgvdc_cpu_limit = $db_orgvdc['OrgVdc']['cpu_limit'];
                    $orgvdc_memory_limit = $db_orgvdc['OrgVdc']['memory_limit'];
                } else {
                    $quota_system = 'Running vApps';
                    $running_vapps_limit = $db_orgvdc['OrgVdc']['running_tb_limit'];
                    $orgvdc_cpu_limit = 'NA';
                    $orgvdc_memory_limit = 'NA';
                }
                if ($providervdc_cpu_limit == 0)
                {
                    $providervdc_ratio_string = "1 / 1";
                }
                else
                {
                    $providervdc_ratio = round($providervdc_memory_limit / $providervdc_cpu_limit, 2);
                    $providervdc_ratio_string = $providervdc_ratio . " / 1 ";
                }
                if ($orgvdc_cpu_limit == 0)
                {
                    $orgvdc_ratio_string = "1 / 1";
                }
                else
                {
                    $orgvdc_ratio = round($orgvdc_memory_limit / $orgvdc_cpu_limit, 2);
                    $orgvdc_ratio_string = $orgvdc_ratio . " / 1";
                }
            ?>

            <tr class="context-menu-one box menu-1 doubleclick" id="<?php echo $db_orgvdc['OrgVdc']['vcd_id']; ?>">
                <td><?php echo $this->Html->link($db_orgvdc['OrgVdc']['name'], array('controller' => 'Vapps', 'action' => 'index', "orgvdc_id" => $db_orgvdc['OrgVdc']['vcd_id'])); ?></td>
                <?php
                    if ($current_user['is_admin'])
                    {
                ?>
                        <td><?php echo $quota_system; ?></td>
                        <td><?php echo $db_orgvdc['ProviderVdc']['name']; ?></td>
                        <td><?php echo $providervdc_ratio_string; ?></td>
                        <td><?php echo $orgvdc_ratio_string; ?></td>
                <?php
                    }
                ?>
                <td><?php echo $orgvdc_cpu_limit; ?></td>
                <td><?php echo $orgvdc_memory_limit; ?></td>
                <td><?php echo $running_vapps_limit; ?></td>
                <td><?php echo $db_orgvdc['OrgVdc']['stored_tb_limit']; ?></td>
                <td><?php echo $db_orgvdc['MigRa']['name'];?></td>
                <td><button class="actions" name=<?php echo $db_orgvdc['OrgVdc']['vcd_id']; ?>>Actions</button></td>
            </tr>
            <?php
        }
        ?>

    </tbody>
</table>
