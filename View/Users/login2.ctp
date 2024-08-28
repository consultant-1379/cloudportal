<div id='loginForm'>
<?php
echo $this->Form->create('Users', array('action' => 'login'));
echo $this->Form->input('username');
echo $this->Form->input('password');
$login_types = array('0' => 'Local', '1' => 'Ldap');
echo $this->Form->input('source_type', array('options' => $login_types, 'default' => '2', 'label' => 'Login Type'));
echo $this->Form->end('Login');
?>
</div>
