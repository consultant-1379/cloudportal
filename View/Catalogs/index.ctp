<?php
echo $this->Html->script('Catalogs/datatable');
?>

<h1>Catalogs</h1>
<hr/>
<table id="catalogs_table" class="datatable">
    <thead>
        <tr>
            <th>Catalog Name</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($dbcatalogs as $dbcatalog): ?>
            <tr class="context-menu-one box menu-1">
                <td><?php echo $this->Html->link($dbcatalog['Catalog']['name'], array('controller' => 'vappTemplates', 'action' => 'index', 'catalog_name' => $dbcatalog['Catalog']['name'], 'org_id' => $dbcatalog['Catalog']['org_id'])); ?></td>
            </tr>
        <?php endforeach; ?>


    </tbody> 
</table>
