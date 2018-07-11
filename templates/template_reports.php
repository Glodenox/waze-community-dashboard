<?php include('templates/template_header.php'); ?>
				<div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 main">
					<h1 class="page-header">Reports</h1>
					<div id="list-view">
						<div class="tab-pane active filters container-fluid" id="geographicsearch">
							<div style="margin-right:15px">
								<form action="#" method="GET">
									<fieldset class="form-group">
										<label for="reportStatusFilter">Filter by report status</label>
										<select class="form-control" id="reportStatusFilter">
											<option value="actionable">Requires action (New, Updated, Cancelled)</option>
<?php foreach(STATUSES as $status_idx => $status) { ?>
											<option value="<?=$status_idx?>"><?=$status?></option>
<?php } ?>
											<option value="-1">Any report status</option>
										</select>
									</fieldset>
									<fieldset class="form-group">
										<label for="sourceFilter">Filter by source</label>
										<select class="form-control" id="sourceFilter">
											<option value="-1">Any source</option>
<?php foreach($report_sources as $source) { ?>
											<option value="<?=$source->id?>"><?=$source->name?></option>
<?php } ?>
										</select>
									</fieldset>
									<fieldset class="form-group">
										<label for="priorityFilter">Filter by priority</label>
										<select class="form-control" id="priorityFilter">
											<option value="-1">Any priority</option>
<?php foreach(PRIORITIES as $priority_idx => $priority) { ?>
											<option value="<?=$priority_idx?>"><?=$priority?></option>
<?php } ?>
										</select>
									</fieldset>
									<fieldset class="form-group">
										<label for="editorLevelFilter">Filter by required editor level</label>
										<select class="form-control" id="editorLevelFilter">
											<option value="-1">Any level</option>
											<option value="0">Not set</option>
<?php foreach(range(1, 6) as $editor_level) { ?>
											<option value="<?=$editor_level?>"><?=$editor_level?></option>
<?php } ?>
										</select>
									</fieldset>
									<fieldset class="form-group">
										<label for="periodFilter">Filter by time period</label>
										<select class="form-control" id="periodFilter">
											<option value="">No filter</option>
											<option value="past">Past</option>
											<option value="active">Active</option>
											<option value="soon">Soon (next two weeks)</option>
											<option value="future">Future</option>
										</select>
									</fieldset>
<?php if (isset($user)) { ?>
									<fieldset class="form-group">
										<label><input type="checkbox" id="followedFilter"> Only show reports that I follow</label>
									</fieldset>
<?php } ?>
								</form>
							</div>
							<div id="filterMap"></div>
						</div>
						<nav class="text-center">
							<ul id="pagination" class="pagination"></ul>
						</nav>
						<div class="table-responsive">
							<table class="table table-striped table-hover table-condensed">
								<thead>
									<tr>
										<th></th>
										<th class="text-center">Start time</th>
										<th class="text-center">End time</th>
										<th>Description</th>
										<th class="text-center">Source</th>
									</tr>
								</thead>
								<tbody id="reports">
									<tr id="no-reports" class="hidden warning">
										<td colspan="6" class="text-center">No reports found</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
					<div id="report-view" class="hidden">
						<div class="container-fluid">
<?php if (!isset($user)) { ?>
							<div class="alert alert-info"><i class="fa fa-info-circle fa-lg fa-fw"></i> You can perform more actions by <a href="https://slack.com/oauth/authorize?scope=identity.basic,identity.team,identity.avatar&amp;client_id=<?=SLACK_CLIENT_ID?>&amp;redirect_uri=<?=BASE_URL.'auth'?>" class="alert-link">logging in</a>.</div>
<?php } ?>
							<div class="row">
								<div class="btn-group">
									<button class="btn btn-default" id="returnToList">&laquo; Return to list</button>
<?php if (isset($user)) { ?>
									<button class="btn btn-default" id="refreshReport" title="Bypass the session cache and reload this report"><i class="fa fa-refresh"></i> Refresh</button>
<?php } ?>
								</div>
								<div class="btn-group hidden" id="reportPagination">
									<button class="btn btn-default"><i class="fa fa-chevron-left"></i></button>
									<button class="btn btn-default"><i class="fa fa-chevron-right"></i></button>
								</div>
								<div class="btn-group">
									<a class="btn btn-default" id="openInWME" target="_blank"><i class="fa fa-map"></i> Waze Map Editor</a>
									<button class="btn btn-default" id="copyCoords"><i class="fa fa-files-o"></i></button>
								</div>
