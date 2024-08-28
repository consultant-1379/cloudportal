<?php

// General initialization
echo $this->Html->script('Vapps/rename');
?>
<h1>Rename vApp</h1>
<?php

echo $this->Form->create('Vapp');
echo $this->Form->input('name', array('label' => "vApp Name",'default' => $name));
echo $this->Form->end(array('label' => 'Rename','id' => 'rename_button'));
?>
<div id="rename_div" style="display:none;">
<b>Please wait, this can take a few seconds to complete....</b>
</div>
