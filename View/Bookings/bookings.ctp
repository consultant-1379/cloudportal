<?php
    // Common Javascript For All Bookings Pages
    echo $this->Html->script('Bookings/common');

    // General javascript
    echo $this->Html->script('Bookings/bookings');

    // Common progress bar updater
    echo $this->Html->script('Common/progress_bar_updater');

    // Team Quota Status Checker
    echo $this->Html->script('Bookings/quotas_progress_bar_updater');

    //General css
    echo $this->Html->css('Bookings/bookings');

    // Actions button initialization
    echo $this->Html->script('Common/button_context');
?>
<div id="error_message_div"  class="flash_bad" style="display:none" ></div>
<div id="success_message_div" class="flash_good"  style="display:none" ></div>
<div id="loading_message_div" style="display:none;font-weight:bold;font-size:1.5em"><img style="padding: 2px;" src="/images/loader.gif"> Please wait...</div>
<div style="display:none" id="team"><?php echo $users_team; ?></div>
<div style="display:none" id="default_extension_hours"><?php echo $default_extension_time_hours; ?></div>
<div style="display:none" id="extension_limit"><?php echo $extension_limit; ?></div>
<div class="align_div">
    <h1>Team <?php echo $users_team; ?> Bookings</h1>
</div>
<div class="align_div">
<?php
    foreach($vapp_types as $type => $description)
    {
        echo '<div class="progress_container">';
        if ($limit_type_message["vapp_type"] != "")
        {
            if ($limit_type_message["team_limit_type"] == "OR" && $limit_type_message["vapp_type"] == $type)
            {
                echo '<div id="progress_bar_' . $type . '" class="progress">';
            } else {
                echo '<div id="progress_bar_' . $type . '" class="progress fade">';
            }
            echo '<div class="progress-bar progress-bar-success" role="progressbar" style="width:0%">
                       <span class="show">Loading...</span>
                   </div>
               </div>';
        } else {
            echo '<div id="progress_bar_' . $type . '" class="progress">
                        <div class="progress-bar progress-bar-success" role="progressbar" style="width:0%">
                             <span class="show">Loading...</span>
                        </div>
                  </div>';
        }
        echo '</div>';
    }
?>
</div>
<?php
   if ($limit_type_message["message"] != "")
   {
       echo '<div id="limit_type_message_div" class="flash_info">' . $limit_type_message["message"] . '</div>';
   }
?>
</div>
</br>
<table id="bookings_table" class="datatable">
    <thead>
        <tr>
            <th>vApp Type</th>
            <th>Gateway</th>
            <th>Origin Template</th>
            <th>Created By</th>
            <th>Team</th>
            <th>Date Created</th>
            <th>Booking Duration</th>
            <th>Extensions Used</th>
            <th>Time Remaining / Queue Time</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Loading...</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    </tbody>
</table>

<div id="dialog-form" title="Book Test Environment" style="display:none;">
    <?php
        echo $this->Form->create('Booking', array('action' => 'create'));

        $vapp_type_values = array();
        foreach ($vapp_types as $vapp_type => $description) {
            $vapp_type_values[$vapp_type] = $vapp_type;
        }

        $duration_values = array();
        for ($hours = 1; $hours <= $max_duration_hours; $hours++) {
            $duration_values[$hours] = $hours;
        }

        echo $this->Form->input('vapp_type', array('id' => 'vapp_type', 'options' => $vapp_type_values, 'empty' => false, 'label' => 'Test Environment vApp Type *'));
        echo $this->Form->input('duration_hours', array('id' => 'duration_hours' , 'options' => $duration_values, 'empty' => false, 'default' => $default_duration_hours, 'label' => 'Hours Required'));
    ?>
    <div>
        <br/>
        <h4>Current Queue Times</h4>
        <?php
            foreach ($vapp_types as $vapp_type => $description) {
               echo '<h5>' . $vapp_type . '<br></h5>';
               echo '<p id=' . $vapp_type . '>Calculating...</p>';
            }
        ?>

    </div>
    <div>
        <br/>
        <h4>* Test Environment vApp Type Descriptions</h4>
        <font color="blue">
            <ul style="list-style-type:disc">
            <?php
                foreach ($vapp_types as $vapp_type => $description) {
                    echo '<li>The "' . $vapp_type . '" Test Environment vApp is ' . $description . '<br></li>';
                }
            ?>
            </ul>
        </font>
    </div>
</div>
