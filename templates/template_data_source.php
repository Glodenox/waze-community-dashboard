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
					<div id="areaMap" style="height:20vw; position:relative">
						<div unselectable="on" class="olControlNoSelect" style="position:absolute; top:10px; z-index:2000; text-align:center; left:0; right:0;" id="heatmap-message">
							<span style="background-color:#fff; padding:3px; color:#777"><i class="fa fa-spinner fa-pulse"></i> Heatmap loading...</span>
						</div>
						<div unselectable="on" class="olControlNoSelect" style="position:absolute; bottom:10px; left:10px; z-index: 2000">
							<label style="background-color:#fff; padding:3px; color:#777">
								Heatmap:
								<select id="heatmap-filter">
									<option value="all">All reports</option>
									<option value="open">Open reports</option>
									<optgroup label="Filter by status" id="heatmap-filter-statuses"></optgroup>
								</select>
							</label>
						</div>
					</div>
<?php } ?>
					<div class="row">
<?php if (isset($source_stats) && count($source_stats !== 0)) { ?>
						<div id="reportStatistics" class="<?=(isset($user) && count($source_personal_stats) !== 0 ? 'col-md-3 col-sm-6' : 'col-md-6 col-sm-12')?>" style="height:350px"></div>
<?php	if (isset($user) && count($source_personal_stats) !== 0) { ?>
						<div id="reportPersonalStatistics" class="col-md-3 col-sm-6" style="height:350px"></div>
<?php	}
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
				<script src="<?=ROOT_FOLDER?>js/OpenLayers.js"></script>
				<script src="<?=ROOT_FOLDER?>js/heatmap.js"></script>
				<script src="<?=ROOT_FOLDER?>js/heatmap-openlayers-renderer.js"></script>
				<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
				<script type="text/javascript">
var mapProjection = 'EPSG:900913';
var siteProjection = 'CRS:84';
OpenLayers.IMAGE_RELOAD_ATTEMPTS = 2;
var areaMap = new OpenLayers.Map({
	div: 'areaMap',
	center: (new OpenLayers.LonLat(<?=($map_bounds->min_lon+$map_bounds->max_lon)/2?>, <?=($map_bounds->min_lat+$map_bounds->max_lat)/2?>)).transform(siteProjection, mapProjection),
	layers: [
		new OpenLayers.Layer.XYZ('Waze Livemap', [
			'https://worldtiles1.waze.com/tiles/${z}/${x}/${y}.png',
			'https://worldtiles2.waze.com/tiles/${z}/${x}/${y}.png',
			'https://worldtiles3.waze.com/tiles/${z}/${x}/${y}.png',
			'https://worldtiles4.waze.com/tiles/${z}/${x}/${y}.png'
		], {
			projection: mapProjection,
			attribution: '&copy; 2006-' + (new Date()).getFullYear() + ' <a href="https://www.waze.com/livemap" target="_blank">Waze Mobile</a>. All Rights Reserved.'
		})
	],
	zoom: 7
});

var heatmap = new OpenLayers.Layer.Vector('Heatmap', {
	opacity: 0.7,
	renderers: [ 'Heatmap' ],
	rendererOptions: {
		weight: 'count',
		heatmapConfig: { radius: 15 }
	}
});
areaMap.addLayer(heatmap);

updateHeatmap('all');

<?php foreach (STATUSES as $name) { ?>
document.getElementById('heatmap-filter-statuses').appendChild(new Option('<?=$name?>', '<?=strtolower(str_replace('_', '-', $name))?>'));
<?php } ?>
document.getElementById('heatmap-filter').addEventListener('change', (e) => updateHeatmap(e.target.value));

function updateHeatmap(filter) {
	document.getElementById('heatmap-message').style.display = 'block';
	fetch('<?=ROOT_FOLDER?>data-sources/<?=$sourceId?>/heatmap?filter=' + filter)
		.then(response => response.json())
		.then(response => {
			if (response.ok) {
				var heatmapData = [];
				response.result.forEach(function(entry) {
					var point = new OpenLayers.Geometry.Point(entry.lon, entry.lat).transform(siteProjection, mapProjection);
					heatmapData.push(new OpenLayers.Feature.Vector(point, { count: entry.reports}));
				});
				heatmap.removeAllFeatures();
				heatmap.addFeatures(heatmapData);
				document.getElementById('heatmap-message').style.display = 'none';
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
