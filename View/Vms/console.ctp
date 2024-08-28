<?php
	echo $this->Html->script('Common/spin.min.js');
	echo $this->Html->script('Vms/console');
	echo $this->Html->css('Vms/console', null, array('inline' => false));
?>
<div style="color:white;"><?php echo $title_for_layout; ?></div>
<div id="loading_div" style="color:white;"></div>
<div class="pluginActions" style="display:none;float:left;height:60px;">
	<button id="fullscreen" style="float:left;" title="Fullscreen" class="tooltip"></button>
	<button id="ctrlaltdel" style="float:left;" title="Send ctrl-alt-del" class="tooltip"></button>
	<div id="breakout_helper" style="display:none;color:white;float:left;margin-left:10px">Press CTRL-ALT to release VM</div>
</div>
<div style="border: solid 1px grey;width:720px;height:400px;float:left;clear:left;" id="pluginPanel"></div>


<div id="accordion" style="float:left;clear:both;width:720px;" class='pluginActions'>
                <h3>Manage CDs/DVDs</h3>
                <div>
		<h4>Choose a local iso file to mount onto the VM</h4>
                <div id="remote_resources_container" style="float:left;width:300px;height:150px;margin:10px;padding:5px;" class="rounded_border">
                        <div style="float:left;width:100%;">
                                <label for="virtual_drive_select" style="margin-right:5px;width:100px;"><b>Virtual Drive:</b></label>
                                <select id="virtual_drive_select" style="width:130px;margin-right:5px;"></select>
                                <button id="disconnect_cdrom_button" disabled title="Eject" class="tooltip"></button>
                        </div>
			<br>
			<hr/>
                        <div style="float:left;width:100%;">
				<div style="float:left;margin-right:5px;width:100px;" ><b>Drive Status:</b></div>
                                <div style="float:left;" id="virtual_info"></div>
				<div style="float:left;" id="spinner"></div>
                        </div>
                </div>
                <div id="local_resources_container" style="float:left;width:300px;height:150px;margin:10px;padding:5px;" class="rounded_border">
                        <div id="local_resources_tabs" style="float:left;width:295px;height:140px;">
                                <ul>
					<li><a href="#local_isos">Local Isos</a></li>
                                        <li><a href="#local_drives">Local Drives</a></li>
					<li><a href="#catalog_isos">Catalog Isos</a></li>
                                </ul>
                                <div id="local_drives" style="float:left;">
                                        <label for="local_drive_select" style="margin-right:5px;width:80px;"><b>Local Drive:</b></label>
                                        <select id="local_drive_select" style="width:60px;margin-right:5px;"></select>
                                        <button id="connect_cdrom_button" title="Mount the selected local drive onto the virtual CD/DVD Drive" class="tooltip"></button>

                                </div>
                                <div id="local_isos" style="float:left;">
                                        <label for="file_select" style="margin-right:5px;width:80px;"><b>File Path:</b></label>
                                        <input type="text" id="file_select" title="Give the full path to an iso on your machine" class="tooltip" style="width:240px;"/>
                                        <button id="connect_file_button" title="Mount the selected iso file onto the virtual CD/DVD Drive" class="tooltip"></button>
                                </div>
				<div id="catalog_isos" style="float:left;">
					<label for="catalog_list" style="margin-right:5px;width:80px;"><b>Catalog:</b></label>
					<select id="catalog_list" style="width:250px;margin-right:5px;"></select>
					<br>
					<label for="media_list" style="margin-right:5px;width:80px;"><b>Media:</b></label>
					<button id="refresh_media_button" title="Refresh Media List" class="tooltip"></button>
					<select id="media_list" style="width:250px;margin-right:5px;"></select>
					<button id="connect_media_button" title="Mount the select iso file onto the virtual CD/DVD Drive" class="tooltip"></button>
				</div>
                        </div>
                </div>
                <div id="local_info" style="height:20px;width:600px;margin:10px;clear:both;">Status: Nothing to report</div>
                </div>
        </div>

<div style="display:none;color:white;" id="vmrc_exe_div">
	You do not have the VMWare Remote Console Plugin Installed. Please download and install the appropriate vmrc installer file for your OS from the links below first and try again.
	<br>
	<br>
	Windows
	<br>
	<button class="download_plugin" onClick="window.open('<?php echo $console_details['vmrc_exe_url']; ?>')">Download VMWare Remote Console Plugin For Windows</button>
	<br>
	<br>
	Linux i386 (Right Click - Save As)
	<br>
	<a href="<?php echo $console_details['vmrc_exe_url_linux_i386']; ?>">Linux i386</a>
	<br>
	<br>
	Linux x86_64 (Right Click - Save As)
	<br>
	<a href="<?php echo $console_details['vmrc_exe_url_linux_x86_64']; ?>">Linux x86_64</a>
</div>
<div style="display:none;">
	<input type="text" id="connect_host" value="<?php echo $console_details['ip']; ?>" />
	<input type="text" id="connect_ticket" value="<?php echo $console_details['ticket']; ?>" />
	<input type="text" id="connect_vmid" value="<?php echo $console_details['mof']; ?>" />
	<input type="text" id="full_vmid" value="<?php echo $full_vmid; ?>" />
</div>
