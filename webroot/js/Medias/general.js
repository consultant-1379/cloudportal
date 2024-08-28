$(function () {

    'use strict';
    $("#tabs").tabs({
        active: 1
    });
    $("#vapp_templates_tab_link").unbind('click');

    // Detecting IE
    var oldIE;
    if ($('#iediv').is('.ie6, .ie7, .ie8, .ie9')) {
        oldIE = true;
    }

    if (oldIE) {
        $('#uploadisodiv').html("<b>Note: </b>Uploading of ISO files is not supported in IE, please use chrome or firefox");
    }

    var iso_regex = /.iso$/i;
    var url = '/FileUploads/upload/';
    var catalog_name = $('#catalog_name').text();
    var uniquedir = "";

    function does_media_exist(catalog_name, item_name) {
        var result;
        $.ajax({
            url: "/Medias/does_media_exist_api/catalog_name:" + catalog_name + "/item_name:" + item_name + "/.json",
            dataType: "json",
            async: false,
            cache: false,
            success: function (json) {
                if (json.result === "false") {
                    result = false;
                } else {
                    result = true;
                }
            }

        });
        return result;
    }

    function update_info_box(message, color) {
        $('#files').html("<b>Status: </b><font color='" + color + "'>" + message + "</font>");
    }
    $('#fileupload').fileupload({
        maxChunkSize: 10000000, // 10 MB
        url: url + "/catalog_name:" + catalog_name,
        dataType: 'json',
        beforeSend: function (e, data) {

            // If its the first chunk do some tests
            if (uniquedir === "") {
                // Must be first chunk
                uniquedir = "isodir" + Date.now();
                data.url = data.url + "/uniquedir:" + uniquedir;
                var return_value = true;

                $.each(data.files, function (index, file) {
                    // Check is it an iso file
                    var RE_OK = iso_regex.exec(file.name);
                    if (!RE_OK) {
                        update_info_box("ERROR: " + file.name + " is not an iso file", 'red');
                        return_value = false;
                        return false;
                    }

                    // Check does that catalog item already exist
                    if (does_media_exist(catalog_name, file.name)) {
                        update_info_box("ERROR: " + file.name + " already exists in catalog " + catalog_name, 'red');
                        return_value = false;
                        return false;
                    }

                    update_info_box("Uploading " + file.name + ", please wait...", 'blue');
                    $('#fileupload').hide();

                });
                return return_value;
            } else {
                data.url = data.url + "/uniquedir:" + uniquedir;
            }
        },
        done: function (e, data) {
            $("#progress").hide();
            $.each(data.result.files, function (index, file) {
                update_info_box(file.name + " now being added to the catalog, please wait as this can take a while depending on the iso size...", 'blue');
                // Now kick off the next phase, from spp to vcloud
                $.ajax({
                    url: "/Medias/upload_media_api/catalog_name:" + catalog_name + "/uniquedir:" + uniquedir + "/.json",
                    dataType: "json",
                    cache: false,
                    success: function (json) {
                        update_info_box(file.name + " was successfully uploaded to the catalog " + catalog_name, '#33CC33');
                        location.reload();
                    },
                    error: function (e, data) {
                        //$.each(data.result.files, function (index, file) {
                        update_info_box("ERROR: There was a problem importing the uploaded file to the catalog, please contact cloud team", 'red');
                        $('#fileupload').show();
                        //});
                    }

                });
            });
        },
        error: function (e, data) {
            //$.each(data.result.files, function (index, file) {
            update_info_box("ERROR: There was a problem uploading the file, please contact cloud team", 'red');
            $('#fileupload').show();
            //});
        },
        always: function (e, data) {
            // Reset the uniquedir as this session has finished
            uniquedir = "";
        },
        progressall: function (e, data) {
            var progress = parseInt(data.loaded / data.total * 100, 10);
            $("#progress").show();
            $("#progress").progressbar({
                value: progress
            });
        }
    }).prop('disabled', !$.support.fileInput)
        .parent().addClass($.support.fileInput ? undefined : 'disabled');
});
