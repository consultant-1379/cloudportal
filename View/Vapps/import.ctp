<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

echo $import_variables['imported'] . " vApps Imported into the database<br>";
echo $import_variables['database'] . " vApps exist in the Database<br>";
echo $import_variables['vcd'] . " vApps exist in the vCloud Array<br>";
echo $import_variables['deleted'] . " vApps deleted from the database<br>";
echo $import_variables['changed'] . " vApps modified in the database<br>";
echo "Current User is " . $current_user['username'];
?>
