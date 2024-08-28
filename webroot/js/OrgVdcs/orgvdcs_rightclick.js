
$(function(){

    // Decide on right click contents
    var items = {};
    items["open"] = {
        name: "Open", 
        icon: "open"
    };
    if ($("#is_admin").length > 0){
        items["seperator3"] = "---------";
        items["edit"] = {
            name: "Edit", 
            icon: "edit"
        };
    }
	
    $.contextMenu({
        selector: '.context-menu-one', 
        callback: function(key, opt){
            var id=opt.$trigger.attr("id");
            if (key == "open" )
            {
                var url=encodeURI("/Vapps/index/orgvdc_id:" + id);
                window.location=url;
            } else if(key == 'edit'){
                var url=encodeURI("/OrgVdcs/edit/orgvdc_id:" + id);
                window.location=url;
            }
		
        },
        items: items
    });
    
//$('.context-menu-one').on('click', function(e){
//    console.log('clicked', this);
//})
});
        
