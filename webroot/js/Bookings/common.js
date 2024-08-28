function seconds_to_hours_minutes_and_seconds(input_seconds) {
    var hours = Math.floor(input_seconds / 3600);
    var minutes = Math.floor((input_seconds - (hours * 3600)) / 60);
    var seconds = Math.floor(input_seconds % 60);
    var final_string = '';

    if (hours > 0) {
        hours_string = hours + ' ' + (hours == 1 ? 'hour' : 'hours');
        final_string = hours_string;
    }
    if (minutes > 0) {
        minutes_string = minutes + ' ' + (minutes == 1 ? 'minute' : 'minutes');
        final_string = final_string + ' ' + minutes_string;
    }
    if (seconds > 0) {
        seconds_string = seconds + ' ' + (seconds == 1 ? 'second' : 'seconds');
        final_string = final_string + ' ' + seconds_string;
    }
    return final_string;
}

function seconds_to_hours_minutes_rounded(input_seconds) {
    var hours = Math.floor(input_seconds / 3600);
    var minutes = Math.floor((input_seconds - (hours * 3600)) / 60);
    var minutes = minutes - (minutes % 10);
    var final_string = '';

    if (hours > 0) {
        hours_string = hours + ' ' + (hours == 1 ? 'hour' : 'hours');
        final_string = hours_string;
    }
    if (minutes > 0) {
        minutes_string = minutes + ' minutes';
        final_string = final_string + ' ' + minutes_string;
    }
    return final_string;
}

