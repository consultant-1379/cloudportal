$(document).ready(function() {

    $('#disks_table').dataTable({
		"aaSorting": [[ 0, "asc" ]],
		"bJQueryUI": true,
		"bPaginate": false,
		"sDom": 'lr'
	});
});
