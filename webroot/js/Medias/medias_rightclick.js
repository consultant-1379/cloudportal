
$(function(){

    // Decide on right click contents
    var items = {};
    if ($("#org_permw").length > 0){
        items["delete"] = {
            name: "Delete", 
            icon: "delete"
        };
    }
	
    $.contextMenu({
        selector: '.context-menu-one', 
        callback: function(key, opt){
            var id=opt.$trigger.attr("id");
            var media_name=opt.$trigger.find('td:first').text();
            if((key == 'delete')){
                var answer=confirm("Are you sure you want to delete this media '" + media_name + "' ?");
                if (answer)
                {
                    var url=encodeURI("/Medias/delete/media_id:" + id);
                    window.location=url;
                }
            }
		
        },
        items: items
    });
    
//$('.context-menu-one').on('click', function(e){
//    console.log('clicked', this);
//})
});
        
