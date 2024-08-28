$(document).ready(function() {

    $('#vapps_table').dataTable({
        "aoColumns": [
        null,
        null,
        null,
        null,
        null,
        null,
        {
            "sType": "date-euro"
        },
        null,
        { "sType": 'numeric' },
        { "sType": 'numeric' },
        null
        ],
        "aaSorting": [[ 6, "desc" ]],
        "bJQueryUI": true,
        "bPaginate": false
    });
});
