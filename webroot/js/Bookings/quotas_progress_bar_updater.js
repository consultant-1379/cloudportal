$(document).ready(function () {
    setTimeout("start_quotas_progress_bar_updates()", 60000);
});

function start_quotas_progress_bar_updates() {
    $.ajax({
        url: "/Bookings/quotas_api/.json",
        type: 'GET',
        cache: false,
        dataType: 'json',
        timeout: 100000,
        success: function (data, textStatus, xhr) {
            $.each(data.quotas, function (key, val) {
                update_progress_bar('#progress_bar_' + key, val.booking_count, val.booking_limit, key, '', true);
            });
        }
    });
}
