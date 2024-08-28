<?php

class SoftwareType extends AppModel {

    var $name = 'SoftwareType';
    var $hasMany = 'SoftwareRelease';

}

?>
