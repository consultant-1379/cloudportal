<?php
    echo $this->Html->css('Bookings/general', null, array('inline' => false));

    // Common Javascript For All Bookings Pages
    echo $this->Html->script('Bookings/common');

    // Queue Checker
    echo $this->Html->script('Bookings/queue');
?>
<h1>Queue Details</h1>
<div class="layout-bar"></div>
<br>
<?php
    foreach($vapp_types as $type => $description)
    {
        echo '<h2>' . $type . ' Queue</h2>';
        echo '
            <table class="queue_table" id="queue_' . $type . '">
                <thead>
                    <tr>
                        <th>vApp Type</th>
                        <th>Created By</th>
                        <th>Team</th>
                        <th>Date Created</th>
                        <th>Booking Duration</th>
                        <th>Queue Position</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
            <br>
            <br>
        ';
    }
?>
