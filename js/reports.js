var fragmentFactory = {
	'ResultList/ReportRow': (function() {
		var priority_class = ['fa-circle-o', 'fa-dot-circle-o', 'fa-circle', 'fa-exclamation-circle'];
		return function(id, priority, description, startTime, endTime, sourceName, reportClick) {
			var row = document.createElement('tr');
			row.className = 'clickable';
			row.id = 'report-row-' + id;
			var priorityCol = document.createElement('td');
			priorityCol.className = 'text-center';
			var priorityLink = document.createElement('a');
			priorityLink.href = '#' + id;
			priorityLink.addEventListener('click', reportClick);
			var priorityContainer = document.createElement('i');
			priorityContainer.className = 'fa ' + priority_class[priority];
			priorityContainer.title = actionDescriptions[5][priority] + ' priority';
			$(priorityContainer).tooltip();
			priorityLink.appendChild(priorityContainer);
			priorityCol.appendChild(priorityLink);
			row.appendChild(priorityCol);
			var start = document.createElement('td');
			start.className = 'text-center';
			var startLink = document.createElement('a');
			startLink.href = '#' + id;
			startLink.addEventListener('click', reportClick);
			if (startTime) {
				var startElement = document.createElement('time');
				startElement.dateTime = startTime.toISOString();
				startElement.appendChild(document.createTextNode(startTime.toShortDateString()));
				startElement.appendChild(document.createElement('br'));
				startElement.appendChild(document.createTextNode(startTime.toTimeString().substr(0, 5)));
				startLink.appendChild(startElement);
			}
			start.appendChild(startLink);
			row.appendChild(start);
			var end = document.createElement('td');
			end.className = 'text-center';
			var endLink = document.createElement('a');
			endLink.href = '#' + id;
			endLink.addEventListener('click', reportClick);
			if (endTime) {
				var endElement = document.createElement('time');
				endElement.dateTime = endTime.toISOString();
				endElement.appendChild(document.createTextNode(endTime.toShortDateString()));
				endElement.appendChild(document.createElement('br'));
				endElement.appendChild(document.createTextNode(endTime.toTimeString().substr(0, 5)));
				endLink.appendChild(endElement);
			}
			end.appendChild(endLink);
			row.appendChild(end);
			var descriptionCol = document.createElement('td');
			var descriptionLink = document.createElement('a');
			descriptionLink.href = '#' + id;
			descriptionLink.addEventListener('click', reportClick);
			descriptionLink.appendChild(document.createTextNode(description || 'No description'));
			descriptionCol.appendChild(descriptionLink);
			row.appendChild(descriptionCol);
			var source = document.createElement('td');
			source.className = 'text-center';
			var sourceLink = document.createElement('a');
			sourceLink.href = '#' + id;
			sourceLink.addEventListener('click', reportClick);
			sourceLink.appendChild(document.createTextNode(sourceName));
			source.appendChild(sourceLink);
			row.appendChild(source);
			return row;
		};
	})(),
	'ReportView/History/Item': function(date, user, message, details) {
		var tr = document.createElement('tr');
		var timeTd = document.createElement('td');
		timeTd.appendChild(document.createTextNode(date));
		tr.appendChild(timeTd);
		var messageTd = document.createElement('td');
		messageTd.appendChild(document.createTextNode(message));
		tr.appendChild(messageTd);
		var userTd = document.createElement('td');
		userTd.appendChild(document.createTextNode(user != null ? user : 'System'));
		tr.appendChild(userTd);
		var detailsTd = document.createElement('td');
		if (details != null) {
			var detailsInfo = document.createElement('a');
			var detailsInfoIcon = document.createElement('i');
			detailsInfoIcon.className = 'fa fa-chevron-right fa-fw';
			detailsInfo.appendChild(detailsInfoIcon);
			tr.style.cursor = 'pointer';
			tr.addEventListener('click', () => {
				detailsInfoIcon.classList.toggle('fa-chevron-right');
				detailsInfoIcon.classList.toggle('fa-chevron-up');
				details.classList.toggle('hidden');
			});
			detailsTd.appendChild(detailsInfo);
		}
		tr.appendChild(detailsTd);
		return tr;
	},
	'ReportView/History/ItemDetails': function(details) {
		var tr = document.createElement('tr');
		tr.className = 'hidden info';
		var detailsTd = document.createElement('td');
		detailsTd.style.paddingLeft = '15px';
		detailsTd.style.whiteSpace = 'pre';
		detailsTd.colSpan = 4;
		detailsTd.textContent = details;
		tr.appendChild(detailsTd);
		return tr;
	}
};
$.fn.tooltip.Constructor.DEFAULTS.trigger = 'hover';
var mapProjection = 'EPSG:900913';
var siteProjection = 'CRS:84';
var reportsLimit = 500;
OpenLayers.IMAGE_RELOAD_ATTEMPTS = 2;

Dashboard = {}; // global namespace
Dashboard.loadPage = function(url, params, callback) {
	var request = new XMLHttpRequest();
	request.addEventListener('load', callback);
	request.responseType = 'json';
	var paramUrl = url + (params && Object.keys(params).length > 0 ? '?' + Object.keys(params).map(function(key) { return key + '=' + encodeURIComponent(params[key]); }).join('&') : '');
	request.open('GET', paramUrl);
	request.send();
};

Dashboard.ViewManager = function() {
	this._views = [];
	this.activeView;
};
Dashboard.ViewManager.prototype.add = function(view) {
	this._views.push(view);
};
Dashboard.ViewManager.prototype.get = function(name) {
	return this._views.find(function(view) {
		return view.name == name;
	});
};
Dashboard.ViewManager.prototype.switchTo = function(viewName) {
	this._views.forEach(function(view) {
		view.toggle(view.name == viewName);
	});
	this.get(viewName).activate();
	this.activeView = viewName;
};

Dashboard.View = function(viewManager, name, container) {
	this.viewManager = viewManager;
	this.name = name;
	this.container = container;
	this.viewManager.add(this);
};
Dashboard.View.prototype.toggle = function(show) {
	this.container.classList.toggle('hidden', !show);
};
Dashboard.View.prototype.restoreView = function(eventState) {
	throw new Error('No view restoration function defined');
};
Dashboard.View.prototype.activate = function() {};

