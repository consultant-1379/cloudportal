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
             $portal_type = 'Cloud Provisioning Portal';
        ?>
        <title><?php echo $title_for_layout . " - " . $portal_type; ?></title>
        <?php
        echo $this->Html->meta('icon');
        //CSS
        echo $this->Html->css('staticfiles/bootstrap.min');
        echo $this->Html->css("staticfiles/redmondericsson/jquery-ui-1.10.2.custom.min");
        echo $this->Html->css('Portal/general');
        echo $this->Html->css('Portal/forms');
        echo $this->Html->css('staticfiles/datatables.1.9.4/datatable');
        echo $this->Html->css('staticfiles/datatables.1.9.4/jquery.dataTables_themeroller');
        echo $this->Html->css("staticfiles/jquery_contextmenu.1.5.22/jquery.contextMenu");
        echo $this->fetch('css');

        // Javascript
        echo $this->Html->script('staticfiles/jquery-1.11.0.min');
        echo $this->Html->script('staticfiles/bootstrap.min');
        echo $this->Html->script('staticfiles/jquery-ui-1.10.3.custom.min');
        echo $this->Html->script('staticfiles/jquery.dataTables.1.9.4.min.js');
        echo $this->Html->script('staticfiles/jquery.contextMenu.1.5.22');
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
            <div id="page_header" style="overflow: hidden;">
                <img alt="elogo" class="elogo" src="/css/staticfiles/images/General/elogo.png" />
                <ul class="links left" style="float:left;">
                   <li>
                       <a href="/OrgVdcs/"<?php
                         if ($page_for_layout == "home") {
                            echo " class='linkselected'";
                          }
                       ?>>MY CLOUD</a>
                   </li>
                   <li>
                      <a href="/Catalogs/"<?php
                           if ($page_for_layout == "catalogs") {
                               echo " class='linkselected'";
                           }
                     ?>>CATALOGS</a>
                   </li>
                   <li>
                        <a href="/Reports/"<?php
                        if ($page_for_layout == "reports") {
                            echo " class='linkselected'";
                        }
                        ?>>REPORTS</a>
                    </li>
                   <li>
                        <a href="http://jira-oss.lmera.ericsson.se/browse/CIS" target="_blank"<?php
                        if ($page_for_layout == "support") {
                            echo " class='linkselected'";
                        }
                        ?>>SUPPORT</a>
                   </li>

			       <?php
				      if ($current_user['is_admin']) {
			       ?>
			       <li>
				      <a href="/Groups/">GROUPS</a>
                   </li>
			      <?php
		           }
			      ?>
                    <?php
                    if ($logged_in) {
                        ?>
                        <li>
                            <a href="/Users/logout">
                                LOGOUT (<?php echo $current_user['username'] ?>)
                            </a>
                        </li>
                        <?php
                    } else {
                        ?>
                        <li>
                            <a href="/Users/login" class="linkselected">
                                LOGIN
                            </a>
                        </li>
                        <?php
                    }
                    ?>
                </ul>
            </div>
            <img alt="ebottomgrad" class="egrad" src="/css/staticfiles/images/General/ebottomgrad.jpg" width="100%" height="3px" />
            <div id="navigation" style="clear:left;">
            </div>
            <div>
                <?php echo $this->Session->flash(); ?>
            </div>
            <?php
            // Only show the not authorized messages when logged in already
            $flash = $this->Session->flash('auth');
            if ($logged_in) {
                echo $flash;
            }
            ?>
            <div>
            </div>
            <div id="main">
                <?php echo $content_for_layout; ?>
            </div>
        </div>
    </body>
</html>
