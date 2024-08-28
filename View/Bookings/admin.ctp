<?php
    echo $this->Html->css('Bookings/general', null, array('inline' => false));

    // common progress bar updater
    echo $this->Html->script('Common/progress_bar_updater');

    // Pool Status checker
    echo $this->Html->script('Bookings/admin_pool_status');
?>
<h1>Pool Usage</h1>
<div class="layout-bar"></div>
<br>
<h2>Overall Usage From <a href="<?php echo $jenkins_url; ?>" target="_blank">Jenkins</a></h2>
<br>
<?php
    foreach($vapp_types as $type => $description)
    {
        echo '
            <div class="progress_container">
                <div id="progress_bar_' . $type . '" class="progress">
                    <div class="progress-bar progress-bar-success" role="progressbar" style="width:0%">
                        <span class="show">Loading...</span>
                    </div>
                </div>
            </div>
        ';
    }
?>
<br>
<br>
<h2>Team Usage</h2>
<table id="team_bookings_table" class="datatable">
    <thead>
        <tr>
            <th>Team</th>
            <?php
                foreach($vapp_types as $type => $description)
                {
                    echo '<th>' . $type . '</th>';
                }
            ?>
        </tr>
    </thead>
    <tbody>
        <?php
            foreach ($booking_counts_and_limits as $team => $team_booking_counts_and_limits)
            {
                echo '<tr>';
                echo '<td>' . $team . '</td>';
                foreach($vapp_types as $type => $description)
                {
                    echo '<td>' . $team_booking_counts_and_limits[$type]['booking_count'] . ' / ' . $team_booking_counts_and_limits[$type]['booking_limit'] . '</td>';
                }
                echo '</tr>';
            }
        ?>
    </tbody>
</table>
