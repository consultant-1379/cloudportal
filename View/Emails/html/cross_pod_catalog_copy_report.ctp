Cloud Portal: <?php echo $spp_hostname; ?>. Please find the results below for the cross pod catalog copy of the vApp Template called <font color="green">'<?php echo $vapptemplate_name; ?>'</font> from catalog <font color="green">'<?php echo $vapptemplate_catalog; ?>'</font>
<br>
<br>
It was started on <?php echo $started_date_string; ?>
<br>
<br>
<ul>
<?php
    foreach ($results as $spp_url => $result)
    {
        echo '<li>' . $spp_url . ': ' . ($result['passed'] ? 'Success' : 'Fail') . ': ' . htmlspecialchars($result['comment']) . '</li>';
    }
?>
</ul>