Dashboard.ListView = function(viewManager, container, reportList) {
	Dashboard.View.call(this, viewManager, 'list', container);
	this.reportList = reportList;
	this.reports = [];
	this._loadedReports = [];
	this._loadedReportsBounds;
	this._mapLoaded = false;
	this.reportList.reports = this.reports;
	this._filterMap;
	this._filterVectors;
	this._viewManager = viewManager;

	this.refresh();
};
Dashboard.ListView.prototype = Object.create(Dashboard.View.prototype);
Dashboard.ListView.prototype.activate = function() {
	var self = this;
	if (!this._mapLoaded) {
		this._mapLoaded = true;
		var reportsFilterCenter = OpenLayers.LonLat.fromString(localStorage.reportsFilterCenter);
		self._filterMap = new OpenLayers.Map({
			div: 'filterMap',
			center: reportsFilterCenter.transform(siteProjection, mapProjection),
			layers: [
				new OpenLayers.Layer.XYZ('Waze Livemap', [
					'https://worldtiles1.waze.com/tiles/${z}/${x}/${y}.png',
					'https://worldtiles2.waze.com/tiles/${z}/${x}/${y}.png',
					'https://worldtiles3.waze.com/tiles/${z}/${x}/${y}.png',
					'https://worldtiles4.waze.com/tiles/${z}/${x}/${y}.png'
				], {
					projection: mapProjection,
					numZoomLevels: 18,
					attribution: '&copy; 2006-' + (new Date()).getFullYear() + ' <a href="https://www.waze.com/livemap" target="_blank">Waze Mobile</a>. All Rights Reserved.'
				})
			],
			zoom: Number(localStorage.reportsFilterZoom)
		});
		console.log('filterMap', self._filterMap);
		self._filterMap.events.on({
			'moveend': () => {
				localStorage.reportsFilterCenter = self._filterMap.getCenter().transform(mapProjection, siteProjection).toShortString();
				localStorage.reportsFilterZoom = self._filterMap.getZoom();
				self.refresh();
			},
			'mouseout': () => document.querySelector('#filterMap .title').classList.add('hidden')
		});
		self._filterVectors = new OpenLayers.Layer.Vector('Vector Layer', {
			strategies: [ new OpenLayers.Strategy.Cluster({ distance: 25, threshold: 3 }) ],
			styleMap: new OpenLayers.StyleMap({
				"default": new OpenLayers.Style({
					pointRadius: "${radius}",
					fillColor: "${fill}",
					fillOpacity: 0.7,
					strokeColor: "${stroke}",
					strokeWidth: "${width}",
					strokeOpacity: 0.7,
					label: "${count}",
					fontColor: "#ffffff",
					fontSize: "10px",
					fontWeight: "bold",
					cursor: "${cursor}"
				}, {
					context: {
						width: (feature) => feature.cluster ? 3 : 2,
						radius: (feature) => feature.cluster ? Math.min(feature.attributes.count + 5, 10) : 6,
						title: (feature) => feature.cluster ? 'Cluster of ' + feature.attributes.count + ' reports' : feature.attributes.title,
						count: (feature) => feature.cluster ? feature.attributes.count : '',
						fill: (feature) => feature.cluster ? '#aaaaaa' : '#bce8f1',
						stroke: (feature) => feature.cluster ? '#888888' : '#31708f',
						cursor: (feature) => feature.cluster ? 'zoom-in' : 'pointer'
					}
				}),
				"select": new OpenLayers.Style({
					fillColor: "${fill}",
					strokeColor: "${stroke}"
				}, {
					context: {
						fill: (feature) => feature.cluster ? '#bbbbbb' : '#cdf9f2',
						stroke: (feature) => feature.cluster ? '#999999' : '#42819f'
					}
				})
			})
		});
		self._filterMap.addLayer(self._filterVectors);
		var highlighter = new OpenLayers.Control.SelectFeature(self._filterVectors, {
			hover: true,
			highlightOnly: true,
			autoActivate: true
		});
		highlighter.events.on({
			featurehighlighted: (e) => {
				var row = document.getElementById('report-row-' + e.feature.attributes.id);
				if (row) { // It might be on another page
					row.classList.add('info');
				}
				var title = document.querySelector('#filterMap .title');
				title.textContent = e.feature.attributes.title;
				title.classList.remove('hidden');
			},
			featureunhighlighted: (e) => {
				var row = document.getElementById('report-row-' + e.feature.attributes.id);
				if (row) { // It might be on another page
					row.classList.remove('info');
				}
				document.querySelector('#filterMap .title').classList.add('hidden');
			}
		});
		self._filterMap.addControl(highlighter);
		self._filterMap.addControl(new OpenLayers.Control.SelectFeature(self._filterVectors, {
			autoActivate: true,
			onSelect: (report) => {
				if (report.cluster) {
					var currentZoom = self._filterMap.getZoom();
					self._filterMap.zoomTo(currentZoom+1, {
						x: (report.geometry.x - self._filterMap.getExtent().left) / self._filterMap.getResolution(),
						y: (self._filterMap.getExtent().top - report.geometry.y) / self._filterMap.getResolution()
					});
				} else {
					document.querySelector('#filterMap .title').classList.add('hidden');
					if (history.pushState) {
						history.pushState({ 'id': report.attributes.id, 'view': 'report' }, null, '#' + report.attributes.id);
					} else {
						location.hash = '#' + report.attributes.id;
					}
					self._viewManager.switchTo('report');
				}
			}
		}));
		this._filterMap.addLayer(self._filterVectors);
		if (typeof managementArea !== 'undefined') {
			var resetAreaLink = document.querySelector('#filterMap .managementReset a');
			resetAreaLink.parentNode.classList.remove('hidden');
			resetAreaLink.addEventListener('click', function(e) {
				e.preventDefault();
				self._filterMap.zoomToExtent(managementArea.clone().transform(siteProjection, mapProjection));
			});
		}
	}
	this.refresh(true);
};
// Load the reports matching the current filter options in localStorage
Dashboard.ListView.prototype.refresh = function(flush) {
	var self = this;
	if (flush) {
		this._loadedReports.length = 0;
		this._loadedReportsBounds = null;
	}
	// Don't refresh if the map hasn't initialized yet
	if (!self._filterMap) {
		return;
	}
	// Try to retrieve the data from our previous load
	if (self._loadedReportsBounds && self._loadedReportsBounds.containsBounds(self._filterMap.getExtent())) {
		var mapExtent = self._filterMap.getExtent().transform(mapProjection, siteProjection);
		self.reports.length = 0;
		self._loadedReports.filter((report) => mapExtent.contains(report.lon, report.lat)).forEach((report) => self.reports.push(report));
		self.displayReports();
	} else { // If not, retrieve the data from the server
		var params = {
			status: localStorage.statusFilter,
			source: localStorage.sourceFilter,
			priority: localStorage.priorityFilter,
			level: localStorage.editorLevelFilter,
			period: localStorage.periodFilter,
			followed: (localStorage.followedFilter == 'true' ? 1 : 0),
			bounds: self._filterMap.getExtent().transform(mapProjection, siteProjection).toString()
		};
		var statusId = Status.show('info', 'Loading results...');
		Dashboard.loadPage(baseUrl + '/query', params, function() {
			Status.hide(statusId);
			self.reports.length = 0;
			Array.prototype.push.apply(self.reports, this.response.reports);
			if (self.reports.length < reportsLimit) {
				Array.prototype.push.apply(self._loadedReports, this.response.reports);
				self._loadedReportsBounds = self._filterMap.getExtent();
			} else {
				self._loadedReports.length = 0;
				self._loadedReportsBounds = null;
			}
			console.log('ListView refreshed', this.response.reports);
			self.displayReports();
		});
	}
};

