var fragmentFactory = {
	'ResultList/ReportRow': (function() {
		var priority_class = ['fa-circle-o', 'fa-dot-circle-o', 'fa-circle', 'fa-exclamation-circle'];
		return function(id, priority, description, startTime, endTime, sourceName, reportClick) {
			var row = document.createElement('tr');
			row.className = 'clickable';
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
			var startElement = document.createElement('time');
			startElement.dateTime = startTime.toISOString();
			startElement.appendChild(document.createTextNode(startTime.toShortDateString()));
			startElement.appendChild(document.createElement('br'));
			startElement.appendChild(document.createTextNode(startTime.toTimeString().substr(0, 5)));
			startLink.appendChild(startElement);
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
	'ResultList/Pagination/PageLabel': function() {
		var label = document.createElement('li');
		label.className = 'disabled';
		var labelSpan = document.createElement('span');
		labelSpan.textContent = 'Page';
		labelSpan.style.cursor = 'default';
		label.appendChild(labelSpan);
		return label;
	},
	'ResultList/Pagination/Page': function(pageNumber, pageClick) {
		var page = document.createElement('li');
		page.className = 'clickable';
		var pageLink = document.createElement('a');
		pageLink.textContent = pageNumber;
		page.addEventListener('click', pageClick);
		page.appendChild(pageLink);
		return page;
	},
	'ResultList/Pagination/ResultCount': function(reports) {
		var resultCount = document.createElement('li');
		resultCount.className = 'disabled';
		var resultCountSpan = document.createElement('span');
		resultCountSpan.textContent = reports + (reports == 300 ? '+' : '') + ' reports in total';
		resultCountSpan.style.cursor = 'default';
		resultCount.appendChild(resultCountSpan);
		return resultCount;
	},
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
			var detailsInfo = document.createElement('i');
			detailsInfo.className = 'fa fa-info-circle fa-fw';
			detailsInfo.style.color = '#428bca';
			detailsInfo.style.cursor = 'help';
			detailsInfo.title = details;
			$(detailsInfo).tooltip({
				container: 'body'
			});
			detailsTd.appendChild(detailsInfo);
		}
		tr.appendChild(detailsTd);
		return tr;
	},
	'ReportView/Discussion/Item': function(date, user, message, avatarSrc) {
		var container = document.createElement('div');
		container.className = 'clearfix list-group-item';
		var avatar = document.createElement('img');
		avatar.src = avatarSrc;
		avatar.title = user;
		avatar.dataset.placement = 'right';
		avatar.dataset.toggle = 'tooltip';
		$(avatar).tooltip();
		container.appendChild(avatar);
		var timestamp = document.createElement('i');
		timestamp.className = 'fa fa-clock-o';
		timestamp.style.float = 'left';
		timestamp.style.marginLeft = '-8px';
		timestamp.title = 'Posted on ' + date;
		timestamp.dataset.placement = 'right';
		timestamp.dataset.toggle = 'tooltip';
		$(timestamp).tooltip();
		container.appendChild(timestamp);
		var messageContainer = document.createElement('div');
		messageContainer.className = 'message-container';
		messageContainer.innerHTML = message;
		container.appendChild(messageContainer);
		return container;
	}
};

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

Dashboard.ListView = function(viewManager, container, filterMapContainer, reportList) {
	Dashboard.View.call(this, viewManager, 'list', container);
	this.reportList = reportList;
	this.filterMapContainer = filterMapContainer;
	this.reports = [];
	this._mapLoaded = false;
	this.reportList.reports = this.reports;
	this.refresh();
};
Dashboard.ListView.prototype = Object.create(Dashboard.View.prototype);
Dashboard.ListView.prototype.activate = function() {
	if (!this._mapLoaded) {
		this._mapLoaded = true;
		var self = this;
		var filterMap = new google.maps.Map(this.filterMapContainer, {
			center: { lat: (datasetBounds.north+datasetBounds.south)/2, lng: (datasetBounds.west+datasetBounds.east)/2 },
			zoom: 7,
			minZoom: 6,
			maxZoom: 11,
			clickableIcons: false,
			mapTypeControl: false,
			streetViewControl: false
		});
		console.log('filterMap', filterMap);
		var filterBounds = new google.maps.Rectangle({
			bounds: JSON.parse(localStorage.reportsFilterArea),
			map: filterMap,
			editable: true,
			draggable: true,
			zIndex: 10
		});
		filterMap.fitBounds(filterBounds.getBounds());
		var dragging = false;
		filterBounds.addListener('drag', function() {
			dragging = true;
		});
		filterBounds.addListener('dragend', function() {
			dragging = false;
			localStorage.reportsFilterArea = JSON.stringify(filterBounds.bounds.toJSON());
			self.refresh();
		});
		filterBounds.addListener('bounds_changed', function() {
			if (!dragging) {
				localStorage.reportsFilterArea = JSON.stringify(filterBounds.bounds.toJSON());
				self.refresh();
			}
		});
		google.maps.event.addDomListener(window, "resize", function() {
			var center = filterMap.getCenter();
			google.maps.event.trigger(filterMap, "resize");
			filterMap.setCenter(center); 
		});
		if (typeof managementArea !== 'undefined') {
			var controlDiv = document.createElement('div');
			controlDiv.style.backgroundColor = '#fff';
			controlDiv.style.padding = '2px';
			controlDiv.style.margin = '3px';
			var resetAreaLink = document.createElement('a');
			resetAreaLink.appendChild(document.createTextNode('Reset to management area'));
			resetAreaLink.style.cursor = 'pointer';
			resetAreaLink.addEventListener('click', function(e) {
				e.preventDefault();
				filterBounds.setBounds(managementArea);
			});
			controlDiv.appendChild(resetAreaLink);
			filterMap.controls[google.maps.ControlPosition.TOP_LEFT].push(controlDiv);
		}
		Dashboard.loadPage(baseUrl + '/heatmap', {}, function() {
			var heatmapData = [];
			this.response.heatmap.forEach(function(entry) {
				heatmapData.push({location: new google.maps.LatLng(entry.lat, entry.lon), weight: entry.reports});
			});
			var heatmap = new google.maps.visualization.HeatmapLayer({
				data: heatmapData
			});
			heatmap.setMap(filterMap);
		});
	}
	this.refresh(this.reportList.page);
};
// Load the reports matching the current filter options in localStorage
Dashboard.ListView.prototype.refresh = function(page) {
	var bounds = JSON.parse(localStorage.reportsFilterArea);
	var params = {
		status: localStorage.statusFilter,
		source: localStorage.sourceFilter,
		priority: localStorage.priorityFilter,
		level: localStorage.editorLevelFilter,
		period: localStorage.periodFilter,
		followed: (localStorage.followedFilter == 'true' ? 1 : 0),
		bounds: bounds.west + ',' + bounds.south + ',' + bounds.east + ',' + bounds.north
	};
	var self = this;
	var statusId = Status.show('info', 'Loading results...');
	Dashboard.loadPage(baseUrl + '/query', params, function() {
		Status.hide(statusId);
		self.reports.length = 0;
		Array.prototype.push.apply(self.reports, this.response.reports);
		console.log('ListView refreshed', this.response.reports);
		self.reportList.refresh(page);
	});
};

Dashboard.ReportList = function(viewManager, paginationContainer, listContainer) {
	this.page = 0;
	this.reportsPerPage = 20;
	this._listContainer = listContainer;
	this._paginationContainer = paginationContainer;
	this.reports = [];
	this._viewManager = viewManager;
};
Dashboard.ReportList.prototype.refresh = function(page) {
	var toPage = page || 0;
	this._paginationContainer.removeAll();
	if (this.reports.length > 0) {
		this._paginationContainer.appendChild(fragmentFactory['ResultList/Pagination/PageLabel']());
		var self = this;
		Array.apply(0, Array(Math.ceil(this.reports.length / this.reportsPerPage))).map(function(e, idx) {
			self._paginationContainer.appendChild(fragmentFactory['ResultList/Pagination/Page'](idx + 1, function() {
				/*if (history.pushState) {
					history.pushState({ 'page': idx, 'view': 'list' }, null, 'reports');
				}*/
				self.showPage(idx);
			}));
		});
		this._paginationContainer.appendChild(fragmentFactory['ResultList/Pagination/ResultCount'](this.reports.length));
	}
	this._listContainer.querySelector('#no-reports').classList.toggle('hidden', this.reports.length > 0);
	this.showPage(toPage);
};
Dashboard.ReportList.prototype.showPage = function(pageNumber) {
	this.page = Math.min(pageNumber, Math.floor((this.reports.length - 1) / this.reportsPerPage));
	console.log('ReportList showPage', this.page, this.reports);
	// Update pagination
	if (this._paginationContainer.querySelector('li.active')) {
		this._paginationContainer.querySelector('li.active').classList.remove('active');
	}
	if (this.reports.length > 0) {
		this._paginationContainer.children.item(this.page+1).classList.add('active');
	}
	// Clear the page of reports
	while (this._listContainer.lastChild.id != 'no-reports') {
		this._listContainer.removeChild(this._listContainer.lastChild);
	}
	// Fill the list with the current page of reports
	var pageReports = this.reports.slice(this.page * this.reportsPerPage, (this.page+1) * this.reportsPerPage);
	var self = this;
	pageReports.forEach(function(report) {
		var startDate = new Date(report.start_time * 1000);
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
	this._wazeMap = container.querySelector('#liveMap');
	this._loadedLocation = (function() { // Object to store and compare currently loaded map coordinates
		var lat = lon = 0;
		return {
			set: function(latarg, lonarg) {
				lat = latarg;
				lon = lonarg;
			},
			equals: function(latarg, lonarg) {
				return lat == latarg && lon == lonarg;
			}
		};
	})();
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
		prompt('You can also press Ctrl+C while hovering over the button', copyCoords.dataset.coords);
	});
	document.addEventListener('copy', function(e) {
		if (!isHoveringCoords) {
			return;
		}
		console.log('Copy to clipboard', e, isHoveringCoords);
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
					title: 'Ctrl+C to copy coordinates'
				});
			}, 200);
		}, 4000);
	});
	$(copyCoords).tooltip({
		container: 'body',
		title: 'Ctrl+C to copy coordinates'
	});
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
	var commentsEditBtn = document.getElementById('report-comments-edit-btn');
	var commentsView = document.getElementById('report-comments');
	var commentsEditor = document.getElementById('report-comments-edit');
	commentsEditBtn.addEventListener('click', function() {
		commentsEditBtn.classList.toggle('hidden');
		commentsView.classList.toggle('hidden');
		commentsEditor.classList.toggle('hidden');
	});
	document.getElementById('report-set-comments').addEventListener('click', function() {
	var statusId = Status.show('info', 'Setting new comments');
		Dashboard.loadPage(baseUrl + '/set-comments', { id: location.hash.substring(1), comments: document.getElementById('report-comments-source').value }, function() {
			if (this.response.ok) {
				Status.hide(statusId);
				commentsView.innerHTML = this.response.comments;
				commentsEditBtn.classList.toggle('hidden');
				commentsView.classList.toggle('hidden');
				commentsEditor.classList.toggle('hidden');
			} else {
				Status.show('danger', this.response.error);
				console.log('Sending comments failed', this.response.error);
			}
		});
	});
	document.getElementById('report-comments-cancel').addEventListener('click', function() {
		commentsEditBtn.classList.toggle('hidden');
		commentsView.classList.toggle('hidden');
		commentsEditor.classList.toggle('hidden');
	});
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
	var startTime = new Date(report.start_time * 1000);
	var startElement = document.createElement('time');
	startElement.dateTime = startTime.toISOString();
	startElement.appendChild(document.createTextNode(startTime.toShortDateString()));
	startElement.appendChild(document.createTextNode(' '));
	startElement.appendChild(document.createTextNode(startTime.toTimeString().substr(0, 5)));
	period.appendChild(startElement);
	if (report.end_time) {
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
	var externalData = this.container.querySelector('#externalData');
	externalData.removeAll();
	if (report.external_data != null) {
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
		var bounds = new google.maps.LatLngBounds();
		var dfs = function(node) {
			if (node.length == 2 && !Array.isArray(node[0])) {
				bounds.extend({lat: node[1], lng: node[0]});
			} else {
				node.forEach(function(child) {
					dfs(child);
				});
			}
		};
		if (report.geojson.coordinates) {
			dfs(report.geojson.coordinates);
		} else if (report.geojson.geometries) {
			report.geojson.geometries.forEach(function(geometry) {
				dfs(geometry.coordinates)
			});
		}
		var center = bounds.getCenter();
		// Require at least a certain viewport
		bounds.extend({ lat: center.lat() - 0.002, lng: center.lng() - 0.002 });
		bounds.extend({ lat: center.lat() + 0.002, lng: center.lng() + 0.002 });
	}
	if (!center) {
		center = new google.maps.LatLng(report.lat, report.lon);
	}
	if (!this._loadedLocation.equals(center.lat, center.lng)) {
		this._loadedLocation.set(center.lat, center.lng);
		this._wazeMap.contentWindow.location.replace('https://embed.waze.com/en/iframe?lon=' + center.lng() + '&lat=' + center.lat() + '&zoom=16');
	}
	var reportMap = new google.maps.Map(this.container.querySelector('#reportMap'), {
		zoom: 16,
		clickableIcons: false,
		mapTypeControl: false,
		streetViewControl: false,
		center: center
	});
	google.maps.event.addDomListener(window, "resize", function() {
		var center = reportMap.getCenter();
		google.maps.event.trigger(reportMap, "resize");
		reportMap.setCenter(center); 
	});
	if (report.geojson) {
		reportMap.fitBounds(bounds);
		var feature = {
			type: 'Feature',
			geometry: report.geojson
		};
		reportMap.data.setStyle({
			clickable: false
		});
		reportMap.data.addGeoJson(feature);
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
		historyList.appendChild(fragmentFactory['ReportView/History/Item'](history.timestamp, history.username, description, history.details));
	});
	var comments = this.container.querySelector('#report-comments');
	comments.removeAll();
	comments.innerHTML = report.comments;
	var commentsSource = this.container.querySelector('#report-comments-source');
	commentsSource.value = report.comments_source;
	// Move last item into view
	//discussionList.scrollTop = discussionList.scrollHeight;
};

