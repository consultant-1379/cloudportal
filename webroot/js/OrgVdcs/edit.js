$(document).ready(function() {

    var cpus_limit_total_without_this=0;
    var memory_limit_total_without_this=0;
    $('.cpus_limit').each(function(index) {
        cpus_limit_total_without_this+=parseInt($(this).html(), 10);
    });
    $('.memory_limit').each(function(index) {
        var this_memory_limit = $(this).html();
        memory_limit_total_without_this+=parseInt(this_memory_limit, 10);
        $(this).html(this_memory_limit);
    });

    function roundToTwo(num) {
        return +(Math.round(num + "e+2")  + "e-2");
    }

    function update_summary()
    {
        // Get provider related fields
        var cpus_available_in_provider = parseInt($('#cpus_available_in_provider').html(), 10);
        var memory_available_in_provider = parseInt($('#memory_available_in_provider').html(), 10);

        // Calculate cpu and memory totals
        var cpus_limit = parseInt($('#cpus_limit').val(), 10);
        var cpus_limit_total = parseInt(cpus_limit_total_without_this, 10) + cpus_limit;
        var memory_limit = parseInt($('#memory_limit').val(), 10);
        var memory_limit_total = memory_limit_total_without_this + memory_limit;

        // Update this orgvdcs fields to make them stand out
        $('#this_orgvdc_cpus_limit').html("<b>" + cpus_limit + "</b>");
        $('#this_orgvdc_memory_limit').html("<b>" + memory_limit + "</b>");

        if (cpus_limit === 0)
        {
            orgvdc_ratio_string = "1 / 1";
        }
        else
        {
            orgvdc_ratio_string = roundToTwo(memory_limit / cpus_limit) + ' / 1';
        }

        if (cpus_available_in_provider === 0)
        {
            providervdc_ratio_string = "1 / 1";
        }
        else
        {
            providervdc_ratio_string = roundToTwo(memory_available_in_provider / cpus_available_in_provider) + ' / 1';
        }
        var summary_string = '<b>Resulting CPUs Allocated:</b> <span class="highlight">' + cpus_limit_total + "</span> of " + cpus_available_in_provider + " available in provider." +
        '<br>' +
        '<b>Resulting Memory Allocated:</b> <span class="highlight">' + memory_limit_total + " GB</span> of " + memory_available_in_provider + " GB available in provider." +
        '<br>' +
        '<br>' +
        '<b>OrgVdc Memory(GB) / CPU Ratio:</b> <span class="highlight">' + orgvdc_ratio_string + '</span>' +
        '<br>' +
        '<b>Provider Memory(GB) / CPU Ratio:</b> ' + providervdc_ratio_string;

        $('#summary_div').html(summary_string);
        $('.highlight').animate({color:'#4169E1', opacity: '1'}).animate({opacity: '1'});
    }

    $('.affects_summary').change(function() {
        update_summary();
    });
    update_summary();
});
