<?php include('templates/template_header.php'); ?>
				<div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 main">
					<h1 class="page-header">Data Sources</h1>
					<p>The data used here is retrieved from several sources, these are all listed here.</p>
					<table class="table table-striped table-hover">
						<thead>
							<tr>
								<th>Source name</th>
								<th>Data type</th>
								<th>Data items</th>
								<th>Last update</th>
							</tr>
						</thead>
						<tbody>
<?php foreach ($sources as $source) { ?>
							<tr>
								<td><a href="<?=ROOT_FOLDER?>data-sources/<?=$source->id?>"><?=$source->name?></a>
<?php if ($source->state == 'running') { ?>
<span class="label label-info pull-right">Running</span>
<?php } elseif ($source->state == 'in error') { ?>
<span class="label label-danger pull-right">In Error</span>
<?php } ?>
								</td>
								<td><?=$source->data_type?></td>
								<td><?=$source->items?></td>
								<td<?=(time() - strtotime($source->last_update) > max(12*60*60, $source->update_cooldown * 2) ? ' style="font-weight:bold;color:red;"' : '')?>><?=$source->last_update?> (<?=time_elapsed_string($source->last_update)?>)</td>
							</tr>
<?php } ?>
						</tbody>
					</table>
				</div>
<?php include('templates/template_footer.php'); ?>
