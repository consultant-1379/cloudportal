<?php
// Datatable initialization
echo $this->Html->script('OrgVdcs/datatable');

?>
<h1>OrgVdc Allocation Models</h1>
<hr />
<table id="orgvdcs_table" class="datatable">
    <thead>
        <tr>
            <th>Allocation Settings</th>
            <th>Name</th>
            <th>Allocation Model</th>
            <th>Guaranteed Memory</th>
            <th>Guaranteed Cpu</th>
        </tr>
    </thead>
    <tbody>
        <?php

        foreach ($orgvdcs as $orgvdc) {
            ?>
            <tr>
                <td><?php
                // Initially set the status to NOK until proven otherwise
                $status="<font color='red'>NOK</font>";

                // If its the pay as you go model and the guarantees are 0, its OK
                if (($orgvdc['allocationmodel'] == "AllocationVApp") && ($orgvdc['resourceguaranteedmemory'] == "0") && ($orgvdc['resourceguaranteedcpu'] == "0"))
                {
                        $status="OK";
                }
                echo "$status";
                ?></td>
                <td><?php echo $orgvdc['name']; ?></td>
                <td><?php
                        // Give user friendly versions of the allocation model names
                        if ($orgvdc['allocationmodel'] == "AllocationVApp")
                        {
                                echo "Pay As You Go";
                        } elseif ($orgvdc['allocationmodel'] == "AllocationPool") {
                                echo "Allocation Pool";
                        } else {
                                echo $orgvdc['allocationmodel'];
                        }
                ?></td>
                <td><?php echo ($orgvdc['resourceguaranteedmemory'] * 100) . "%"; ?></td>
                <td><?php echo ($orgvdc['resourceguaranteedcpu'] * 100) . "%"; ?></td>
            </tr>
            <?php
        }
        ?>
    </tbody>      
</table>
