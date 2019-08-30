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
//require_once dirname(__FILE__).'/include/triggers.inc.php';
//require_once dirname(__FILE__).'/include/media.inc.php';
//require_once dirname(__FILE__).'/include/users.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';
require_once dirname(__FILE__).'/include/js.inc.php';

$page['title'] = _('Configuration of AD user groups');
$page['file'] = 'adusergrps.php';
$page['scripts'] = ['multiselect.js'];

require_once dirname(__FILE__).'/include/page_header.php';

//	VAR		TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = [
	// group
	'adusrgrpid' =>				[T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		'isset({form}) && {form} == "update"'],
	'adgname' =>				[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	'isset({add}) || isset({update})', _('AD Group name')],
	'user_groups' =>			[T_ZBX_STR, O_OPT, null,	NOT_EMPTY,	null],
	'user_type' =>				[T_ZBX_INT, O_OPT, null,	IN('1,2,3'),	'isset({add}) || isset({update})'],
	// actions
	'action' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT,
							IN('"adusergroup.massdelete"'),
							null
						],
	'add' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'update' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'delete' =>				[T_ZBX_STR, O_OPT, P_SYS|P_ACT, null,	null],
	'groupids' =>				[T_ZBX_STR, O_OPT, null,	null,	null],
	'tag_filters' =>			[T_ZBX_STR, O_OPT, null,	null,	null],
	// form
	'form' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,	null],
	'form_refresh' =>			[T_ZBX_INT, O_OPT, null,	null,	null],
	// filter
	'filter_set' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,	null],
	'filter_rst' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,	null],
	'filter_name' =>			[T_ZBX_STR, O_OPT, null,	null,	null],
	// sort and sortorder
	'sort' =>				[T_ZBX_STR, O_OPT, P_SYS, IN('"name"'),					null],
	'sortorder' =>				[T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null]
];
check_fields($fields);

$form = getRequest('form');

/*
 * Permissions
 */
if (hasRequest('adusrgrpid')) {
	$db_aduser_group = API::AdUserGroup()->get([
		'adusrgrpid' => getRequest('adusrgrpid'),
		'output' => API_OUTPUT_EXTEND,
		'editable' => true
	]);

	if (!$db_aduser_group) {
		access_deny();
	}
}
elseif (hasRequest('action')) {
	if (!hasRequest('adgroup_groupid') || !is_array(getRequest('adgroup_groupid'))) {
		access_deny();
	}
	else {
		$group_users = API::UserGroup()->get([
			'output' => [],
			'usrgrpids' => getRequest('group_groupid')
		]);

		if (count($group_users) != count(getRequest('group_groupid'))) {
			uncheckTableRows(null, zbx_objectValues($group_users, 'usrgrpid'));
		}
	}
}

/*
 * Actions
 */
