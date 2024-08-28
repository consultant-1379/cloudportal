$(document).ready(function(){
		
                
    $('.doubleclick').dblclick(function(e) {
        var id=$(this).attr("id");
        //var orgvdc_id=$("#orgvdc_id").html();
        var url=encodeURI("/Vms/vapptemplate_index/vapp_template_id:" + id);
        window.location=url;

    });

});

