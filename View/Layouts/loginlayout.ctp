<?php
/**
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       Cake.View.Layouts
 * @since         CakePHP(tm) v 0.10.0.1076
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
//$cakeDescription = __d('cake_dev', 'CakePHP: the rapid development php framework');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <?php echo $this->Html->charset(); ?>
        <?php
             $portal_type = 'Test Environment on Demand';
        ?>
        <title><?php echo $title_for_layout . " - " . $portal_type; ?></title>
        <?php
        echo $this->Html->meta('icon');
        //CSS
        echo $this->Html->css('Bookings/forms');
        echo $this->Html->css('Bookings/login');
        echo $this->Html->css("staticfiles/bookingtheme/jquery-ui-1.10.4.custom.min");
        echo $this->Html->css("staticfiles/jquery_contextmenu.1.5.22/jquery.contextMenu");
        echo $this->Html->css('staticfiles/bootstrap.min');
        echo $this->Html->css('Bookings/general');
        echo $this->fetch('css');
        ?>

        <?php
        if (!isset($page_for_layout)) {
            $page_for_layout = "";
        }
        ?>
    </head>
    <body>
        <div>
         <div>
                <?php echo $content_for_layout; ?>
         </div>
       </div>
    </body>
</html>
