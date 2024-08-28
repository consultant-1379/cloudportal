<!-- File: /app/View/Posts/index.ctp -->
<table>
    <tr>
        <th>Id</th>
        <th>Name</th>
        <th>Software Type</th>
        <th>Actions</th>
    </tr>
    <!-- Here is where we loop through our $posts array, printing out post info -->
<?PHP
     foreach ($softwareReleases as $software_release): ?>
    
        <tr>
            <td><?php echo $software_release['SoftwareRelease']['id']; ?></td>
            <td>
                <?php echo $this->Html->link($software_release['SoftwareRelease']['name'], array('controller' => 'softwareReleases', 'action' => 'edit', $software_release['SoftwareRelease']['id']));
                ?>
            </td>
            <td><?php echo $software_release['SoftwareType']['name']; ?></td>
            <td>
                <?php echo $this->Html->link('Edit', array('controller' => 'softwareReleases', 'action' => 'edit', $software_release['SoftwareRelease']['id']));
                ?>
                <?php echo $this->Form->postLink('Delete', array('controller' => 'softwareReleases', 'action' => 'delete', $software_release['SoftwareRelease']['id']), array('confirm' => 'Are you sure?'));
                ?>
                
            </td>
        </tr>
    <?php endforeach; ?>

</table>
<?php
echo $this->Html->Link('Add Release', array('controller' => 'softwareReleases', 'action' => 'add'));
?>