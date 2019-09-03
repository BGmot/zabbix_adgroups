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

$widget = (new CWidget())
	->setTitle(_('AD groups'))
	->setControls((new CTag('nav', true,
		(new CList())
			->addItem(new CRedirectButton(_('Create AD group'), (new CUrl('adusergrps.php'))
				->setArgument('form', 'create')
				->getUrl()
			))
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem((new CFilter(new CUrl('adusergrps.php')))
		->setProfile($data['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addFilterTab(_('Filter'), [
			(new CFormList())->addRow(_('Name'),
				(new CTextBox('filter_name', $data['filter']['name']))
					->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
					->setAttribute('autofocus', 'autofocus')
			)
		])
	);

// create form
$adGroupsForm = (new CForm())->setName('adGroupsForm');

// create AD group table
$adGroupTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_adgroups'))->onClick("checkAll('".$adGroupsForm->getName()."','all_adgroups','adgroup_groupid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], 'adusergrps.php'),
		_('User groups'),
		_('User type')
	]);

foreach ($this->data['adusergroups'] as $adusrgrp) {
	$adGroupId = $adusrgrp['adusrgrpid'];

	if (isset($adusrgrp['usrgrps'])) {
		$adGroupUserGroups = $adusrgrp['usrgrps'];
		order_result($adGroupUserGroups, 'name');

		$userGroups = [];
		$i = 0;

		foreach ($adGroupUserGroups as $usergroup) {
			$i++;

			if ($i > $this->data['config']['max_in_table']) {
				$userGroups[] = ' &hellip;';

				break;
			}

			if ($userGroups) {
				$userGroups[] = ', ';
			}

			$userGroups[] = (new CLink($usergroup['name'], 'usergrps.php?form=update&usrgrpid='.$usergroup['usrgrpid']))
				->addClass($usergroup['gui_access'] == GROUP_GUI_ACCESS_DISABLED
					|| $usergroup['users_status'] == GROUP_STATUS_DISABLED
					? ZBX_STYLE_LINK_ALT . ' ' . ZBX_STYLE_RED
					: ZBX_STYLE_LINK_ALT . ' ' . ZBX_STYLE_GREEN);
		}
	}

	$name = new CLink($adusrgrp['name'], 'adusergrps.php?form=update&adusrgrpid='.$adGroupId);

	$adGroupTable->addRow([
		new CCheckBox('adgroup_groupid['.$adGroupId.']', $adGroupId),
		(new CCol($name))->addClass(ZBX_STYLE_NOWRAP),
		$userGroups,
		user_type2str($adusrgrp['user_type']),
	]);
}

// append table to form
$adGroupsForm->addItem([
	$adGroupTable,
	$this->data['paging'],
	new CActionButtonList('action', 'adgroup_groupid', [
		'adusergroup.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected AD groups?')]
	])
]);

// append form to widget
$widget->addItem($adGroupsForm);

return $widget;
