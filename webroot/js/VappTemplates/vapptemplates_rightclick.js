
$(function(){

    // Decide on right click contents
    var items = {};
    items["open"] = {
        name: "Open", 
        icon: "open"
    };
    items["seperator"] = "---------";
    items["add"] = {
        name: "Add To My Cloud", 
        icon: "deploy"
    };
    items["seperator2"] = "---------";
    if ($("#org_permw").length > 0){
        items["delete"] = {
            name: "Delete", 
            icon: "delete"
        };
	items["seperator3"] = "---------";
        items["rename"] = {
            name: "Rename",
            icon: "edit"
        };
    }

    $.contextMenu({
        selector: '.context-menu-one', 
        //callback: function(key, options) {
        //    var m = "clicked: " + key;
        //    window.console && console.log(m) || alert(m); 
        //},
        callback: function(key, opt){

            var id=opt.$trigger.attr("id");
            var vapptemplate_name=opt.$trigger.find('td:first').text();
            var org_id=$("#org_id").html();

            if (key == "open" )
            {
                var url=encodeURI("/Vms/vapptemplate_index/vapp_template_id:" + id + "/org_id:" + org_id);
                window.location=url;
            }
            else if(key == 'add'){
                var url=encodeURI("/VappTemplates/deploy/vapp_template_id:" + id);
                window.location=url;
            }
	    else if(key == 'rename'){
		var url=encodeURI("/VappTemplates/rename/vapp_template_id:" + id + "/org_id:" + org_id);
		window.location=url;
	    }
            else if((key == 'delete')){
                var answer=confirm("Are you sure you want to delete this template '" + vapptemplate_name + "' ?");
                if (answer)
                {
                    var url=encodeURI("/VappTemplates/delete/vapp_template_id:" + id + "/org_id:" + org_id);
                    window.location=url;
                }
            }
        //alert("Clicked on " + key + " on element " + opt.$trigger.attr("id")); 
        },
        items: items
    });
    
    $('.context-menu-one').on('click', function(e){
        //console.log('clicked', this);
        })
});
        
