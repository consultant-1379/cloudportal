var mimetype = "application/x-vmware-remote-console-2012";
var clsid = "CLSID:4AEA1010-0A0C-405E-9B74-767FC8A998CB";

var isIE = /MSIE (\d+\.\d+);/.test(navigator.userAgent);
var vmrc = null;

// Define variables to hold references to html objects
var catalog_list;
var media_list;
var vm_id;

// Variables to help with knowing when to display various connect buttons
var connected_by_me = false;
var drive_media_inserted = false;
var connect_media_catalog_exists = false;
var connect_media_media_exists = false;
var connect_cdrom_drives_exist = false;

// Setup the spinner variables
var spinner_opts = {
    lines: 9, // The number of lines to draw
    length: 2, // The length of each line
    width: 2, // The line thickness
    radius: 4, // The radius of the inner circle
    corners: 0.9, // Corner roundness (0..1)
    rotate: 0, // The rotation offset
    direction: 1, // 1: clockwise, -1: counterclockwise
    color: '#ffffff', // #rgb or #rrggbb
    speed: 1.3, // Rounds per second
    trail: 30, // Afterglow percentage
    shadow: true, // Whether to render a shadow
    hwaccel: true, // Whether to use hardware acceleration
    className: 'spinner', // The CSS class to assign to the spinner
    zIndex: 2e9, // The z-index (defaults to 2000000000)
    top: '2px', // Top position relative to parent in px
    left: '20px' // Left position relative to parent in px
};
var spinner_target;
var spinner;

function $native(id) {
    return document.getElementById(id);
}

function $Vnative(id) {
    return $native(id).value;
}

function log(text) {
    $('#loading_div').html(text);
    try {
        console.log(text);
    } catch (ex) {}
}

function attachEventHandler(eventName, handler) {
    if (isIE) {
        vmrc.attachEvent(eventName, handler);
    } else {
        vmrc[eventName] = handler;
    }
}

function onConnectionStateChangeHandler(cs,
host,
datacenter,
vmId,
userRequested,
reason) {
    if (cs == 2) {
        log('Starting connection completed successfully' + 'onConnectionStateChange - connectionState: ' + cs +
            ', host: ' + host +
            ', datacenter: ' + datacenter +
            ', vmId: ' + vmId +
            ', userRequested: ' + userRequested +
            ', reason: ' + reason);
        console_connected_functions();
    } else {
        log('Starting connection failed. Please contact the cloud team with this message. ' + 'onConnectionStateChange - connectionState: ' + cs +
            ', host: ' + host +
            ', datacenter: ' + datacenter +
            ', vmId: ' + vmId +
            ', userRequested: ' + userRequested +
            ', reason: ' + reason);
        console_disconnected_functions();
    }
}

function createPluginObject(parentId) {
    var obj = document.createElement("object");
    obj.setAttribute("id", "vmrc");
    obj.setAttribute("height", "100%");
    obj.setAttribute("width", "100%");
    if (isIE) {
        obj.setAttribute("classid", clsid);
    } else {
        obj.setAttribute("type", mimetype);
    }

    $native(parentId).appendChild(obj);
    return $native('vmrc');
}

function onGrabStateChangeHandler(grabState) {
    var grabStateStr = grabState;
    switch (parseInt(grabState, 10)) {
        case 2:
            $('#breakout_helper').hide("slow");
            grabStateStr = "ungrabbed hard";
            break;
        case vmrc.GrabState.GS_UNGRABBED_SOFT:
            $('#breakout_helper').hide("slow");
            grabStateStr = "ungrabbed soft";
            break;
        case 1:
            $('#breakout_helper').show("slow");
            grabStateStr = "grabbed";
            break;
        default:
            $('#breakout_helper').hide("slow");
            log('Could not match grabState: ' + grabState);
            break;
    }
    log('onGrabStateChange - grabState: ' + grabStateStr);
}

