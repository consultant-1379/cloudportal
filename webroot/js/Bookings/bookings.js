var dialog;
var vapp_type;
var duration_hours;
var team;
var table;
var error_message_div;
var success_message_div;
var loading_message_div;
var default_extension_hours;

$(document).ready(function() {
    table = $('#bookings_table').dataTable({
        "aoColumns": [
            null,
            null,
            null,
            null,
            null, {
                "sType": "date-euro"
            },
            null,
            null,
            null,
            null
        ],
        "aaSorting": [
            [5, "desc"],
            [7, "desc"]
        ],
        "bJQueryUI": true,
        "bPaginate": false
    });

    $(".fg-toolbar").prepend($('<div id="bookdiv"></div>'))
    $("#bookdiv").html('<a href="#" id="create-booking" title="Book Test Environment" style=" margin-top: 10px; margin-left: 5px; float:left; "><img src="/images/plus_black_16px.svg"> <span style="color: #0967b2;text-decoration: underline;">Book Test Environment</span></a>');
    $("#create-booking").on("click", function() {
        dialog = $("#dialog-form").dialog({
            autoOpen: false,
            width: 950,
            modal: true,
            buttons: {
                "Create Booking": create_booking,
                Cancel: function() {
                    dialog.dialog("close");
                }
            }
        });
        get_overall_queue_wait_times();
        dialog.dialog("open");
    });

    load_bookings();
    var cancel_only_menu = {}
    cancel_only_menu["cancel"] = {
        name: "Cancel Booking",
        icon: "redx"
    };
    var cancel_and_extend_menu = {};
    cancel_and_extend_menu["cancel"] = {
        name: "Cancel Booking",
        icon: "redx"
    };
    cancel_and_extend_menu["extend"] = {
        name: "Extend Booking",
        icon: "recompose"
    };
    $.contextMenu({
        selector: '.context-menu-one',
        callback: function(key, opt){
            var unique_booking_id = opt.$trigger.find('.unique_booking_id').text();
            if (key == "cancel" ) {
                cancel_callback(opt, unique_booking_id);
            }
            if (key == "extend" ) {
                var vapp_gateway_hostname = opt.$trigger.find('.vapp_gateway_hostname').text();
                var confirm_text = "Are you sure you want to extend the booking for Test Environment vApp '" + vapp_gateway_hostname + "' by " + default_extension_hours.html() + " hours?";
                var answer = confirm(confirm_text);
                if (answer){
                    extend_booking(unique_booking_id, vapp_gateway_hostname);
                }
            }
        },
        items: cancel_and_extend_menu
    });

    $.contextMenu({
        selector: '.context-menu-two',
        callback: function(key, opt){
            var unique_booking_id = opt.$trigger.find('.unique_booking_id').text();
            if (key == "cancel" ) {
                cancel_callback(opt, unique_booking_id)
            }
        },
        items: cancel_only_menu
    });

    vapp_type = $("#vapp_type");
    duration_hours = $("#duration_hours");
    error_message_div = $("#error_message_div");
    success_message_div = $("#success_message_div");
    loading_message_div = $("#loading_message_div");
    team = $("#team");
    default_extension_hours = $("#default_extension_hours");
    extension_limit = $("#extension_limit");
});

function cancel_callback(opt, unique_booking_id){
    if (opt.$trigger.find('.status').text() == 'booked')
    {
        var vapp_gateway_hostname = opt.$trigger.find('.vapp_gateway_hostname').text();
        var confirm_text = "Are you sure you want to cancel this booking for Test Environment vApp '" + vapp_gateway_hostname + "' ?";
        var answer = confirm(confirm_text);
        if (answer){
            cancel_booking(unique_booking_id, vapp_gateway_hostname);
        }
    } else {
        var confirm_text = "Are you sure you want to cancel this queued booking?";
        var answer = confirm(confirm_text);
        if (answer){
            cancel_queued_booking(unique_booking_id);
        }
    }

}

function set_loading_message() {
    error_message_div.hide();
    success_message_div.hide();
    loading_message_div.show();
}

function set_error_message(input) {
    loading_message_div.hide();
    success_message_div.hide();
    error_message_div.html('<img style="padding: 2px;" src="/images/error_red_16px.svg">' + input);
    error_message_div.show();
}

function set_success_message(input) {
    loading_message_div.hide();
    error_message_div.hide();
    success_message_div.html('<img style="padding: 2px;" src="/images/tick_green_16px.svg">' + input);
    success_message_div.show();
}

function create_booking() {
    duration_seconds = duration_hours.val() * 3600;
    set_loading_message('Please wait..');

    $.ajax({
        url: "/Bookings/create_api/vapp_type:" + vapp_type.val() + "/duration_seconds:" + duration_seconds + "/team:" + team.html() + "/.json",
        type: 'GET',
        cache: false,
        dataType: 'json',
        timeout: 240000,
        error: function(xhr, textStatus, errorThrown) {
            load_bookings();
            error_message = 'Your Test Environment vApp of type "' + vapp_type.val() + '" couldn\'t be booked. ' +
                '<br>' +
                '<br>' +
                'Please see the following error for the reason why: "' + JSON.parse(xhr.responseText).name + '"';
            set_error_message(error_message);
        },
        success: function(data, textStatus, xhr) {
            load_bookings();
            success_message = 'Your Test Environment Booking Request of type "' + vapp_type.val() + '" has been received.' +
                '<br>' +
                '<br>' +
                'It has been added to the queue and you will be notified as soon as it becomes available.'
            set_success_message(success_message);
        }
    });
    dialog.dialog("close");
}

