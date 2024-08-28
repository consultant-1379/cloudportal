<?php
    $title_string = '';
    foreach($titles as $title)
    {
        $title_string = $title_string . $title . ',';
    }
    $title_string = rtrim($title_string,',');
    echo $title_string . "\n";

    foreach($OrgVdcs as $OrgVdc)
    {
        $orgvdc_string = '';
        foreach ($OrgVdc as $entry)
        {
            $orgvdc_string = $orgvdc_string . $entry . ',';
        }
        $orgvdc_string = rtrim($orgvdc_string,',');
        echo $orgvdc_string . "\n";
    }
?>
