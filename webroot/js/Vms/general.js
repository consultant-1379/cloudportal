$(document).ready(function(){
    
    $('.monitor_button').button();
    $('.monitor_button').click(function() {
        var id = $(this).parents("tr:first").attr("id");
        var id_escaped = escapeit(id);
        $('#' + id_escaped + " .vapp_status").html("Retrieving..");
        get_tasks(id, "/Vms/tasks/vm_id:");
    });

    $('.cpu_combobox').dblclick(function(event) {
        event.stopPropagation();
    });
    
    
    $('.cpu_combobox').change(function(event) {
        event.stopPropagation();
        var id=$(this).parents("tr:first").attr("id");
        var orgvdc_id=$("#orgvdc_id").html();
        var vapp_id=$("#vapp_id").html();
        var cpu_count=$(this).val();
        
        var url=encodeURI("/Vms/set_cpu_count/vm_id:" + id + "/vapp_id:" + vapp_id + "/orgvdc_id:" + orgvdc_id + "/cpu_count:" + cpu_count);
        
        window.location=url;
    });
    $('.set_memory_button').button();
    $('.set_memory_button').click (function(){
        var id=$(this).parents("tr:first").attr("id");
        var id_escaped=escapeit(id);
        var orgvdc_id=$("#orgvdc_id").html();
        var vapp_id=$("#vapp_id").html();
        var memory_size_field=$("#" + id_escaped + " .mem_size_field");
        var memory_size=memory_size_field.val();
        
        var memory_type_combobox=$("#" + id_escaped + " .mem_type_combobox");
        var memory_type=memory_type_combobox.val();
        
        if (memory_type == "GB")
        {
            memory_size=memory_size * 1024;
        }
     
    var url=encodeURI("/Vms/set_memory_mb/vm_id:" + id + "/vapp_id:" + vapp_id + "/orgvdc_id:" + orgvdc_id + "/memory_mb:" + memory_size);
    window.location=url;
    });

    $("#tabs").tabs({active: 0});
    $("#vapp_diagram_link").unbind('click');

});
