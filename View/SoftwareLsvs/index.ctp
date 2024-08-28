<!-- File: /app/View/Posts/index.ctp -->
<table>
    <tr>
        <th>Id</th>
        <th>Name</th>
        <th>Actions</th>
    </tr>
    <!-- Here is where we loop through our $posts array, printing out post info -->

    
    <?PHP foreach ($software_lsvs as $software_lsv): ?>
    
        <tr>
            <td><?php echo $software_lsv['SoftwareLsv']['id']; ?></td>
            <td>
                <?php echo $this->Html->link($software_lsv['SoftwareLsv']['name'], array('controller' => 'SoftwareLsvs', 'action' => 'edit', $software_lsv['SoftwareLsv']['id']));
                ?>
            </td>
            
            <td>
                <?php echo $this->Html->link('Edit', array('controller' => 'SoftwareLsvs', 'action' => 'edit', $software_lsv['SoftwareLsv']['id']));
                ?>
                <?php echo $this->Form->postLink('Delete', array('controller' => 'SoftwareLsvs', 'action' => 'delete', $software_lsv['SoftwareLsv']['id']), array('confirm' => 'Are you sure?'));
                ?>
                
            </td>
        </tr>
    <?php endforeach; ?>

</table>
<?php
echo $this->Html->Link('Add LSV', array('controller' => 'SoftwareLsvs', 'action' => 'add'));
?>