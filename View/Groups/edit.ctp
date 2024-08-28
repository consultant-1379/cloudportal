<?php
    if (Configure::read('pooling_enabled'))
    {
        echo $this->Html->css('Bookings/forms');
        echo $this->Html->css('Portal/general');
    }
?>
<?php
    if (Configure::read('pooling_enabled')) {
?>
        <h1>Edit Group</h1>
        <div class="layout-bar"><a class="action-back" href="/Groups"><img src="/images/ArrowLeft_black_16px.svg"> Back To Groups</a></div>
<?php }  else { ?>
        <?php echo $this->Html->link('<< Back To Groups', array('action' => 'index'));?>
        <h1>Edit Group</h1>
<?php } ?>

<hr/>
<?php
    echo $this->Form->create('Group');
    echo $this->Form->input('group_dn', array('label' => 'LDAP Group Distinguished Name (e.g. CN=<font color="blue">IEAT-VCD-CI1-Admin</font>,OU=INACC,OU=P001,OU=GRP,OU=Data,DC=ericsson,DC=se )', 'style' => 'width:650px'));
    if (Configure::read('pooling_enabled'))
    {
        echo $this->Form->hidden('vcloudorgvdcid', array('value' => 'bookings'));
        echo $this->Form->hidden('vcloudorgid', array('value' => 'bookings'));
    } else {
        // Build up the key value pairs for orgvdc
        $orgvdc_field_array=array();
        foreach ($orgvdcs as $orgvdc) {
            $orgvdc_field_array[$orgvdc['OrgVdc']['vcd_id']] = $orgvdc['OrgVdc']['name'];
        }
        echo $this->Form->input('vcloudorgvdcid', array('options' => $orgvdc_field_array,'type' => 'select', 'empty' => '-- Select an OrgvDC --', 'label' => "OrgvDC To Give Permission To"));

        // Build up the key value pairs for org
        $org_field_array=array();
        foreach ($orgs as $org) {
            $org_field_array[$org['Org']['vcd_id']] = $org['Org']['name'];
        }
        echo $this->Form->input('vcloudorgid', array('options' => $org_field_array,'type' => 'select', 'empty' => '-- Select an Org --', 'label' => "Org To Give Permission To"));
        echo $this->Form->input('unrestricted', array('options' => $catalog_details, 'multiple' => true, 'selected' => $select_option, 'label' => 'Restrict read access to the Catalogs below (select many by holding down Ctrl)',  'size' => '10', 'style' => 'width:650px'));
        echo $this->Form->input('restrict_catalogs', array('label' => 'Restrict read  access to selected Catalogs'));
        echo $this->Form->input('read_permission');
        echo $this->Form->input('write_permission');
    }
    echo $this->Form->input('admin_permission');
    echo $this->Form->end('Submit');
?>
