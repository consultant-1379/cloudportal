var team_bookings_table;
$(document).ready(function () {
    start_pool_status_ajax();
    team_bookings_table = $('#team_bookings_table').dataTable({
        "aaSorting": [
            [0, "asc"]
        ],
        "bJQueryUI": true,
        "bPaginate": false
    });
});

function start_pool_status_ajax() {
    $.ajax({
        url: "/Bookings/pool_status/.json",
        type: 'GET',
        cache: false,
        dataType: 'json',
        timeout: 100000,
        success: function (data, textStatus, xhr) {
            $.each(data.pool_status, function (key, val) {
                update_progress_bar('#progress_bar_' + key, val.total - val.available, val.total, key, '', true);
            });
        },
        error: function (xhr, textStatus, errorThrown) {},
        complete: function (xhr, textStatus) {
            setTimeout("start_pool_status_ajax()", 10000);
        }
    });
}
