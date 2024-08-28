<h1>Add vApp (<?php echo $vapp_name ?>) To Catalog</h1>
<?php
echo $this->Form->create('Vapp');
echo $this->Form->input('name', array('label' => "Catalogued vApp Name"));
//echo $this->Form->input('description', array('rows' => '3'));
$default = null;
$values = array();
$listcatalogs = array();

foreach ($catalogs as $catalog) {
    if ($default == null) {
        $default = $catalog['Catalog']['name'];
    }
    $listcatalogs[$catalog['Catalog']['name']] = $catalog['Catalog']['name'];
}
echo $this->Form->input('catalog', array('options' => $listcatalogs, 'empty' => false, 'default' => $default, 'label' => 'Catalog'));
echo $this->Form->end('Add To Catalog');
?>
