$(document).ready(function () {
    $("#tabs").tabs({
        active: 1
    });
    $("#vms_tab_link").unbind('click');

    // Setup the dialog box on click of each nic
    $('.clickable').click(function () {
        var contents = "<div title='Nic Details'><b>Nic: </b>" + $(this).find('nic_no').text() + "</br><b>Mac: </b>" + $(this).find('mac_addr').text();
        // Only if it has an ip address field set, show it
        if ($(this).find('ip_address').text()) {
            contents += "</br><b>IP Address: </b>" + $(this).find('ip_address').text();
        }
        contents += "</br><b>Network: </b>" + $(this).find('network_name').text() + "</div>";
        $(contents).dialog();
    });


    // Add or remove the class to highlight the network connections on hover
    $('.connection').hover(function () {
        var oldclass_unformatted = $(this).attr('class');
        var oldclass = oldclass_unformatted.replace(" ", ".");
        $("." + oldclass).each(function () {
            $(this).attr('class', oldclass_unformatted + " highlighted");
        });
    }, function () {
        var oldclass_unformatted = $(this).attr('class');
        var oldclass = oldclass_unformatted.replace(" ", ".");
        var oldclass_cleaned = oldclass.replace(" highlighted", "");
        var oldclass_cleaned_other = oldclass_cleaned.replace(".", " ");
        $("." + oldclass_cleaned).each(function () {
            $(this).attr('class', oldclass_cleaned_other);
        });
    });

    // Set the size of the svg container and element itself to the element size plus a bit of padding
    $('#svg_container').width((($('#svg_element')[0].getBBox().width + 50) + "px"));
    $('#svg_container').height((($('#svg_element')[0].getBBox().height + 50) + "px"));
    $('#svg_element').width((($('#svg_element')[0].getBBox().width + 50) + "px"));
    $('#svg_element').height((($('#svg_element')[0].getBBox().height + 50) + "px"));
});
