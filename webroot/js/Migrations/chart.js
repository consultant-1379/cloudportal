//// All of the graph objects
var chart_cloudy_summary_ras;
var chart_cloudy_summary_teams;
var chart_cloudy_time_team;
var chart_cloudy_time_ra;
var chart_spun_time_ra;

// Define the colors
var virtual_hosts_color = '#50B432';
var physical_hosts_color = '#ed561b';
var virtual_machines_color = '#058dc7';
var virtual_apps_color = '#dddf00';
var virtual_apps_spun_up_color = '#009933';
var virtual_apps_spun_down_color = '#3333CC';

var yesterday = new Date();
yesterday.setDate(yesterday.getDate() - 1);
var yy = yesterday.getFullYear();
var dd = yesterday.getDate();
var mm = yesterday.getMonth(); //January is 0!
var yesterday_utc = Date.UTC(yy, mm, dd);

$(document).ready(function () {

    // Define the select boxes
    team_select_box = $('#migration_teams');
    ra_select_box = $('#migration_ras');

    create_empty_charts();

    // Populate migration ras select box, and only then do the general functions, as they need the ra names to be set on the graph first
    $.when(
    populate_migration_ras_select([chart_cloudy_summary_ras])).then(function () {
        general_functions();
    });
    // Setup change event actions
    ra_select_box.change(function () {
        populate_migration_teams_select($(this).val(), [chart_cloudy_summary_teams]);
        // do ra specific functions
        ra_specific_functions();
    });
    team_select_box.change(function () {
        // do team specific functions
        team_specific_functions();
    });

    // Reldraw every 2 hours
    setInterval(function () {
        general_functions();
        ra_specific_functions();
        team_specific_functions();
    }, 7200000);
});

function create_empty_charts() {
    chart_cloudy_summary_ras = create_chart_cloudy_summary_ras();
    chart_cloudy_summary_teams = create_chart_cloudy_summary_teams();
    chart_cloudy_time_team = create_chart_cloudy_time_team();
    chart_cloudy_time_ra = create_chart_cloudy_time_ra();
    chart_spun_time_ra = create_chart_spun_time_ra();
}

function general_functions() {

    var virtual_hosts_summary_title = "Cloud Hosts";
    var physical_hosts_summary_title = "Physical Hosts";
    var virtual_machines_summary_title = "VMs";
    var virtual_apps_summary_title = "vApps";

    // Set the title of the chart
    chart_cloudy_summary_ras.setTitle({
        text: "Loading..."
    });
    remove_chart_series(chart_cloudy_summary_ras);

    $.when(
    populate_ra_charts_summary("virtual_hosts", virtual_hosts_summary_title, virtual_hosts_color, 1, [chart_cloudy_summary_ras]),
    populate_ra_charts_summary("physical_hosts", physical_hosts_summary_title, physical_hosts_color, 2, [chart_cloudy_summary_ras]),
    populate_ra_charts_summary("virtual_machines", virtual_machines_summary_title, virtual_machines_color, 3, [chart_cloudy_summary_ras]),
    populate_ra_charts_summary("virtual_apps", virtual_apps_summary_title, virtual_apps_color, 4, [chart_cloudy_summary_ras])).then(function () {
        chart_cloudy_summary_ras.setTitle({
            text: "Todays Summary of Resources Per RA"
        });
        chart_cloudy_summary_ras.redraw();
    });
}

