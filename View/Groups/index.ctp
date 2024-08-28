<?php
        // Datatable initialization
    echo $this->Html->script('Groups/datatable');

	echo $this->Html->script('Groups/general');

    if (Configure::read('pooling_enabled'))
    {
        echo $this->Html->script('Groups/groups');
    }
    // Build up the key value pairs for orgvdc
    $orgvdc_field_array=array();
    foreach ($orgvdcs as $orgvdc) {
        $orgvdc_field_array[$orgvdc['OrgVdc']['vcd_id']] = $orgvdc['OrgVdc']['name'];
    }
    // Build up the key value pairs for org
    $org_field_array=array();
    foreach ($orgs as $org) {
         $org_field_array[$org['Org']['vcd_id']] = $org['Org']['name'];
    }
?>
	<h1>Ericsson Tools</h1>
	<div class="actions">
		<a title="Request new 'Domain Group' using Gordon" target="_blank" href="https://gordon-web.ericsson.se/">Gordon</a>
		<a title="Add / Remove Users and Administrators of Groups" target="_blank" href="https://i-acc.ericsson.se/">Instant Access</a>
	</div>
	<hr/>
	<h1>Group Mappings</h1>
      <?php
       if (!Configure::read('pooling_enabled')) {
       ?>
	    <div class="actions">
                <?php echo $this->Html->link('New Group Mapping', array('action' => 'add')); ?>
        </div>
      <?php } ?>
	<table id="groups_table" class="datatable">
	<thead>
	<tr>
        <?php
            if (!Configure::read('pooling_enabled'))
            {
        ?>
        <th>Description</th>
        <th>OrgvDC Name</th>
        <th>Org Name</th>
        <?php } ?>
        <th>LDAP Group DN</th>
        <?php
            if (!Configure::read('pooling_enabled'))
            {
        ?>
        <th>Read</th>
        <th>Write</th>
        <?php } ?>
        <th>Admin</th>
        <?php
            if (!Configure::read('pooling_enabled'))
            {
        ?>
            <th>Catalogs Read Access Restricted</th>
        <?php } ?>
        <th class="actions">Actions</th>
	</tr>
	</thead>
	<tbody>
	<?php
	foreach ($groups as $group):

		// Check has the group got a valid orgvdc and org
		if (isset($orgvdc_field_array[$group['Group']['vcloudorgvdcid']]))
		{
			$orgvdc_name=$orgvdc_field_array[$group['Group']['vcloudorgvdcid']];
			$valid_orgvdc=true;
		}
		else
		{
			$valid_orgvdc=false;
		}
		if (isset($org_field_array[$group['Group']['vcloudorgid']]))
                {
                        $org_name=$org_field_array[$group['Group']['vcloudorgid']];
                        $valid_org=true;
                }
                else
                {
                        $valid_org=false;
                }
	?>
	<tr>
        <?php
            if (!Configure::read('pooling_enabled'))
            {
        ?>
		<td>
		<?php
			// Build up a friendly description based on orgvdc and r w a permissions
			if ($valid_orgvdc && $valid_org)
			{
				$friendly_name=$orgvdc_name . "-";
				if ($group['Group']['admin_permission'])
	                        {
	                                $friendly_name.="Admin";
	                        }
				else
				{
					if ($group['Group']['write_permission'])
	                                {
	                                        $friendly_name.="RW";
	                                }
					else
					{
						if ($group['Group']['read_permission'])
						{
							$friendly_name.="RO";
						}
					}
				}
				echo $friendly_name;
			}
			else
			{
				echo "<font color='red'>Invalid Group. Delete or Fix.</font>";
			}
		?>
		</td>
		<td>
		<?php
			if ($valid_orgvdc)
			{
				echo $orgvdc_name;
			}
			else
			{
				echo "<font color='red'>Missing OrgvDC</font>";
			}
		?>
		</td>
		<td>
		<?php
			if ($valid_org)
			{
				echo $org_name;
			}
			else
                        {
                                echo "<font color='red'>Missing Org</font>";
                        }
		?>
		</td>
        <?php } ?>
		<td>
		<?php
			$groupCN=ldap_explode_dn($group['Group']['group_dn'] , 1);
			echo $groupCN[0];
		?>
		</td>
        <?php
            if (!Configure::read('pooling_enabled'))
            {
        ?>
        <td><?php echo $group['Group']['read_permission']; ?></td>
        <td><?php echo $group['Group']['write_permission']; ?></td>
        <?php } ?>
        <td><?php echo $group['Group']['admin_permission']; ?></td>
        <?php
            if (!Configure::read('pooling_enabled'))
            {
        ?>
        <td><?php echo $group['Group']['restrict_catalogs']; ?></td>
        <?php } ?>
		<td class="actions">
			    <?php echo $this->Html->link('Edit', array('action' => 'edit', $group['Group']['id'])); ?>
			    <?php echo $this->Form->postLink('Delete', array('action' => 'delete', $group['Group']['id']), array('confirm'=>'Are you sure you want to delete that group?')); ?>
		</td>
	</tr>
	<?php endforeach; ?>
	</tbody>
	</table>
