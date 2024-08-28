<?php
// Datatable initialization
echo $this->Html->script('Reports/datatable_index');

?>

<h1>Reports</h1>
<hr />
<table id="table" class="datatable">
    <thead>
        <tr>
            <th>Report Type</th>
            <th>Details</th>
        </tr>
    </thead>
    <tbody>
        <?php
        foreach ($reports as $report) {
            ?>
            <tr>
                <td><?php echo $this->Html->link($report['title'], array('controller' => 'Reports', 'action' => $report['link'])); ?></td>
                <td><?php echo $report['details']; ?></td>
            </tr>
            <?php } ?>
    </tbody>
</table>