if (hasRequest('add') || hasRequest('update')) {
	$usrgrps = getRequest('user_groups', []);
	$aduser_group = [
		'name' => getRequest('adgname'),
		'usrgrps' => zbx_toObject($usrgrps, 'usrgrpid'),
		'user_type' => getRequest('user_type')
	];

	if (hasRequest('add')) {
		$result = (bool) API::AdUserGroup()->create($aduser_group);
		show_messages($result, _('AD group added'), _('Cannot add AD group'));
	}
	else {
		$aduser_group['adusrgrpid'] = getRequest('adusrgrpid');
		$result = (bool) API::AdUserGroup()->update($aduser_group);
		show_messages($result, _('AD group updated'), _('Cannot update AD group'));
	}

	if ($result) {
		uncheckTableRows();
		$form = null;
	}
}
elseif (hasRequest('delete')) {
	$result = (bool) API::UserGroup()->delete([getRequest('usrgrpid')]);
	show_messages($result, _('Group deleted'), _('Cannot delete group'));

	if ($result) {
		uncheckTableRows();
		$form = null;
	}
}
elseif (hasRequest('action')) {
	$action = getRequest('action');
	$result = false;

	switch ($action) {
		case 'usergroup.massdelete':
			if (hasRequest('group_groupid')) {
				$result = (bool) API::UserGroup()->delete(getRequest('group_groupid'));
				show_messages($result, _('Group deleted'), _('Cannot delete group'));
			}
			break;

		case 'usergroup.set_gui_access':
			$user_groups = [];

			foreach ((array) getRequest('group_groupid', getRequest('usrgrpid')) as $usrgrpid) {
				$user_groups[] = [
					'usrgrpid' => $usrgrpid,
					'gui_access' => getRequest('set_gui_access')
				];
			}

			$result = (bool) API::UserGroup()->update($user_groups);
			show_messages($result, _('Frontend access updated'), _('Cannot update frontend access'));
			break;

		case 'usergroup.massenabledebug':
		case 'usergroup.massdisabledebug':
			$user_groups = [];

			foreach ((array) getRequest('group_groupid', getRequest('usrgrpid')) as $usrgrpid) {
				$user_groups[] = [
					'usrgrpid' => $usrgrpid,
					'debug_mode' => ($action === 'usergroup.massenabledebug')
						? GROUP_DEBUG_MODE_ENABLED
						: GROUP_DEBUG_MODE_DISABLED
				];
			}

			$result = (bool) API::UserGroup()->update($user_groups);
			show_messages($result, _('Debug mode updated'), _('Cannot update debug mode'));
			break;

		case 'usergroup.massenable':
		case 'usergroup.massdisable':
			$user_groups = [];

			foreach ((array) getRequest('group_groupid', getRequest('usrgrpid')) as $usrgrpid) {
				$user_groups[] = [
					'usrgrpid' => $usrgrpid,
					'users_status' => ($action === 'usergroup.massenable')
						? GROUP_STATUS_ENABLED
						: GROUP_STATUS_DISABLED
				];
			}

			$result = (bool) API::UserGroup()->update($user_groups);
			$message_success = ($action === 'usergroup.massenable')
				? _n('User group enabled', 'User groups enabled', count($user_groups))
				: _n('User group disabled', 'User groups disabled', count($user_groups));
			$message_failed = ($action === 'usergroup.massenable')
				? _n('Cannot enable user group', 'Cannot enable user groups', count($user_groups))
				: _n('Cannot disable user group', 'Cannot disable user groups', count($user_groups));
			show_messages($result, $message_success, $message_failed);
			break;
	}

	if ($result) {
		uncheckTableRows();
	}
}

/*
 * Display
 */
if ($form !== null) {
	$data = [
		'adusrgrpid' => getRequest('adusrgrpid', 0),
		'form' => $form,
		'name' => getRequest('adgname', ''),
                'usrgrps' => [],
		'user_type' => 1,
		'form_refresh' => getRequest('form_refresh', 0),
		'value' => getRequest('value', '')
	];

	// render view
	$view = new CView('administration.adusergroups.edit', $data);
}
else {
	$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
	$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

	CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
	CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

	// filter
	if (hasRequest('filter_set')) {
		CProfile::update('web.adusergroup.filter_name', getRequest('filter_name', ''), PROFILE_TYPE_STR);
		CProfile::update('web.adusergroup.filter_users_status', getRequest('filter_users_status', -1), PROFILE_TYPE_INT);
	}
	elseif (hasRequest('filter_rst')) {
		CProfile::delete('web.adusergroup.filter_name');
		CProfile::delete('web.adusergroup.filter_users_status');
	}

	$filter = [
		'name' => CProfile::get('web.adusergroup.filter_name', '')
	];

	$config = select_config();

	$data = [
		'sort' => $sortField,
		'sortorder' => $sortOrder,
		'filter' => $filter,
		'config' => $config,
		'profileIdx' => 'web.adusergroup.filter',
		'active_tab' => CProfile::get('web.adusergroup.filter.active', 1),
		'adusergroups' => API::AdUserGroup()->get([
			'output' => API_OUTPUT_EXTEND,
			'selectGroups' => API_OUTPUT_EXTEND,
			'search' => ['name' => ($filter['name'] !== '') ? $filter['name'] : null],
			'sortfield' => $sortField,
			'limit' => $config['search_limit'] + 1
		])
	];

	// sorting & paging
	order_result($data['adusergroups'], $sortField, $sortOrder);
	$data['paging'] = getPagingLine($data['adusergroups'], $sortOrder, new CUrl('adusergrps.php'));

	// render view
	$view = new CView('administration.adusergroups.list', $data);
}

$view->render();
$view->show();

require_once dirname(__FILE__).'/include/page_footer.php';
