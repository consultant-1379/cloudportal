
$(function(){

    // Decide on right click contents
    var items = {};
    
    
    if ($("#orgvdc_permw").length > 0){
        items["poweron"] = {
            name: "Power On", 
            icon: "poweron"
        };
        items["seperator"] = "---------";
        items["shutdown"] = {
            name: "Guest Shutdown", 
            icon: "guestshutdown"
        };
        items["poweroff"] = {
            name: "Hard Poweroff", 
            icon: "hardpoweroff"
        };
	items["seperator4"] = "---------";
        items["console"] = {
            name: "Open Console",
            icon: "console"
        };
    }

    items["disk"] = {
            name: "Disk Details",
            icon: "disk"
        };

    if ($("#orgvdc_permw").length > 0){
        items["seperator3"] = "---------";
        items["delete"] = {
            name: "Delete",
            icon: "delete"
        };
    }
	
    $.contextMenu({
        selector: '.context-menu-one', 
        callback: function(key, opt){
            var id=opt.$trigger.attr("id");
            var vm_name=opt.$trigger.find('td:first').text();
            var vapp_id=$("#vapp_id").html();
            var orgvdc_id=$("#orgvdc_id").html();
            if((key == 'poweron')){
                var url=encodeURI("/Vms/power/vm_id:" + id + "/vapp_id:" + vapp_id + "/power_action:poweron" + "/orgvdc_id:" + orgvdc_id);
                window.location=url;
                
            }else if((key == 'shutdown')){
                var answer=confirm("Are you sure you want to shutdown this vm '" + vm_name + "' ?");
                if (answer)
                {
                    var url=encodeURI("/Vms/power/vm_id:" + id + "/vapp_id:" + vapp_id + "/power_action:shutdown" + "/orgvdc_id:" + orgvdc_id);
                    window.location=url;
                }
            }else if((key == 'poweroff')){
                var answer=confirm("Are you sure you want to hard poweroff this vm '" + vm_name + "' ? The operating system may come up in a bad state!");
                if (answer)
                {
                    var url=encodeURI("/Vms/power/vm_id:" + id + "/vapp_id:" + vapp_id + "/power_action:poweroff" + "/orgvdc_id:" + orgvdc_id);
                    window.location=url;
                }
            
            }else if((key == 'delete')){
                var answer=confirm("Are you sure you want to delete this vm '" + vm_name + "' ?");
                if (answer)
                {
                    var url=encodeURI("/Vms/delete/vm_id:" + id + "/vapp_id:" + vapp_id + "/orgvdc_id:" + orgvdc_id);
                    window.location=url;
                }
            }
            else if((key == 'console')){
			$('#'+escapeit(id)).find('.console_link').get(0).click();
            }
            else if((key == 'disk')){
		var url=encodeURI("/Vms/disks/vm_id:" + id);
		$( "<div title='Disk Details'>Please wait..</div>" ).load(url).dialog({
			minWidth: 400,
			modal: true,
			close: function (e) {
				$(this).empty();
				$(this).dialog('destroy');
			}
		});
            }
        },
        items: items
    });
});
