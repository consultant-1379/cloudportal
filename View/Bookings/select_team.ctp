<?php
    // Javascript
    echo $this->Html->script('Bookings/select_team');
    // CSS
    echo $this->Html->css('Bookings/select_team');
?>
<h1>Select Team</h1>
<div class="layout-bar"></div>
<h3>Please select your team from the list below, before completing any actions.
If your team isn't in the list, please log a support ticket to have it added.</h3>
<?php
        $team_values = array();
        $team_values[''] = '<Select Your Team>';
        foreach ($teams as $team) {
            $team_values[$team['team']] = $team['team'];
        }
        echo $this->Form->input('team', array('id' => 'team', 'options' => $team_values, 'empty' => false, 'default' => $users_team, 'label' => 'Select Your Team'));
?>
