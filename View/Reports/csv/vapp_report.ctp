<?php
    $title_string = '';
    foreach($titles as $title)
    {
        $title_string = $title_string . $title . ',';
    }
    $title_string = rtrim($title_string,',');
    echo $title_string . "\n";

    foreach($Vapp as $vapp)
    {
        $vapp_string = '';
        foreach ($vapp as $key => $entry)
        {
            if ($key == 'creation_date')
            {
                $entry = date('d/m/Y H:i', strtotime($entry));
            }
            $vapp_string = $vapp_string . $entry . ',';
        }
        $vapp_string = rtrim($vapp_string,',');
        echo $vapp_string . "\n";
    }
?>
