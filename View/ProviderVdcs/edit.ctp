<?php
    echo $this->Html->script('ProviderVdcs/edit');

    $options = array(
        'label' => 'Save',
        'name' => 'editProviderVDC',
        'div' => array(
            'id' => 'editProviderVDC',
        )
    );
    echo "<h1>Edit Provider Vdc '" . $this->request->data['ProviderVdc']['name'] . "'</h1>";
    echo $this->Form->create('ProviderVdc');
    echo $this->Form->hidden('name');
    echo $this->Form->input('new_quota_system', array('label' => "Use CPU / Memory Quota System"));
    echo $this->Form->input('available_cpus', array('label' => "Available CPUs", 'id' => 'provider_cpus', 'disabled' => 'disabled'));
    echo $this->Form->input('cpu_multiplier', array('label' => "CPU Multiplier", 'class' => 'affects_summary', 'id' => 'cpu_multiplier'));
    echo $this->Form->input('available_memory', array('label' => "Available Memory (GB)", 'id' => 'provider_memory', 'disabled' => 'disabled'));
    echo $this->Form->input('memory_multiplier', array('label' => "Memory Multiplier", 'class' => 'affects_summary', 'id' => 'memory_multiplier'));
?>
<div id="summary_div"></div>
<?php
    echo $this->Form->end($options);
?>
