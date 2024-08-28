function update_progress_bar(outer_progress_bar_selector, current_value, max_value, title, unit) {
    var percentage_value = 0;
    if (max_value > 0) {
        percentage_value = (current_value / max_value) * 100;
    }
    var percentage_value_normalized = 0;
    if (percentage_value == Number.POSITIVE_INFINITY) {
        percentage_value = 100;
    }
    if (percentage_value > 100) {
        percentage_value_normalized = 100;
    } else {
        percentage_value_normalized = percentage_value;
    }

    var percentage_text = Math.ceil(percentage_value);
    var outer_progress_obj = $(outer_progress_bar_selector);
    var inner_progress_obj = $(outer_progress_bar_selector + ' > .progress-bar');
    var inner_progress_span_obj = $(outer_progress_bar_selector + '> .progress-bar > .show');

    var desired_class = '';
    if (percentage_value < 80) {
        desired_class = 'progress-bar-success';
    } else if (percentage_value < 100) {
        desired_class = 'progress-bar-warning';
    } else {
        desired_class = 'progress-bar-danger';
    }

    var possible_classes = ["progress-bar-success", "progress-bar-warning", "progress-bar-danger", ".progress-bar-info"];
    for (i = 0; i < possible_classes.length; i++) {
        if (possible_classes[i] === desired_class) {
            if (!inner_progress_obj.hasClass(desired_class)) {
                inner_progress_obj.addClass(desired_class);
            }
        } else {
            if (inner_progress_obj.hasClass(possible_classes[i])) {
                inner_progress_obj.removeClass(possible_classes[i]);
            }
        }
    }

    var text_first_part_non_html = title + ': ';
    var text_first_part_html = '<b>' + title + ':</b> ';
    var text_second_part = current_value + ' of ' + max_value + ' ' + unit + ' - ' + percentage_text + '%';
    var progress_text_non_html = text_first_part_non_html + text_second_part;
    var progress_text_html = text_first_part_html + text_second_part;

    inner_progress_obj.css('width', percentage_value_normalized + '%');
    outer_progress_obj.attr('title', progress_text_non_html);
    inner_progress_span_obj.html(progress_text_html);
}
