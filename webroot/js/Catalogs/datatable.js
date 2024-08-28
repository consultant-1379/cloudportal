$(document).ready(function() {

    $('#catalogs_table').dataTable({
        "aaSorting": [[ 0, "asc" ]],
        "bJQueryUI": true,
        "bPaginate": false
    });
});
