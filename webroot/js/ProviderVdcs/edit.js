$(document).ready(function() {
    function update_summary()
    {
        var provider_cpus = $('#provider_cpus').val();
        var provider_memory = $('#provider_memory').val();
        var cpu_multiplier = $('#cpu_multiplier').val();
        var memory_multiplier = $('#memory_multiplier').val();
        var calculated_cpu_value = Math.floor(provider_cpus * cpu_multiplier);
        var calculated_memory_value = Math.floor(provider_memory * memory_multiplier);

        var summary_string = '<b>Resulting CPUs Available:</b> ' + calculated_cpu_value  + ' (' + provider_cpus + ' * ' + cpu_multiplier + ')' +
        '<br>' +
        '<b>Resulting Memory Available:</b> ' + calculated_memory_value + ' GB (' + provider_memory + ' GB * ' + memory_multiplier + ')';
        $('#summary_div').html(summary_string);
    }

    $('.affects_summary').change(function() {
        update_summary();
    });
    update_summary();
});