function ra_specific_functions() {
    ra_select_box.prop("disabled", true);
    var ra_name = ra_select_box.find("option:selected").text();
    var ra_id = ra_select_box.val();

    // Define legend titles
    var virtual_hosts_title = ra_name + " - Cloud Hosts";
    var physical_hosts_title = ra_name + " - Physical Hosts";
    var virtual_machines_title = ra_name + " - VMs";
    var virtual_apps_title = ra_name + " - vApps";
    var virtual_apps_spun_up_title = ra_name + " - vApps Spun Up";
    var virtual_apps_spun_down_title = ra_name + " - vApps Spun Down";

    // Remove series from charts

    chart_cloudy_time_ra.setTitle({
        text: "Loading..."
    });
    remove_chart_series(chart_cloudy_time_ra);

    // Get the data via REST
    $.when(
    populate_ra_charts_over_time(ra_id, "virtual_hosts", virtual_hosts_title, virtual_hosts_color, 1, [chart_cloudy_time_ra]),
    populate_ra_charts_over_time(ra_id, "physical_hosts", physical_hosts_title, physical_hosts_color, 2, [chart_cloudy_time_ra]),
    populate_ra_charts_over_time(ra_id, "virtual_machines", virtual_machines_title, virtual_machines_color, 3, [chart_cloudy_time_ra]),
    populate_ra_charts_over_time(ra_id, "virtual_apps", virtual_apps_title, virtual_apps_color, 4, [chart_cloudy_time_ra])).then(function () {
        chart_cloudy_time_ra.setTitle({
            text: ra_name + " - Resources Over Time"
        });
        chart_cloudy_time_ra.redraw();
    });


    chart_spun_time_ra.setTitle({
        text: "Loading..."
    });
    remove_chart_series(chart_spun_time_ra);

    // Get the data via REST
    $.when(
    populate_ra_charts_over_time(ra_id, "virtual_apps_spun_up", virtual_apps_spun_up_title, virtual_apps_spun_up_color, 5, [chart_spun_time_ra]),
    populate_ra_charts_over_time(ra_id, "virtual_apps_spun_down", virtual_apps_spun_down_title, virtual_apps_spun_down_color, 6, [chart_spun_time_ra])).then(function () {
        chart_spun_time_ra.setTitle({
            text: ra_name + " - Spun Up / Down vApps Over Time"
        });
        chart_spun_time_ra.redraw();
    });


    var virtual_hosts_summary_title = "Cloud Hosts";
    var physical_hosts_summary_title = "Physical Hosts";
    var virtual_machines_summary_title = "VMs";
    var virtual_apps_summary_title = "vApps";

    chart_cloudy_summary_teams.setTitle({
        text: "Loading..."
    });
    remove_chart_series(chart_cloudy_summary_teams);

    $.when(
    populate_team_charts_summary(ra_id, "virtual_hosts", virtual_hosts_summary_title, virtual_hosts_color, 1, [chart_cloudy_summary_teams]),
    populate_team_charts_summary(ra_id, "physical_hosts", physical_hosts_summary_title, physical_hosts_color, 2, [chart_cloudy_summary_teams]),
    populate_team_charts_summary(ra_id, "virtual_machines", virtual_machines_summary_title, virtual_machines_color, 3, [chart_cloudy_summary_teams]),
    populate_team_charts_summary(ra_id, "virtual_apps", virtual_apps_summary_title, virtual_apps_color, 4, [chart_cloudy_summary_teams])).then(function () {
        chart_cloudy_summary_teams.setTitle({
            text: ra_name + " - Todays Summary of Resources Per Team"
        });
        chart_cloudy_summary_teams.redraw();
        ra_select_box.prop("disabled", false);
    });
}

function populate_ra_charts_summary(type, title, series_color, series_legend_index, chart_array) {
    return $.ajax({
        url: "MigNightlyCounts/ras_summary_per_type/type:" + type + "/.json",
        dataType: "json",
        success: function (json) {

            var series_data = [];
            $.each(json.summary, function (i, item) {
                var thenumber = parseInt(item.count, 10);
                var missing = parseInt(item.missing, 10);
                if (isNaN(thenumber)) {
                    thenumber = null;
                } else if (type == "virtual_hosts") {
                    thenumber = Math.round(thenumber / 10000);
                }
                series_data.push({
                    y: thenumber,
                    missing: missing
                });
            });
            var visibility = true;
            if (type == "virtual_machines") {
                visibility = false;
            }
            for (i = 0; i < chart_array.length; i++) {
                chart_array[i].addSeries({
                    name: title,
                    legendIndex: series_legend_index,
                    index: series_legend_index,
                    data: series_data,
                    color: series_color,
                    visible: visibility
                }, false);
            }
        }
    });
}

