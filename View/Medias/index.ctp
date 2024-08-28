<?php
echo $this->Html->script('Medias/general');
echo $this->Html->script('Medias/datatable');
echo $this->Html->script('Medias/medias_rightclick');
echo $this->Html->script("FileUploads/jquery.fileupload.js");
?>
<?php
if ($current_user["is_admin"] || (isset($current_user['permissions'][$this->passedArgs['org_id']]['write_permission']) && $current_user['permissions'][$this->passedArgs['org_id']]['write_permission'] )) {
    echo "<div id='org_permw' style='display:none;'></div>";
}
?>

<?php
function formatBytes($bytes, $precision = 2) { 
    $units = array('B', 'KB', 'MB', 'GB', 'TB'); 

    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 

    // Uncomment one of the following alternatives
     $bytes /= pow(1024, $pow);
     //$bytes /= (1 << (10 * $pow)); 

    return round($bytes, $precision) . ' ' . $units[$pow]; 
}
?>

<h1><?php echo $title_for_layout; ?></h1>
<hr/>
<?php echo $this->Html->link("Browse Other Catalogs", array('controller' => 'Catalogs', 'action' => 'index', 'redirect' => "no")); ?>
<br />
<br />
<div id="tabs">
  <ul>
    <li><?php echo $this->Html->link("vApp Templates", array('controller' => 'VappTemplates', 'action' => 'index', 'catalog_name' => $this->passedArgs['catalog_name'], 'org_id' => $this->passedArgs['org_id']),array('id' => 'vapp_templates_tab_link')); ?></li>
    <li><a href="#tabs-2">Media</a></li>
  </ul>
<div id="tabs-1">
</div>
<div id="tabs-2">
<?php
if ($current_user["is_admin"] || (isset($current_user['permissions'][$this->passedArgs['org_id']]['write_permission']) && $current_user['permissions'][$this->passedArgs['org_id']]['write_permission'] )) {
?>
    <div style="display:none;" id="catalog_name"><?php echo $catalog_name ?></div>
<div id="uploadisodiv">
    <span><b>Upload ISO File</b><span>
	<br>
    <input id="fileupload" type="file" name="files[]" >
    <div id="progress" class="progress"></div>
    <div id="files" class="files"></div>
</div>
	<hr/>
<?php
}
?>
<table id="medias_table" class="datatable">
    <thead>
        <tr>
            <th>Media Name</th>
	    <th>Status</th>
	    <th>Date Created</th>
	    <th>Size</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($medias as $media): ?>
            <tr class="context-menu-one box menu-1" id="<?php echo $media['media_id']; ?>">
                <td><?php echo $media['name']; ?></td>
		<td><?php echo $media['status']; ?></td>
		<td><?php echo date('d/m/Y H:i', strtotime($media['creation_date'])); ?></td>
		<td><?php echo formatBytes($media['storage_used']); ?></td>
            </tr>
        <?php endforeach; ?>


    </tbody> 
</table>
</div>
<!--[if IE 7 ]>    <div id='iediv' class="ie7"></div> <![endif]-->
<!--[if IE 8 ]>    <div id='iediv' class="ie8"></div> <![endif]-->
<!--[if IE 9 ]>    <div id='iediv' class="ie9"></div> <![endif]-->
</div>
