<?php
// Datatable initialization
echo $this->Html->script('Reports/datatable_historical_reports');

?>

<h1>Historical Reports</h1>
<hr />
<table id="table" class="datatable">
    <thead>
        <tr>
            <th>Report Name</th>
            <th>Creation Date</th>
        </tr>
    </thead>
    <tbody>
        <?php
        foreach ($reports as $report) {
            ?>
            <tr>
                <td><?php echo $this->Html->link($report['filename'], array('controller' => '', 'action' => 'historical_reports/' . $report['filename'])); ?></td>
                <td><?php echo date('d/m/Y H:i', $report['modified_time']); ?></td>
            </tr>
            <?php } ?>
    </tbody>
</table>