function populate_team_charts_summary(ra_id, type, title, series_color, series_legend_index, chart_array) {
    return $.ajax({
        url: "MigNightlyCounts/teams_summary_per_ra_and_type/ra_id:" + ra_id + "/type:" + type + "/.json",
        dataType: "json",
        success: function (json) {

            var series_data = [];
            $.each(json.summary, function (i, item) {
                var thenumber = parseInt(item, 10);
                if (isNaN(thenumber)) {
                    thenumber = null;
                } else if (type == "virtual_hosts") {
                    thenumber = Math.round(thenumber / 10000);
                }
                series_data.push(
                thenumber);
            });
            var visibility = true;
            if (type == "virtual_machines") {
                visibility = false;
            }
            for (i = 0; i < chart_array.length; i++) {
                chart_array[i].addSeries({
                    name: title,
                    legendIndex: series_legend_index,
                    index: series_legend_index,
                    data: series_data,
                    color: series_color,
                    visible: visibility
                }, false);
            }
        }
    });
}

function team_specific_functions() {
    team_select_box.prop("disabled", true);
    ra_select_box.prop("disabled", true);
    var team_name = team_select_box.find("option:selected").text();
    var team_id = team_select_box.val();

    // Define legend titles
    var virtual_hosts_title = team_name + " - Cloud Hosts";
    var physical_hosts_title = team_name + " - Physical Hosts";
    var virtual_machines_title = team_name + " - VMs";
    var virtual_apps_title = team_name + " - vApps";

    // Remove series from charts
    chart_cloudy_time_team.setTitle({
        text: "Loading..."
    });
    remove_chart_series(chart_cloudy_time_team);
    // Get the data via REST
    $.when(
    populate_team_charts_over_time(team_id, "virtual_hosts", virtual_hosts_title, virtual_hosts_color, 1, [chart_cloudy_time_team]),
    populate_team_charts_over_time(team_id, "physical_hosts", physical_hosts_title, physical_hosts_color, 2, [chart_cloudy_time_team]),
    populate_team_charts_over_time(team_id, "virtual_machines", virtual_machines_title, virtual_machines_color, 3, [chart_cloudy_time_team]),
    populate_team_charts_over_time(team_id, "virtual_apps", virtual_apps_title, virtual_apps_color, 4, [chart_cloudy_time_team])).then(function () {
        chart_cloudy_time_team.setTitle({
            text: team_name + " - Resources Over Time"
        });
        chart_cloudy_time_team.redraw();
        team_select_box.prop("disabled", false);
        ra_select_box.prop("disabled", false);
    });
}

function populate_ra_charts_over_time(ra_id, type, series_title, series_color, series_legend_index, chart_array) {
    return $.ajax({
        url: "MigNightlyCounts/by_ra_and_type/ra_id:" + ra_id + "/type:" + type + "/.json",
        dataType: "json",
        success: function (json) {
            var series_data = [];
            // Prepare the returned data into an object for the graph to use
            $.each(json.counts, function (i, item) {
                var thenumber = parseInt(item.MigNightlyCount.count, 10);
                if (type == "virtual_hosts") {
                    thenumber = Math.round(thenumber / 10000);
                }
                var t = item.MigNightlyCount.date.split(/[-]/);
                series_data.push([
                Date.UTC(t[0], t[1] - 1, t[2]),
                thenumber]);
            });
            series_data = add_dummy_point(series_data);
            var visibility = true;
            if (type == "virtual_machines") {
                visibility = false;
            }

            for (i = 0; i < chart_array.length; i++) {
                // Add the new series to the graph
                chart_array[i].addSeries({
                    type: 'line',
                    pointInterval: 24 * 3600 * 1000,
                    name: series_title,
                    legendIndex: series_legend_index,
                    index: series_legend_index,
                    data: series_data,
                    color: series_color,
                    visible: visibility
                }, false);
            }
        }

    });
}

function add_dummy_point(series_data) {
    // Add a dummy last point if it doesn't exist, to make the line graphs look better
    var latest = series_data[series_data.length - 1];
    if (latest) {
        // If the latest point is older than yesterday, add a new dummy one
        if (latest[0] < yesterday_utc) {
            series_data.push([
            yesterday_utc,
            latest[1]]);
        }
    }
    // End of adding dummy point
    return series_data;
}

