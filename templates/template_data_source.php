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
<?php if (count($heatmap) !== 0) { ?>
					<div id="areaMap" style="height: 20vw;"></div>
<?php } ?>
					<dl class="dl-horizontal">
						<dt>URL to documentation</dt>
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
<?php if (count($heatmap) !== 0) { ?>
				<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDH7C24331Mc6DQJc7xf7gxMOb3Z69yZ-E&amp;libraries=visualization"></script>
				<script type="text/javascript">
var heatmap = <?=json_encode($heatmap, JSON_NUMERIC_CHECK)?>;
var latAvg = createAverageObj();
var lonAvg = createAverageObj();
var heatmapData = [];
heatmap.forEach(function(entry) {
	heatmapData.push({location: new google.maps.LatLng(entry.lat, entry.lon), weight: entry.reports});
	latAvg.add(entry.lat);
	lonAvg.add(entry.lon);
});
var areaMap = new google.maps.Map(document.getElementById('areaMap'), {
	center: { lat: latAvg.getAverage(), lng: lonAvg.getAverage() },
	zoom: 6,
	maxZoom: 6,
	clickableIcons: false,
	mapTypeControl: false,
	streetViewControl: false
});
var heatmap = new google.maps.visualization.HeatmapLayer({
	data: heatmapData
});
heatmap.setMap(areaMap);

function createAverageObj() {
	var count = 0;
	var sum = 0;
	return {
		'add': function(n) {
			sum += n;
			count++;
		},
		'getAverage': function() {
			return sum / count;
		}
	};
}
				</script>
<?php } ?>
<?php include('templates/template_footer.php'); ?>
