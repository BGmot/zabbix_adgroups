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


include('include/views/js/administration.users.edit.js.php');

if ($data['is_profile']) {
	$userWidget = ($data['name'] !== '' || $data['surname'] !== '')
		? (new CWidget())->setTitle(_('User profile').NAME_DELIMITER.$data['name'].' '.$data['surname'])
		: (new CWidget())->setTitle(_('User profile').NAME_DELIMITER.$data['alias']);
}
else {
	$userWidget = (new CWidget())->setTitle(_('Users'));
}

// create form
$userForm = (new CForm())
	->setName('userForm')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('form', $data['form']);

if ($data['userid'] != 0) {
	$userForm->addVar('userid', $data['userid']);
}

/*
 * User tab
 */
$userFormList = new CFormList('userFormList');
$form_autofocus = false;

if (!$data['is_profile']) {
	$userFormList->addRow(
		(new CLabel(_('Alias'), 'alias'))->setAsteriskMark(),
		(new CTextBox('alias', $data['alias']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
			->setAttribute('maxlength', DB::getFieldLength('users', 'alias'))
	);
	$form_autofocus = true;
	$userFormList->addRow(_x('Name', 'user first name'),
		(new CTextBox('name', $data['name']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('maxlength', DB::getFieldLength('users', 'name'))
	);
	$userFormList->addRow(_('Surname'),
		(new CTextBox('surname', $data['surname']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('maxlength', DB::getFieldLength('users', 'surname'))
	);
}

// append user groups to form list
if (!$data['is_profile']) {
	$user_groups = [];

	foreach ($data['groups'] as $group) {
		$user_groups[] = CArrayHelper::renameKeys($group, ['usrgrpid' => 'id']);
	}

	$userFormList->addRow(
		(new CLabel(_('Groups'), 'user_groups__ms'))->setAsteriskMark(),
		(new CMultiSelect([
			'name' => 'user_groups[]',
			'object_name' => 'usersGroups',
			'data' => $user_groups,
			'popup' => [
				'parameters' => [
					'srctbl' => 'usrgrp',
					'srcfld1' => 'usrgrpid',
					'dstfrm' => $userForm->getName(),
					'dstfld1' => 'user_groups_'
				]
			]
		]))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	);
}

// append password to form list
if ($data['userid'] == 0 || $data['change_password']) {
	$userForm->disablePasswordAutofill();
	$password_box = new CPassBox('password1', $data['password1']);

	if (!$form_autofocus) {
		$form_autofocus = true;
		$password_box->setAttribute('autofocus', 'autofocus');
	}

	$userFormList->addRow(
		(new CLabel(_('Password'), 'password1'))->setAsteriskMark(),
		$password_box
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
	);
	$userFormList->addRow(
		(new CLabel(_('Password (once again)'), 'password2'))->setAsteriskMark(),
		(new CPassBox('password2', $data['password2']))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
	);

	if ($data['change_password']) {
		$userForm->addVar('change_password', $data['change_password']);
	}

	$userFormList->addRow('', _('Password is not mandatory for non internal authentication type.'));
}
else {
	$passwdButton = (new CSimpleButton(_('Change password')))
		->onClick('javascript: submitFormWithParam("'.$userForm->getName().'", "change_password", "1");')
		->addClass(ZBX_STYLE_BTN_GREY);
	if ($data['alias'] == ZBX_GUEST_USER) {
		$passwdButton->setAttribute('disabled', 'disabled');
	}

	if (!$form_autofocus) {
		$form_autofocus = true;
		$passwdButton->setAttribute('autofocus', 'autofocus');
	}

	$userFormList->addRow(_('Password'), $passwdButton);
}

// append languages to form list
$languageComboBox = new CComboBox('lang', $data['lang']);

$allLocalesAvailable = true;
foreach (getLocales() as $localeId => $locale) {
	if ($locale['display']) {
		// checking if this locale exists in the system. The only way of doing it is to try and set one
		// trying to set only the LC_MONETARY locale to avoid changing LC_NUMERIC
		$localeExists = ($localeId === 'en_GB' || setlocale(LC_MONETARY , zbx_locale_variants($localeId)));

		$languageComboBox->addItem(
			$localeId,
			$locale['name'],
			($localeId == $data['lang']) ? true : null,
			$localeExists
		);

		$allLocalesAvailable &= $localeExists;
	}
}

// restoring original locale
setlocale(LC_MONETARY, zbx_locale_variants(CWebUser::$data['lang']));

$languageError = '';
if (!function_exists('bindtextdomain')) {
	$languageError = 'Translations are unavailable because the PHP gettext module is missing.';
	$languageComboBox->setAttribute('disabled', 'disabled');
}
elseif (!$allLocalesAvailable) {
	$languageError = _('You are not able to choose some of the languages, because locales for them are not installed on the web server.');
}

if (!$form_autofocus && $languageComboBox->getAttribute('disabled') === null) {
	$languageComboBox->setAttribute('autofocus', 'autofocus');
	$form_autofocus = true;
}

$userFormList->addRow(
	_('Language'),
	$languageError
		? [$languageComboBox, SPACE, (new CSpan($languageError))->addClass('red')->addClass('wrap')]
		: $languageComboBox
);

// append themes to form list
$themes = array_merge([THEME_DEFAULT => _('System default')], Z::getThemes());
$themes_combobox = new CComboBox('theme', $data['theme'], null, $themes);

if (!$form_autofocus) {
	$themes_combobox->setAttribute('autofocus', 'autofocus');
	$form_autofocus = true;
}

$userFormList->addRow(_('Theme'), $themes_combobox);

// append auto-login & auto-logout to form list
$autologoutCheckBox = (new CCheckBox('autologout_visible'))->setChecked($data['autologout_visible']);
if ($data['autologout_visible']) {
	$autologoutTextBox = (new CTextBox('autologout', $data['autologout']))->setWidth(ZBX_TEXTAREA_TINY_WIDTH);
}
else {
	$autologoutTextBox = (new CTextBox('autologout', DB::getDefault('users', 'autologout')))
		->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
		->setAttribute('disabled', 'disabled');
}

if ($data['alias'] != ZBX_GUEST_USER) {
	$userFormList->addRow(_('Auto-login'), (new CCheckBox('autologin'))->setChecked($data['autologin']));
	$userFormList->addRow(_('Auto-logout'), [
		$autologoutCheckBox,
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		$autologoutTextBox
	]);
}

$userFormList
	->addRow((new CLabel(_('Refresh'), 'refresh'))->setAsteriskMark(),
		(new CTextBox('refresh', $data['refresh']))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
	)
	->addRow((new CLabel(_('Rows per page'), 'rows_per_page'))->setAsteriskMark(),
		(new CNumericBox('rows_per_page', $data['rows_per_page'], 6))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setAriaRequired()
	)
	->addRow(_('URL (after login)'),
		(new CTextBox('url', $data['url']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

/*
 * Media tab
 */
if (uint_in_array(CWebUser::$data['type'], [USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN])) {
	$userMediaFormList = new CFormList('userMediaFormList');
	$userForm->addVar('user_medias', $data['user_medias']);

	$mediaTableInfo = (new CTable())
		->setAttribute('style', 'width: 100%;')
		->setHeader([_('Type'), _('Send to'), _('When active'), _('Use if severity'), ('Status'), _('Action')]);

	foreach ($data['user_medias'] as $id => $media) {
		if (!array_key_exists('active', $media) || !$media['active']) {
			$status = (new CLink(_('Enabled'), '#'))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_GREEN)
				->onClick('return create_var("'.$userForm->getName().'","disable_media",'.$id.', true);');
		}
		else {
			$status = (new CLink(_('Disabled'), '#'))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->addClass(ZBX_STYLE_RED)
				->onClick('return create_var("'.$userForm->getName().'","enable_media",'.$id.', true);');
		}

		$popup_options = [
			'dstfrm' => $userForm->getName(),
			'media' => $id,
			'mediatypeid' => $media['mediatypeid'],
			'period' => $media['period'],
			'severity' => $media['severity'],
			'active' => $media['active']
		];

		if ($media['mediatype'] == MEDIA_TYPE_EMAIL) {
			foreach ($media['sendto'] as $email) {
				$popup_options['sendto_emails'][] = $email;
			}
		}
		else {
			$popup_options['sendto'] = $media['sendto'];
		}

		$mediaSeverity = [];

		for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$severityName = getSeverityName($severity, $data['config']);
			$severity_status_style = getSeverityStatusStyle($severity);

			$mediaActive = ($media['severity'] & (1 << $severity));

			$mediaSeverity[$severity] = (new CSpan(mb_substr($severityName, 0, 1)))
				->setHint($severityName.' ('.($mediaActive ? _('on') : _('off')).')', '', false)
				->addClass($mediaActive ? $severity_status_style : ZBX_STYLE_STATUS_DISABLED_BG);
		}

		if ($media['mediatype'] == MEDIA_TYPE_EMAIL) {
			$media['sendto'] = implode(', ', $media['sendto']);
		}

		if (mb_strlen($media['sendto']) > 50) {
			$media['sendto'] = (new CSpan(mb_substr($media['sendto'], 0, 50).'...'))->setHint($media['sendto']);
		}

		$mediaTableInfo->addRow(
			(new CRow([
				$media['description'],
				$media['sendto'],
				(new CDiv($media['period']))
					->setAttribute('style', 'max-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
					->addClass(ZBX_STYLE_OVERFLOW_ELLIPSIS),
				(new CDiv($mediaSeverity))->addClass(ZBX_STYLE_STATUS_CONTAINER),
				$status,
				(new CCol(
					new CHorList([
						(new CButton(null, _('Edit')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->onClick('return PopUp("popup.media",'.CJs::encodeJson($popup_options).', null, this);'),
						(new CButton(null, _('Remove')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->onClick('javascript: removeMedia('.$id.');')
					])
				))->addClass(ZBX_STYLE_NOWRAP)
			]))->setId('user_medias_'.$id)
		);
	}

	$userMediaFormList->addRow(_('Media'),
		(new CDiv([
			$mediaTableInfo,
			(new CButton(null, _('Add')))
				->onClick('return PopUp("popup.media",'.
					CJs::encodeJson([
						'dstfrm' => $userForm->getName()
					]).', null, this);'
				)
				->addClass(ZBX_STYLE_BTN_LINK),
		]))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);
}
else {
	$userMediaFormList = null;
}

/*
 * Profile fields
 */
if ($data['is_profile']) {
	$zbxSounds = getSounds();

	$userMessagingFormList = new CFormList();
	$userMessagingFormList->addRow(_('Frontend messaging'),
		(new CCheckBox('messages[enabled]'))
			->setChecked($data['messages']['enabled'] == 1)
			->setUncheckedValue(0)
	);
	$userMessagingFormList->addRow(_('Message timeout'),
		(new CTextBox('messages[timeout]', $data['messages']['timeout']))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH),
		'timeout_row'
	);

	$repeatSound = new CComboBox('messages[sounds.repeat]', $data['messages']['sounds.repeat'],
		'if (IE) { submit() }',
		[
			1 => _('Once'),
			10 => '10 '._('Seconds'),
			-1 => _('Message timeout')
		]
	);
	$userMessagingFormList->addRow(_('Play sound'), $repeatSound, 'repeat_row');

	$soundList = new CComboBox('messages[sounds.recovery]', $data['messages']['sounds.recovery']);
	foreach ($zbxSounds as $filename => $file) {
		$soundList->addItem($file, $filename);
	}

	$triggersTable = (new CTable())
		->addRow([
			(new CCheckBox('messages[triggers.recovery]'))
				->setLabel(_('Recovery'))
				->setChecked($data['messages']['triggers.recovery'] == 1)
				->setUncheckedValue(0),
			[
				$soundList,
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CButton('start', _('Play')))
					->addClass(ZBX_STYLE_BTN_GREY)
					->onClick("javascript: testUserSound('messages_sounds.recovery');")
					->removeId(),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CButton('stop', _('Stop')))
					->addClass(ZBX_STYLE_BTN_GREY)
					->onClick('javascript: AudioControl.stop();')
					->removeId()
			]
		]);

	$msgVisibility = ['1' => [
		'messages[timeout]',
		'messages[sounds.repeat]',
		'messages[sounds.recovery]',
		'messages[triggers.recovery]',
		'timeout_row',
		'repeat_row',
		'triggers_row'
	]];

	// trigger sounds
	for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
		$soundList = new CComboBox('messages[sounds.'.$severity.']', $data['messages']['sounds.'.$severity]);
		foreach ($zbxSounds as $filename => $file) {
			$soundList->addItem($file, $filename);
		}

		$triggersTable->addRow([
			(new CCheckBox('messages[triggers.severities]['.$severity.']'))
				->setLabel(getSeverityName($severity, $data['config']))
				->setChecked(array_key_exists($severity, $data['messages']['triggers.severities']))
				->setUncheckedValue(0),
			[
				$soundList,
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CButton('start', _('Play')))
					->addClass(ZBX_STYLE_BTN_GREY)
					->onClick("javascript: testUserSound('messages_sounds.".$severity."');")
					->removeId(),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CButton('stop', _('Stop')))
					->addClass(ZBX_STYLE_BTN_GREY)
					->onClick('javascript: AudioControl.stop();')
					->removeId()
			]
		]);

		zbx_subarray_push($msgVisibility, 1, 'messages[triggers.severities]['.$severity.']');
		zbx_subarray_push($msgVisibility, 1, 'messages[sounds.'.$severity.']');
	}

	$userMessagingFormList
		->addRow(_('Trigger severity'), $triggersTable, 'triggers_row')
		->addRow(_('Show suppressed problems'),
			(new CCheckBox('messages[show_suppressed]'))
				->setChecked($data['messages']['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE)
				->setUncheckedValue(ZBX_PROBLEM_SUPPRESSED_FALSE)
		);
}
else {
	$userMessagingFormList = null;
}

// append form lists to tab
$userTab = new CTabView();
if (!$data['form_refresh']) {
	$userTab->setSelected(0);
}
$userTab->addTab('userTab', _('User'), $userFormList);
if ($userMediaFormList) {
	$userTab->addTab('mediaTab', _('Media'), $userMediaFormList);
}

// Permissions tab.
if (!$data['is_profile']) {
	$permissionsFormList = new CFormList('permissionsFormList');

	$userTypeComboBox = new CComboBox('user_type', $data['user_type'], 'submit();', [
		USER_TYPE_ZABBIX_USER => user_type2str(USER_TYPE_ZABBIX_USER),
		USER_TYPE_ZABBIX_ADMIN => user_type2str(USER_TYPE_ZABBIX_ADMIN),
		USER_TYPE_SUPER_ADMIN => user_type2str(USER_TYPE_SUPER_ADMIN)
	]);

	if ($data['userid'] != 0 && bccomp(CWebUser::$data['userid'], $data['userid']) == 0) {
		$userTypeComboBox->setEnabled(false);
		$permissionsFormList->addRow(_('User type'), [$userTypeComboBox, SPACE, new CSpan(_('User can\'t change type for himself'))]);
		$userForm->addItem((new CVar('user_type', $data['user_type']))->removeId());
	}
	else {
		$permissionsFormList->addRow(_('User type'), $userTypeComboBox);
	}

	$permissions_table = (new CTable())
		->setAttribute('style', 'width: 100%;')
		->setHeader([_('Host group'), _('Permissions')]);

	if ($data['user_type'] == USER_TYPE_SUPER_ADMIN) {
		$permissions_table->addRow([italic(_('All groups')), permissionText(PERM_READ_WRITE)]);
	}
	else {
		foreach ($data['groups_rights'] as $groupid => $group_rights) {
			if (array_key_exists('grouped', $group_rights) && $group_rights['grouped']) {
				$group_name = ($groupid == 0)
					? italic(_('All groups'))
					: [$group_rights['name'], SPACE, italic('('._('including subgroups').')')];
			}
			else {
				$group_name = $group_rights['name'];
			}
			$permissions_table->addRow([$group_name, permissionText($group_rights['permission'])]);
		}
	}

	$permissionsFormList
		->addRow(_('Permissions'),
			(new CDiv($permissions_table))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		)
		->addInfo(_('Permissions can be assigned for user groups only.'));

	$userTab->addTab('permissionsTab', _('Permissions'), $permissionsFormList);
}

if ($userMessagingFormList) {
	$userTab->addTab('messagingTab', _('Messaging'), $userMessagingFormList);
}

// append buttons to form
if ($data['userid'] != 0) {
	$buttons = [
		new CButtonCancel()
	];

	if (!$data['is_profile']) {
		$deleteButton = new CButtonDelete(_('Delete selected user?'), url_param('form').url_param('userid'));
		if (bccomp(CWebUser::$data['userid'], $data['userid']) == 0) {
			$deleteButton->setAttribute('disabled', 'disabled');
		}

		array_unshift($buttons, $deleteButton);
	}

	$userTab->setFooter(makeFormFooter(new CSubmit('update', _('Update')), $buttons));
}
else {
	$userTab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel()]
	));
}

// append tab to form
$userForm->addItem($userTab);

// append form to widget
$userWidget->addItem($userForm);

return $userWidget;
