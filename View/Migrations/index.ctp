
<?php
	// Highcharts
	echo $this->Html->script('staticfiles/highstock.1.3.10/highstock.js');
	echo $this->Html->script('staticfiles/highstock.1.3.10/modules/exporting.js');
	echo $this->Html->script('staticfiles/highstock.1.3.10/themes/gray.js');

	// Our javascript code
	echo $this->Html->script('Migrations/chart.js');
?>
<div style="display: none;">
        <label for="migration_ras">Select an RA:</label>
        <select id="migration_ras">
        </select>
        <label for="migration_teams">Select A Team:</label>
        <select id="migration_teams">
        </select>
</div>
<div id="graph_container">
	<div id="summary_ras">
		<h3>Overall Summary</h3>
		<div id="chart_cloudy_summary_ras_container" style="min-width: 1000px; height: 400px; margin: 0 auto"></div>
		<br>
	</div>
	<div id="ra_specific">
		<h3>RA Specific</h3>
		<div id="chart_cloudy_summary_teams_container" style="min-width: 1000px; height: 400px; margin: 0 auto"></div>
		<br>
		<div id="chart_cloudy_time_ra_container" style="min-width: 1000px; height: 400px; margin: 0 auto"></div>
		<br>
		<div id="chart_spun_time_ra_container" style="min-width: 1000px; height: 400px; margin: 0 auto"></div>
                <br>
	</div>
	<div id="team_specific">
		<h3>Team Specific</h3>
		<div id="chart_cloudy_time_team_container" style="min-width: 1000px; height: 400px; margin: 0 auto"></div>
		<br>
	</div>
</div>
<a href='#'>Back to top</a>
<div style="height:600px;"></div>
