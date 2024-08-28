<?php
    echo $this->Html->css('Portal/clearfix', null, array('inline' => false));
    echo $this->Html->script('OrgVdcs/edit');
    echo $this->Html->script('OrgVdcs/edit_datatable');

    $options = array(
        'label' => 'Save',
        'name' => 'editOrgsVDC',
        'div' => array(
            'id' => 'editOrgsVDC',
        )
    );
?>
<div class="clearfix">
    <?php
        echo "<h1>Edit '" . $this->request->data['OrgVdc']['name'] . "' Cloud</h1>";
    ?>
    <div id="formdiv" style="min-width:600px;float:left">
        <div style="display: none" id="cpus_available_in_provider"><?php echo floor($this->request->data['ProviderVdc']['available_cpus'] * $this->request->data['ProviderVdc']['cpu_multiplier']); ?></div>
        <div style="display: none" id="memory_available_in_provider"><?php echo floor($this->request->data['ProviderVdc']['available_memory'] * $this->request->data['ProviderVdc']['memory_multiplier']); ?></div>
        <h2>'<?php echo $this->request->data['OrgVdc']['name']; ?>' Quota Settings</h2>
        <hr>
        <?php
            echo $this->Form->create('OrgVdc');
            echo $this->Form->input('cpu_limit', array('label' => "Running CPUs Quota", 'class' => 'affects_summary', 'id' => 'cpus_limit'));
            echo $this->Form->input('memory_limit', array('label' => "Running Memory Quota (GB)", 'class' => 'affects_summary', 'id' => 'memory_limit'));
        ?>
        <?php
            echo $this->Form->input('running_tb_limit', array('label' => "Running vApps Quota"));
        ?>
        <?php
            echo $this->Form->input('stored_tb_limit', array('label' => "Total vApps Quota"));
            echo $this->Form->input('mig_ra_id', array('label' => "RA"));
            echo $this->Form->end($options);
        ?>
    </div>
    <div style="min-width:600px;float:left">
        <h2>Provider Resources Summary</h2>
        <hr>
        <div id="summary_div"></div>
        <br>
        <table id="orgvdcs_table" class="datatable">
            <thead>
                <tr>
                    <th>Datacenter Name</th>
                    <th>Running CPUs Quota</th>
                    <th>Running Memory Quota (GB)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                    $cpus_limit_total = 0;
                    $memory_limit_total = 0;
                    foreach ($db_orgvdcs as $db_orgvdc) {
                        $cpus_limit = $db_orgvdc['OrgVdc']['cpu_limit'];
                        $memory_limit = $db_orgvdc['OrgVdc']['memory_limit'];
                        $on_current_orgvdc = ($db_orgvdc['OrgVdc']['vcd_id'] == $this->request->data['OrgVdc']['vcd_id']);
                        if ($on_current_orgvdc)
                        {
                            $cpus_quota_classes="class='cpus_limit highlight'";
                            $memory_quota_classes="class='memory_limit highlight'";
                            $cpu_column_id="id='this_orgvdc_cpus_limit'";
                            $memory_column_id="id='this_orgvdc_memory_limit'";
                            $cpu_value="0";
                            $memory_value="0";
                        } else
                        {
                            $cpus_quota_classes="class='cpus_limit'";
                            $memory_quota_classes="class='memory_limit'";
                            $cpu_column_id="";
                            $memory_column_id="";
                            $cpu_value=$db_orgvdc['OrgVdc']['cpu_limit'];
                            $memory_value=$db_orgvdc['OrgVdc']['memory_limit'];
                        }
                ?>
                        <tr>
                            <td><?php echo $db_orgvdc['OrgVdc']['name']; ?></td>
                            <td <?php echo $cpus_quota_classes . ' ' . $cpu_column_id; ?>><?php echo $cpu_value; ?></td>
                            <td <?php echo $memory_quota_classes . ' ' . $memory_column_id; ?>><?php echo $memory_value; ?></td>
                        </tr>
                <?php
                    }
                ?>
            </tbody>
        </table>
    </div>
</div>