<?php if (isset($user)) { ?>
								<div class="btn-group">
									<button class="btn btn-default" id="claim"><i class="fa fa-fw fa-rocket"></i> I'm on it!</button>
								</div>
								<div class="btn-group">
									<button class="btn btn-default" id="follow"><i class="fa fa-star"></i></button>
								</div>
								<div class="btn-group">
									<button class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Set report status <span class="caret"></span></button>
									<ul class="dropdown-menu" id="reportStatus">
<?php foreach(array(STATUS_REPORTED, STATUS_PROCESSED, STATUS_TO_INVESTIGATE, STATUS_TO_IGNORE, STATUS_REMOVED) as $status) { ?>
										<li><a href="#" data-value="<?=$status?>"><?=STATUSES[$status]?></a></li>
<?php } ?>
									</ul>
								</div>
								<div class="btn-group">
									<button class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Change priority <span class="caret"></span></button>
									<ul class="dropdown-menu" id="reportPriority">
<?php foreach(PRIORITIES as $priority_idx => $priority) { ?>
										<li><a href="#" data-value="<?=$priority_idx?>"><?=$priority?></a></li>
<?php } ?>
									</ul>
								</div>
								<div class="btn-group">
									<button class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Change editor level <span class="caret"></span></button>
									<ul class="dropdown-menu" id="reportLevel">
										<li><a href="#" data-value="0">Not Set</a></li>
<?php foreach(range(1, 6) as $level) { ?>
										<li><a href="#" data-value="<?=$level?>"><?=$level?></a></li>
<?php } ?>
									</ul>
								</div>
<?php } ?>
							</div>
							<div class="row" style="display:flex;flex-wrap:wrap">
								<div class="col-sm-6 col-xs-12 container-fluid">
									<div class="row">
										<div class="col-xs-4 text-right"><strong>Description</strong></div>
										<div class="col-xs-8 report-description"></div>
									</div>
									<div class="row">
										<div class="col-xs-4 text-right"><strong>Period</strong></div>
										<div class="col-xs-8 report-period"></div>
									</div>
									<div class="row">
										<div class="col-xs-4 text-right"><strong>Status</strong></div>
										<div class="col-xs-8 report-status"></div>
									</div>
									<div class="row">
										<div class="col-xs-4 text-right"><strong>Priority</strong></div>
										<div class="col-xs-8 report-priority"></div>
									</div>
									<div class="row">
										<div class="col-xs-4 text-right"><strong>Editor Level</strong></div>
										<div class="col-xs-8 report-level"></div>
									</div>
									<div class="row">
										<div class="col-xs-4 text-right"><strong>Source</strong></div>
										<div class="col-xs-8 report-source"></div>
									</div>
								</div>
								<div class="col-sm-6 col-xs-12 notes">
<?php if (isset($user)) { ?>
									<div class="pull-right"><button class="btn btn-default" id="report-notes-edit-btn">Edit Notes</button></div>
<? } ?>
									<div id="report-notes"></div>
									<div id="report-notes-edit" class="hidden" style="height:100%">
										<textarea id="report-notes-source" maxlength="1000" style="width:100%; height:calc(100% - 50px); resize:vertical"></textarea>
										<button class="btn btn-default" id="report-set-notes">Update</button>
										<button class="btn btn-default" id="report-notes-cancel">Cancel</button>
										<span class="pull-right"><a href="https://en.wikipedia.org/wiki/Markdown" target="_blank">Markdown</a> text formatting supported</span>
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-sm-12 col-md-12">
									<div class="panel panel-default hidden" style="top:0; right:0; bottom:0; position:absolute; z-index:2000; display:flex; max-width:40%; flex-direction:column; overflow-y:scroll;" id="feature-details">
										<table class="table table-striped table-condensed"><tbody></tbody></table>
									</div>
									<div id="reportMap" style="height:400px; position:relative"></div>
								</div>
							</div>
							<h3>Additional Data</h3>
							<div class="row">
								<div class="col-sm-6" id="externalData"></div>
								<div class="col-sm-6">
									<div class="panel panel-default">
										<div class="panel-heading"><strong>History</strong></div>
										<table class="table table-condensed">
											<tbody id="report-history"></tbody>
										</table>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<script>
var baseUrl = '<?=ROOT_FOLDER?>reports';
var actions = <?php echo json_encode(ACTIONS) ?>;
var actionDescriptions = [];
actionDescriptions[<?=ACTION_SET_STATUS?>] = <?php echo json_encode(STATUSES) ?>;
actionDescriptions[<?=ACTION_SET_PRIORITY?>] = <?php echo json_encode(PRIORITIES) ?>;
var datasetBounds = {
	north: <?=$map_data->max_lat?>,
	south: <?=$map_data->min_lat?>,
	west: <?=$map_data->min_lon?>,
	east: <?=$map_data->max_lon?>
};
<?php if (isset($user)) { ?>
var managementArea = {
	north: <?=$user->area_north?>,
	south: <?=$user->area_south?>,
	west: <?=$user->area_west?>,
	east: <?=$user->area_east?>
};
var user = {
	username: "<?=addslashes($user->name)?>",
	id: <?=$user->id?>
}
<?php } ?>
				</script>
				<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDH7C24331Mc6DQJc7xf7gxMOb3Z69yZ-E&amp;libraries=visualization"></script>
				<script src="<?=ROOT_FOLDER?>js/OpenLayers.js"></script>
				<script src="<?=ROOT_FOLDER?>js/reports.js?<?=substr(md5(filectime('js/reports.js')), 0, 10)?>"></script>
<?php include('templates/template_footer.php'); ?>
