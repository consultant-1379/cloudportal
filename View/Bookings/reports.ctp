<?php
    // CSS
    echo $this->Html->css('Bookings/reports');
    // javascript
    echo $this->Html->script('Bookings/reports');
?>
<div id="error_message_div"  class="flash_bad" style="display:none" ></div>
<div id="loading_message_div" style="display:none;font-weight:bold;font-size:1.5em"></div>
<h1>Pool Usage Reports</h1>
<div class="layout-bar"></div>
<div id="dateFilterDiv">
   <br>
   <h2>Filter</h2>
   <p>Date From <input type="text" class="datePicker" style='float: right;' id="datepicker_start"></p>
   <p>Date To <input type="text" class="datePicker" style='float: right;' id="datepicker_end"></p>
   <button type="button" class="btn btn-default" style='font-size: 12px; height: 28px;' id="date_submit">Apply</button>
   <br>
</div>
<h5 id="user_note">Note Default is 4 weeks of Reports</h5>
<h2>Overall Usage Report</h2>
</br>
<table id="overall_report_table" class="datatable">
    <thead>
        <tr>
            <th>vApp Type</th>
            <th>Total Count</th>
            <th>Total Hours</th>
            <th>Average Hours</th>
            <th>Total Canceled</th>
            <th>Total Extended</th>
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
        </tr>
    </tbody>
</table>
</br>
</br>
</br>
<h2>Team Usage Report</h2>
</br>
<table id="team_report_table" class="datatable">
    <thead>
        <tr>
            <th>Team</th>
            <th>RA</th>
           <?php
                foreach($vapp_types as $type => $description)
                {
                   echo '<th>' . $type . ' Total Count</th>';
                   echo '<th>' . $type . ' Total Hours</th>';
                   echo '<th>' . $type . ' Average Hours</th>';
                   echo '<th>' . $type . ' Total Canceled</th>';
                   echo '<th>' . $type . ' Total Extended</th>';
                }
            ?>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Loading...</td>
            <td></td>
            <?php
                foreach($vapp_types as $type => $description)
                {
                   echo '<td></td>';
                   echo '<td></td>';
                   echo '<td></td>';
                   echo '<td></td>';
                   echo '<td></td>';
                }
            ?>
        </tr>
    </tbody>
</table>
