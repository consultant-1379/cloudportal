var theTable;
$(document).ready(function () {
    theTable = $('#vapps_table').dataTable();
    var orgvdc_id = $('#orgvdc_id').html();

    start_quota_ajax(orgvdc_id);

    // The monitor status button
    $('.monitor_button').button();
    $('.monitor_button').click(function () {
        var id = $(this).parents("tr:first").attr("id");
        var id_escaped = escapeit(id);
        $('#' + id_escaped + " .vapp_status").html("Retrieving..");
        get_tasks(id, "/Vapps/tasks/vapp_id:");
    });

    // Stop double clicking the sharing checkbox, bubbling down
    $('.share_checkbox').dblclick(function (event) {
        event.stopPropagation();
    });

    // When sharing / unsharing a vApp, what to do
    $('.share_checkbox').change(function (event) {
        event.stopPropagation();
        var id = $(this).parents("tr:first").attr("id");
        var orgvdc_id = $("#orgvdc_id").html();
        var url = "";
        if ($(this).val() == "1") {
            url = encodeURI("/Vapps/share/vapp_id:" + id + "/orgvdc_id:" + orgvdc_id);
        } else {
            url = encodeURI("/Vapps/unshare/vapp_id:" + id + "/orgvdc_id:" + orgvdc_id);
        }

        window.location = url;
    });
});

function start_quota_ajax(orgvdc_id) {
    $.ajax({
        url: "/Vapps/newquotas/orgvdc_id:" + orgvdc_id + "/.json",
        type: 'GET',
        cache: false,
        dataType: 'json',
        timeout: 100000,
        success: function (data, textStatus, xhr) {
            var total_cpus_on_count = 0;
            var total_memory_on_count = 0;
            var total_cpus_count = 0;
            var total_memory_count = 0;
            var total_vapps = data.vapps.length;

            $.each(data.vapps, function (key, val) {
                total_cpus_on_count += val.cpu_on_count;
                total_memory_on_count += val.memory_on_count;
                total_cpus_count += val.cpu_total;
                total_memory_count += val.memory_total;

                var vapp_id_escaped = escapeit(val.vapp_id);

                var memory_on_gb = Math.ceil(val.memory_on_count / 1024);
                var memory_total_gb = Math.ceil(val.memory_total / 1024);
                if ($('#' + vapp_id_escaped).length) {
                    $('#' + vapp_id_escaped + ' .vapp_cpu_usage').attr('title', val.cpu_on_count + ' of ' + val.cpu_total);
                    theTable.fnUpdate(val.cpu_on_count, $('#' + vapp_id_escaped)[0], 8, false, false);
                    $('#' + vapp_id_escaped + ' .vapp_memory_usage').attr('title', memory_on_gb + ' of ' + memory_total_gb);
                    theTable.fnUpdate(memory_on_gb, $('#' + vapp_id_escaped)[0], 9, false, false);
                }
            });

            if (data.datacenter_quotas.ProviderVdc.new_quota_system == 1) {
                $('#running_cpus_quota').parent().show();
                $('#running_memory_quota').parent().show();
                $('#running_vapps_quota').parent().hide();
                update_progress_bar('#running_cpus_quota', total_cpus_on_count, data.datacenter_quotas.OrgVdc.cpu_limit, 'Running CPUs', 'CPUs', true);
                update_progress_bar('#running_memory_quota', Math.ceil(total_memory_on_count / 1024), data.datacenter_quotas.OrgVdc.memory_limit, 'Running Memory', 'GB', true);
            } else {
                $('#running_cpus_quota').parent().hide();
                $('#running_memory_quota').parent().hide();
                $('#running_vapps_quota').parent().show();
                update_progress_bar('#running_vapps_quota', data.running_vapps.running, data.datacenter_quotas.OrgVdc.running_tb_limit, 'Running vApps', '', true);
            }
            update_progress_bar('#total_vapps_quota', total_vapps, data.datacenter_quotas.OrgVdc.stored_tb_limit, 'Total vApps', '', true);
        },
        error: function (xhr, textStatus, errorThrown) {},
        complete: function (xhr, textStatus) {
            setTimeout("start_quota_ajax('" + orgvdc_id + "')", 10000);
        }
    });
}