Dashboard.ListView.prototype.displayReports = function() {
	var markers = [];
	if (this.reports) {
		this.reports.forEach(function(report) {
			var marker = new OpenLayers.Feature.Vector(new OpenLayers.Geometry.Point(report.lon, report.lat).transform(siteProjection, mapProjection), {
				title: report.description,
				id: report.id
			});
			report.marker = marker.id;
			markers.push(marker);
		});
		this._filterVectors.removeAllFeatures();
		this._filterVectors.addFeatures(markers);
	} else {
		this._filterVectors.removeAllFeatures();
	}
	this.reportList.refresh(0);
};

Dashboard.ReportList = function(viewManager, paginationContainer, listContainer) {
	this.page = 0;
	this.reportsPerPage = 20;
	this._listContainer = listContainer;
	this._paginationContainer = paginationContainer;
	this._prevPage = paginationContainer.querySelector('button:nth-child(1)');
	this._nextPage = paginationContainer.querySelector('button:nth-child(2)');
	this.reports = [];
	this._viewManager = viewManager;
	this._prevPage.addEventListener('click', () => {
		this.refresh(this.page - 1);
	});
	this._nextPage.addEventListener('click', () => {
		this.refresh(this.page + 1);
	});
};
Dashboard.ReportList.prototype.refresh = function(page) {
	var toPage = page || 0;
	var maximumPage = Math.floor((this.reports.length - 1) / this.reportsPerPage);
	this.page = Math.max(0, Math.min(toPage, maximumPage));
	this._prevPage.classList.toggle('disabled', this.page == 0 || this.reports.length < this.reportsPerPage);
	this._nextPage.classList.toggle('disabled', this.page == maximumPage || this.reports.length < this.reportsPerPage);
	this._paginationContainer.querySelector('span').textContent = (this.reports.length > 0 ?
		((this.page * this.reportsPerPage) + 1) + '-' + Math.min((this.page * this.reportsPerPage) + this.reportsPerPage, this.reports.length) + ' of ' + this.reports.length :
		''
	);
	this._listContainer.querySelector('#no-reports').classList.toggle('hidden', this.reports.length > 0);
	this.showPage();
};
Dashboard.ReportList.prototype.showPage = function() {
	// Clear the page of reports
	while (this._listContainer.lastChild.id != 'no-reports') {
		this._listContainer.removeChild(this._listContainer.lastChild);
	}
	// Fill the list with the current page of reports
	var pageReports = this.reports.slice(this.page * this.reportsPerPage, (this.page+1) * this.reportsPerPage);
	var self = this;
	pageReports.forEach(function(report) {
		var startDate = report.start_time == null ? null : new Date(report.start_time * 1000);
		var endDate = report.end_time == null ? null : new Date(report.end_time * 1000);
		var row = fragmentFactory['ResultList/ReportRow'](report.id, report.priority, report.description, startDate, endDate, report.source_name, function(e) {
			if (e.defaultPrevented || e.metaKey || e.ctrlKey) {
				return;
			}
			console.log('Report clicked, showing report item page', e);
			e.preventDefault();
			if (history.pushState) {
				history.pushState({ 'id': report.id, 'view': 'report' }, null, '#' + report.id);
			} else {
				location.hash = '#' + report.id;
			}
			self._viewManager.switchTo('report');
		});
		self._listContainer.appendChild(row);
	});
};
Dashboard.ReportList.prototype.update = function() {
	this.showPage(this.page);
};

