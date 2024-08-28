$(document).ready(function() {
    $('#table').dataTable({
        "aoColumns": [
        null,
        null,
        null,
        null,
        null,
        {
            "sType": "date-euro"
        },
        null,
        null,
        null,
        null
        ],
        "aaSorting": [[ 0, "asc" ]],
        "bJQueryUI": true,
        "bPaginate": false
    });
});
