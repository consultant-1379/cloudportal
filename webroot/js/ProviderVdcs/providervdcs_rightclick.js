$(function(){
    // Decide on right click contents
    var items = {};
    items["edit"] = {
        name: "Edit",
        icon: "edit"
    };

    $.contextMenu({
        selector: '.context-menu-one',
        callback: function(key, opt){
            var id=opt.$trigger.attr("id");
            if(key == 'edit'){
                var url=encodeURI("/ProviderVdcs/edit/providervdc_id:" + id);
                window.location=url;
            }
        },
        items: items
    });
});
