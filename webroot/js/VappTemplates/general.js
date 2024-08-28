$(function() {

    // The monitor status button
    $('.monitor_button').button();
    $('.monitor_button').click(function() {
        var id = $(this).parents("tr:first").attr("id");
        var id_escaped = escapeit(id);
        $('#' + id_escaped + " .vapp_status").html("Retrieving..");
        get_tasks(id, "/Vapps/tasks/vapp_id:");
    });

    $("#tabs").tabs({active: 0});
    $("#media_tab_link").unbind('click');
});
