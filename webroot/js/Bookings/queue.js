var queue_tables = [];
$(document).ready(function () {
    start_queue_status_ajax();
    $('.queue_table').each( function (index) {
        queue_tables[$(this).attr('id')] = $(this).dataTable({
            "aoColumns": [
                null,
                null,
                null, {
                    "sType": "date-euro"
                },
                null,
                null
            ],
            "aaSorting": [
                [5, "asc"]
            ],
            "bJQueryUI": true,
            "bPaginate": false
        });
    });
});

function start_queue_status_ajax() {
    $.ajax({
        url: "/Bookings/queue_api/.json",
        type: 'GET',
        cache: false,
        dataType: 'json',
        timeout: 100000,
        success: function (data, textStatus, xhr) {
            $('.queue_table').each( function (index) {
                queue_tables[$(this).attr('id')].fnClearTable();
            });
            var duration_string = '';
            $.each(data.queued_bookings, function (key, booking) {
                duration_string =  seconds_to_hours_minutes_and_seconds(booking.duration_seconds);
                queue_tables['queue_' + booking.vapp_type].fnAddData([booking.vapp_type, booking.username,booking.team, booking.created_datetime, duration_string, booking.queue_position], false);
            });
            $('.queue_table').each( function (index) {
                queue_tables[$(this).attr('id')].fnDraw();
            });
        },
        error: function (xhr, textStatus, errorThrown) {},
        complete: function (xhr, textStatus) {
            setTimeout("start_queue_status_ajax()", 10000);
        }
    });
}
