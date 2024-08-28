<?php
// General vapp stuff
echo $this->Html->css('Vapps/general', null, array('inline' => false));

// Datatable initialization
echo $this->Html->script('Vapps/datatable');

// Right click /actions stuff
echo $this->Html->script('Vapps/vapps_rightclick');

// actions button initialization
echo $this->Html->script('Common/button_context');

// Double click stuff
echo $this->Html->script('Vapps/vapp_dclick.js');

// common progress bar updater
echo $this->Html->script('Common/progress_bar_updater');

// General
echo $this->Html->script('Vapps/general');

// Spinner
echo $this->Html->script('Common/spin.min');

// used for monitor progress button
echo $this->Html->script('Common/monitor_status.js');
?>

<?php
$orgvdc_id = $this->passedArgs['orgvdc_id'];
if ($current_user["is_admin"] || (isset($current_user['permissions'][$orgvdc_id]['write_permission']) && $current_user['permissions'][$orgvdc_id]['write_permission'] )) {
    echo "<div id='orgvdc_permw' style='display:none;'></div>";
}
?>
<div id="orgvdc_id" style="display:none;"><?php echo $orgvdc_id; ?></div>
<div class="align_div">
    <h1 id="orgvdc_header"><?php echo $orgvdc_name; ?> Cloud</h1>
</div>
<div class="align_div">
    <div class="progress_container">
        <div id="running_cpus_quota" class="progress">
            <div class="progress-bar progress-bar-success" role="progressbar" style="width:0%">
                <span class="show"></span>
            </div>
        </div>
    </div>

    <div class="progress_container">
        <div id="running_memory_quota" class="progress">
            <div class="progress-bar progress-bar-success" role="progressbar" style="width:0%">
                <span class="show"></span>
            </div>
        </div>
    </div>

    <div class="progress_container" style="display:none">
        <div id="running_vapps_quota" class="progress">
            <div class="progress-bar progress-bar-success" role="progressbar" style="width:0%">
                <span class="show"></span>
            </div>
        </div>
    </div>

    <div class="progress_container">
        <div id="total_vapps_quota" class="progress">
            <div class="progress-bar progress-bar-success" role="progressbar" style="width:0%">
                <span class="show"></span>
            </div>
        </div>
    </div>
</div>
<hr>
<table id="vapps_table" class="datatable">
    <thead>
        <tr>
            <th>vApp Name</th>
            <th>Status</th>
            <th>Progress</th>
            <th>Gateway</th>
            <th>Created By</th>
            <th>Sharing</th>
            <th>Date Created</th>
            <th>VMs</th>
            <th>Running CPUs</th>
            <th>Running Memory (GB)</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php

        // Show vapps listed in the queued vapps cache, if they exist

        if ((isset($queued_vapps)) && (sizeof($queued_vapps) > 0))
        {
            foreach ($queued_vapps as $queued_vapp)
            {
                // Safety measure to check if this name is already in the list of vapps

                $already_exists = false;
                if ($vapps != 0) {
                    foreach ($vapps as $vapp) {
                        if ($vapp['name'] == $queued_vapp['name'])
                        {
                            $already_exists = true;
                            break;
                        }
                    }
                }

                // If its not already there show it

                if (!$already_exists)
                {
                    echo "<tr><td>" . $queued_vapp['name'] . "</td><td>QUEUED</td><td>QUEUED</td><td></td><td>" . $queued_vapp['created_by_id'] . "</td><td><select class='share_checkbox' disabled='disabled'><option>Off</option></select></td><td>" . date('d/m/Y H:i', strtotime($queued_vapp['creation_date'])) . "</td><td></td><td></td><td></td><td></td></tr>";
                }
            }
        }

        if ($vapps != 0) {                                    //Checks to see if the /vapps/index has been called without a container ID
            foreach ($vapps as $vapp) {
                if($vapp['busy'] !== false): ?>
                    <tr class="doubleclick" id="<?php echo $vapp['vapp_id']; ?>">
                    <td><?php echo $this->Html->link($vapp['name'], array('controller' => 'Vms', 'action' => 'vapp_index', 'vapp_id' => $vapp['vapp_id'], 'orgvdc_id' => $orgvdc_id)); ?></td>
                <?php else : ?>
                     <tr class="context-menu-one box menu-1 doubleclick" id="<?php echo $vapp['vapp_id']; ?>">
                     <td><?php echo $this->Html->link($vapp['name'], array('controller' => 'Vms', 'action' => 'vapp_index', 'vapp_id' => $vapp['vapp_id'], 'orgvdc_id' => $orgvdc_id)); ?></td>
                <?php endif; ?>
                    <td><?php echo $vapp['status']; ?></td>
                    <td class="vapp_status"><button class="monitor_button">Monitor</button></td>
                    <td><?php echo $vapp['gateway_hostname']; ?></td>
                    <td><?php echo $vapp['owner']; ?></td>
                    <td>
                        <select class="share_checkbox" <?php
        if ($current_user['is_admin'] || (isset($current_user['permissions'][$orgvdc_id]['admin_permission']) && $current_user['permissions'][$orgvdc_id]['admin_permission']) || $current_user['username'] == $vapp['owner']) {
            // Don't disable it
        } else {
            echo "disabled='disabled'";
        }
                ?>>
                            <option value="1" <?php
                if ($vapp['shared']) {
                    echo "selected='selected'";
                }
                ?>>On</option>
                            <option value="0" <?php
                    if (!$vapp['shared']) {
                        echo "selected='selected'";
                    }
                ?>>Off</option>
                        </select>
                    </td>
                    <td><?php echo date('d/m/Y H:i', strtotime($vapp['creation_date'])); ?></td>
                    <td><?php echo $vapp['number_of_vms']; ?></td>
                    <td class="vapp_cpu_usage"></td>
                    <td class="vapp_memory_usage"></td>
                    <?php if($vapp['busy'] !== false): ?>
                      <td><button class="actions" disabled>Actions</button></td>
                    <?php else : ?>
                      <td><button class="actions">Actions</button></td>
                    <?php endif; ?>
                </tr>
        <?php
    }
}
?>
    </tbody>
</table>
