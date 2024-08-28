$(document).ready(function() {

    // Datatable click / selected styles
    //$(".datatable tbody tr").click( function( e ) {
    //       if ( $(this).hasClass('row_selected') ) {
    //          $(this).removeClass('row_selected');
    //     }
    //    else {
    //           $('.datatable tr.row_selected').removeClass('row_selected');
    //      $(this).addClass('row_selected');
    // }
    //});


    // Add the datatable date sorter
    jQuery.extend( jQuery.fn.dataTableExt.oSort, {
        "date-euro-pre": function ( a ) {
            if ($.trim(a) != '') {
                var frDatea = $.trim(a).split(' ');
                var frTimea = frDatea[1].split(':');
                var frDatea2 = frDatea[0].split('/');
                var x = (frDatea2[2] + frDatea2[1] + frDatea2[0] + frTimea[0] + frTimea[1])  * 1;
            } else {
                var x = 10000000000000; // = l'an 1000 ...
            }

            return x;
        },

        "date-euro-asc": function ( a, b ) {
            return a - b;
        },

        "date-euro-desc": function ( a, b ) {
            return b - a;
        }
    } );

});

function escapeit(myid) { 
    return myid.replace(/(:|\.)/g,'\\$1');
}