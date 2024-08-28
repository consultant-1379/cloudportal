
$(function(){

    // Decide on right click contents
    var items = {};
    items["open"] = {
        name: "Open",
        icon: "open"
    };
    items["seperator"] = "---------";
    if ($("#orgvdc_permw").length > 0){
        items["start"] = {
            name: "Start",
            icon: "poweron"
        };
        items["stop"] = {
            name: "Stop",
            icon: "stop"
        };
        items["seperator2"] = "---------";
        items["poweroff"] = {
            name: "Hard Poweroff",
            icon: "hardpoweroff"
        };
        items["seperator3"] = "---------";

    }
    items["add"] = {
        name: "Add To A Catalog",
        icon: "save"
    };
    items["recompose"] = {
        name: "Recompose",
        icon: "recompose"
    };
    //items["seperator3"] = "---------";
    if ($("#orgvdc_permw").length > 0){
      //  items["edit"] = {
         //   name: "Edit",
          //  icon: "edit"
       // };
        items["seperator4"] = "---------";
        items["delete"] = {
            name: "Delete",
            icon: "delete"
        };
        items["poweroff_delete"] = {
            name: "Poweroff And Delete",
            icon: "poweroffdelete"
        };
        items["seperator5"] = "---------";
        items["rename"] = {
            name: "Rename",
            icon: "edit"
        };

    }

    $.contextMenu({
        selector: '.context-menu-one',
        callback: function(key, opt){
            var id=opt.$trigger.attr("id");
            var vapp_name=opt.$trigger.find('td:first').text();
            var orgvdc_id=$("#orgvdc_id").html();
            if (key == "open" )
            {
                var url=encodeURI("/Vms/vapp_index/vapp_id:" + id + "/orgvdc_id:" + orgvdc_id);
                window.location=url;
            } else if(key == 'start'){
                var url=encodeURI("/Vapps/power/vapp_id:" + id + "/power_action:start" + "/orgvdc_id:" + orgvdc_id);
                window.location=url;
            }else if((key == 'stop')){
                var answer=confirm("Are you sure you want to stop this vApp '" + vapp_name + "' ?");
                if (answer)
                {
                    var url=encodeURI("/Vapps/power/vapp_id:" + id + "/power_action:stop" + "/orgvdc_id:" + orgvdc_id);
                    window.location=url;
                }
            }else if((key == 'shutdown')){
                var answer=confirm("Are you sure you want to shutdown this vApp '" + vapp_name + "' ?");
                if (answer)
                {
                    var url=encodeURI("/Vapps/power/vapp_id:" + id + "/power_action:shutdown" + "/orgvdc_id:" + orgvdc_id);
                    window.location=url;
                }
            }else if((key == 'poweroff')){
                var answer=confirm("Are you sure you want to hard poweroff this vApp '" + vapp_name + "' ? The operating system may come up in a bad state!");
                if (answer)
                {
                    var url=encodeURI("/Vapps/power/vapp_id:" + id + "/power_action:poweroff" + "/orgvdc_id:" + orgvdc_id);
                    window.location=url;
                }
            }else if((key == 'edit')){
                var url=encodeURI("/Vapps/edit/vapp_id:" + id + "/orgvdc_id:" + orgvdc_id);
                window.location=url;
            }else if((key == 'add')){
                var url=encodeURI("/Vapps/add/vapp_id:" + id);
                window.location=url;
            }else if((key == 'delete')){
                var confirm_text = "Are you sure you want to delete this vApp '" + vapp_name + "' ?";
                if (vapp_name.match(/^Jenkins_/))
                {
                    confirm_text+= "\n\nWARNING: This vApp looks like it was created using the Jenkins Cloud Plugin. Please delete this vApp Slave from Jenkins instead if possible, to keep Jenkins and the Cloud Portal in sync.";
                }
                var answer=confirm(confirm_text);
                if (answer)
                {
                    var url=encodeURI("/Vapps/delete/vapp_id:" + id + "/orgvdc_id:" + orgvdc_id);
                    window.location=url;
                }
            }else if((key == 'poweroff_delete')){
                var confirm_text = "Are you sure you want to Power Off and Delete this vApp '" + vapp_name + "' ?";
                if (vapp_name.match(/^Jenkins_/)){
                    confirm_text+= "\n\nWARNING: This vApp looks like it was created using the Jenkins Cloud Plugin. Please delete this vApp Slave from Jenkins instead if possible, to keep Jenkins and the Cloud Portal in sync.";
                }
                var answer=confirm(confirm_text);
                if (answer){
                    var url=encodeURI("/Vapps/destroy_vapp/vapp_id:" + id + "/orgvdc_id:" + orgvdc_id);
                    window.location=url;
                }

            }else if((key == 'rename')){
                var answer=confirm("Are you sure you want to rename this vApp '" + vapp_name + "' ?");
                if (answer)
                {
                    var url=encodeURI("/Vapps/rename/vapp_id:" + id + "/orgvdc_id:" + orgvdc_id);
                    window.location=url;
                }
            }
            else if((key == 'recompose')){
                var url=encodeURI("/Vapps/recompose_vapp/vapp_id:" + id);
                window.location=url;
            }

        },
        items: items
    });

//$('.context-menu-one').on('click', function(e){
//    console.log('clicked', this);
//})
});