function populate_team_charts_over_time(team_id, type, series_title, series_color, series_legend_index, chart_array) {
    return $.ajax({
        url: "MigNightlyCounts/by_team_and_type/team_id:" + team_id + "/type:" + type + "/.json",
        dataType: "json",
        success: function (json) {
            var series_data = [];
            // Prepare the returned data into an object for the graph to use
            $.each(json.counts, function (i, item) {
                var thenumber = parseInt(item.MigNightlyCount.count, 10);
                if (type == "virtual_hosts") {
                    thenumber = Math.round(thenumber / 10000);
                }
                var t = item.MigNightlyCount.date.split(/[-]/);
                series_data.push([
                Date.UTC(t[0], t[1] - 1, t[2]),
                thenumber]);
            });
            series_data = add_dummy_point(series_data);
            var visibility = true;
            if (type == "virtual_machines") {
                visibility = false;
            }

            for (i = 0; i < chart_array.length; i++) {
                // Add the new series to the graph
                chart_array[i].addSeries({
                    type: 'line',
                    pointInterval: 24 * 3600 * 1000,
                    name: series_title,
                    legendIndex: series_legend_index,
                    index: series_legend_index,
                    data: series_data,
                    color: series_color,
                    visible: visibility
                }, false);
            }
        }

    });
}

function populate_migration_teams_select(ra_id, chart_array) {
    ra_select_box.prop("disabled", true);
    team_select_box.prop("disabled", true);
    team_select_box.empty();
    team_select_box.append('<option value="0">Loading...</option>');

    $.ajax({
        url: "MigTeams/teams_per_ra/ra_id:" + ra_id + "/.json",
        dataType: "json",
        success: function (json) {
            var team_names = [];
            team_select_box.empty();

            $.each(json.migTeams, function (i, item) {
                team_select_box.append('<option value="' + item.MigTeam.id + '">' + item.MigTeam.name + '</option>');
                team_names.push(item.MigTeam.name);
            });
            for (i = 0; i < chart_array.length; i++) {
                chart_array[i].xAxis[0].setCategories(team_names, false);
            }
            team_select_box.prop("disabled", false);
            //ra_select_box.prop("disabled", false);
            team_select_box.trigger('change');
        }
    });
}

function populate_migration_ras_select(chart_array) {
    ra_select_box.prop("disabled", true);
    team_select_box.prop("disabled", true);
    ra_select_box.empty();
    ra_select_box.append('<option value="0">Loading...</option>');
    team_select_box.empty();
    team_select_box.append('<option value="0">Loading...</option>');

    return $.ajax({
        url: "MigRas/index.json",
        dataType: "json",
        success: function (json) {
            var ra_names = [];
            ra_select_box.empty();
            $.each(json.migRas, function (i, item) {
                ra_select_box.append('<option value="' + item.MigRa.id + '">' + item.MigRa.name + '</option>');
                ra_names.push(item.MigRa.name);
            });
            for (i = 0; i < chart_array.length; i++) {
                chart_array[i].xAxis[0].setCategories(ra_names, false);
            }
            ra_select_box.prop("disabled", false);
            ra_select_box.trigger('change');
        }
    });
}

function create_chart_cloudy_time_ra() {
    var chart_params = {
        chart: {
            type: 'line',
            zoomType: 'xy'
        },
        title: {
            text: "Loading..."
        },
        credits: false,
        subtitle: {
            text: 'Click and drag to zoom'
        },
        xAxis: {
            type: 'datetime',
            maxZoom: 14 * 24 * 3600000 // 2 weeks
        },
        yAxis: {
            title: {
                text: 'Count'
            },
            showFirstLabel: false,
            allowDecimals: false
        },
        plotOptions: {
            series: {
                animation: false
            },
            line: {
                dataLabels: {
                    enabled: false
                },
                marker: {
                    enabled: false
                },
                enableMouseTracking: true
            }
        },
        tooltip: {
            shared: true,
            followPointer: true,
            crosshairs: true
        }
    };
    return $('#chart_cloudy_time_ra_container').highcharts(chart_params).highcharts();
}

