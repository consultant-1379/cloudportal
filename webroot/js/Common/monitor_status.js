
function get_tasks(id, url) {
    $.ajax({
        url: url + id,
        type: 'GET',
        cache: false,
        dataType: 'html',
        timeout: 100000,
        beforeSend: function() {
            //alert("Sending");
            //$('#'+id+' .contentarea').html('<img src="/function-demos/functions/ajax/images/loading.gif" />');
        },
        success: function(data, textStatus, xhr) {
            var id_escaped = escapeit(id);

            if ($('#' + id_escaped + ' .vapp_status .task_error').dialog('isOpen') !== true) {

                // Old way to remove dialog if its not appendTo somewhere that we know about
                //$('#' + id_escaped + '_dialog').dialog('destroy').remove();

                // Empty the contents of the vapp_status div
                $('#' + id_escaped + " .vapp_status").empty();

                // Set its html contents to be that of the returned data
                $('#' + id_escaped + " .vapp_status").html(data);

                // Create the dialog if the task_error class exists
                $('#' + id_escaped + ' .vapp_status .task_error').dialog({
                    // Set the new dialogs position to be the original div, normally it moves to the bottom of the page meaning you have to do a destroy + remove to clean old ones out
                    appendTo: $('#' + id_escaped + ' .vapp_status .task_error').parent(),
                    autoOpen: false,
                    modal: true,
                    title: 'Task Error Details',
                    resizable: true,
                    minWidth: 500,
                    maxWidth: 800,
                    maxHeight: 200
                });

                // Link from the link to the dialog opening
                $('#' + id_escaped + ' .vapp_status .task_error_link').click(function() {
                    // Open the dialog from above
                    $('#' + id_escaped + ' .vapp_status .task_error').dialog('open');
                    return false;
                });
            }
            setTimeout("get_tasks('" + id + "','" + url + "')", 10000);

        },
        error: function(xhr, textStatus, errorThrown) {
            setTimeout("get_tasks('" + id + "','" + url + "')", 10000);
        },
        complete: function(xhr, textStatus) {
            //id_escaped=escapeit(id);
            //setTimeout("get_tasks('" + id  + "')",1000);
        }

    });
}
