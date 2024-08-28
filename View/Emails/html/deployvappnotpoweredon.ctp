<div class=WordSection1>
<p class=MsoPlainText><span style='font-size:12.0pt'>Dear <?php echo $vappUser; ?>, <o:p></o:p></span></p>
<p class=MsoPlainText><span style='font-size:12.0pt'><o:p>&nbsp;</o:p></span></p>
<p class=MsoPlainText><span style='font-size:12.0pt'>Your vApp called &quot;<span style='color:#00B050'><?php echo $vappName; ?></span>&quot; was added to your &quot;<span style='color:#00B050'><?php echo $catName; ?></span>&quot; cloud successfully, but is not powered on. 
<span style='color:#1F497D'><o:p></o:p></span></span></p><p class=MsoPlainText><span style='font-size:12.0pt'>
Please login to the <span style='color:#1F497D'><a href="<?php echo $url; ?>">Cloud Portal</a></span> to manipulate it.<o:p>
</o:p></span></p>
<?php if( isset($runtimeLease) || isset($storageLease) ) : ?>    
<p class=MsoPlainText><span style='font-size:12.0pt;color:#1F497D'><o:p>&nbsp;</o:p></span></p>
<p class=MsoPlainText><span style='font-size:12.0pt'>Leases:<o:p></o:p></span></p>
<p class=MsoPlainText style='margin-left:36.0pt;text-indent:-18.0pt;mso-list:l1 level1 lfo2'><![if !supportLists]>
<span style='font-size:12.0pt;font-family:Symbol'><span style='mso-list:Ignore'>·<span style='font:7.0pt "Times New Roman"'>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span></span></span><![endif]><span style='font-size:12.0pt'>Click 
<a href="<?php echo $runtimeLease; ?>">here</a> to modify the Runtime lease.<span style='color:#1F497D'><o:p>
</o:p></span></span></p>
<p class=MsoPlainText style='margin-left:36.0pt;text-indent:-18.0pt;mso-list:l1 level1 lfo2'><![if !supportLists]>
<span style='font-size:12.0pt;font-family:Symbol'><span style='mso-list:Ignore'>·<span style='font:7.0pt "Times New Roman"'>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span></span></span><![endif]><span style='font-size:12.0pt'>Click
 <a href="<?php echo $storageLease; ?>">here</a> to modify the Storage lease<span style='color:#1F497D'>.</span>
<o:p></o:p></span></p>
<?php endif ?>
<p class=MsoPlainText><span style='font-size:12.0pt;color:#1F497D'><o:p>&nbsp;</o:p></span></p><![endif]>
<p class=MsoPlainText><span style='font-size:12.0pt'>Kind regards,<span style='color:#1F497D'><o:p></o:p></span></span></p>
<p class=MsoPlainText><span style='font-size:12.0pt'>CITE<o:p></o:p></span></p>
</div>