Dashboard.ReportView = function(viewManager, container) {
	Dashboard.View.call(this, viewManager, 'report', container);
	var reportView = this;
	this._reportMap;
	document.querySelector('#returnToList').addEventListener('click', function() {
		if (history.pushState) {
			history.pushState({ 'page': 0, 'view': 'list' }, null, 'reports');
		}
		viewManager.switchTo('list');
	});
	var refreshReport = document.getElementById('refreshReport');
	if (refreshReport) {
		$(refreshReport).tooltip({
			container: 'body'
		});
		refreshReport.addEventListener('click', function() {
			reportView.activate(true); // Force the update on activation
		});
	}
	var reportNavigation = document.querySelectorAll('#reportPagination button');
	for (var i = 0; i < 2; i++) {
		reportNavigation[i].addEventListener('click', function() {
			if (this.dataset.id == null) {
				return;
			}
			if (history.pushState) {
				history.pushState({ 'id': this.dataset.id, 'view': 'report' }, null, '#' + this.dataset.id);
			} else {
				location.hash = '#' + this.dataset.id;
			}
			reportView.activate();
		});
	}
	var copyCoords = document.getElementById('copyCoords');
	var isHoveringCoords = false;
	copyCoords.addEventListener('mouseover', function(e) {
		isHoveringCoords = true;
	});
	copyCoords.addEventListener('mouseout', function(e) {
		isHoveringCoords = false;
	});
	copyCoords.addEventListener('click', function() {
		var copyInput = document.getElementById('copy-input');
		copyInput.value = copyCoords.dataset.coords;
		copyInput.select();
		if (!document.execCommand('copy')) {
			prompt('You can also press Ctrl+C while hovering over the button', copyCoords.dataset.coords);
		} else {
			// The following section is convoluted because Bootstrap's tooltip code doesn't work well when replacing the tooltip content while it is being displayed, bleh.
			$(copyCoords).tooltip('destroy');
			setTimeout(function() {
				$(copyCoords).tooltip({
					container: 'body',
					title: 'Coordinates copied!'
				});
				$(copyCoords).tooltip('show');
			}, 200);
			setTimeout(function() {
				$(copyCoords).tooltip('destroy');
				setTimeout(function() {
					$(copyCoords).tooltip({
						container: 'body',
						title: 'Copy location coordinates'
					});
				}, 200);
			}, 4000);
		}
		this.focus();
	});
	document.addEventListener('copy', function(e) {
		if (!isHoveringCoords) {
			return;
		}
		e.clipboardData.setData('text/plain', copyCoords.dataset.coords);
		e.preventDefault();
		// The following section is convoluted because Bootstrap's tooltip code doesn't work well when replacing the tooltip content while it is being displayed, bleh.
		$(copyCoords).tooltip('destroy');
		setTimeout(function() {
			$(copyCoords).tooltip({
				container: 'body',
				title: 'Coordinates copied!'
			});
			$(copyCoords).tooltip('show');
		}, 200);
		setTimeout(function() {
			$(copyCoords).tooltip('destroy');
			setTimeout(function() {
				$(copyCoords).tooltip({
					container: 'body',
					title: 'Copy location coordinates'
				});
			}, 200);
		}, 4000);
	});
	$(copyCoords).tooltip({
		container: 'body',
		title: 'Copy location coordinates'
	});
	var reportClaim = document.getElementById('claim');
	if (reportClaim) {
		reportClaim.addEventListener('click', function(e) {
			e.preventDefault();
			var unlocking = reportClaim.firstChild.classList.contains('fa-unlock-alt');
			var statusId = Status.show('info', (unlocking ? 'Releasing claim' : 'Attempting to claim'));
			Dashboard.loadPage(baseUrl + (unlocking ? '/release-claim' : '/claim'), { id: location.hash.substring(1) }, function() {
				Status.hide(statusId);
				reportClaim.classList.toggle('btn-danger', !this.response.ok);
				$(reportClaim).tooltip('destroy');
				if (this.response.ok) {
					reportClaim.classList.toggle('btn-info', !reportClaim.firstChild.classList.contains('fa-unlock-alt'));
					if (!reportClaim.firstChild.classList.contains('fa-unlock-alt')) {
						reportClaim.firstChild.classList.remove('fa-rocket');
						reportClaim.firstChild.classList.remove('fa-lock');
						reportClaim.firstChild.classList.add('fa-unlock-alt');
						reportClaim.title = 'Claimed by you';
						$(reportClaim).tooltip();
					} else {
						reportClaim.firstChild.classList.remove('fa-unlock-alt');
						reportClaim.firstChild.classList.add('fa-rocket');
						reportClaim.title = '';
					}
				} else {
					Status.show('danger', this.response.error);
					reportClaim.title = this.response.error;
					$(reportClaim).tooltip();
				}
			});
		});
	}
	var reportFollow = document.getElementById('follow');
	if (reportFollow) {
		reportFollow.addEventListener('click', function(e) {
			e.preventDefault();
			var statusId = Status.show('info', 'Adjusting follow status');
			Dashboard.loadPage(baseUrl + (reportView.reportData.following ? '/unfollow' : '/follow'), { id: location.hash.substring(1) }, function() {
				Status.hide(statusId);
				if (this.response.ok) {
					reportView.reportData.following = !reportView.reportData.following;
					reportView.refresh();
				} else {
					Status.show('danger', this.response.error);
				}
			});
		});
	}
	var reportStatus = document.querySelectorAll('#reportStatus a');
	for (var i = 0; i < reportStatus.length; i++) {
		reportStatus.item(i).addEventListener('click', function(e) {
			e.preventDefault();
			var shiftHeld = e.shiftKey;
			var statusId = Status.show('info', 'Adjusting report status');
			Dashboard.loadPage(baseUrl + '/set-status', { id: location.hash.substring(1), status: this.dataset.value }, function() {
				Status.hide(statusId);
				if (this.response.ok) {
					reportView.reportData.status = e.target.dataset.value;
					reportView.reportData.following = reportView.reportData.following || this.response.following;
					if (!reportNavigation[1].disabled && this.response.autojump ? !shiftHeld : shiftHeld) {
						reportNavigation[1].click();
					} else {
						reportView.refresh();
					}
				} else {
					Status.show('danger', this.response.error);
					console.log('Update failed', this.response.error);
				}
			});
			return false;
		});
	}
	var reportPriority = document.querySelectorAll('#reportPriority a');
	for (var i = 0; i < reportPriority.length; i++) {
		reportPriority.item(i).addEventListener('click', function(e) {
			e.preventDefault();
			var shiftHeld = e.shiftKey;
			var statusId = Status.show('info', 'Adjusting report priority');
			Dashboard.loadPage(baseUrl + '/set-priority', { id: location.hash.substring(1), priority: this.dataset.value }, function() {
				Status.hide(statusId);
				if (this.response.ok) {
					reportView.reportData.priority = e.target.dataset.value;
					reportView.reportData.following = reportView.reportData.following || this.response.following;
					if (shiftHeld) {
						reportNavigation[1].click();
					} else {
						reportView.refresh();
					}
				} else {
					Status.show('danger', this.response.error);
					console.log('Update failed', this.response.error);
				}
			});
			return false;
		});
	}
	var reportLevel = document.querySelectorAll('#reportLevel a');
	for (var i = 0; i < reportLevel.length; i++) {
		reportLevel.item(i).addEventListener('click', function(e) {
			e.preventDefault();
			var shiftHeld = e.shiftKey;
			var statusId = Status.show('info', 'Adjusting report editor level');
			Dashboard.loadPage(baseUrl + '/set-level', { id: location.hash.substring(1), level: this.dataset.value }, function() {
				if (this.response.ok) {
					Status.hide(statusId);
					reportView.reportData.required_editor_level = e.target.dataset.value;
					reportView.reportData.following = reportView.reportData.following || this.response.following;
					if (shiftHeld) {
						reportNavigation[1].click();
					} else {
						reportView.refresh();
					}
				} else {
					Status.show('danger', this.response.error);
					console.log('Update failed', this.response.error);
				}
			});
			return false;
		});
	}
	var notesView = document.getElementById('report-notes');
	var notesEditBtn = document.getElementById('report-notes-edit-btn');
	if (notesEditBtn) {
		notesEditBtn.addEventListener('click', function() {
			notesEditBtn.classList.toggle('hidden');
			notesView.classList.toggle('hidden');
			notesEditor.classList.toggle('hidden');
		});
	}
	var notesEditor = document.getElementById('report-notes-edit');
	document.getElementById('report-set-notes').addEventListener('click', function() {
	var statusId = Status.show('info', 'Setting new notes');
		Dashboard.loadPage(baseUrl + '/set-notes', { id: location.hash.substring(1), notes: document.getElementById('report-notes-source').value }, function() {
			if (this.response.ok) {
				Status.hide(statusId);
				notesView.innerHTML = this.response.notes;
				notesEditBtn.classList.toggle('hidden');
				notesView.classList.toggle('hidden');
				notesEditor.classList.toggle('hidden');
			} else {
				Status.show('danger', this.response.error);
				console.log('Sending notes failed', this.response.error);
			}
		});
	});
	document.getElementById('report-notes-cancel').addEventListener('click', function() {
		notesEditBtn.classList.toggle('hidden');
		notesView.classList.toggle('hidden');
		notesEditor.classList.toggle('hidden');
	});
	var self = this;
};
Dashboard.ReportView.prototype = Object.create(Dashboard.View.prototype);
Dashboard.ReportView.prototype.activate = function(forceUpdate) {
	console.log('Activating report', location.hash.substring(1));
	var self = this;
	var params = { id: location.hash.substring(1) };
	if (forceUpdate) {
		params.force = 1;
	}
	var statusId = Status.show('info', 'Loading report...');
	Dashboard.loadPage(baseUrl + '/get', params, function() {
		Status.hide(statusId);
		if (this.response.ok) {
			self.reportData = this.response.report;
			self.refresh();
		} else {
			Status.show('danger', this.response.error);
			console.log('Error loading the report', this.response);
		}
	});
};
Dashboard.ReportView.prototype.refresh = function() {
	var self = this;
	var report = this.reportData;
	console.log('Showing report', report);
	window.scroll(0, 0);
	var reportIndex = this.viewManager.get('list').reportList.reports.findIndex(function(element) {
		return element.id == report.id;
	});
	document.getElementById('reportPagination').classList.toggle('hidden', reportIndex == -1)
	var paginationButtons = this.container.querySelectorAll('#reportPagination button');
	paginationButtons[0].dataset.id = (reportIndex <= 0 ? null : this.viewManager.get('list').reportList.reports[reportIndex - 1].id);
	paginationButtons[0].disabled = reportIndex <= 0;
	paginationButtons[1].dataset.id = (reportIndex >= this.viewManager.get('list').reportList.reports.length - 1 ? null : this.viewManager.get('list').reportList.reports[reportIndex + 1].id);
	paginationButtons[1].disabled = reportIndex >= this.viewManager.get('list').reportList.reports.length - 1;
	this.container.querySelector('#openInWME').href = 'https://www.waze.com/editor/?env=row&lon=' + report.lon + '&lat=' + report.lat + '&zoom=5';
	this.container.querySelector('#copyCoords').dataset.coords = report.lon + ',' + report.lat;
	var claim = this.container.querySelector('#claim');
	if (claim) {
		claim.firstChild.classList.toggle('fa-rocket', report.claimUserId == null);
		claim.firstChild.classList.toggle('fa-unlock-alt', report.claimUserId == user.id);
		claim.firstChild.classList.toggle('fa-lock', report.claimUserId != null && report.claimUserId != user.id);
		claim.classList.toggle('btn-danger', report.claimUserId != null && report.claimUserId != user.id);
		claim.classList.toggle('btn-info', report.claimUserId == user.id);
		claim.title = (report.claimUserId == user.id ? 'Claimed by you' : (report.claimUserId == null ? '' : 'Claimed by ' + report.claimUsername + ' ' + Math.floor((Date.now()/1000 - report.claimTime) / 60) + ' minutes ago'));
		$(claim).tooltip(report.claimUserId == null ? 'destroy' : {});
	}
	var follow = this.container.querySelector('#follow');
	if (follow) {
		follow.firstChild.classList.toggle('fa-star', report.following);
		follow.firstChild.classList.toggle('fa-star-o', !report.following);
	}
	var statusOptions = this.container.querySelectorAll('#reportStatus a');
	for (var i = 0; i < statusOptions.length; i++) {
		statusOptions.item(i).parentNode.classList.toggle('disabled', statusOptions.item(i).dataset.value == report.status);
		if (statusOptions.item(i).dataset.value == 3) {
			statusOptions.item(i).parentNode.classList.toggle('hidden', report.status == 4);
		}
		if (statusOptions.item(i).dataset.value == 5) {
			statusOptions.item(i).parentNode.classList.toggle('hidden', report.status != 4);
		}
	}
	var priorityOptions = this.container.querySelectorAll('#reportPriority a');
	for (var i = 0; i < priorityOptions.length; i++) {
		priorityOptions.item(i).parentNode.classList.toggle('disabled', priorityOptions.item(i).dataset.value == report.priority);
	}
	var levelOptions = this.container.querySelectorAll('#reportLevel a');
	for (var i = 0; i < levelOptions.length; i++) {
		levelOptions.item(i).parentNode.classList.toggle('disabled', levelOptions.item(i).dataset.value == report.required_editor_level);
	}
	this.container.querySelector('.report-priority').innerHTML = actionDescriptions[5][report.priority];
	this.container.querySelector('.report-level').innerHTML = (report.required_editor_level == 0 ? 'Not set' : report.required_editor_level);
	this.container.querySelector('.report-status').innerHTML = actionDescriptions[3][report.status];
	var description = this.container.querySelector('.report-description');
	if (description.firstChild) {
		description.removeChild(description.firstChild);
	}
	description.appendChild(document.createTextNode(report.description));
	var period = this.container.querySelector('.report-period');
	period.removeAll();
	period.parentNode.style.display = (report.start_time || report.end_time ? 'block' : 'none');
	if (report.start_time != null) {
		var startTime = new Date(report.start_time * 1000);
		var startElement = document.createElement('time');
		startElement.dateTime = startTime.toISOString();
		startElement.appendChild(document.createTextNode(startTime.toShortDateString()));
		startElement.appendChild(document.createTextNode(' '));
		startElement.appendChild(document.createTextNode(startTime.toTimeString().substr(0, 5)));
		period.appendChild(startElement);
	}
	if (report.end_time != null) {
		var pointer = document.createElement('i');
		pointer.className = 'fa fa-chevron-right';
		pointer.style.margin = '0 1em';
		period.appendChild(pointer);
		var endTime = new Date(report.end_time * 1000);
		var endElement = document.createElement('time');
		endElement.dateTime = endTime.toISOString();
		endElement.appendChild(document.createTextNode(endTime.toShortDateString()));
		endElement.appendChild(document.createTextNode(' '));
		endElement.appendChild(document.createTextNode(endTime.toTimeString().substr(0, 5)));
		period.appendChild(endElement);
	}
	var source = this.container.querySelector('.report-source');
	source.removeAll();
	var sourceLink = document.createElement('a');
	sourceLink.href = 'data-sources/' + report.source;
	sourceLink.appendChild(document.createTextNode(report.source_name));
	source.appendChild(sourceLink);
	var detailsPanel = document.getElementById('feature-details');
	detailsPanel.classList.add('hidden');
	var externalData = this.container.querySelector('#externalData');
	externalData.removeAll();
	externalData.style.display = report.external_data == '' ? 'none' : 'block';
	if (report.external_data != '') {
		var dl = document.createElement('dl');
		dl.classList.add('dl-horizontal');
		Object.keys(report.external_data)
			.sort()
			.sort(function(a, b) { // Move non-objects to the end of the list
				if (typeof report.external_data[a] == 'object' && typeof report.external_data[b] != 'object') {
					return 1;
				}
				if (typeof report.external_data[a] != 'object' && typeof report.external_data[b] == 'object') {
					return -1;
				}
				if (a == 'Periods' && typeof report.external_data[b] == 'object') {
					return -1;
				}
				if (typeof report.external_data[a] == 'object' && b == 'Periods') {
					return 1;
				}
				return 0;
			})
			.forEach(function(key) {
				var value = report.external_data[key];
				if (Array.isArray(value)) {
					externalData.appendChild(dl);
					dl = document.createElement('dl');
					dl.classList.add('dl-horizontal');
					var subheader = document.createElement('h4');
					subheader.className = 'col-sm-offset-1';
					subheader.appendChild(document.createTextNode(key));
					externalData.appendChild(subheader);
					var list = document.createElement('ul');
					list.className = 'col-sm-offset-1';
					value.sort().forEach(function(el) {
						var li = document.createElement('li');
						if (el.startDateTime && el.endDateTime) {
							var startElement = document.createElement('time');
							startElement.dateTime = el.startDateTime;
							startElement.appendChild(document.createTextNode(el.startDateTime.substr(0, 10)));
							startElement.appendChild(document.createTextNode(' '));
							startElement.appendChild(document.createTextNode(el.startDateTime.substr(11, 5)));
							var pointer = document.createElement('i');
							pointer.className = 'fa fa-chevron-right';
							pointer.style.margin = '0 1em';
							var endElement = document.createElement('time');
							endElement.dateTime = el.endDateTime;
							endElement.appendChild(document.createTextNode(el.endDateTime.substr(0, 10)));
							endElement.appendChild(document.createTextNode(' '));
							endElement.appendChild(document.createTextNode(el.endDateTime.substr(11, 5)));
							li.appendChild(startElement);
							li.appendChild(pointer);
							li.appendChild(endElement);
						} else if (el.toString().startsWith('http')) {
							var anchor = document.createElement('a');
							anchor.appendChild(document.createTextNode(el));
							anchor.href = el;
							anchor.target = '_blank';
							li.appendChild(anchor);
						} else {
							li.appendChild(document.createTextNode(el));
						}
						list.appendChild(li);
					});
					externalData.appendChild(list);
				} else if (typeof value == 'object') {
					externalData.appendChild(dl);
					dl = document.createElement('dl');
					dl.classList.add('dl-horizontal');
					var subheader = document.createElement('h4');
					subheader.className = 'col-sm-offset-1';
					subheader.appendChild(document.createTextNode(key));
					externalData.appendChild(subheader);
					var subentry = document.createElement('dl');
					subentry.classList.add('dl-horizontal');
					Object.keys(value)
						.sort()
						.forEach(function(subkey) {
							var dt = document.createElement('dt');
							dt.appendChild(document.createTextNode(subkey));
							var dd = document.createElement('dd');
							if (Array.isArray(value[subkey])) {
								dd.appendChild(document.createTextNode(value[subkey].join(', ')));
							} else if (value[subkey].toString().startsWith('http')) {
								var anchor = document.createElement('a');
								anchor.appendChild(document.createTextNode(value[subkey]));
								anchor.href = value[subkey];
								anchor.target = '_blank';
								dd.appendChild(anchor);
							} else {
								dd.appendChild(document.createTextNode(value[subkey]));
							}
							subentry.appendChild(dt);
							subentry.appendChild(dd);
						});
					externalData.appendChild(subentry);
				} else {
					var dt = document.createElement('dt');
					dt.appendChild(document.createTextNode(key));
					var dd = document.createElement('dd');
					if (value.toString().startsWith('http')) {
						var anchor = document.createElement('a');
						anchor.appendChild(document.createTextNode(value));
						anchor.href = value;
						anchor.target = '_blank';
						dd.appendChild(anchor);
					} else {
						dd.appendChild(document.createTextNode(value));
					}
					dl.appendChild(dt);
					dl.appendChild(dd);
				}
			});
		externalData.appendChild(dl);
	}
	var center = null;
	if (report.geojson) {
		var bounds = new OpenLayers.Bounds();
		var dfs = function(node) {
			if (node.length == 2 && !Array.isArray(node[0])) {
				bounds.extend(new OpenLayers.LonLat(node[0], node[1]));
			} else {
				node.forEach(function(child) {
					dfs(child);
				});
			}
		};
		if (report.geojson.coordinates) { // Polygon
			dfs(report.geojson.coordinates);
		} else if (report.geojson.geometries) { // GeometryCollection
			report.geojson.geometries.forEach(function(geometry) {
				dfs(geometry.coordinates)
			});
		} else if (report.geojson.geometry) { // Feature
			dfs(report.geojson.geometry);
		} else if (report.geojson.features) { // FeatureCollection
			report.geojson.features.forEach(function(feature) {
				dfs(feature.geometry.coordinates)
			});
		}
		var center = bounds.getCenterLonLat();
		// Require at least a certain viewport
		bounds.extend(new OpenLayers.LonLat(center.lon - 0.002, center.lat - 0.002));
		bounds.extend(new OpenLayers.LonLat(center.lon + 0.002, center.lat + 0.002));
		center.transform(siteProjection, mapProjection);
		bounds.transform(siteProjection, mapProjection);
	}
	if (!center) {
		center = new OpenLayers.LonLat(report.lon, report.lat).transform(siteProjection, mapProjection);
	}
	if (this._reportMap) {
		this._reportMap.destroy();
	}
	this._reportMap = new OpenLayers.Map({
		div: 'reportMap',
		center: center,
		layers: [
			new OpenLayers.Layer.XYZ('Waze Livemap', [
				'https://worldtiles1.waze.com/tiles/${z}/${x}/${y}.png',
				'https://worldtiles2.waze.com/tiles/${z}/${x}/${y}.png',
				'https://worldtiles3.waze.com/tiles/${z}/${x}/${y}.png',
				'https://worldtiles4.waze.com/tiles/${z}/${x}/${y}.png'
			], {
				projection: mapProjection,
				numZoomLevels: 23,
				attribution: '&copy; 2006-' + (new Date()).getFullYear() + ' <a href="https://www.waze.com/livemap" target="_blank">Waze Mobile</a>. All Rights Reserved.'
			})
		],
		zoom: 16
	});
	var vectors = new OpenLayers.Layer.Vector('Vector Layer');
	this._reportMap.addControl(new OpenLayers.Control.SelectFeature(vectors, {hover: true, highlightOnly: true, renderIntent: 'hover', autoActivate: true}));
	var redrawPropertiesWindow = function() {
		var detailsTable = detailsPanel.querySelector('tbody');
		detailsTable.removeAll();
		if (vectors.selectedFeatures.length == 0) {
			detailsPanel.classList.add('hidden');
			return;
		}
		var rows = new Map();
		var currentCount = 1;
		vectors.selectedFeatures.forEach(feature => {
			currentCount++;
			Object.entries(feature.attributes).forEach(([name, value]) => {
				if (!rows.has(name)) {
					var row = document.createElement('tr');
					var propertyName = document.createElement('th');
					propertyName.textContent = name;
					row.appendChild(propertyName);
					while (row.childNodes.length + 1 < currentCount) {
						row.appendChild(document.createElement('td'));
					}
					rows.set(name, row);
				}
				var propertyValue = document.createElement('td');
				propertyValue.textContent = String(value).replace(/(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}):\d{2}\.\d{3}\+\d{4}/, '$1 $2');
				rows.get(name).appendChild(propertyValue);
			});
			rows.forEach((row, name) => {
				if (row.childNodes.length < currentCount) {
					row.appendChild(document.createElement('td'));
				}
			});
		});
		rows.forEach((row, name) => {
			var cols = Array.from(row.childNodes); // .forEach is still a bit too recently added to NodeList
			var firstValue = row.childNodes[1].textContent;
			cols.forEach((col, index) => {
				if (index == 0) {
					return;
				}
				if (col.textContent != firstValue) {
					var mark = document.createElement('mark');
					mark.textContent = col.textContent;
					col.textContent = '';
					col.appendChild(mark);
				}
			});
			detailsTable.appendChild(row);
		});
		detailsPanel.classList.remove('hidden');
	}
	this._reportMap.addControl(new OpenLayers.Control.SelectFeature(vectors, {
		autoActivate: true,
		multiple: true,
		toggle: true,
		geometryTypes: ['OpenLayers.Geometry.MultiLineString', 'OpenLayers.Geometry.LineString'],
		onSelect: redrawPropertiesWindow,
		onUnselect: redrawPropertiesWindow
	}));
	this._reportMap.addLayer(vectors);
	vectors.styleMap.styles.hover = vectors.styleMap.styles.default.clone();
	vectors.styleMap.styles.hover.addRules([
		new OpenLayers.Rule({
			filter: new OpenLayers.Filter.Comparison({
				type: OpenLayers.Filter.Comparison.EQUAL_TO,
				property: 'style',
				value: 'ADDED'
			}),
			symbolizer: {
				strokeColor: '#00ff00',
				strokeWidth: 6,
				strokeOpacity: 0.7
			}
		}),
		new OpenLayers.Rule({
			filter: new OpenLayers.Filter.Comparison({
				type: OpenLayers.Filter.Comparison.EQUAL_TO,
				property: 'style',
				value: 'DELETED'
			}),
			symbolizer: {
				strokeColor: '#ff0000',
				strokeWidth: 16,
				strokeOpacity: 0.7
			}
		}),
		new OpenLayers.Rule({
			elseFilter: true,
			symbolizer: {
				strokeWidth: 4,
				fillColor: '#86cffe',
				strokeColor: '#428bca',
				fillOpacity: 0.7
			}
		})
	]);
	vectors.styleMap.styles.default.addRules([
		new OpenLayers.Rule({
			filter: new OpenLayers.Filter.Comparison({
				type: OpenLayers.Filter.Comparison.EQUAL_TO,
				property: 'style',
				value: 'ADDED'
			}),
			symbolizer: {
				strokeColor: '#00ff00',
				strokeWidth: 6,
				strokeOpacity: 0.3
			}
		}),
		new OpenLayers.Rule({
			filter: new OpenLayers.Filter.Comparison({
				type: OpenLayers.Filter.Comparison.EQUAL_TO,
				property: 'style',
				value: 'DELETED'
			}),
			symbolizer: {
				strokeColor: '#ff0000',
				strokeWidth: 16,
				strokeOpacity: 0.3
			}
		}),
		new OpenLayers.Rule({
			elseFilter: true,
			symbolizer: {
				strokeWidth: 4,
				fillColor: '#86cffe',
				strokeColor: '#428bca',
				fillOpacity: 0.7
			}
		})
	]);
	vectors.styleMap.styles.select.addRules([
		new OpenLayers.Rule({
			filter: new OpenLayers.Filter.Comparison({
				type: OpenLayers.Filter.Comparison.EQUAL_TO,
				property: 'style',
				value: 'ADDED'
			}),
			symbolizer: {
				strokeColor: '#00ff00',
				strokeWidth: 6,
				strokeOpacity: 0.7
			}
		}),
		new OpenLayers.Rule({
			filter: new OpenLayers.Filter.Comparison({
				type: OpenLayers.Filter.Comparison.EQUAL_TO,
				property: 'style',
				value: 'DELETED'
			}),
			symbolizer: {
				strokeColor: '#ff0000',
				strokeWidth: 16,
				strokeOpacity: 0.7
			}
		}),
		new OpenLayers.Rule({
			elseFilter: true,
			symbolizer: {
				strokeWidth: 4,
				fillColor: '#86cffe',
				strokeColor: '#428bca',
				fillOpacity: 0.7
			}
		})
	]);
	if (report.geojson) {
		this._reportMap.zoomToExtent(bounds);
		var geojsonFormatter = new OpenLayers.Format.GeoJSON();
		var features = geojsonFormatter.read(report.geojson);
		features.forEach(feature => feature.geometry.transform(siteProjection, mapProjection));
		// Sort features so deleted lines are put underneath added lines
		features.sort((a,b) => b.attributes.style.localeCompare(a.attributes.style));
		vectors.addFeatures(features);
		console.log('reportMap', this._reportMap);
	} else {
		vectors.addFeature(new OpenLayers.Geometry.Point(center.lon, center.lat));
	}

	var historyList = this.container.querySelector('#report-history');
	historyList.removeAll();
	report.history.forEach(function(history) {
		var description = actions[history.action_id];
		if (history.action_id in actionDescriptions) {
			description += ' to: ' + actionDescriptions[history.action_id][history.value];
		} else if (history.action_id == 4) {
			description += ' to: ' + history.value;
		}
		var detailsFragment = (history.details ? fragmentFactory['ReportView/History/ItemDetails'](history.details) : null);
		historyList.appendChild(fragmentFactory['ReportView/History/Item'](history.timestamp, history.username, description, detailsFragment));
		if (detailsFragment) {
			historyList.appendChild(detailsFragment);
		}
	});
	var notes = this.container.querySelector('#report-notes');
	notes.removeAll();
	notes.innerHTML = report.notes;
	var notesSource = this.container.querySelector('#report-notes-source');
	notesSource.value = report.notes_source;
};