function getPhysicalClientDevices() {
    //var mask = vmrc.DeviceType.DEVICE_CDROM;

    // hardcoding as IE doesn't like line above
    var mask = 2;
    try {
        var devices;
        if (isIE) {
            devices = new VBArray(vmrc.getPhysicalClientDevices(mask)).toArray();
            log('getPhysicalClientDevices returned "' + devices + '"');
        } else {
            devices = vmrc.getPhysicalClientDevices(mask);
            log('getPhysicalClientDevices returned "' + devices + '"');
        }
        $("#local_drive_select").empty();
        for (var i = 0; i < devices.length; i++) {
            log("A local drive is " + devices[i]);
            $("#local_drive_select").append('<option value=' + devices[i] + '>' + devices[i] + '</option>');
        }
        if (devices.length < 2) {
            $("#local_drive_select").prop('disabled', 'disabled');
        }
        if (devices.length === 0) {
            $("#local_drive_select").append('<option>None</option>');
            connect_cdrom_drives_exist = false;
        } else {
            connect_cdrom_drives_exist = true;
        }
        update_connect_cdrom_button();

    } catch (err) {
        log('getPhysicalClientDevices failed: ' + err);
    }
}

function getVirtualDevices() {
    // var mask = vmrc.DeviceType.DEVICE_CDROM;

    // hardcoding as IE doesn't like line above
    var mask = 2;
    try {
        var devices;
        if (isIE) {
            devices = new VBArray(vmrc.getVirtualDevices(mask)).toArray();
            log('getVirtualDevices returned "' + devices + '"');
        } else {
            devices = vmrc.getVirtualDevices(mask);
            log('getVirtualDevices returned "' + devices + '"');
        }
        $("#virtual_drive_select").empty();
        var device_details = "";
        for (var i = 0; i < devices.length; i++) {
            device_details = getVirtualDeviceDetails(devices[i]);
            log("A virtual drive is " + devices[i]);
            $("#virtual_drive_select").append('<option value=' + devices[i] + '>' + device_details.name + '</option>');
        }

        if (devices.length < 2) {
            $("#virtual_drive_select").prop('disabled', 'disabled');
        }
        if (devices.length === 0) {
            $("#virtual_drive_select").append('<option>None</option>');
            $("#local_resources_tabs").tabs("disable");
        }
        if (devices.length > 0) {
            $("#virtual_drive_select").trigger('change');
        }
    } catch (err) {
        log('getVirtualDevices failed: ' + err);

        // Run below anyways to update the ui
        set_virtual_device_specific();
    }
}

function getVirtualDeviceDetails(key) {

    try {
        var keys = [
            'key',
            'type',
            'state',
            'connectedByMe',
            'name',
            'clientBacking',
            'backing',
            'backingKey',
            'hostName'];
        var details = vmrc.getVirtualDeviceDetails(key);
        var s = '';
        for (var i in keys) {
            var prop = keys[i];
            s += prop + ": '" + details[prop] + "'; ";
        }
        log('getVirtualDeviceDetails returned "' + s + '"');
        return details;
    } catch (err) {
        log('getVirtualDeviceDetails failed: ' + err);
    }

}

function set_emulate_backing_device(cdromkey) {
    return $.ajax({
        url: "/Vms/set_cdrom_emulate_mode/vm_id:" + vm_id + "/cdromkey:" + cdromkey,
        cache: false
    });
}

function connectFile() {
    var virtualKey = $("#virtual_drive_select").val();
    var physicalKey = $("#file_select").val();
    var backing = 1;
    log('connecting virtual ' + virtualKey + ' to physical ' + physicalKey);
    update_virtual_info("Please wait...");
    spinner.spin(spinner_target);
    $.when(
    set_emulate_backing_device(virtualKey)).then(function () {
        try {
            vmrc.connectDevice(virtualKey, physicalKey, backing);
            log('connectFile succeeded');
        } catch (err) {
            log('connectFile failed: ' + err);
        }
    });

}

