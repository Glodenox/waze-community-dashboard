<?php include('templates/template_header.php'); ?>
				<div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 main">
<?php if (isset($error)) { ?>
					<div class="alert alert-danger"><?=$error?></div>
<?php } else { ?>
					<h1 class="page-header"><?=$source->name?>
<?php if ($source->state == 'running') { ?>
<span class="label label-info pull-right">Running</span>
<?php } elseif ($source->state == 'in error') { ?>
<span class="label label-danger pull-right">In Error</span>
<?php } ?>
					</h1>
					<p><?=$source->description?></p>
<?php if (isset($map_bounds) && $map_bounds !== FALSE) { ?>
					<div id="areaMap" style="height: 20vw;"></div>
<?php } ?>
					<div class="row">
<? if (isset($source_stats) && count($source_stats !== 0)) { ?>
						<div id="reportStatistics" class="<?=(isset($user) && count($source_personal_stats) !== 0 ? 'col-md-3 col-sm-6' : 'col-md-6 col-sm-12')?>" style="height:350px"></div>
<? if (isset($user) && count($source_personal_stats) !== 0) { ?>
						<div id="reportPersonalStatistics" class="col-md-3 col-sm-6" style="height:350px"></div>
<?php }
} ?>
						<dl class="dl-horizontal col-md-6 col-sm-12" style="margin-top: 4em">
							<dt>URL to source</dt>
							<dd><a href="<?=$source->url?>"><?=$source->url?></a></dd>
							<dt>Data Type</dt>
							<dd><?=$source->data_type?></dd>
							<dt>Last Update</dt>
							<dd><?=$source->last_update?> (<?=time_elapsed_string($source->last_update)?>)</dd>
<?php	if ($source->last_execution_result) { ?>
							<dt>Last update results</dt>
<?php	foreach (json_decode($source->last_execution_result) as $result_name => $result_data) {
			if ($result_name != 'errors') { ?>
							<dd><?=ucwords(str_replace('-', ' ', $result_name))?>: <?=$result_data?></dd>
<?php
			} else if (count($result_data) > 0) { ?>
							<dd>
								<strong>Errors:</strong><br />
<?php			foreach ($result_data as $error) { ?>
								<?=$error?><br />
<?php			}?>
							</dd>
<?php		}
		}
	}
?>
						</dl>
<?php } ?>
					</div>
				</div>