window.addEventListener('load', function() {
	// Initialize filters
	var statusFilter = document.querySelector('#reportStatusFilter');
	if (localStorage.statusFilter == null) {
		localStorage.statusFilter = 'actionable';
	}
	statusFilter.value = localStorage.statusFilter;
	var sourceFilter = document.querySelector('#sourceFilter');
	if (localStorage.sourceFilter == null) {
		localStorage.sourceFilter = sourceFilter.value;
	}
	sourceFilter.value = localStorage.sourceFilter;
	var priorityFilter = document.querySelector('#priorityFilter');
	if (localStorage.priorityFilter == null) {
		localStorage.priorityFilter = priorityFilter.value;
	}
	priorityFilter.value = localStorage.priorityFilter;
	var editorLevelFilter = document.querySelector('#editorLevelFilter');
	if (localStorage.editorLevelFilter == null) {
		localStorage.editorLevelFilter = editorLevelFilter.value;
	}
	editorLevelFilter.value = localStorage.editorLevelFilter;
	if (localStorage.periodFilter == null) {
		localStorage.periodFilter = 'soon';
	}
	periodFilter.value = localStorage.periodFilter;
	if (localStorage.reportsFilterCenter == null) {
		localStorage.reportsFilterCenter = datasetBounds.getCenterLonLat().toShortString();
		localStorage.reportsFilterZoom = 11;
	}
	// Convert Google Maps format to OpenLayers format
	if (localStorage.reportsFilterArea) {
		var oldBoundsObj = JSON.parse(localStorage.reportsFilterArea);
		var oldBounds = new OpenLayers.Bounds(oldBoundsObj.west, oldBoundsObj.south, oldBoundsObj.east, oldBoundsObj.north);
		localStorage.reportsFilterCenter = oldBounds.getCenterLonLat().toShortString();
		localStorage.reportsFilterZoom = 11;
		localStorage.removeItem('reportsFilterArea');
	}
	var followedFilter = document.querySelector('#followedFilter');
	if (followedFilter) {
		if (!localStorage.followedFilter) {
			localStorage.followedFilter = "false";
		}
		followedFilter.checked = (localStorage.followedFilter == "true");
	}

	// Construct views
	var viewManager = new Dashboard.ViewManager();
	var reportView = new Dashboard.ReportView(viewManager, document.querySelector('#report-view'));
	var reportList = new Dashboard.ReportList(viewManager, document.querySelector('#pagination'), document.querySelector('#reports'));
	var listView = new Dashboard.ListView(viewManager, document.querySelector('#list-view'), reportList);

	// Set listeners
	window.addEventListener('popstate', function(event) {
		console.log('popstate event', event);
		if (event.state) {
			viewManager.switchTo(event.state.view);
		} else {
			viewManager.switchTo((location.hash ? 'report' : 'list'));
		}
	});

	statusFilter.addEventListener('change', function(event) {
		localStorage.statusFilter = statusFilter.value;
		listView.refresh(true);
	});
	sourceFilter.addEventListener('change', function(event) {
		localStorage.sourceFilter = sourceFilter.value;
		listView.refresh(true);
	});
	priorityFilter.addEventListener('change', function(event) {
		localStorage.priorityFilter = priorityFilter.value;
		listView.refresh(true);
	});
	editorLevelFilter.addEventListener('change', function(event) {
		localStorage.editorLevelFilter = editorLevelFilter.value;
		listView.refresh(true);
	});
	periodFilter.addEventListener('change', function(event) {
		localStorage.periodFilter = periodFilter.value;
		listView.refresh(true);
	});
	if (followedFilter) {
		followedFilter.addEventListener('change', function(event) {
			localStorage.followedFilter = followedFilter.checked;
			listView.refresh(true);
		});
	}
	// Load the page, show the report detail page if a hash is present
	viewManager.switchTo((location.hash ? 'report' : 'list'));
});

Date.prototype.toShortDateString = function() {
	function pad(number) {
		if (number < 10) {
			return '0' + number;
		}
		return number;
	}

	return this.getFullYear() + '-' + pad(this.getMonth() + 1) + '-' + pad(this.getDate());
}

Element.prototype.removeAll = function () {
	while (this.firstChild) {
		this.removeChild(this.firstChild);
	}
	return this;
};
