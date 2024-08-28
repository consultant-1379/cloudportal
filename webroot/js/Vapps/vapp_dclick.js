$(document).ready(function(){

    $('.doubleclick').dblclick(function(event) {

        var id=$(this).attr("id");
        var orgvdc_id=$("#orgvdc_id").html();
        var url=encodeURI("/Vms/vapp_index/vapp_id:" + id + "/orgvdc_id:" + orgvdc_id);
        window.location=url;
        
    });

});