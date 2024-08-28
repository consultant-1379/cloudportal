<?php
// General initialization
echo $this->Html->script('VappTemplates/general');

// Datatable initialization
echo $this->Html->script('VappTemplates/datatable');

// Right click /actions stuff
echo $this->Html->script('VappTemplates/vapptemplates_rightclick');

// actions button initialization
echo $this->Html->script('Common/button_context');

// Double click stuff
echo $this->Html->script('VappTemplates/vapptemplate_dclick.js');

// Spinner
echo $this->Html->script('Common/spin.min');

// monitor button status
echo $this->Html->script('Common/monitor_status.js');
?>

<?php
//echo $this->Html->script('prettify');
if ($current_user["is_admin"] || (isset($current_user['permissions'][$this->passedArgs['org_id']]['write_permission']) && $current_user['permissions'][$this->passedArgs['org_id']]['write_permission'] )) {
    echo "<div id='org_permw' style='display:none;'></div>";
}
?>
<div id="org_id" style="display:none;"><?php echo $this->passedArgs['org_id']; ?></div>


<h1><?php echo $title_for_layout; ?></h1>
<hr/>
<?php echo $this->Html->link("Browse Other Catalogs", array('controller' => 'Catalogs', 'action' => 'index', 'redirect' => "no")); ?>
<br />
<br />
<div id="tabs">
    <ul>
        <li><a href="#tabs-1">vApp Templates</a></li>
        <li><?php echo $this->Html->link("Media", array('controller' => 'Medias', 'action' => 'index', 'catalog_name' => $this->passedArgs['catalog_name'], 'org_id' => $this->passedArgs['org_id']), array('id' => 'media_tab_link')); ?></li>
    </ul>
    <div id="tabs-1">
        <table id="vapptemplates_table" class="datatable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Progress</th>
                    <th>Date Created</th>
                    <th>CPUs</th>
                    <th>Memory (GB)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>

                <?php
                foreach ($vapptemplates as $vapptemplate) {
                    ?>
                    <tr class="context-menu-one box menu-1 doubleclick" id="<?php echo $vapptemplate['vapp_template_id']; ?>">
                        <td><?php echo $this->Html->link($vapptemplate['name'], array('controller' => 'Vms', 'action' => 'vapptemplate_index', 'vapp_template_id' => $vapptemplate['vapp_template_id'])); ?></td>
                        <td><?php echo $vapptemplate['status']; ?></td>
                        <td class="vapp_status"><button class="monitor_button">Monitor</button></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($vapptemplate['creationDate'])); ?></td>
                        <td><?php echo $vapptemplate['cpu_total']; ?></td>
                        <td><?php echo ceil($vapptemplate['memory_total'] / 1024);?></td>
                        <td><button class="actions">Actions</button></td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
    </div>
    <div id="tabs-2">
    </div>
</div>
