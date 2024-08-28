<?php
if (sizeof($orgs) == 0) {
    echo "You don't have access to any orgs yet";
    return;
}
?>
<table>
    <tr>
        <th></th>
        <th>Org Name</th>
        <th>DisplayName</th>
        <th>No. Catalogs</th>
        <th>No. PvDCs</th>
        <th>No. vApps</th>
    </tr>

    <?php
    $i = 1;
    foreach ($orgs as $org):
        ?>

        <tr>
            <td><?php echo $i; ?></td>
            <td><?php echo $this->Html->link($org['name'], array('controller' => 'OrgVdcs', 'action' => 'index', 'org_id' => $org['org_id'])); ?></td>
            <td><?php echo $org['display_name'] ?></td>            
            <td><?php echo $org['catalogs'] ?></td>
            <td><?php echo $org['vdcs']; ?></td>
            <td><?php echo $org['vapps']; ?></td>
        </tr>
        <?php
        $i++;
    endforeach;
    ?>



</table>