function create_chart_spun_time_ra() {
    var chart_params = {
        chart: {
            type: 'line',
            zoomType: 'xy'
        },
        title: {
            text: "Loading..."
        },
        credits: false,
        subtitle: {
            text: 'Click and drag to zoom'
        },
        xAxis: {
            type: 'datetime',
            maxZoom: 14 * 24 * 3600000 // 2 weeks
        },
        yAxis: {
            title: {
                text: 'Count'
            },
            showFirstLabel: false,
            allowDecimals: false
        },
        plotOptions: {
            series: {
                animation: false
            },
            line: {
                dataLabels: {
                    enabled: false
                },
                marker: {
                    enabled: false
                },
                enableMouseTracking: true
            }
        },
        tooltip: {
            shared: true,
            followPointer: true,
            crosshairs: true
        }
    };
    return $('#chart_spun_time_ra_container').highcharts(chart_params).highcharts();
}


function create_chart_cloudy_time_team() {
    var chart_params = {
        chart: {
            type: 'line',
            zoomType: 'xy'
        },
        title: {
            text: "Loading..."
        },
        credits: false,
        subtitle: {
            text: 'Click and drag to zoom'
        },
        xAxis: {
            type: 'datetime',
            maxZoom: 14 * 24 * 3600000 // 2 weeks
        },
        yAxis: {
            title: {
                text: 'Count'
            },
            showFirstLabel: false,
            allowDecimals: false
        },
        plotOptions: {
            series: {
                animation: false
            },
            line: {
                dataLabels: {
                    enabled: false
                },
                marker: {
                    enabled: false
                },
                enableMouseTracking: true
            }
        },
        tooltip: {
            shared: true,
            followPointer: true,
            crosshairs: true
        }
    };
    return $('#chart_cloudy_time_team_container').highcharts(chart_params).highcharts();
}

function create_chart_cloudy_summary_ras() {
    var chart_params = {
        chart: {
            type: 'column'
        },
        title: {
            text: "Loading..."
        },
        subtitle: {
            text: "Click an RA to see its teams"
        },
        credits: false,
        yAxis: {
            min: 0,
            title: {
                text: 'Count'
            },
            allowDecimals: false
        },
        plotOptions: {
            series: {
                animation: false,
                cursor: 'pointer',
                point: {
                    events: {
                        click: function () {
                            change_select_by_text(ra_select_box, this.category);
                            scroll_to_element('#ra_specific');
                        }
                    }
                }
            },
            column: {
                pointWidth: 60,
                stacking: 'normal',
                dataLabels: {
                    enabled: true,
                    formatter: function () {
                        if (this.y !== 0) {
                            return this.y;
                        } else {
                            return '';
                        }
                    },
                    color: (Highcharts.theme && Highcharts.theme.dataLabelsColor) || 'black'
                }
            }
        },
        tooltip: {
            shared: true,
            followPointer: true,
            formatter: function () {
                var chart = this.points[0].series.chart; //get the chart object
                var categories = chart.xAxis[0].categories; //get the categories array
                var index = 0;
                while (this.x !== categories[index]) {
                    if (!categories[index]) {
                        return "";
                    }
                    index++;
                } //compute the index of corr y value in each data arrays

                var percentage = '';
                var percentage_color = '';
                var missing_data_indicator = '';

                if ((chart.series[0].data[index].y !== null) && (chart.series[1].data[index].y !== null)) {
                    percentage = Math.round((chart.series[0].data[index].y * 100) / (chart.series[0].data[index].y + chart.series[1].data[index].y));
                    if (percentage < 80) {
                        percentage_color = physical_hosts_color;
                    } else {
                        percentage_color = virtual_hosts_color;
                    }
                    percentage = percentage + '%';

                } else {
                    percentage = "Unsure";
                    percentage_color = 'orange';
                }

                // If either physical or cloud hosts are missing any teams data, show a star beside the percentage
                if ((chart.series[0].data[index].missing > 0) || (chart.series[1].data[index].missing > 0)) {
                    missing_data_indicator = '<span style="color:red;">*</span>';
                }

                var percentage_string = '<span style="color:' + percentage_color + '">' + percentage + '</span>' + missing_data_indicator;
                var s = this.x + " on " + Highcharts.dateFormat('%a %d %b, %Y', new Date()) + "";
                s += "<br/><b>% of Hosts In Cloud: </b>" + percentage_string;
                $.each(this.points, function (i, point) {
                    // If theres any missing data for this type, show it next to the data
                    var missing_note = "";
                    if (point.point.missing > 0) {
                        missing_note = ' (<span style="color:red;">* Missing ' + point.point.missing + ' teams data</span>)';
                    }
                    s += '<br/><span style="color:' + point.series.color + '">' + point.series.name + '</span>: <b>' + point.y + '</b>' + missing_note;
                });
                return s;
            }
        }
    };
    return $('#chart_cloudy_summary_ras_container').highcharts(chart_params).highcharts();
}

