

<table>
    <tr>
        <th>Id</th>
        <th>Tag Name</th>
        <th>Created By</th>
        <th>Date Created</th>
        <th>Organisation</th>
        <th>Functions</th>
    </tr>

    <?php foreach ($citags as $citag): ?>
        <tr>
            <td><?php echo $citag['Citag']['id']; ?></td>
            <td>
                <?php echo $this->Html->link($citag['Citag']['name'], array('controller' => 'citags', 'action' => 'edit', $citag['Citag']['id']));
                ?>
            </td>
            
            <td><?php echo $citag['Citag']['user_id']; ?></td>
            <td><?php echo $citag['Citag']['created']; ?></td>
            <td><?php echo $citag['OrgVdc']['name']; ?></td>
            <td>
                <?php echo $this->Html->link('Edit', array('controller' => 'citags', 'action' => 'edit', $citag['Citag']['id']));
                ?>
                <?php echo $this->Html->link('Delete', array('controller' => 'citags', 'action' => 'delete', $citag['Citag']['id']));
                ?>
                
                
            </td>
        </tr>
    <?php endforeach; ?>

</table>
<?php
echo $this->Html->Link('Create CI Tag', array('controller' => 'citags', 'action' => 'add'));
?>