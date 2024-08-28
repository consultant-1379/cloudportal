$(document).ready(function() {

    $('#vapptemplates_table').dataTable({
        "aoColumns": [
        null,
        null,
        null,
        {
            "sType": "date-euro"
        },
        null,
        null,
        null
        ],
        "aaSorting": [[ 3, "desc" ]],
        "bJQueryUI": true,
        "bPaginate": false
    });
});

