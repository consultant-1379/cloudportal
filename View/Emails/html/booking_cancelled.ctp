Dear <?php echo $original_username; ?>,
<br>
<br>
Your<?php echo ($status == 'queued') ? ' queued ' : ' ' ?>Test Environment vApp<?php echo ($status == 'queued') ? ' ' : ' with hostname "<font color="green">' . $vapp_gateway_hostname . '</font>" ' ?>that was booked on <?php echo $created_datetime; ?> has been cancelled by <?php echo $username; ?>.
<br>
