<h1>Add To My Cloud</h1>
<?php
echo $this->Form->create('VappTemplate');
echo $this->Form->input('name', array('label' => "vApp Name"));
//echo $this->Form->input('description', array('rows' => '3'));

$default = null;
$values = array();
foreach ($orgvdcs as $orgvdc) {
    //if (!$orgvdc['is_system_vdc']) {
        if ($default == null) {
            $default = $orgvdc['OrgVdc']['name'];
        }
        $values[$orgvdc['OrgVdc']['name']] = $orgvdc['OrgVdc']['name'];
    //}
}

echo $this->Form->input('orgvdc', array('options' => $values, 'empty' => false, 'default' => $default, 'label' => 'Cloud'));
echo $this->Form->input('powerOn', array('type' => 'checkbox', 'checked' => true, 'label' => 'Power on vApp'));
echo $this->Form->end('Add To Cloud');
?>

