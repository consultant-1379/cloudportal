$(document).ready(function() {
    $('#table').dataTable({
        "aoColumns": [
        null,
        {
            "sType": "date-euro"
        }
        ],
        "aaSorting": [[ 1, "desc" ]],
        "bJQueryUI": true,
        "bPaginate": false
    });
});
