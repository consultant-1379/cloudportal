<!-- File: /app/View/Posts/index.ctp -->
<table>
    <tr>
        <th>Id</th>
        <th>Name</th>
        <th>Actions</th>
    </tr>
    <!-- Here is where we loop through our $posts array, printing out post info -->

    
    <?PHP foreach ($software_types as $software_type): ?>
    
        <tr>
            <td><?php echo $software_type['SoftwareType']['id']; ?></td>
            <td>
                <?php echo $this->Html->link($software_type['SoftwareType']['name'], array('controller' => 'SoftwareTypes', 'action' => 'edit', $software_type['SoftwareType']['id']));
                ?>
            </td>
            
            <td>
                <?php echo $this->Html->link('Edit', array('controller' => 'SoftwareTypes', 'action' => 'edit', $software_type['SoftwareType']['id']));
                ?>
                <?php echo $this->Form->postLink('Delete', array('controller' => 'SoftwareTypes', 'action' => 'delete', $software_type['SoftwareType']['id']), array('confirm' => 'Are you sure?'));
                ?>
                
            </td>
        </tr>
    <?php endforeach; ?>

</table>
<?php
echo $this->Html->Link('Add Software Type', array('controller' => 'SoftwareTypes', 'action' => 'add'));
?>