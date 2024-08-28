<?php
	echo $this->Html->script('Vms/disks_datatable');
?>
<table id="disks_table" class="datatable">
    <thead>
        <tr>
            <th>Controller No</th>
            <th>Disk No</th>
            <th>Disk Size</th>
        </tr>
    </thead>
    <tbody>
	<?php
		foreach ($disk_details as $disk) {
			$disk_size=$disk['disk_capacity_mb'];
			$size_type = "MB";
			if ($disk_size % 1024 == 0) {
				$disk_size = $disk_size / 1024;
				$size_type = "GB";
			}
	?>
		<tr>
                	<td><?php echo $disk['controller_no']; ?></td>
			<td><?php echo $disk['disk_no']; ?></td>
			<td><?php
				echo $disk_size . " " . $size_type; ?>
			</td>
		</tr>
	<?php
	}
	?>
    </tbody>
</table>
