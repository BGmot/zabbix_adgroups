<?php
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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/actions.inc.php';
require_once dirname(__FILE__).'/include/events.inc.php';
require_once dirname(__FILE__).'/include/triggers.inc.php';
require_once dirname(__FILE__).'/include/users.inc.php';
require_once dirname(__FILE__).'/include/html.inc.php';

$page['title'] = _('Event details');
$page['file'] = 'tr_events.php';
$page['type'] = detect_page_type();
$page['scripts'] = ['layout.mode.js'];

CView::$has_web_layout_mode = true;
$page['web_layout_mode'] = CView::getLayoutMode();

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'triggerid' =>	[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		PAGE_TYPE_HTML.'=='.$page['type']],
	'eventid' =>	[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		PAGE_TYPE_HTML.'=='.$page['type']],
	// Ajax
	'widget' =>		[T_ZBX_STR, O_OPT, P_ACT,	IN('"'.WIDGET_HAT_EVENTACTIONS.'","'.WIDGET_HAT_EVENTLIST.'"'), null],
	'state' =>		[T_ZBX_INT, O_OPT, P_ACT,	IN('0,1'), null]
];
check_fields($fields);

/*
 * Ajax
 */
if (hasRequest('widget') && hasRequest('state')) {
	CProfile::update('web.tr_events.hats.'.getRequest('widget').'.state', getRequest('state'), PROFILE_TYPE_INT);
}

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

// triggers
$triggers = API::Trigger()->get([
	'output' => API_OUTPUT_EXTEND,
	'selectHosts' => API_OUTPUT_EXTEND,
	'triggerids' => getRequest('triggerid')
]);

if (!$triggers) {
	access_deny();
}

$trigger = reset($triggers);

$events = API::Event()->get([
	'output' => ['eventid', 'r_eventid', 'clock', 'ns', 'objectid', 'name', 'acknowledged', 'severity'],
	'selectTags' => ['tag', 'value'],
	'select_acknowledges' => ['clock', 'message', 'action', 'userid', 'old_severity', 'new_severity'],
	'source' => EVENT_SOURCE_TRIGGERS,
	'object' => EVENT_OBJECT_TRIGGER,
	'eventids' => getRequest('eventid'),
	'objectids' => getRequest('triggerid'),
	'value' => TRIGGER_VALUE_TRUE
]);

if (!$events) {
	access_deny();
}
$event = reset($events);

if ($event['r_eventid'] != 0) {
	$r_events = API::Event()->get([
		'output' => ['correlationid', 'userid'],
		'source' => EVENT_SOURCE_TRIGGERS,
		'object' => EVENT_OBJECT_TRIGGER,
		'eventids' => [$event['r_eventid']],
		'objectids' => getRequest('triggerid')
	]);

	if ($r_events) {
		$r_event = reset($r_events);

		$event['correlationid'] = $r_event['correlationid'];
		$event['userid'] = $r_event['userid'];
	}
}

$config = select_config();
$severity_config = [
	'severity_name_0' => $config['severity_name_0'],
	'severity_name_1' => $config['severity_name_1'],
	'severity_name_2' => $config['severity_name_2'],
	'severity_name_3' => $config['severity_name_3'],
	'severity_name_4' => $config['severity_name_4'],
	'severity_name_5' => $config['severity_name_5']
];
$actions = getEventDetailsActions($event);
$users = API::User()->get([
	'output' => ['alias', 'name', 'surname'],
	'userids' => array_keys($actions['userids']),
	'preservekeys' => true
]);
$mediatypes = API::Mediatype()->get([
	'output' => ['maxattempts'],
	'mediatypeids' => array_keys($actions['mediatypeids']),
	'preservekeys' => true
]);

/*
 * Display
 */
$eventTab = (new CTable())
	->addRow([
		new CDiv([
			(new CUiWidget(WIDGET_HAT_TRIGGERDETAILS, make_trigger_details($trigger)))->setHeader(_('Trigger details')),
			(new CUiWidget(WIDGET_HAT_EVENTDETAILS,
				make_event_details($event,
					$page['file'].'?triggerid='.getRequest('triggerid').'&eventid='.getRequest('eventid')
				)
			))->setHeader(_('Event details'))
		]),
		new CDiv([
			(new CCollapsibleUiWidget(WIDGET_HAT_EVENTACTIONS,
				makeEventDetailsActionsTable($actions, $users, $mediatypes, $severity_config)
			))
				->setExpanded((bool) CProfile::get('web.tr_events.hats.'.WIDGET_HAT_EVENTACTIONS.'.state', true))
				->setHeader(_('Actions'), [], 'web.tr_events.hats.'.WIDGET_HAT_EVENTACTIONS.'.state'),
			(new CCollapsibleUiWidget(WIDGET_HAT_EVENTLIST,
				make_small_eventlist($event,
					$page['file'].'?triggerid='.getRequest('triggerid').'&eventid='.getRequest('eventid')
				)
			))
				->setExpanded((bool) CProfile::get('web.tr_events.hats.'.WIDGET_HAT_EVENTLIST.'.state', true))
				->setHeader(_('Event list [previous 20]'), [], 'web.tr_events.hats.'.WIDGET_HAT_EVENTLIST.'.state')
		])
	]);

$eventWidget = (new CWidget())
	->setTitle(_('Event details'))
	->setWebLayoutMode($page['web_layout_mode'])
	->setControls((new CTag('nav', true,
		(new CList())
			->addItem(get_icon('fullscreen'))
		))
		->setAttribute('aria-label', _('Content controls'))
	)
	->addItem($eventTab)
	->show();

require_once dirname(__FILE__).'/include/page_footer.php';
