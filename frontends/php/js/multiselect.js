/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


jQuery(function($) {
	var ZBX_STYLE_CLASS = 'multiselect-control',
		KEY = {
			ARROW_DOWN: 40,
			ARROW_LEFT: 37,
			ARROW_RIGHT: 39,
			ARROW_UP: 38,
			BACKSPACE: 8,
			DELETE: 46,
			ENTER: 13,
			ESCAPE: 27,
			TAB: 9
		};

	/**
	 * Multi select helper.
	 *
	 * @param string options['objectName']		backend data source
	 * @param object options['objectOptions']	parameters to be added the request URL (optional)
	 *
	 * @see jQuery.multiSelect()
	 */
	$.fn.multiSelectHelper = function(options) {
		options = $.extend({objectOptions: {}}, options);

		// url
		options.url = new Curl('jsrpc.php', false);
		options.url.setArgument('type', 11); // PAGE_TYPE_TEXT_RETURN_JSON
		options.url.setArgument('method', 'multiselect.get');
		options.url.setArgument('objectName', options.objectName);

		for (var key in options.objectOptions) {
			options.url.setArgument(key, options.objectOptions[key]);
		}

		options.url = options.url.getUrl();

		// labels
		options.labels = {
			'No matches found': t('No matches found'),
			'More matches found...': t('More matches found...'),
			'type here to search': t('type here to search'),
			'new': t('new'),
			'Select': t('Select')
		};

		return this.each(function() {
			$(this).empty().multiSelect(options);
		});
	};

	/*
	 * Multiselect methods
	 */
	var methods = {
		/**
		 * Get multi select selected data.
		 *
		 * @return array    array of multiselect value objects
		 */
		getData: function() {
			var ms = this.first().data('multiSelect');

			var data = [];
			for (var id in ms.values.selected) {
				var item = ms.values.selected[id];

				data[data.length] = {
					id: id,
					name: item.name,
					prefix: (typeof item.prefix === 'undefined') ? '' : item.prefix
				};
			}

			return data;
		},

		/**
		 * Resize multiselect selected text
		 *
		 * @return jQuery
		 */
		resize: function() {
			return this.each(function() {
				var obj = $(this);
				var ms = $(this).data('multiSelect');

				resizeAllSelectedTexts(obj, ms.options, ms.values);
			});
		},

		/**
		 * Insert outside data
		 *
		 * @param object    multiselect value object
		 *
		 * @return jQuery
		 */
		addData: function(item) {
			return this.each(function() {
				var obj = $(this),
					ms = $(this).data('multiSelect');

				// clean input if selectedLimit == 1
				if (ms.options.selectedLimit == 1) {
					for (var id in ms.values.selected) {
						removeSelected(id, obj, ms.values, ms.options);
					}

					cleanAvailable(item, ms.values);
				}
				addSelected(item, obj, ms.values, ms.options);
			});
		},

		/**
		 * Clean multi select object values.
		 *
		 * @return jQuery
		 */
		clean: function() {
			return this.each(function() {
				var obj = $(this);
				var ms = $(this).data('multiSelect');

				for (var id in ms.values.selected) {
					removeSelected(id, obj, ms.values, ms.options);
				}

				cleanAvailable(obj, ms.values);
			});
		},

		/**
		 * Disable multi select UI control.
		 *
		 * @return jQuery
		 */
		disable: function() {
			return this.each(function() {
				var $obj = $(this),
					$wrapper = $obj.parent('.'+ZBX_STYLE_CLASS),
					ms = $obj.data('multiSelect');

				if (ms.options.disabled === false) {
					$obj.attr('aria-disabled', true);
					$('.multiselect-list', $obj).addClass('disabled');
					$('.multiselect-button > button', $wrapper).prop('disabled', true);
					$('input[type=text]', $wrapper).remove();
					cleanAvailable($obj, ms.values);
					$('.available', $wrapper).remove();

					ms.options.disabled = true;
				}
			});
		},

		/**
		 * Enable multi select UI control.
		 *
		 * @return jQuery
		 */
		enable: function() {
			return this.each(function() {
				var $obj = $(this),
					$wrapper = $obj.parent('.'+ZBX_STYLE_CLASS),
					ms = $(this).data('multiSelect');

				if (ms.options.disabled === true) {
					var $input = makeMultiSelectInput($obj);
					$obj.removeAttr('aria-disabled');
					$('.multiselect-list', $obj).removeClass('disabled');
					$('.multiselect-button > button', $wrapper).prop('disabled', false);
					$obj.append($input);
					makeSuggsetionsBlock($obj);

					// set readonly
					if (ms.options.selectedLimit != 0 && $('.selected li', $obj).length >= ms.options.selectedLimit) {
						setSearchFieldVisibility(false, $obj, ms.options);
					}

					ms.values.isAvailableOpened = true;
					ms.options.disabled = false;
				}
			});
		},

		/**
		 * Modify one or more multiselect options after multiselect object has been created.
		 *
		 * @return jQuery
		 */
		modify: function(options) {
			return this.each(function() {
				var $obj = $(this),
					ms = $(this).data('multiSelect');

				for (var ms_key in ms.options) {
					if (ms_key in options) {
						ms.options[ms_key] = options[ms_key];
					}

					/*
					 * When changing the option "addNew" few things need to happen:
					 *   1) previous search results must be cleared, in case same search string is requested. So
					 *      a new request is sent and new results are received. With or without "(new)".
					 *   2) Already selected "(new)" items must be hidden and disabled, so that they are not sent
					 *      when form is submitted.
					 *   3) Already visible block with results must be hidden. It will reappear on new search.
					 */
					if (ms_key === 'addNew') {
						cleanLastSearch($obj);

						$('input[name*="[new]"]', $obj)
							.prop('disabled', !ms.options[ms_key])
							.each(function() {
								$('.selected li[data-id="' + this.value + '"]', $obj).toggle(ms.options[ms_key]);
							});

						hideAvailable($obj);
					}
				}
			});
		}
	};

	/**
	 * Create multi select input element.
	 *
	 * @param string options['url']					backend url
	 * @param string options['name']				input element name
	 * @param object options['labels']				translated labels (optional)
	 * @param object options['data']				preload data {id, name, prefix} (optional)
	 * @param string options['data'][id]
	 * @param string options['data'][name]
	 * @param string options['data'][prefix]		(optional)
	 * @param bool   options['data'][inaccessible]	(optional)
	 * @param bool   options['data'][disabled]		(optional)
	 * @param array  options['excludeids']			the list of excluded ids (optional)
	 * @param string options['defaultValue']		default value for input element (optional)
	 * @param bool   options['disabled']			turn on/off readonly state (optional)
	 * @param bool   options['addNew']				allow user to create new names (optional)
	 * @param int    options['selectedLimit']		how many items can be selected (optional)
	 * @param int    options['limit']				how many available items can be received from backend (optional)
	 * @param object options['popup']				popup data {parameters, width, height} (optional)
	 * @param string options['popup']['parameters']
	 * @param int    options['popup']['width']
	 * @param int    options['popup']['height']
	 * @param string options['styles']				additional style for multiselect wrapper HTML element (optional)
	 * @param string options['styles']['property']
	 * @param string options['styles']['value']
	 *
	 * @return object
	 */
	$.fn.multiSelect = function(options) {
		// call a public method
		if (methods[options]) {
			return methods[options].apply(this, Array.prototype.slice.call(arguments, 1));
		}

		var defaults = {
			url: '',
			name: '',
			labels: {
				'No matches found': 'No matches found',
				'More matches found...': 'More matches found...',
				'type here to search': 'type here to search',
				'new': 'new',
				'Select': 'Select'
			},
			data: [],
			only_hostid: 0,
			excludeids: [],
			addNew: false,
			defaultValue: null,
			disabled: false,
			selectedLimit: 0,
			limit: 20,
			popup: [],
			styles: []
		};
		options = $.extend({}, defaults, options);

		return this.each(function() {
			var obj = $(this);

			options = $.extend({}, {
				required: (typeof obj.attr('aria-required') !== 'undefined') ? obj.attr('aria-required') : false
			}, options);

			var ms = {
				options: options,
				values: {
					search: '',
					width: parseInt(obj.css('width')),
					isWaiting: false,
					isAjaxLoaded: true,
					isMoreMatchesFound: false,
					isAvailableOpened: false,
					selected: {},
					available: {}
				}
			};

			obj.removeAttr('aria-required');

			// store the configuration in the elements data
			obj.data('multiSelect', ms);

			var values = ms.values;

			// add wrap
			obj.wrap(jQuery('<div>', {
				'class': ZBX_STYLE_CLASS,
				css: options.styles
			}));

			// selected
			var selected_div = $('<div>', {
				'class': 'selected'
			});
			var selected_ul = $('<ul>', {
				'class': 'multiselect-list'
			});

			obj.append(selected_div.append(selected_ul));

			if (options.disabled) {
				selected_ul.addClass('disabled');
			}
			else {
				obj.append(makeMultiSelectInput(obj));
			}

			// available
			if (!options.disabled) {
				makeSuggsetionsBlock(obj);
			}

			// preload data
			if (empty(options.data)) {
				setDefaultValue(obj, options);
			}
			else {
				loadSelected(options.data, obj, values, options);
			}

			cleanLastSearch(obj);

			// draw popup link
			if (options.popup.parameters != null) {
				var popup_options = options.popup.parameters;

				if (typeof popup_options['only_hostid'] !== 'undefined') {
					options.only_hostid = popup_options['only_hostid'];
				}

				var popupButton = $('<button>', {
					type: 'button',
					'class': 'btn-grey',
					text: options.labels['Select']
				});

				if (options.disabled) {
					popupButton.prop('disabled', true);
				}

				popupButton.click(function(event) {
					return PopUp('popup.generic', popup_options, null, event.target);
				});

				obj.parent().append($('<div>', {
					'class': 'multiselect-button'
				}).append(popupButton));
			}
		});
	};

	function makeSuggsetionsBlock($obj) {
		var ms = $obj.data('multiSelect'),
			values = ms.values,
			$available = $('<div>', {
				'class': 'available',
				css: { display: 'none' }
			});

		// multi select
		$obj.append($available)
			.focusout(function() {
				setTimeout(function() {
					if (!values.isAvailableOpened && $('.available', $obj).is(':visible')) {
						hideAvailable($obj);
					}
				}, 200);
			});
	}

	function makeMultiSelectInput($obj) {
		var ms = $obj.data('multiSelect'),
			values = ms.values,
			options = ms.options,
			$label = $('label[for=' + $obj.attr('id') + '_ms]'),
			$aria_live = $('[aria-live]', $obj),
			$input = $('<input>', {
				'id': $label.length ? $label.attr('for') : null,
				'class': 'input',
				'type': 'text',
				'placeholder': options.labels['type here to search'],
				'aria-label': ($label.length ? $label.text() + '. ' : '') + options.labels['type here to search'],
				'aria-required': options.required
			})
			.on('keyup change', function(e) {

				if (typeof e.which === 'undefined') {
					return false;
				}

				switch (e.which) {
					case KEY.ARROW_DOWN:
					case KEY.ARROW_LEFT:
					case KEY.ARROW_RIGHT:
					case KEY.ARROW_UP:
						return false;
					case KEY.ESCAPE:
						cleanSearchInput($obj);
						return false;
				}

				if (options.selectedLimit != 0 && $('.selected li', $obj).length >= options.selectedLimit) {
					setSearchFieldVisibility(false, $obj, options);
					return false;
				}

				var search = $input.val();

				// Replace trailing slashes to check if search term contains anything else.
				if (search !== '') {
					$('.selected li.selected', $obj).removeClass('selected');

					if ($input.data('lastSearch') != search) {
						if (!values.isWaiting) {
							values.isWaiting = true;

							var jqxhr = null;
							window.setTimeout(function() {
								values.isWaiting = false;

								var search = $input.val().replace(/^\s+|\s+$/g, '');

								// re-check search after delay
								if (search !== '' && $input.data('lastSearch') != search) {
									values.search = search;

									$input.data('lastSearch', values.search);

									if (jqxhr != null) {
										jqxhr.abort();
									}

									values.isAjaxLoaded = false;
									var request_data = {
										search: values.search,
										limit: getLimit(values, options)
									};

									jqxhr = $.ajax({
										url: options.url + '&curtime=' + new CDate().getTime(),
										type: 'GET',
										dataType: 'json',
										cache: false,
										data: request_data,
										success: function(data) {
											values.isAjaxLoaded = true;
											loadAvailable(data.result, $obj, values, options);
										}
									});
								}
							}, 500);
						}
					}
					else {
						if ($('.available', $obj).is(':hidden')) {
							showAvailable($obj, values);
						}
					}
				}
				else {
					hideAvailable($obj);
				}
			})
			.on('keydown', function(e) {
				switch (e.which) {

					case KEY.TAB:
					case KEY.ESCAPE:
						hideAvailable($obj);
						cleanSearchInput($obj);
						break;

					case KEY.ENTER:
						if ($input.val() !== '') {
							var $selected = $('.available li.suggest-hover', $obj);

							if ($selected.length) {
								select($selected.data('id'), $obj, values, options);
								$aria_live.text(sprintf(t('Added, %1$s'), $selected.data('label')));
							}

							return cancelEvent(e);
						}
						break;

					case KEY.ARROW_LEFT:
						if ($input.val() === '') {
							var $collection = $('.selected li', $obj);

							if ($collection.length) {
								var $prev = $collection.filter('.selected').removeClass('selected').prev();
								$prev = ($prev.length ? $prev : $collection.last()).addClass('selected');

								$aria_live.text(($prev.hasClass('disabled'))
									? sprintf(t('%1$s, read only'), $prev.data('label'))
									: $prev.data('label')
								);
							}
						}
						break;

					case KEY.ARROW_RIGHT:
						if ($input.val() === '') {
							var $collection = $('.selected li', $obj);

							if ($collection.length) {
								var $next = $collection.filter('.selected').removeClass('selected').next();
								$next = ($next.length ? $next : $collection.first()).addClass('selected');

								$aria_live.text(($next.hasClass('disabled'))
									? sprintf(t('%1$s, read only'), $next.data('label'))
									: $next.data('label')
								);
							}
						}
						break;

					case KEY.ARROW_UP:
					case KEY.ARROW_DOWN:
						var $collection = $('.available:visible li', $obj),
							$selected = $collection.filter('.suggest-hover').removeClass('suggest-hover');

						if ($selected.length) {
							$selected = (e.which == KEY.ARROW_UP)
								? ($selected.is(':first-child') ? $collection.last() : $selected.prev())
								: ($selected.is(':last-child') ? $collection.first() : $selected.next());

							$selected.addClass('suggest-hover');
							$aria_live.text($selected.data('label'));
						}

						scrollAvailable($obj);
						return cancelEvent(e);

					case KEY.BACKSPACE:
					case KEY.DELETE:
						if ($input.val() === '') {
							var $selected = $('.selected li.selected', $obj);

							if ($selected.length) {
								var id = $selected.data('id'),
									item = values.selected[id];

								if (typeof item.disabled === 'undefined' || !item.disabled) {
									var aria_text = sprintf(t('Removed, %1$s'), $selected.data('label'));

									$selected = (e.which == KEY.BACKSPACE)
										? ($selected.is(':first-child') ? $selected.next() : $selected.prev())
										: ($selected.is(':last-child') ? $selected.prev() : $selected.next());

									removeSelected(id, $obj, values, options);

									if ($selected.length) {
										var $collection = $('.selected li', $obj);
										$selected.addClass('selected');

										aria_text += ', ' + sprintf(
											($selected.hasClass('disabled'))
												? t('Selected, %1$s, read only, in position %2$d of %3$d')
												: t('Selected, %1$s in position %2$d of %3$d'),
											$selected.data('label'),
											$collection.index($selected) + 1,
											$collection.length
										);
									}

									$aria_live.text(aria_text);
								}
								else {
									$aria_live.text(t('Can not be removed'));
								}
							}
							else if (e.which == KEY.BACKSPACE) {
								/* Pressing Backspace on empty input field should select last element in
								 * multiselect. For next Backspace press to be able to remove it.
								 */
								var $selected = $('.selected li:last-child', $obj).addClass('selected');
								$aria_live.text($selected.data('label'));
							}

							return cancelEvent(e);
						}
						break;
				}
			})
			.on('focusin', function() {
				$obj.addClass('active');

				if (getSearchFieldVisibility($obj) == false) {
					$('.selected li:first-child', $obj).addClass('selected');
				}
			})
			.on('focusout', function() {
				$obj.removeClass('active').find('li.selected').removeClass('selected');
				cleanSearchInput($obj);
			});

		return $input;
	}

	function setDefaultValue(obj, options) {
		if (!empty(options.defaultValue)) {
			obj.append($('<input>', {
				type: 'hidden',
				name: options.name,
				value: options.defaultValue,
				'data-default': 1
			}));
		}
	}

	function removeDefaultValue(obj, options) {
		if (!empty(options.defaultValue)) {
			$('input[data-default="1"]', obj).remove();
		}
	}

	function loadSelected(data, obj, values, options) {
		$.each(data, function(i, item) {
			addSelected(item, obj, values, options);
		});
	}

	function loadAvailable(data, obj, values, options) {
		cleanAvailable(obj, values);

		// add new
		if (options.addNew) {
			var value = values['search'].replace(/^\s+|\s+$/g, '');

			if (value.length) {
				var addNew = false;

				if (data.length || objectLength(values.selected) > 0) {
					var names = {};

					// check if value exists among available
					if (data.length) {
						$.each(data, function(i, item) {
							if (item.name === value) {
								names[item.name.toUpperCase()] = true;
							}
						});

						if (typeof names[value.toUpperCase()] === 'undefined') {
							addNew = true;
						}
					}

					// check if value exists among selected
					if (!addNew && objectLength(values.selected) > 0) {
						$.each(values.selected, function(i, item) {
							if (typeof item.isNew === 'undefined') {
								names[item.name.toUpperCase()] = true;
							}
							else {
								names[item.id.toUpperCase()] = true;
							}
						});

						if (typeof names[value.toUpperCase()] === 'undefined') {
							addNew = true;
						}
					}
				}
				else {
					addNew = true;
				}

				if (addNew) {
					data[data.length] = {
						id: value,
						prefix: '',
						name: value + ' (' + options.labels['new'] + ')',
						isNew: true
					};
				}
			}
		}

		if (!empty(data)) {
			$.each(data, function(i, item) {
				if (options.limit != 0 && objectLength(values.available) < options.limit) {
					if (typeof values.available[item.id] === 'undefined'
							&& typeof values.selected[item.id] === 'undefined'
							&& options.excludeids.indexOf(item.id) === -1) {
						values.available[item.id] = item;
					}
				}
				else {
					values.isMoreMatchesFound = true;
				}
			});
		}

		var found = 0,
			preselected = '';

		// write empty result label
		if (objectLength(values.available) == 0) {
			var div = $('<div>', {
				'class': 'multiselect-matches',
				text: options.labels['No matches found']
			})
			.click(function() {
				$('input[type="text"]', obj).focus();
			});

			$('.available', obj).append(div);
		}
		else {
			$('.available', obj)
				.append($('<ul>', {
					'class': 'multiselect-suggest',
					'aria-hidden': true
				}))
				.mouseenter(function() {
					values.isAvailableOpened = true;
				})
				.mouseleave(function() {
					values.isAvailableOpened = false;
				});

			$.each(data, function (i, item) {
				if (typeof values.available[item.id] !== 'undefined') {
					if (found == 0) {
						preselected = (item.prefix || '') + item.name;
					}
					addAvailable(item, obj, values, options);
					found++;
				}
			});
		}

		if (found > 0) {
			$('[aria-live]', obj).text(
				(values.isMoreMatchesFound
					? sprintf(t('More than %1$d matches for %2$s found'), found, values.search)
					: sprintf(t('%1$d matches for %2$s found'), found, values.search)) +
				', ' + sprintf(t('%1$s preselected, use down,up arrow keys and enter to select'), preselected)
			);
		}
		else {
			$('[aria-live]', obj).text(options.labels['No matches found']);
		}

		// write more matches found label
		if (values.isMoreMatchesFound) {
			var div = $('<div>', {
				'class': 'multiselect-matches',
				text: options.labels['More matches found...']
			})
			.click(function() {
				$('input[type="text"]', obj).focus();
			});

			$('.available', obj).prepend(div);
		}

		showAvailable(obj, values);
	}

	function addSelected(item, obj, values, options) {
		if (typeof values.selected[item.id] === 'undefined') {
			removeDefaultValue(obj, options);
			values.selected[item.id] = item;

			var prefix = (item.prefix || ''),
				item_disabled = (typeof(item.disabled) !== 'undefined' && item.disabled);

			// add hidden input
			obj.append($('<input>', {
				type: 'hidden',
				name: (options.addNew && item.isNew) ? options.name + '[new]' : options.name,
				value: item.id,
				'data-name': item.name,
				'data-prefix': prefix
			}));

			var li = $('<li>', {
				'data-id': item.id,
				'data-label': prefix + item.name
			}).append(
				$('<span>', {
					'class': 'subfilter-enabled'
				})
					.append($('<span>', {
						text: prefix + item.name,
						title: item.name
					}))
					.append($('<span>')
						.addClass('subfilter-disable-btn')
						.on('click', function() {
							if (!options.disabled && !item_disabled) {
								removeSelected(item.id, obj, values, options);
							}
						}))
			);

			if (typeof(item.inaccessible) !== 'undefined' && item.inaccessible) {
				li.addClass('inaccessible');
			}

			if (item_disabled) {
				li.addClass('disabled');
			}

			$('.selected ul', obj).append(li);

			resizeSelectedText(li, obj);

			// set readonly
			if (options.selectedLimit != 0 && $('.selected li', obj).length >= options.selectedLimit) {
				setSearchFieldVisibility(false, obj, options);
			}
		}
	}

	function removeSelected(id, obj, values, options) {
		// remove
		$('.selected li[data-id="' + id + '"]', obj).remove();
		$('input[value="' + id + '"]', obj).remove();

		delete values.selected[id];

		// remove readonly
		if ($('.selected li', obj).length == 0) {
			setDefaultValue(obj, options);
		}

		// clean
		cleanAvailable(obj, values);
		cleanLastSearch(obj);

		if (options.selectedLimit == 0 || $('.selected li', obj).length < options.selectedLimit) {
			setSearchFieldVisibility(true, obj, options);
			$('input[type="text"]', obj).focus();
		}
	}

	function addAvailable(item, obj, values, options) {
		var li = $('<li>', {
			'data-id': item.id,
			'data-label': (item.prefix || '') + item.name
		})
		.click(function() {
			select(item.id, obj, values, options);
		})
		.hover(function() {
			$('.available li.suggest-hover', obj).removeClass('suggest-hover');
			li.addClass('suggest-hover');
		});

		if (!empty(item.prefix)) {
			li.append($('<span>', {
				'class': 'grey',
				text: item.prefix
			}));
		}

		// highlight matched
		var text = item.name.toLowerCase(),
			search = values.search.toLowerCase(),
			is_new = item.isNew || false,
			start = 0,
			end = 0;

		while (text.indexOf(search, end) > -1) {
			end = text.indexOf(search, end);

			if (end > start) {
				li.append($('<span>', {
					text: item.name.substring(start, end)
				}));
			}

			li.append($('<span>', {
				'class': !is_new ? 'suggest-found' : null,
				text: item.name.substring(end, end + search.length)
			})).toggleClass('suggest-new', is_new);

			end += search.length;
			start = end;
		}

		if (end < item.name.length) {
			li.append($('<span>', {
				text: item.name.substring(end, item.name.length)
			}));
		}

		$('.available ul', obj).append(li);
	}

	function select(id, obj, values, options) {
		if (values.isAjaxLoaded && !values.isWaiting) {
			addSelected(values.available[id], obj, values, options);

			hideAvailable(obj);
			cleanAvailable(obj, values);
			cleanLastSearch(obj);

			if (options.selectedLimit == 0 || $('.selected li', obj).length < options.selectedLimit) {
				$('input[type="text"]', obj).focus();
			}
		}
	}

	function showAvailable(obj, values) {
		var available = $('.available', obj),
			available_paddings = available.outerWidth() - available.width();

		available.css({
			'width': obj.outerWidth() - available_paddings,
			'left': -1
		});

		available.fadeIn(0);
		available.scrollTop(0);

		if (objectLength(values.available) != 0) {
			// remove selected item selected state
			if ($('.selected li.selected', obj).length > 0) {
				$('.selected li.selected', obj).removeClass('selected');
			}

			// pre-select first available
			if ($('li', available).length > 0) {
				if ($('li.suggest-hover', available).length > 0) {
					$('li.suggest-hover', available).removeClass('suggest-hover');
				}
				$('li:first-child', available).addClass('suggest-hover');
			}
		}
	}

	function hideAvailable(obj) {
		$('.available', obj).fadeOut(0);
	}

	function cleanAvailable(obj, values) {
		$('.multiselect-matches', obj).remove();
		$('.available ul', obj).remove();
		values.available = {};
		values.isMoreMatchesFound = false;
	}

	function cleanLastSearch(obj) {
		$('input[type="text"]', obj).data('lastSearch', '').val('');
	}

	function cleanSearchInput(obj) {
		$('input[type="text"]', obj).val('');
	}

	function resizeSelectedText(li, obj) {
		var	li_margins = li.outerWidth(true) - li.width(),
			span = $('span.subfilter-enabled', li),
			span_paddings = span.outerWidth(true) - span.width(),
			max_width = $('.selected ul', obj).width() - li_margins - span_paddings,
			text = $('span:first-child', span);

		if (text.width() > max_width) {
			var t = text.text();
			var l = t.length;

			while (text.width() > max_width && l != 0) {
				text.text(t.substring(0, --l) + '...');
			}
		}
	}

	function resizeAllSelectedTexts(obj, options, values) {
		$('.selected li', obj).each(function() {
			var li = $(this),
				id = li.data('id'),
				span = $('span.subfilter-enabled', li),
				text = $('span:first-child', span),
				t = empty(values.selected[id].prefix)
					? values.selected[id].name
					: values.selected[id].prefix + values.selected[id].name;

			// rewrite previous text to original
			text.text(t);

			resizeSelectedText(li, obj);
		});
	}

	function scrollAvailable(obj) {
		var	selected = $('.available li.suggest-hover', obj),
			available = $('.available', obj);

		if (selected.length > 0) {
			var	available_height = available.height(),
				selected_top = 0,
				selected_height = selected.outerHeight(true);

			if ($('.multiselect-matches', obj)) {
				selected_top += $('.multiselect-matches', obj).outerHeight(true);
			}

			$('.available li', obj).each(function() {
				var item = $(this);
				if (item.hasClass('suggest-hover')) {
					return false;
				}
				selected_top += item.outerHeight(true);
			});

			if (selected_top < available.scrollTop()) {
				var prev = selected.prev();

				available.scrollTop((prev.length == 0) ? 0 : selected_top);
			}
			else if (selected_top + selected_height > available.scrollTop() + available_height) {
				available.scrollTop(selected_top - available_height + selected_height);
			}
		}
		else {
			available.scrollTop(0);
		}
	}

	function setSearchFieldVisibility(visible, container, options) {
		if (visible) {
			container.removeClass('search-disabled')
				.find('input[type="text"]')
				.attr({
					placeholder: options.labels['type here to search'],
					'aria-label': options.labels['type here to search'],
					readonly: false
				});
		}
		else {
			container.addClass('search-disabled')
				.find('input[type="text"]')
				.attr({
					placeholder: '',
					'aria-label': '',
					readonly: true
				});
		}
	}

	function getSearchFieldVisibility(container) {
		return container.not('.search-disabled').length > 0;
	}

	function getLimit(values, options) {
		return (options.limit != 0)
			? options.limit + objectLength(values.selected) + options.excludeids.length + 1
			: null;
	}

	function objectLength(obj) {
		var length = 0;

		for (var key in obj) {
			if (obj.hasOwnProperty(key)) {
				length++;
			}
		}

		return length;
	}
});
