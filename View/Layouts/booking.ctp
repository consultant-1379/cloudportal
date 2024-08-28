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
        echo $this->Html->css('staticfiles/bootstrap.min');
        echo $this->Html->css('staticfiles/datatables.1.9.4/datatable');
        echo $this->Html->css('staticfiles/datatables.1.9.4/jquery.dataTables_themeroller');
        echo $this->Html->css('staticfiles/jquery_contextmenu.1.5.22/jquery.contextMenu');
        echo $this->Html->css('staticfiles/bookingtheme/jquery-ui-1.10.4.custom.min');
        echo $this->Html->css('staticfiles/TableTools-2.2.4/dataTables.tableTools.min.css');
        echo $this->Html->css('Bookings/general');
        echo $this->fetch('css');

        // Javascript
        echo $this->Html->script('staticfiles/jquery-1.11.0.min');
        echo $this->Html->script('staticfiles/bootstrap.min');
        echo $this->Html->script('staticfiles/jquery-ui-1.10.3.custom.min');
        echo $this->Html->script('staticfiles/jquery.dataTables.1.9.4.min.js');
        echo $this->Html->script('staticfiles/jquery.contextMenu.1.5.22');
        echo $this->Html->script('staticfiles/TableTools-2.2.4/dataTables.tableTools.min.js');
        echo $this->Html->script('Portal/general');
        echo $this->fetch('script');
        ?>
        <?php
        if (!isset($page_for_layout)) {
            $page_for_layout = "";
        }
        ?>
    </head>
    <body>
        <div id="page">
           <div class="before" ></div>
           <div class="navbar navbar-default navbar-static-top" role="navigation">
             <div class="navbar-header">
              <span class="navbar-brand">
                  <span><img class="brand-logo" src="/images/econ01.svg"></span>
                    PDU NAM Test Environment on Demand
               </span>
             </div>
             <div id="navbar" class="navbar-collapse collapse">
                 <ul class="nav navbar-nav">
                    <li>&nbsp;&nbsp;&nbsp;&nbsp;</li>
                    <li>
                        <a href="/Bookings/bookings"<?php
                            if ($page_for_layout == "book") {
                                echo " class='linkselected'";
                            }
                        ?>>Bookings</a>
                    </li>
                    <li>
                        <a href="/Bookings/reports/">Reports</a>
                    </li>
                    <?php
                        if ($current_user['is_admin']) {
                    ?>
                    <li class="dropdown">
                          <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">Admin<span class="caret"></span></a>
                          <ul class="dropdown-menu" role="menu">
                            <li>
                               <a href="/Bookings/admin"<?php
                                if ($page_for_layout == "admin") {
                                echo " class='linkselected'";
                                }
                               ?>>Pool Usage</a>
                            </li>
                            <li>
                               <a href="/Bookings/queue">Queue</a>
                            </li>
                            <li>
                               <a href="/Groups/">Groups</a>
                            </li>
                          </ul>

                   </li>
                   <?php
                     }
                    ?>
                    <li>
                        <a href="http://jira-oss.lmera.ericsson.se/browse/CIS" target="_blank"<?php
                        if ($page_for_layout == "support") {
                            echo " class='linkselected'";
                        }
                        ?>>Support</a>
                    </li>
                    <li class="dropdown">
                       <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">Team<span class="caret"></span></a>
                       <ul class="dropdown-menu" role="menu">
                         <li>
                            <a href="/Bookings/select_team/">Change Team</a>
                         </li>
                       </ul>
                    </li>
                 </ul>
                 <ul class="nav navbar-nav navbar-right">

                    <li>
                        <a class="help" title="Help" target="_blank"
                        <?php
                           if ($current_user['is_admin']) {
                        ?>
                              href="http://confluence-nam.lmera.ericsson.se/display/CIAxis/Test+Environment+on+Demand+Admin+Guide"
                        <?php  } else {
                        ?>
                              href="http://confluence-nam.lmera.ericsson.se/display/CIE/Test+Environment+on+Demand+User+Guide"
                        <?php
                           }
                        ?>><img src="/images/help_black_16px.svg"> Help</a>
                    </li>
                    <li>&nbsp;</li>
                    <?php

                    if ($logged_in) {
                        ?>
                        <li>
                           <a class="btn btn-default userButton-button" title="Logout" href="/Users/logout">
                              <img src="/images/user_black_16px.svg">&nbsp;<?php echo $current_user['username'] ?>&nbsp;&nbsp;<img src="/images/logout_black.svg">
                            </a>
                        </li>
                        <?php
                    } else {
                        ?>
                        <li>
                            <a class="btn btn-default userButton-button" title="Login" href="/Users/login" >
                               <img src="/images/logout_black.svg">
                            </a>
                        </li>
                        <?php
                    }
                    ?>
                 </ul>
               </div>
            </div>
            <?php
            if (isset($flagtext)) {
            ?>
                <div class = "marquee">
                <marquee  scrolldelay="100"><?php echo $flagtext; ?> </marquee>
                </div>
            <?php
            }
            ?>
            <div class="container-fluid" >
                <?php echo $this->Session->flash(); ?>
            <?php
            // Only show the not authorized messages when logged in already
            $flash = $this->Session->flash('auth');
            if ($logged_in) {
                if($flash){
            ?>
              </br>
              </br>
              <div class="flash_bad"> <?php echo $flash;?></div>
            <?php
               }
            }
            ?>
            </div>
            <div class="container-fluid" id="content">
                <?php echo $content_for_layout; ?>
            </div>
        </div>
    </body>
</html>
