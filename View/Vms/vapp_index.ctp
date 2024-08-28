<?php
// Datatable initialization
echo $this->Html->script('Vms/vappvms_datatable');

// Right click /actions stuff
echo $this->Html->script('Vms/vappvms_rightclick');

// General
echo $this->Html->script('Vms/general');
echo $this->Html->css('Vms/general', null, array('inline' => false));


echo $this->Html->script('Common/button_context');
//spinner
echo $this->Html->script('Common/spin.min');

echo $this->Html->script('Common/monitor_status.js');

?>
<?php
if ($current_user["is_admin"] || (isset($current_user['permissions'][$this->passedArgs['orgvdc_id']]['write_permission']) && $current_user['permissions'][$this->passedArgs['orgvdc_id']]['write_permission'] )) {
    echo "<div id='orgvdc_permw' style='display:none;'></div>";
}
?>
<div id="orgvdc_id" style="display:none;"><?php echo $this->passedArgs['orgvdc_id']; ?></div>
<div id="vapp_id" style="display:none;"><?php echo $this->passedArgs['vapp_id']; ?></div>
<h1><?php echo $vapp_name; ?></h1>
<hr />
<div id="tabs">
  <ul>
    <li><a href="#tabs-1">VMs</a></li>
    <li><?php echo $this->Html->link("vApp Diagram", array('controller' => 'Vms', 'action' => 'vapp_network','vapp_id' => $this->passedArgs['vapp_id'], 'orgvdc_id' => $this->passedArgs['orgvdc_id']),array('id' => 'vapp_diagram_link')); ?></li>
  </ul>
<div id="tabs-1">
<table id="vappvms_table" class="datatable">
    <thead>
        <tr>
            <th>VM Name</th>
            <th>Status</th>
            <th>Progress</th>
            <th>CPUs</th>
            <th>Memory</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>

    <?php
    if ($vms != 0) {
        foreach ($vms as $vm) {
    ?>
               <?php if (($current_user['is_admin'] || (isset($current_user['permissions'][$this->passedArgs['orgvdc_id']]['admin_permission']) && $current_user['permissions'][$this->passedArgs['orgvdc_id']]['admin_permission'])) && $vm['busy'] !== true){
                      $disabled = "";
                } else {
                      $disabled='disabled';
                }
                ?>
                <?php if($vm['busy'] !== false): ?>
                    <tr id="<?php echo $vm['vm_id']; ?>">
                <?php else : ?>
                    <tr class="context-menu-one box menu-1" id="<?php echo $vm['vm_id']; ?>">
                <?php endif; ?>
                <td><?php echo $this->Html->link($vm['name'], array('controller' => 'Vms', 'action' => 'console', 'vm_id' => $vm['vm_id'], 'vapp_id' => $this->passedArgs['vapp_id']), array('class' => 'console_link', 'target' => '_blank')); ?></td>
                <td><?php echo $vm['status'] ?></td>
                <td class ="vapp_status"><button class="monitor_button">Monitor</button></td>
                <?php
            // figure out the ram mb / gb
            $memory_size = $vm['memory_mb'];
            $memory_type = "MB";

            if ($memory_size % 1024 == 0) {
                $memory_size = $memory_size / 1024;
                $memory_type = "GB";
            }
            ?>
                <td><select class="cpu_combobox" <?php echo $disabled; ?>>
                  <?php
                        for ($i = 1; $i <= 32; $i++) {
                  ?>
                    <option value="<?php echo $i; ?>"<?php
                    if ($vm['cpu_count'] == $i) {
                           echo " selected='selected'";
                    }
                     ?>><?php echo $i; ?></option>
                    <?php } ?>
                    </select></td>
                <td>
                    <input class="mem_size_field" type="text" value="<?php echo $memory_size; ?>" <?php echo $disabled; ?> />
                      <select class="mem_type_combobox" <?php echo $disabled; ?> >
                        <option value="MB"<?php
                        if ($memory_type == "MB") {
                            echo " selected='selected'";
                        }
                                ?>>MB</option>
                        <option value="GB"<?php
                            if ($memory_type == "GB") {
                                echo " selected='selected'";
                            }
                                ?>>GB</option>
                    </select>
                    <button class="set_memory_button" <?php echo $disabled; ?>>Set Memory</button>
                    <?php if($vm['busy'] !== false): ?>
                        <td><button class ="actions" disabled>Actions</button></td>
                    <?php else: ?>
                        <td><button class ="actions">Actions</button></td>
                    <?php endif; ?>
                </td>
            </tr>
	<?php
    }
}
?>

    </tbody>
</table>
</div>
<div id="tabs-2">
</div>
</div>