window.addEventListener('load', function() {
	// Initialize filters
	var statusFilter = document.querySelector('#reportStatusFilter');
	if (!localStorage.statusFilter) {
		localStorage.statusFilter = 'actionable';
	}
	statusFilter.value = localStorage.statusFilter;
	var sourceFilter = document.querySelector('#sourceFilter');
	if (!localStorage.sourceFilter) {
		localStorage.sourceFilter = sourceFilter.value;
	}
	sourceFilter.value = localStorage.sourceFilter;
	var priorityFilter = document.querySelector('#priorityFilter');
	if (!localStorage.priorityFilter) {
		localStorage.priorityFilter = priorityFilter.value;
	}
	priorityFilter.value = localStorage.priorityFilter;
	var editorLevelFilter = document.querySelector('#editorLevelFilter');
	if (!localStorage.editorLevelFilter) {
		localStorage.editorLevelFilter = editorLevelFilter.value;
	}
	editorLevelFilter.value = localStorage.editorLevelFilter;
	if (!localStorage.periodFilter) {
		localStorage.periodFilter = 'soon';
	}
	periodFilter.value = localStorage.periodFilter;
	if (!localStorage.reportsFilterArea) {
		localStorage.reportsFilterArea = JSON.stringify(datasetBounds);
	}
	var bounds = JSON.parse(localStorage.reportsFilterArea);
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
	var listView = new Dashboard.ListView(viewManager, document.querySelector('#list-view'), document.querySelector('#filterMap'), reportList);

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
		listView.refresh();
	});
	sourceFilter.addEventListener('change', function(event) {
		localStorage.sourceFilter = sourceFilter.value;
		listView.refresh();
	});
	priorityFilter.addEventListener('change', function(event) {
		localStorage.priorityFilter = priorityFilter.value;
		listView.refresh();
	});
	editorLevelFilter.addEventListener('change', function(event) {
		localStorage.editorLevelFilter = editorLevelFilter.value;
		listView.refresh();
	});
	periodFilter.addEventListener('change', function(event) {
		localStorage.periodFilter = periodFilter.value;
		listView.refresh();
	});
	if (followedFilter) {
		followedFilter.addEventListener('change', function(event) {
			localStorage.followedFilter = followedFilter.checked;
			listView.refresh();
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
