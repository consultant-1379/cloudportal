$(document).ready(function(){


    $('.doubleclick').dblclick(function(e) {
        var id=$(this).attr("id");
        var url=encodeURI("/Vapps/index/orgvdc_id:" + id);
        window.location=url;

    });

});
