<?php if (Configure::read('pooling_enabled'))
    {
?>
<div id="container">
 <div class="login">
   <div class="login-inner">
     <div class="login-ericssonLogo"></div>
     </br>
     <div class="login-title">Test Environment on Demand</div>
     </br>
     </br>
     <div class="login-inputWrap">
        <?php
           $options = array(
                      'label' => '',
                      'name' => 'sppLogin',
                      'div' => array(
                      'id' => 'sppLogin',
                       )
           );
           echo $this->Form->create();
           echo $this->Form->input('username', array('placeholder' => 'Username', 'label' => ''));
           echo $this->Form->input('password', array('placeholder' => 'Password', 'label' => ''));
           echo $this->Form->end($options);
        ?>
        </br>
        </br>
        <span id="login-failure"><?php echo $this->Session->flash(); ?></span>
     </div>
    </div>
  </div>
<?php } else { ?>
<h1>Please Login</h1>
<?php
$options = array(
    'label' => 'Login',
    'name' => 'sppLogin',
    'div' => array(
        'id' => 'sppLogin',
    )
);
echo $this->Form->create();
echo $this->Form->input('username');
echo $this->Form->input('password' );
echo $this->Form->end($options);
?>
<?php } ?>