function populate_media_list() {
    $('#connect_media_button').button('disable');
    media_list.prop("disabled", true);
    media_list.empty();
    media_list.append('<option value="0">Loading...</option>');

    $.ajax({
        url: "/Medias/index_api/catalog_name:" + catalog_list.val() + "/.json",
        type: 'GET',
        cache: false,
        dataType: 'json',
        timeout: 100000,
        success: function (data, textStatus, xhr) {
            media_list.empty();
            $.each(data.medias, function (key, val) {
                media_list.append('<option value="' + val.media_id + '">' + val.name + '</option>');
            });
            if (data.medias.length === 0) {
                media_list.append('<option value="0">No Media In Catalog<option>');
                media_list.prop("disabled", true);
                $('#connect_media_button').button('disable');
                connect_media_media_exists = false;
            } else {
                media_list.prop("disabled", false);
                $('#connect_media_button').button('enable');
                connect_media_media_exists = true;
            }
            update_connect_media_button();
        }

    });
}

function connectMedia() {

    log('connecting catalog iso ' + media_list.val() + ' from catalog ' + catalog_list.val());
    update_virtual_info("Please wait...");
    spinner.spin(spinner_target);
    $.ajax({
        url: "/Vms/insert_media_api/media_id:" + media_list.val() + "/vm_id:" + vm_id + "/.json",
        type: 'GET',
        cache: false,
        dataType: 'json',
        timeout: 1000000,
        error: function (data, textStatus, xhr) {
            spinner.stop();
            update_local_info("ERROR: Something went wrong mounting the iso, please check that you are still logged in to the portal, otherwise please contact the cloud team");
            set_virtual_device_specific();
        },
        success: function (data, textStatus, xhr) {
            spinner.stop();
            update_local_info("ISO mounted successfully");
            set_virtual_device_specific();
        }

    });
}

function connectDevice() {
    var virtualKey = $("#virtual_drive_select").val();
    var physicalKey = $("#local_drive_select").val();
    var backing = 2;
    log('connecting virtual ' + virtualKey + ' to physical ' + physicalKey);
    update_virtual_info("Please wait...");
    spinner.spin(spinner_target);
    $.when(
    set_emulate_backing_device(virtualKey)).then(function () {
        try {
            vmrc.connectDevice(virtualKey, physicalKey, backing);
            log('connectDevice succeeded');
        } catch (err) {
            log('connectDevice failed: ' + err);
        }
    });
}

function disconnect_detect() {
    // If the mounted media was connected by me (iso or drive) then use disconnect method, otherwise force eject (other user iso / drive or catalog iso)
    if (connected_by_me === true) {
        disconnectDevice();
    } else {
        force_eject();
    }
}

function disconnectDevice() {
    var key = $('#virtual_drive_select').val();

    log('disconnecting virtual ' + key);
    update_virtual_info("Please wait...");
    spinner.spin(spinner_target);
    try {
        vmrc.disconnectDevice(key);
        log('disconnectDevice succeeded');
    } catch (err) {
        log('disconnectDevice failed: ' + err);
    }
}

function force_eject() {
    log('disconnecting via force eject');
    update_virtual_info("Please wait...");
    spinner.spin(spinner_target);
    //return $.ajax({
    //    url: "/Vms/flip_cdrom_mode/vm_id:" + vm_id,
    //    cache: false
    //});

    $.ajax({
        url: "/Vms/flip_cdrom_mode/vm_id:" + vm_id,
        type: 'GET',
        cache: false,
        timeout: 1000000,
        error: function (data, textStatus, xhr) {
            spinner.stop();
            update_local_info("ERROR: Something went wrong ejecting the Drive, please contact the cloud team if this persists " + xhr.responseText);
            set_virtual_device_specific();
        },
        success: function (data, textStatus, xhr) {
            spinner.stop();
            update_local_info("Drive ejected successfully");
            set_virtual_device_specific();
        }

    });
}


