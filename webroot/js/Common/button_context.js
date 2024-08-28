$(document).ready(function(){
    initialize_action_buttons();
});

function initialize_action_buttons()
{
    // Match the action button to the right click menu
    $('.actions').button({
        icons: {
            primary: "ui-icon-gear",
            secondary: "ui-icon-triangle-1-s"
        }
    })
    .click(function(e) {
        var newevent = jQuery.Event('contextmenu',{
            data: e.data,
            pageX: e.pageX,
            pageY: e.pageY
        });
        $(this).trigger(newevent);

    });
}
