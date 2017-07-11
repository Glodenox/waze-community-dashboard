<?php include('templates/template_header.php'); ?>
				<div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 main">
					<h1 class="page-header">Dashboard</h1>
					<div>
						<?php foreach($alerts as $alert) {
							if ($alert['counter'] != 0) { ?>
						<div class="container" style="width: 250px; float:left">
							<div class="panel panel-<?=$alert['color']?>">
								<div class="panel-heading">
									<div class="row">
										<div class="col-md-4 col-sm-4 col-xs-4 text-left">
											<span class="fa <?=$alert['fa-class']?> fa-5x fa-fw" aria-hidden="true"></span>
										</div>
										<div class="col-md-8 col-sm-8 col-xs-8 text-right">
											<span class="fa-2x"><?=number_format($alert['counter'])?></span>
										</div>
										<div class="col-md-8 col-sm-8 col-xs-8 text-right"><?=$alert['name']?></div>
									</div>
								</div>
								<a href="<?=$alert['url']?>">
									<div class="panel-footer">
										<span class="pull-left">See Details</span>
										<span class="pull-right"><span class="fa fa-arrow-circle-right" aria-hidden="true"></span></span>
										<span class="clearfix"></span>
									</div>
								</a>
							</div>
						</div>
						<?php }
						} ?>
						<div class="clearfix"></div>
					</div>
				</div>
<?php include('templates/template_footer.php'); ?>