function onMessageHandler(msgType, message) {
    log('onMessage - msgType: ' + msgType + ', message: ' + message);
    update_local_info('onMessage - msgType: ' + msgType + ', message: ' + message);
}

function onDeviceStateChangeHandler(deviceState,
hostname,
datacenter,
vmID,
virtualDeviceKey,
physicalClientDeviceKey,
userRequested,
reason) {
    log('onDeviceStateChange - deviceState: ' + deviceState +
        ', hostname: ' + hostname +
        ", datacenter: " + datacenter +
        ', vmID: ' + vmID +
        ', virtualDeviceKey: ' + virtualDeviceKey +
        ', physicalClientDeviceKey: ' + physicalClientDeviceKey +
        ', userRequested: ' + userRequested +
        ', reason: ' + reason);

    // Update the information bar on the page with the reason for the device state change as it has most of the required details

    update_local_info(reason);
    // Forcibly update the drive status, if a device changed and the user didn't request it. This avoids leaving the spinner spinning
    if (userRequested !== true) {
        set_virtual_device_specific();
    }
}

function update_virtual_info(input_string) {
    $('#virtual_info').html(input_string);
}

function update_local_info(input_string) {
    $('#local_info').html(input_string);
}

function onVirtualDevicesChangeHandler() {
    log('onVirtualDevicesChange');
    set_virtual_device_specific();
}

function onPhysicalClientDevicesChangeHandler() {
    log('onPhysicalClientDevicesChange');
}

function init() {
    vmrc = createPluginObject("pluginPanel");
    if (vmrc === null) {
        return;
    }

    attachEventHandler("onConnectionStateChange", onConnectionStateChangeHandler);
    attachEventHandler("onScreenSizeChange", onScreenSizeChangeHandler);
    attachEventHandler("onGrabStateChange", onGrabStateChangeHandler);
    attachEventHandler("onMessage", onMessageHandler);
    attachEventHandler("onDeviceStateChange", onDeviceStateChangeHandler);
    attachEventHandler("onVirtualDevicesChange", onVirtualDevicesChangeHandler);
    attachEventHandler("onPhysicalClientDevicesChange", onPhysicalClientDevicesChangeHandler);

    var enumDefs = [
        'VMRC_ConnectionState',
        'VMRC_DeviceBacking',
        'VMRC_DeviceState',
        'VMRC_DeviceType',
        'VMRC_GrabState',
        'VMRC_MessageMode',
        'VMRC_MessageType',
        'VMRC_Mode',
        'VMRC_USBDeviceFamily',
        'VMRC_USBDeviceSpeed'];

    for (var e in enumDefs) {
        var propertyName = enumDefs[e];
        var keys = [];
        var value = "";
        var shortName = propertyName.replace(/VMRC_/g, "");
        var shortKey = "";

        vmrc[shortName] = {};

        //if (isIE) {
        // var vbkeys = new VBArray(vmrc[propertyName].Keys());
        //for (k = 0; k <= vbkeys.ubound(1); k++) {
        //  key = vbkeys.getItem(k);
        //    keys.push(key);
        // }
        // } else {
        for (var k in vmrc[propertyName]) {
            keys.push(k);
        }
        //}

        for (var i = 0; i < keys.length; i++) {
            key = keys[i];
            //if (isIE) {
            //   value = vmrc[propertyName](key);
            //} else {
            value = vmrc[propertyName][key];
            //}

            shortkey = key.replace(/VMRC_/g, "");
            vmrc[shortName][shortkey] = value;

            if ($(propertyName)) {
                var opt = new Option(shortkey, value);
                opt.id = shortkey;
                $(propertyName)[$(propertyName).length] = opt;
            }
        }
    }
}

