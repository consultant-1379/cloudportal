<?php
$spinner_opts = "
    {
        // web documentation at http://fgnass.github.com/spin.js/#?lines=13&length=4&width=2&radius=6&rotate=0&trail=60&speed=1.7
        lines: 13, // The number of lines to draw
        length: 4, // The length of each line
        width: 2, // The line thickness
        radius: 6, // The radius of the inner circle
        rotate: 0, // The rotation offset
        color: '#000', // #rgb or #rrggbb
        speed: 1.7, // Rounds per second
        trail: 60, // Afterglow percentage
        shadow: false, // Whether to render a shadow
        hwaccel: false, // Whether to use hardware acceleration
        className: 'spinner', // The CSS class to assign to the spinner
        top: -18, // Top position relative to parent in px
        left: 0 // Left position relative to parent in px
    }
";

if (isset($vapp_task['task'])) {
    //echo $vapp_task['task']->get_operation();
    //echo "</br>";
    //echo $vapp_task['task']->get_status();
    //echo "</br>";
    if ($vapp_task['task']->get_status() == "running") {
        //echo "its running so show spinning icon";
        //echo "</br>";
        ##echo $vapp_task['status'];
        ##echo "</br>";
        $name = $vapp_task['task']->get_operation();
        if (strstr($name, "Starting Virtual Application")) {
            $operation_name = "Starting..";
        } else if (strstr($name, "Stopping Virtual Application")) {
            $operation_name = "Stopping..";
        } else if (strstr($name, "Creating Virtual Application")) {
            $operation_name = "Creating..";
        } else if (strstr($name, "Deleting Virtual Application")) {
            $operation_name = "Deleting..";
        } else if (strstr($name, "Updating Virtual Application")) {
            $operation_name = "Updating..";
        } else if (strstr($name, "Updating Virtual Machine")) {
            $operation_name = "Updating VM..";
        } else if (strstr($name, "Recomposing Virtual Application")) {
            $operation_name = "Recomposing..";
        } else if (strstr($name, "Composing Virtual Application")) {
            $operation_name = "Composing..";
        } else if (strstr($name, "Purging Stranded Item")) {
            $operation_name = "Cleaning up..";
        } else if (strstr($name, "Importing Virtual Application")) {
            $operation_name = "Importing..";
        } else if (strstr($name, "Capturing Virtual Application Template")) {
            $operation_name = "Copying..";
        } else if (strstr($name, "Starting Virtual Machine")) {
            $operation_name = "Starting VM..";
        } else if (strstr($name, "Powering Off Virtual Machine")){
            $operation_name = "Powering Off VM..";
        } else if (strstr($name, "Resetting Virtual Machine")){
            $operation_name = "Resetting VM..";
        }
        else {
            $operation_name = $name;
        }
        if ($vapp_task['task']->getProgress() == 0 || $vapp_task['task']->getProgress() == "") {
            echo "<div class='vapp_status_text'>";
            echo $operation_name;
            echo "</div>";
            echo "<div class='progress_spinner'></div>";
            echo $this->Html->scriptBlock("
                var myid='" . $vapp_id . "';
                var idescaped=escapeit(myid);
                var spinner = new Spinner($spinner_opts).spin($('#' + idescaped + ' .progress_spinner')[0]);
            ");
        } else {
            echo $operation_name;
            echo "(" . $vapp_task['task']->getProgress() . "%)";
            echo "<div class='progress_bar'></div>";
            echo $this->Html->scriptBlock("
				var myid='" . $vapp_task['vapp_id'] . "';
				var idescaped=escapeit(myid);
				$('#' + idescaped + ' .progress_bar').progressbar({ value: " . $vapp_task['task']->getProgress() . " });
			");
        }
    } elseif ($vapp_task['task']->get_status() == "error") {
        //echo "it failed so show red icon and error details inside clickable menu";
        //echo "</br>";
        //echo $vapp_task['status'];
        //echo "</br>";
        $error_object = $vapp_task['task']->getError();
        if ($error_object) {
            echo "<div class='task_error'>";
            //echo $vapp_task['task']->get_operationName();
            //echo "</br>";
            //echo $vapp_task['task']->get_status();
            //echo "</br>";
            //echo $vapp_task['task']->get_tagName();
            //echo "</br>";
            echo "<table>";
            echo "<tr>";
            echo "<td>Operation:</td>";
            echo "<td>" . $vapp_task['task']->get_operation() . "</td>";
            echo "</tr>";
            echo "<tr>";
            echo "<td>Start Time:</td>";
            echo "<td>" . date('d/m/Y H:i', strtotime($vapp_task['task']->get_startTime())) . "</td>";
            echo "</tr>";
            echo "<tr>";
            echo "<td>Stop Time:</td>";
            echo "<td>" . date('d/m/Y H:i', strtotime($vapp_task['task']->get_endTime())) . "</td>";
            echo "</tr>";
            echo "<tr>";
            echo "<td>Details:</td>";
            echo "<td> " . $error_object->get_message() . "</td>";
            echo "<tr>";
            echo "</table>";
            /*
              echo $vapp_task['task']->getProgress();
              echo "</br>";
              echo $vapp_task['task']->getParams();
              echo "</br>";
              echo $vapp_task['task']->getDescription();
              echo "</br>";
              echo $error_object->get_majorErrorCode();
              echo "</br>";
              echo $error_object->get_message();
              echo "</br>";
              echo $error_object->get_minorErrorCode();
              echo "</br>";
              echo $error_object->get_stackTrace();
              echo "</br>";
              echo $error_object->get_tagName();
              echo "</br>";
              echo $error_object->get_vendorSpecificErrorCode();
              echo "</br>";
             */
            echo "</div>";


            // The link to the error dialog

            $error_name = "Error";
            if ($vapp_task['task']->get_operationName() == "vappUndeployPowerOff") {
                $error_name = "Cannot Stop";
            } else if ($vapp_task['task']->get_operationName() == "vappDeploy") {
                $error_name = "Cannot Start";
            } else if ($vapp_task['task']->get_operationName() == "vdcInstantiateVapp") {
                $error_name = "Cannot Create";
            } else {
                $error_name = "Error";
                //$error_name = $vapp_task['task']->get_operationName();
            }
            //echo "<button class='task_error_button' type='button' >Error</button>";
            echo "<a class='task_error_link' href='error'><img src='/css/staticfiles/images/Vapps/error.png' /> " . $error_name . "</a>";
            exit(0);
        }
    }
}
else if (isset($vapp_busy) && $vapp_busy != false){
    echo "<div class='vapp_status_text'>";
    echo $vapp_busy . "....";
    echo "</div>";
    echo "<div class='progress_spinner'></div>";
    echo $this->Html->scriptBlock("
          var myid='" . $vapp_id . "';
          var idescaped=escapeit(myid);
          var spinner = new Spinner($spinner_opts).spin($('#' + idescaped + ' .progress_spinner')[0]);
    ");


} else {
    echo "No running tasks";
    //echo $vapp_task['status'];
    //echo "no task running";
}

?>
