Dear <?php echo $username; ?>,
<br>
<br>
Your "<font color="green"><?php echo $vapp_type; ?></font>" Test Environment vApp was booked successfully.
<br>
<br>
It will be deleted in <?php echo $duration_hours; ?> hours. That is on <?php echo $deletion_date_time; ?>.
<br>
<?php
    if ($limit_type == "OR")
    {
        echo '<br>
              Please note you can only book one type of vApp at a time. As you have now booked a vApp of type: ' . $vapp_type . ', you will not be allowed to book a different type of vApp.
              <br>';
    }
?>
<br>
More Information
<ul>
    <li>The private gateway for this vApp is <font color="red"><?php echo $vapp_gateway_fqhn; ?></font></li>
    <li>The vapp template that it was created from was called "<font color="green"><?php echo $vapp_template_name; ?></font>"
    <li><?php echo $templatestatus; ?></li>
    <li>The latest vapp template version in the pool is <font color="blue"><?php echo $latest_templateversion; ?></font></li>
    <li>You can import the attached ShrewSoft VPN file using the VPN Access Manager and open the vpn to the vApp</li>
    <li>Please click <a href="http://confluence-oss.lmera.ericsson.se/display/PDUCD/VPN+User+Guides">here</a> to view the VPN user guides</li>
    <li>Please click <a href="http://confluence-nam.lmera.ericsson.se/pages/viewpage.action?pageId=30905470">here</a> for details on how to access this private vApp</li>
</ul>