function onScreenSizeChangeHandler(width, height) {
    $('#pluginPanel').width(width);
    $('#pluginPanel').height(height);
    log('onScreenSizeChange - width: ' + width + ', height: ' + height);
}

function start_console() {
    init();
    var mks = 2;
    var devices = 4;
    var mode = mks + devices;

    // Try first in mks + devices mode, then revert to mks only incase mks + devices isn't supported by the OS (Eg Linux)
    try {
        log("Starting plugin...");
        var startup_ret = vmrc.startup(mode, 2, "usebrowserproxy=true;tunnelmks=true");
        log('Starting plugin completed "' + startup_ret + '"');

    } catch (err) {
        try {

            log("Starting plugin in mks mode only...");
            var startup_ret = vmrc.startup(mks, 2, "usebrowserproxy=true;tunnelmks=true");
            log('Starting plugin completed "' + startup_ret + '"');

            // Disable devices related tabs / buttons
            $("#local_resources_tabs").tabs("option", "active", 2);
            $("#local_resources_tabs").tabs({
                disabled: [0, 1]
            });
            $("#virtual_drive_select").prop('disabled', 'disabled');

        } catch (err2) {

            log('Starting plugin failed: Its probably because you dont have the vmware remote console plugin. Please download it. Message was: ' + err2);
            $('#pluginPanel').hide();
            $('#loading_div').hide();
            $('.download_plugin').button();
            $('#vmrc_exe_div').show();

            return;
        }
    }

    // Once the plugin starts, we can query for the list of physical DVD drives
    getPhysicalClientDevices();

    // Start making the connection to the vm
    var host = $('#connect_host').val();
    var thumb = "";
    //var allowSSLErrors = $('connect_allow_ssl_errors').checked;
    var allowSSLErrors = true;
    var ticket = $('#connect_ticket').val();
    var user = "";
    var pass = "";
    var vmid = $('#connect_vmid').val();
    var datacenter = "";
    var vmPath = "";
    try {
        log("Starting connection...");
        var connect_ret = vmrc.connect(host, thumb, allowSSLErrors, ticket, user, pass, vmid, datacenter, vmPath);
        log('Connecting...');
    } catch (err) {
        log('Starting connection failed: Please contact the cloud team with this message. ' + err);
        log(err.message);
    }
}

function console_connected_functions() {
    $('#loading_div').hide();
    $('.pluginActions').show();

    // Once we connect, we can query for the list of virtual DVD drives
    getVirtualDevices();
}

function console_disconnected_functions() {
    $('.pluginActions').hide();
}

function set_virtual_device_specific() {
    spinner.stop();
    var device_details = getVirtualDeviceDetails($("#virtual_drive_select").val());

    // If no device details come back, maybe we arn't connected in devices mode, report to the user
    if (!device_details) {
        update_virtual_info("<font color='red'>Unable to retrieve drive status</font>");
        $('#disconnect_cdrom_button').button('enable');
        return;
    }

    // Show its state
    if (device_details.state == 2) {
        drive_media_inserted = true;
        $('#disconnect_cdrom_button').button('enable');

        var connected_by = "";
        if (device_details.connectedByMe === true) {
            connected_by = device_details.backingKey + " on your machine";
            connected_by_me = true;
        } else {
            if (device_details.clientBacking === true) {
                connected_by = "another users machine";
            } else {
                connected_by = "an iso file from a catalog";
            }
            connected_by_me = false;
        }

        $('#disconnect_cdrom_button').button('enable');
        update_virtual_info("<font color='green'>Connected to " + connected_by + "</font>");
    } else {
        drive_media_inserted = false;
        $('#disconnect_cdrom_button').button('disable');
        update_virtual_info("<font color='red'>Not Connected</font>");
    }
    update_connect_media_button();
    update_connect_file_button();
    update_connect_cdrom_button();
}

