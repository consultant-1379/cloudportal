<?php

// recompose data
echo $this->Html->script('Vapps/recompose.js');

?>



<div>
<h1>Recompose vApp (<?php echo $vapp_name ?>)</h1>
    <div style="width:25%;">
<?php
echo $this->Form->create('recompose');
?>
<span style="margin:1px;">
<h3>Select a Catalog:</h3>
<?php
$default = null;
$values = array();
$listcatalogs = array();

foreach ($catalogs as $catalog) {
   $listcatalogs[$catalog['Catalog']['name']] = $catalog['Catalog']['name'];
}

echo $this->Form->select('catalog',  $listcatalogs, array('id' => 'catalog', 'empty' => '-- Select a Catalog --'));
?>
</span>
</br>
<span style="margin:1px;">
<h3>Select a Template:</h3>
<?php
echo $this->Form->select('template', null, array('id' => 'template'));
?>
</span>
</br>
<span style="margin:1px;">
<h3>Select the VM(s):</h3>
<span style="color:green;">(For multiple VMs selection use Ctrl+Click)</span>
<?php
echo $this->Form->select('vms', null, array('multiple' => true, 'id' => 'vmList'));
?>
</span>
<?php
echo $this->Form->end('Recompose');
?>
</div>
</div>
