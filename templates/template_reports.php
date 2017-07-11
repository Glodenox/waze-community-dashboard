<?php include('templates/template_header.php'); ?>
				<div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 main">
					<h1 class="page-header">Reports</h1>
					<div id="list-view">
						<div class="tab-pane active filters container-fluid" id="geographicsearch">
							<div class="col-sm-12 col-md-6">
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
<?php if (isset($user)) { ?>
									<fieldset class="form-group">
										<label><input type="checkbox" id="followedFilter"> Only show reports that I follow</label>
									</fieldset>
<?php } ?>
								</form>
							</div>
							<div id="filterMap" class="col-sm-12 col-md-6"></div>
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
									<button class="btn btn-default" id="follow"><i class="fa fa-star"></i></button>
								</div>
								<div class="btn-group">
									<button class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Set report status <span class="caret"></span></button>
									<ul class="dropdown-menu" id="reportStatus">
<?php foreach(array(STATUS_REPORTED, STATUS_PROCESSED, STATUS_TO_IGNORE, STATUS_REMOVED) as $status) { ?>
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
								<div class="col-sm-6 col-xs-12">
<?php if (isset($user)) { ?>
									<div class="pull-right"><button class="btn btn-default" id="report-comments-edit-btn">Edit Comments</button></div>
<? } ?>
									<div id="report-comments"></div>
									<div id="report-comments-edit" class="hidden" style="height:100%">
										<textarea id="report-comments-source" style="width:100%; height:calc(100% - 50px); resize:vertical"></textarea>
										<button class="btn btn-default" id="report-set-comments">Update</button>
										<button class="btn btn-default" id="report-comments-cancel">Cancel</button>
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-sm-12 col-md-6">
									<div id="reportMap" style="height:400px"></div>
								</div>
								<div class="col-sm-12 col-md-6"><div class="embed-responsive" style="height:400px"><iframe id="liveMap" class="embed-responsive-item"></iframe></div></div>
							</div>
							<h3>Additional External Data</h3>
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
<?php } ?>
				</script>
				<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDH7C24331Mc6DQJc7xf7gxMOb3Z69yZ-E&amp;libraries=visualization"></script>
				<script src="<?=ROOT_FOLDER?>js/reports.js"></script>
<?php include('templates/template_footer.php'); ?>
