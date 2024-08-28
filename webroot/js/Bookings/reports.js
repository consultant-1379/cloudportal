var teamTable;
var overallTable;
var fileNameOverall;
var fileNameTeam;
$(document).ready(function() {
    var exportTable = "/js/staticfiles/TableTools-2.2.4/swf/copy_csv_xls_pdf.swf";
    var now = new Date();
    var day = ("0" + now.getDate()).slice(-2);
    var month = ("0" + (now.getMonth() + 1)).slice(-2);
    var today = day + "/" + month + "/" + now.getFullYear();
    var previous = new Date(now.getTime() - 28 * 24 * 60 * 60 * 1000);
    var previousDay = ("0" + previous.getDate()).slice(-2);
    var previousMonth = ("0" + (previous.getMonth() + 1)).slice(-2);
    var previousDate = previousDay + "/" + previousMonth + "/" + previous.getFullYear();
    $('#datepicker_start').datepicker({
        dateFormat: 'dd/mm/yy'
    }).val(previousDate);
    $('#datepicker_end').datepicker({
        dateFormat: 'dd/mm/yy'
    }).val(today);
    fileNameTeam = 'Test_Environment_on_Demand-Team_Usage_Report_';
    fileNameOverall = 'Test_Environment_on_Demand-Overall_Usage_Report_';
    teamTable = $('#team_report_table').dataTable({
        "aaSorting": [
            [0, "asc"]
        ],
        "bJQueryUI": true,
        "bPaginate": false,
        "sDom": 'Tlfrtip',
        "oTableTools": {
            "sSwfPath": exportTable,
            "aButtons": ['copy', {
                sExtends: 'csv',
                "fnClick": function(nButton, oConfig, flash) {
                    var fileName = get_filename_date(fileNameTeam) + '.csv';
                    flash.setFileName(fileName);
                    this.fnSetText(flash, this.fnGetTableData(oConfig));
                }
            }, {
                sExtends: 'pdf',
                "fnClick": function(nButton, oConfig, flash) {
                    var fileName = get_filename_date(fileNameTeam) + '.pdf';
                    flash.setFileName(fileName);
                    this.fnSetText(flash, "title:" + this.fnGetTitle(oConfig) + "\n" + "message:" + oConfig.sPdfMessage + "\n" + "colWidth:" + this.fnCalcColRatios(oConfig) +
                        "\n" + "orientation:" + oConfig.sPdfOrientation + "\n" + "size:" + oConfig.sPdfSize + "\n" + "--/TableToolsOpts--\n" + this.fnGetTableData(
                            oConfig));
                }
            }, 'print']
        }
    });
    overallTable = $('#overall_report_table').dataTable({
        "aaSorting": [
            [0, "asc"]
        ],
        "bJQueryUI": true,
        "bPaginate": false,
        "sDom": 'Tlfrtip',
        "oTableTools": {
            "sSwfPath": exportTable,
            "aButtons": ['copy', {
                sExtends: 'csv',
                "fnClick": function(nButton, oConfig, flash) {
                    var fileName = get_filename_date(fileNameOverall) + '.csv';
                    flash.setFileName(fileName);
                    this.fnSetText(flash, this.fnGetTableData(oConfig));
                }
            }, {
                sExtends: 'pdf',
                "fnClick": function(nButton, oConfig, flash) {
                    var fileName = get_filename_date(fileNameOverall) + '.pdf';
                    flash.setFileName(fileName);
                    this.fnSetText(flash, "title:" + this.fnGetTitle(oConfig) + "\n" + "message:" + oConfig.sPdfMessage + "\n" + "colWidth:" + this.fnCalcColRatios(oConfig) +
                        "\n" + "orientation:" + oConfig.sPdfOrientation + "\n" + "size:" + oConfig.sPdfSize + "\n" + "--/TableToolsOpts--\n" + this.fnGetTableData(
                            oConfig));
                }
            }, 'print']
        }
    });
    $('#dateFilterDiv').children().hide();
    load_team_reports(previousDate, today);
    $('#date_submit').click(filter_by_dates);
    $('#datepicker_start').datepicker({
        dateFormat: 'dd/mm/yy'
    }).val(previousDate);
    $('#datepicker_end').datepicker({
        dateFormat: 'dd/mm/yy'
    }).val(today);
    $(".layout-bar").prepend($('<div id="filterdiv"></div>'));
    $("#filterdiv").html('<button id="filterDate" type="button" style="margin-left: 5px; float:left; "><img src="/images/search_black_16px.svg"> Filter</button>');
    $('#filterDate').click(function() {
        $('#dateFilterDiv').children().toggle();
    });
});

function get_filename_date(fileName) {
    var startDate = $("#datepicker_start").val();
    var endDate = $("#datepicker_end").val();
    var fileNameDate = startDate.split("/").join("-") + "_to_" + endDate.split("/").join("-");
    return fileName + fileNameDate;
}

function filter_by_dates() {
    $('#user_note').hide();
    var startDate = $("#datepicker_start").val();
    var endDate = $("#datepicker_end").val();
    teamTable.fnClearTable();
    overallTable.fnClearTable();
    load_team_reports(startDate, endDate);
}

function set_loading_message() {
    $('#error_message_div').hide();
    $('#loading_message_div').html('<img style="padding: 2px;" src="/images/loader.gif"> Please wait..');
    $('#loading_message_div').show();
}

function set_error_message(input) {
    $('#loading_message_div').hide();
    $('#error_message_div').html('<img style="padding: 2px;" src="/images/error_red_16px.svg">' + input);
    $('#error_message_div').show();
}

function load_team_reports(start, end) {
    set_loading_message();
    $.ajax({
        url: "/Bookings/reports_api/.json?start=" + start + "&end=" + end,
        type: 'GET',
        cache: false,
        dataType: 'json',
        timeout: 60000,
        error: function(data, textStatus, xhr) {
            error_message = 'There was an issue listing the Pool Usage Reports' + '<br>' + '<br>' + 'Please see the following error for the reason why: "' + JSON.parse(data.responseText)
                .name + '"';
            set_error_message(error_message);
        },
        success: function(data, textStatus, xhr) {
            teamTable.fnClearTable();
            overallTable.fnClearTable();
            $.each(data.reports, function(key, report) {
                var reportData = []
                reportData.push(key);
                reportData.push(report.parent);
                $.each(report.vapp_types, function(key, type) {
                    reportData.push(type.booking_count);
                    reportData.push(type.booking_hours);
                    reportData.push(type.booking_hours_average);
                    reportData.push(type.booking_canceled_count);
                    reportData.push(type.booking_extended_count);
                });
                teamTable.fnAddData(reportData, false);
            });
            teamTable.fnDraw();
            $.each(data.overallReport, function(key, vAppReport) {
                overallTable.fnAddData([key, vAppReport.booking_count, vAppReport.booking_hours, vAppReport.booking_hours_average, vAppReport.booking_canceled_count, vAppReport.booking_extended_count], false);
            });
            overallTable.fnDraw();
            $('#loading_message_div').hide();
        }
    });
}
