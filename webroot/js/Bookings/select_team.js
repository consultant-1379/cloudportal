var team;

$(document).ready(function() {
    team = $("#team");

    $('#team').change(function() {
        var team = $(this).val();
        set_users_team(team);
    });
});

function set_users_team(team) {
    $.ajax({
        url: "/Bookings/set_users_team/team:" + team + "/.json",
        type: 'GET',
        cache: false,
        dataType: 'json',
        timeout: 20000,
        success: function(data, textStatus, xhr) {
            var url = encodeURI("/Bookings/bookings");
            window.location = url;
        },
        error: function(xhr, textStatus, errorThrown) {}
    });
}