function update_connect_media_button() {
    if (drive_media_inserted === false && connect_media_catalog_exists === true && connect_media_media_exists === true) {
        $('#connect_media_button').button('enable');
    } else {
        $('#connect_media_button').button('disable');
    }
}

function update_connect_file_button() {
    if (drive_media_inserted === false) {
        $('#connect_file_button').button('enable');
    } else {
        $('#connect_file_button').button('disable');
    }
}

function update_connect_cdrom_button() {
    if (drive_media_inserted === false && connect_cdrom_drives_exist === true) {
        $('#connect_cdrom_button').button('enable');
    } else {
        $('#connect_cdrom_button').button('disable');
    }
}

function setup_ui_elements() {
    // Setup the jquery tooltips. Everything with class tooltip gets one
    $('.tooltip').tooltip();

    // CDROM Related
    $('#local_resources_tabs').tabs();
    $("#virtual_drive_select").change(function () {
        set_virtual_device_specific();
    });
    $("#accordion").accordion({
        collapsible: true,
        active: false,
        speed: 'fast'
    });
    $("#accordion").hide();

    $('#connect_cdrom_button').button({
        icons: {
            primary: "ui-icon-link"
        },
        text: false
    });
    $('#connect_cdrom_button').click(function () {
        connectDevice();
    });
    $('#disconnect_cdrom_button').button({
        icons: {
            primary: "ui-icon-eject"
        },
        text: false
    });
    $('#disconnect_cdrom_button').click(function () {
        disconnect_detect();
    });
    $('#connect_file_button').button({
        icons: {
            primary: "ui-icon-link"
        },
        text: false
    });
    $('#connect_file_button').click(function () {
        connectFile();
    });

    catalog_list.change(function () {
        populate_media_list();
    });
    // Catalog list
    $.ajax({
        url: "/Catalogs/index_by_vm_api/vm_id:" + vm_id + "/.json",
        type: 'GET',
        cache: false,
        dataType: 'json',
        timeout: 100000,
        success: function (data, textStatus, xhr) {
            catalog_list.empty();
            $.each(data.catalogs, function (key, val) {
                catalog_list.append('<option value="' + val.name + '">' + val.name + '</option>');
            });
            if (data.catalogs.length === 0) {
                catalog_list.append('<option value="0">No Catalog<option>');
                media_list.append('<option value="0">NA<option>');
                media_list.prop("disabled", true);
                catalog_list.prop("disabled", true);
                connect_media_catalog_exists = false;
            } else {
                catalog_list.prop("disabled", false);
                catalog_list.trigger('change');
                connect_media_catalog_exists = true;
            }
            update_connect_media_button();
        }

    });

    $('#connect_media_button').button({
        icons: {
            primary: "ui-icon-link"
        },
        text: false
    });
    $('#connect_media_button').click(function () {
        connectMedia();
    });

    $('#refresh_media_button').button({
        icons: {
            primary: "ui-icon-arrowrefresh-1-w"
        },
        text: false
    });
    $('#refresh_media_button').click(function () {
        populate_media_list();
    });

    // Other
    $('#ctrlaltdel').button({
        icons: {
            primary: "ui-icon-print"
        },
        text: false
    });
    $('#ctrlaltdel').click(function () {
        vmrc.sendCAD();
    });
    $('#fullscreen').button({
        icons: {
            primary: "ui-icon-arrow-4-diag"
        },
        text: false
    });
    $('#fullscreen').click(function (e) {
        vmrc.setFullscreen(true);
    });

    // The spinner
    spinner_target = document.getElementById('spinner');
    spinner = new Spinner(spinner_opts).spin(spinner_target).stop();
}
$(function () {

    // Start of execution

    // Get references to html objects
    catalog_list = $("#catalog_list");
    media_list = $("#media_list");
    vm_id = $('#full_vmid').val();

    // Call the function to create all of the jquery ui elements
    setup_ui_elements();

    // Start the plugin and console etc
    start_console();

});
