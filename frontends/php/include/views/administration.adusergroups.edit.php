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


$widget = (new CWidget())->setTitle(_('AD groups'));

// create form
$adGroupForm = (new CForm())
	->setName('adGroupsForm')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('form', $data['form']);

if ($data['adusrgrpid'] != 0) {
	$adGroupForm->addVar('adusrgrpid', $data['adusrgrpid']);
}

$adGroupFormList = (new CFormList())
	->addRow(
		(new CLabel(_('AD group name'), 'adgname'))->setAsteriskMark(),
		(new CTextBox('adgname', $data['name']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
			->setAttribute('maxlength', DB::getFieldLength('adusrgrp', 'name'))
	);

$user_groups = [];
foreach ($data['groups'] as $group) {
	$user_groups[] = CArrayHelper::renameKeys($group, ['usrgrpid' => 'id']);
}
$adGroupFormList->addRow(
		(new CLabel(_('User groups'), 'user_groups__ms'))->setAsteriskMark(),
		(new CMultiSelect([
			'name' => 'user_groups[]',
			'object_name' => 'usersGroups',
			'data' => $user_groups,
			'popup' => [
				'parameters' => [
					'srctbl' => 'usrgrp',
					'srcfld1' => 'usrgrpid',
					'dstfrm' => $adGroupForm->getName(),
					'dstfld1' => 'user_groups_'
				]
			]
		]))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	);

$userTypeComboBox = new CComboBox('user_type', $data['user_type'], null, [
	USER_TYPE_ZABBIX_USER => user_type2str(USER_TYPE_ZABBIX_USER),
	USER_TYPE_ZABBIX_ADMIN => user_type2str(USER_TYPE_ZABBIX_ADMIN),
	USER_TYPE_SUPER_ADMIN => user_type2str(USER_TYPE_SUPER_ADMIN)
]);
$adGroupFormList->addRow(_('User type for users in this AD group'), $userTypeComboBox);

// append form lists to tab
$adGroupTab = (new CTabView())
	->addTab('adGroupTab', _('AD group'), $adGroupFormList);
if (!$data['form_refresh']) {
	$adGroupTab->setSelected(0);
}

// append buttons to form
if ($data['adusrgrpid'] != 0) {
	$adGroupTab->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			new CButtonDelete(_('Delete selected AD group?'), url_param('form').url_param('adusrgrpid')),
			new CButtonCancel()
		]
	));
}
else {
	$adGroupTab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel()]
	));
}

// append tab to form
$adGroupForm->addItem($adGroupTab);

$widget->addItem($adGroupForm);

return $widget;