function cancel_booking(unique_booking_id, vapp_gateway_hostname) {
    set_loading_message();
    $.ajax({
        url: "/Bookings/cancel_api/unique_booking_id:" + unique_booking_id + "/team:" + team.html() + "/.json",
        type: 'GET',
        cache: false,
        dataType: 'json',
        timeout: 240000,
        error: function(data, textStatus, xhr) {
            error_message = 'Your booking for Test Environment vApp "' + vapp_gateway_hostname + '" couldn\'t be been cancelled.' +
                '<br>' +
                '<br>' +
                'Please see the following error for the reason why: "' + JSON.parse(data.responseText).name + '"';
            set_error_message(error_message);
        },
        success: function(data, textStatus, xhr) {
            load_bookings();
            success_message = 'Your booking for Test Environment vApp "' + vapp_gateway_hostname + '" has been successfully cancelled.';
            set_success_message(success_message);
        }
    });
}


function cancel_queued_booking(unique_booking_id) {
    set_loading_message();
    $.ajax({
        url: "/Bookings/cancel_queued_booking_api/unique_booking_id:" + unique_booking_id + "/team:" + team.html() + "/.json",
        type: 'GET',
        cache: false,
        dataType: 'json',
        timeout: 240000,
        error: function(data, textStatus, xhr) {
            error_message = 'Your queued booking couldn\'t be been cancelled.' +
                '<br>' +
                '<br>' +
                'Please see the following error for the reason why: "' + JSON.parse(data.responseText).name + '"';
            set_error_message(error_message);
        },
        success: function(data, textStatus, xhr) {
            load_bookings();
            success_message = 'Your queued booking has been successfully cancelled.';
            set_success_message(success_message);
        }
    });
}

function extend_booking(unique_booking_id, vapp_gateway_hostname) {

    set_loading_message();
    $.ajax({
        url: "/Bookings/extend_api/unique_booking_id:" + unique_booking_id + "/team:" + team.html() + "/.json",
        type: 'GET',
        cache: false,
        dataType: 'json',
        timeout: 240000,
        error: function(data, textStatus, xhr) {
            error_message = 'Your booking for Test Environment vApp ' + vapp_gateway_hostname + '" couldn\'t be been extended.' +
                '<br>' +
                '<br>' +
                'Please see the following error for the reason why: "' + JSON.parse(data.responseText).name + '"';
            set_error_message(error_message);
        },
        success: function(data, textStatus, xhr) {
            load_bookings();
            success_message = 'Your booking for Test Environment vApp "' + vapp_gateway_hostname + '" has been successfully extended by "' + default_extension_hours.html() + '" hours.';
            set_success_message(success_message);
        }
    });
}


function load_bookings() {
    $.ajax({
        url: "/Bookings/bookings_api/.json",
        type: 'GET',
        cache: false,
        dataType: 'json',
        timeout: 60000,
        error: function(data, textStatus, xhr) {
            error_message = 'There was an issue listing the bookings' +
                '<br>' +
                '<br>' +
                'Please see the following error for the reason why: "' + JSON.parse(data.responseText).name + '"';
            set_error_message(error_message);
        },
        success: function(data, textStatus, xhr) {
            var time_remaining_string = '';
            var extensions_used_string = '';
            table.fnClearTable();
            $.each(data.bookings, function(key, booking) {
                if (booking.status == 'booked')
                {
                    time_remaining_string = seconds_to_hours_minutes_and_seconds(booking.time_remaining_seconds);
                    extensions_used_string = booking.extension_count + ' / ' + extension_limit.html();
                } else {
                    if (booking.time_left_in_queue == 0){
                        time_remaining_string = 'vApp currently coming online';
                    } else {
                        time_remaining_string = 'Approx: ' + seconds_to_hours_minutes_rounded(booking.time_left_in_queue) + ' remaining in queue';
                    }
                    extensions_used_string = '0 / ' + extension_limit.html();
                }
                table.fnAddData([booking.vapp_type, '<div class="unique_booking_id" style="display:none">' + booking.unique_booking_id + '</div>' + '<div class="status" style="display:none">' + booking.status+ '</div>' + '<div class="vapp_gateway_hostname">' + booking.vapp_gateway_hostname + '</div>', booking.vapp_template_name, booking.username, booking.team, (booking.created_datetime).substr(0,16), seconds_to_hours_minutes_and_seconds(booking.duration_seconds), extensions_used_string, time_remaining_string, '<button class="actions">Actions</button>'],false);

            });
            table.fnDraw();
            initialize_action_buttons();
            if (Object.keys(data.bookings).length)
            {
                $('tbody > tr').each(function(){
                    var row = $(this);
                    var unique_booking_id = row.find('.unique_booking_id').text();
                    $.each(data.bookings, function(key, booking) {
                        if (booking.unique_booking_id == unique_booking_id){
                            if(booking.extension_count >= parseInt(extension_limit.html()) || booking.status == 'queued'){
                                row.addClass('context-menu-two');
                            }else{
                                row.addClass('context-menu-one');
                            }
                        }
                    });
                });
            }
            start_quotas_progress_bar_updates();
        }

    });
}

function get_overall_queue_wait_times(){
   $.ajax({
        url: "/Bookings/queue_time_api" + "/.json",
        type: 'GET',
        cache: false,
        dataType: 'json',
        timeout: 240000,
        error: function(data, textStatus, xhr) {
        },
        success: function(data, textStatus, xhr) {
            $.each(data.queue_times, function(key, time) {
                if ((time != 0) && (time != null)){
                    $('#' + key).html(seconds_to_hours_minutes_rounded(time));
                } else {
                    $('#' + key).html("No Wait Time");
                }
            });
        }
    });
}