<?php if (isset($map_bounds) && $map_bounds !== FALSE) { ?>
				<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDH7C24331Mc6DQJc7xf7gxMOb3Z69yZ-E&amp;libraries=visualization"></script>
				<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
				<script type="text/javascript">
var areaMap = new google.maps.Map(document.getElementById('areaMap'), {
	center: { lat: <?=($map_bounds->min_lat+$map_bounds->max_lat)/2?>, lng: <?=($map_bounds->min_lon+$map_bounds->max_lon)/2?> },
	zoom: 6,
	maxZoom: 7,
	clickableIcons: false,
	mapTypeControl: false,
	streetViewControl: false
});
var heatmap = new google.maps.visualization.HeatmapLayer();
heatmap.setMap(areaMap);
updateHeatmap('all');

var dataSelectionContainer = document.createElement('div');
dataSelectionContainer.style.color = 'rgb(25,25,25)';
dataSelectionContainer.style.fontFamily = 'Roboto,Arial,sans-serif';
dataSelectionContainer.style.margin = '10px';
dataSelectionContainer.style.fontSize = '14px';
dataSelectionContainer.style.backgroundColor = '#fff';
dataSelectionContainer.style.border = '5px solid #fff';
dataSelectionContainer.style.borderRadius = '4px';
var dataSelectionLabel = document.createElement('label');
var dataSelection = document.createElement('select');
dataSelection.id = 'heatmap-filter';
dataSelectionLabel.htmlFor = dataSelection.id;
dataSelectionLabel.textContent = 'Heatmap:';
dataSelectionLabel.style.paddingRight = '4px';
dataSelectionLabel.style.margin = '0';
dataSelectionContainer.appendChild(dataSelectionLabel);
dataSelection.appendChild(new Option('All reports', 'all'));
dataSelection.appendChild(new Option('Open reports', 'open'));
<?php /*if (isset($user)) { ?>
dataSelection.appendChild(new Option('My reports', 'user'));
<?php }*/ ?>
var selectionByType = document.createElement('optgroup');
selectionByType.label = 'Filter by status';
<?php foreach (STATUSES as $name) { ?>
selectionByType.appendChild(new Option('<?=$name?>', '<?=strtolower(str_replace('_', '-', $name))?>'));
<?php } ?>
dataSelection.appendChild(selectionByType);
dataSelection.addEventListener('change', () => updateHeatmap(dataSelection.value));
dataSelectionContainer.appendChild(dataSelection);
areaMap.controls[google.maps.ControlPosition.TOP_LEFT].push(dataSelectionContainer);

function updateHeatmap(filter) {
	fetch('<?=ROOT_FOLDER?>data-sources/<?=$sourceId?>/heatmap?filter=' + filter)
		.then(response => response.json())
		.then(response => {
			if (response.ok) {
				var heatmapData = [];
				response.result.forEach(function(entry) {
					heatmapData.push({location: new google.maps.LatLng(entry.lat, entry.lon), weight: entry.reports});
				});
				heatmap.setData(heatmapData);
			} else {
				alert(response.error);
			}
		})
		.catch(error => console.error(error));
}

<?php if (isset($source_stats)) { ?>
google.charts.load('current', {'packages':['corechart']});
google.charts.setOnLoadCallback(function() {
	var statuses = new google.visualization.DataTable();
	statuses.addColumn('string', 'Status');
	statuses.addColumn('number', 'amount');
	statuses.addRows([
<?php 
if (count($source_stats) > 0) {
	$last_key = end($source_stats)->status;
	foreach ($source_stats as $source_stat) {
		echo "		['" . STATUSES[$source_stat->status] . "'," . $source_stat->count . ']' . ($source_stat->status != $last_key ? ',' : '');
	}
} ?>
	]);
	var options = { pieHole: 0.4, sliceVisibilityThreshold: 0.03, legend: { position: 'bottom', maxLines: 2 }, pieSliceText: 'value', 'title': 'Current report statuses' };
	var chart = new google.visualization.PieChart(document.getElementById('reportStatistics'));
	chart.draw(statuses, options);
	window.addEventListener('resize', function() {
		chart.draw(statuses, options);
	});
<?php if (isset($user) && count($source_personal_stats) !== 0) { ?>
	var personalStatuses = new google.visualization.DataTable();
	personalStatuses.addColumn('string', 'Status');
	personalStatuses.addColumn('number', 'amount');
	personalStatuses.addRows([
<?php 
if (count($source_personal_stats) > 0) {
	$last_key = end($source_personal_stats)->status;
	foreach ($source_personal_stats as $source_personal_stat) {
		echo "		['" . STATUSES[$source_personal_stat->status] . "'," . $source_personal_stat->count . ']' . ($source_personal_stat->status != $last_key ? ',' : '');
	}
} ?>
	]);
	var personalOptions = { pieHole: 0.4, sliceVisibilityThreshold: 0.03, legend: { position: 'bottom', maxLines: 2 }, pieSliceText: 'value', title: 'Personal changes (last 2 months)' };
	var personalChart = new google.visualization.PieChart(document.getElementById('reportPersonalStatistics'));
	personalChart.draw(personalStatuses, personalOptions);
	window.addEventListener('resize', function() {
		personalChart.draw(personalStatuses, personalOptions);
	});
<?php } ?>
});
<?php } ?>
				</script>
<?php } ?>
<?php include('templates/template_footer.php'); ?>
