<div class=WordSection1>
    <p class=MsoPlainText><span lang=EN-US style='mso-fareast-language:EN-IE'>Dear <?php echo $vappUser; ?>,<o:p></o:p></span></p>
    <p class=MsoPlainText><span style='color:black'><o:p>&nbsp;</o:p></span></p>
    <p class=MsoPlainText>Your vApp was added to the catalog &quot;<b><span style='color:red'><?php echo $catName; ?></span></b>&quot;  successfully as  &quot;<b><span style='color:red'><?php echo $newTemplateName; ?></span></b>&quot;.<o:p>
                
    </o:p></p>
    <p class=MsoPlainText>Please login to the <a href="<?php echo $url; ?>">Cloud Portal</a> to manipulate it.<o:p></o:p></p>
    <?php if(isset($modifyLease)): ?>    
    <p class=MsoPlainText><o:p>&nbsp;</o:p></p>
    <p class=MsoPlainText>Click <a href="<?php echo $modifyLease; ?>">here</a> to modify the lease.<o:p></o:p></p>
    <?php endif ?>
    <p class=MsoPlainText><span style='color:black'><o:p>&nbsp;</o:p></span></p>
    <p class=MsoPlainText>Kind regards,<o:p></o:p></p>
    <p class=MsoPlainText><span style='color:black'>CITE<o:p></o:p></span></p>
    <p class=MsoPlainText><o:p>&nbsp;</o:p></p>
</div>