function create_chart_cloudy_summary_teams() {
    var chart_params = {
        chart: {
            type: 'column'
        },
        title: {
            text: "Loading..."
        },
        subtitle: {
            text: "Click a team to see its progression over time"
        },
        scrollbar: {
            enabled: true
        },
        credits: false,
        xAxis: {
            max: 10
        },
        yAxis: {
            min: 0,
            title: {
                text: 'Count'
            },
            allowDecimals: false
        },
        plotOptions: {
            series: {
                animation: false,
                cursor: 'pointer',
                point: {
                    events: {
                        click: function () {
                            change_select_by_text(team_select_box, this.category);
                            scroll_to_element('#team_specific');
                        }
                    }
                }
            },
            column: {
                pointWidth: 30,
                stacking: 'normal',
                dataLabels: {
                    enabled: true,
                    formatter: function () {
                        if (this.y !== 0) {
                            return this.y;
                        } else {
                            return '';
                        }
                    },
                    color: (Highcharts.theme && Highcharts.theme.dataLabelsColor) || 'black'
                }
            }
        },
        tooltip: {
            shared: true,
            followPointer: true,
            formatter: function () {
                var chart = this.points[0].series.chart; //get the chart object
                var categories = chart.xAxis[0].categories; //get the categories array
                var index = 0;
                while (this.x !== categories[index]) {
                    if (!categories[index]) {
                        return "";
                    }
                    index++;
                } //compute the index of corr y value in each data arrays
                var percentage = '';
                var percentage_color = '';
                if ((chart.series[0].data[index].y !== null) && (chart.series[1].data[index].y !== null)) {
                    percentage = Math.round((chart.series[0].data[index].y * 100) / (chart.series[0].data[index].y + chart.series[1].data[index].y));
                    if (percentage < 80) {
                        percentage_color = physical_hosts_color;
                    } else {
                        percentage_color = virtual_hosts_color;
                    }
                    percentage = percentage + '%';
                } else {
                    percentage = "Unsure";
                    percentage_color = 'orange';
                }
                var percentage_string = '<span style="color:' + percentage_color + '">' + percentage + '</span>';
                var s = this.x + " on " + Highcharts.dateFormat('%a %d %b, %Y', new Date()) + "";
                s += "<br/><b>% of Hosts In Cloud: </b>" + percentage_string;
                $.each(this.points, function (i, point) {
                    s += '<br/><span style="color:' + point.series.color + '">' + point.series.name + '</span>: <b>' + point.y + '</b>';
                });
                return s;
            }
        }
    };
    return $('#chart_cloudy_summary_teams_container').highcharts(chart_params).highcharts();
}


// some helper functions
function remove_chart_series(chart) {
    while (chart.series.length > 0) {
        chart.series[0].remove(false);
    }
}

function change_select_by_text(select, text) {
    // Make sure if the select is disabled not to touch it
    if (select.prop("disabled") === true) {
        return;
    }
    select.each(function () {
        $('option', this).each(function () {
            if ($(this).text() == text) {
                if (select.val() != $(this).val()) {
                    select.val($(this).val());
                    select.trigger('change');
                }
                return false;
            }
        });
    });
}

function scroll_to_element(element_name) {
    $('html, body').animate({
        scrollTop: $(element_name).offset().top
    }, 'fast');
}
