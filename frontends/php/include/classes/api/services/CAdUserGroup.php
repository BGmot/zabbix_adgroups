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


/**
 * Class containing methods for operations with user groups.
 */
class CAdUserGroup extends CApiService {

	protected $tableName = 'adusrgrp';
	protected $tableAlias = 'ag';
	protected $sortColumns = ['adusrgrpid', 'name'];

	/**
	 * Get AD groups.
	 *
	 * @param array  $options
	 * @param array  $options['adusrgrpids']
	 * @param array  $options['usrgrpids']
	 * @param int    $options['count']
	 * @param string $options['pattern']
	 * @param int    $options['limit']
	 * @param string $options['order']
	 *
	 * @return array
	 */
	public function get($options = []) {
		$result = [];

		$sqlParts = [
			'select'	=> ['adusrgrp' => 'ag.adusrgrpid'],
			'from'		=> ['adusrgrp' => 'adusrgrp ag'],
			'where'		=> [],
			'order'		=> [],
			'limit'		=> null
		];

		$defOptions = [
			'adusrgrpids'				=> null,
			'usrgrpids'				=> null,
			// filter
			'filter'				=> null,
			'search'				=> null,
			'searchByAny'				=> null,
			'startSearch'				=> false,
			'excludeSearch'				=> false,
			'searchWildcardsEnabled'		=> null,
			// output
			'editable'				=> false,
			'output'				=> API_OUTPUT_EXTEND,
			'selectGroups'				=> null,
			'countOutput'				=> false,
			'preservekeys'				=> false,
			'sortfield'				=> '',
			'sortorder'				=> '',
			'limit'					=> null
		];

		$options = zbx_array_merge($defOptions, $options);

		// permissions
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			if (!$options['editable']) {
				$sqlParts['where'][] = 'ag.usrgrpid IN ('.
					'SELECT adgg.usrgrpid'.
					' FROM adgroups_groups adgg'.
					' WHERE adgg.adusrgrpid='.self::$userData['adusrgrpid'].
				')';
			}
			else {
				return [];
			}
		}

		// adusrgrpids
		if (!is_null($options['adusrgrpids'])) {
			zbx_value2array($options['adusrgrpids']);

			$sqlParts['where'][] = dbConditionInt('ag.adusrgrpid', $options['adusrgrpids']);
		}

		// usrgrpids
		if (!is_null($options['usrgrpids'])) {
			zbx_value2array($options['usrgrpids']);

			$sqlParts['from']['adgroups_groups'] = 'adgroups_groups agg';
			$sqlParts['where'][] = dbConditionInt('agg.usrgrpid', $options['usrgrpids']);
			$sqlParts['where']['agug'] = 'agg.adusrgrpid=ag.adusrgrpid';
		}

		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('adusrgrp ag', $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('adusrgrp ag', $options, $sqlParts);
		}

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($adusrgrp = DBfetch($res)) {
			if ($options['countOutput']) {
				$result = $adusrgrp['rowscount'];
			}
			else {
				$result[$adusrgrp['adusrgrpid']] = $adusrgrp;
			}
		}

		if ($options['countOutput']) {
			return $result;
		}

		// adding user groups
		if ($options['selectGroups'] !== null && $options['selectGroups'] != API_OUTPUT_COUNT) {
			$relationMap = $this->createRelationMap($result, 'adusrgrpid', 'usrgrpid', 'adgroups_groups');

			$dbUserGroups = API::UserGroup()->get([
				'output' => $options['selectGroups'],
				'usrgrpids' => $relationMap->getRelatedIds(),
				'preservekeys' => true
			]);

			$result = $relationMap->mapMany($result, $dbUserGroups, 'usrgrps');
		}

		// removing keys (hash -> array)
		if (!$options['preservekeys']) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}

	/**
	 * @param array  $adusrgrps
	 *
	 * @return array
	 */
	public function create(array $adusrgrps) {
		$this->validateCreate($adusrgrps);

		$ins_adusrgrps = [];

		foreach ($adusrgrps as $adusrgrp) {
			unset($adusrgrp['usgrpids']);
			$ins_adusrgrps[] = $adusrgrp;
		}
		$adusrgrpids = DB::insert('adusrgrp', $ins_adusrgrps);

		foreach ($adusrgrps as $index => &$adusrgrp) {
			$adusrgrp['adusrgrpid'] = $adusrgrpids[$index];
		}
		unset($adusrgrp);

		$this->updateAdGroupsGroups($adusrgrps, __FUNCTION__);

		$this->addAuditBulk(AUDIT_ACTION_ADD, AUDIT_RESOURCE_AD_GROUP, $adusrgrps);

		return ['adusrgrpids' => $adusrgrpids];
	}

	/**
	 * @param array $adusrgrps
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateCreate(array &$adusrgrps) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can create AD groups.'));
		}

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'name' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('adusrgrp', 'name')],
			'user_type' =>		['type' => API_INT32, 'in' => implode(',', [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN])],
			'usrgrps' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['usrgrpid']], 'fields' => [
			'usrgrpid' =>		['type' => API_ID, 'flags' => API_REQUIRED]
			]]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $adusrgrps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$this->checkDuplicates(zbx_objectValues($adusrgrps, 'name'));
		$this->checkUserGroups($adusrgrps);
	}

	/**
	 * @param array  $adusrgrps
	 *
	 * @return array
	 */
	public function update($adusrgrps) {
		$this->validateUpdate($adusrgrps, $db_adusrgrps);

		$upd_adusrgrps = [];

		foreach ($adusrgrps as $adusrgrp) {
		
			$db_adusrgrp = $db_adusrgrps[$adusrgrp['adusrgrpid']];

			$upd_adusrgrp = [];

			if (array_key_exists('name', $adusrgrp) && $adusrgrp['name'] !== $db_adusrgrp['name']) {
				$upd_adusrgrp['name'] = $adusrgrp['name'];
			}

			if (array_key_exists('user_type', $adusrgrp) && $adusrgrp['user_type'] !== $db_adusrgrp['user_type']) {
				$upd_adusrgrp['user_type'] = $adusrgrp['user_type'];
			}

			if ($upd_adusrgrp) {
				$upd_adusrgrps[] = [
					'values' => $upd_adusrgrp,
					'where' => ['adusrgrpid' => $adusrgrp['adusrgrpid']]
				];
			}
		}

		if ($upd_adusrgrps) {
			DB::update('adusrgrp', $upd_adusrgrps);
		}

		$this->updateAdGroupsUserGroups($adusrgrps, __FUNCTION__);
		$this->addAuditBulk(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_AD_GROUP, $adusrgrps, $db_adusrgrps);

		return ['adusrgrpids'=> zbx_objectValues($adusrgrps, 'adusrgrpid')];
	}

	/**
	 * @param array $adusrgrps
	 * @param array $db_adusrgrps
	 *
	 * @throws APIException if the input is invalid.
	 */
	private function validateUpdate(array &$adusrgrps, array &$db_adusrgrps = null) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can update AD groups.'));
		}

		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['adusrgrpid'], ['name']], 'fields' => [
			'adusrgrpid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>		['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('adusrgrp', 'name')],
			'user_type' =>		['type' => API_INT32, 'in' => implode(',', [USER_TYPE_ZABBIX_USER, USER_TYPE_ZABBIX_ADMIN, USER_TYPE_SUPER_ADMIN])],
			'usrgrpids' =>		['type' => API_IDS, 'flags' => API_NORMALIZE]
		]];
		if (!CApiInputValidator::validate($api_input_rules, $adusrgrps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		// Check AD group names.
		$db_adusrgrps = DB::select('adusrgrp', [
			'output' => ['adusrgrpid', 'name', 'user_type'],
			'adusrgrpids' => zbx_objectValues($adusrgrps, 'adusrgrpid'),
			'preservekeys' => true
		]);

		$names = [];

		foreach ($adusrgrps as $adusrgrp) {
			// Check if this AD group exists.
			if (!array_key_exists($adusrgrp['adusrgrpid'], $db_adusrgrps)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$db_adusrgrp = $db_adusrgrps[$adusrgrp['adusrgrpid']];

			if (array_key_exists('name', $adusrgrp) && $adusrgrp['name'] !== $db_adusrgrp['name']) {
				$names[] = $adusrgrp['name'];
			}
		}

		if ($names) {
			$this->checkDuplicates($names);
		}
		$this->checkUserGroups($adusrgrps);
	}

	/**
	 * Check for duplicated AD groups.
	 *
	 * @param array  $names
	 *
	 * @throws APIException  if AD group already exists.
	 */
	private function checkDuplicates(array $names) {
		$db_adusrgrps = DB::select('adusrgrp', [
			'output' => ['name'],
			'filter' => ['name' => $names],
			'limit' => 1
		]);

		if ($db_adusrgrps) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('AD group "%1$s" already exists.', $db_adusrgrps[0]['name']));
		}
	}

	/**
	 * Check for valid user groups.
	 *
	 * @param array  $adusrgrps
	 * @param array  $adusrgrps[]['usrgrpids']   (optional)
	 *
	 * @throws APIException
	 */
	private function checkUserGroups(array $adusrgrps) {
		$usrgrpids = [];

		foreach ($adusrgrps as $adusrgrp) {
			if (array_key_exists('usrgrpids', $adusrgrp)) {
				foreach ($adusrgrp['usrgrpids'] as $usrgrpid) {
					$usrgrpids[$usrgrpid] = true;
				}
			}
		}

		if (!$usrgrpids) {
			return;
		}

		$usrgrpids = array_keys($usrgrpids);

		$db_usrgrps = DB::select('usrgrp', [
			'output' => [],
			'usrgrpids' => $usrgrpids,
			'preservekeys' => true
		]);

		foreach ($usrgrpids as $usrgrpid) {
			if (!array_key_exists($usrgrpid, $db_usrgrps)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('User group with ID "%1$s" is not available.', $usrgrpid));
			}
		}
	}

	/**
	 * Check to exclude an opportunity to leave AD group without user groups.
	 *
	 * @param array  $adusrgrps
	 * @param array  $adusrgrps[]['adusrgrpid']
	 * @param array  $adusrgrps[]['usrgrpids']   (optional)
	 *
	 * @throws APIException
	 */
	private function checkAdGroupsWithoutUserGroups(array $adusrgrps) {
		$adgroups_groups = [];

		foreach ($adusrgrps as $adusrgrp) {
			if (array_key_exists('usrgrpids', $adusrgrp)) {
				$adgroups_groups[$adusrgrp['adusrgrpid']] = [];

				foreach ($adusrgrp['usrgrpids'] as $usrgrpid) {
					$adgroups_groups[$adusrgrp['adusrgrpid']][$usrgrpid] = true;
				}
			}
		}

		if (!$adgroups_groups) {
			return;
		}

		$db_adgroups_groups = DB::select('adgroups_groups', [
			'output' => ['usrgrpid', 'adusrgrpid'],
			'filter' => ['usrgrpid' => array_keys($adgroups_groups)]
		]);

		$ins_usrgrpids = [];
		$del_usrgrpids = [];

		foreach ($db_adgroups_groups as $db_adgroup_group) {
			if (array_key_exists($db_adgroup_group['usrgrpid'], $adgroups_groups[$db_adgroups_group['adusrgrpid']])) {
				unset($adgroups_groups[$db_adgroup_group['adusrgrpid']][$db_adgroup_group['usrgrpid']]);
			}
			else {
				if (!array_key_exists($db_adgroup_group['usrgrpid'], $del_usrgrpids)) {
					$del_usrgrpids[$db_adgroup_group['usrgrpid']] = 0;
				}
				$del_usrgrpids[$db_adgroup_group['usrgrpid']]++;
			}
		}

		foreach ($adgroups_groups as $adusrgrpid => $usrgrpids) {
			foreach (array_keys($usrgrpids) as $usrgrpid) {
				$ins_usrgrpids[$usrgrpid] = true;
			}
		}

		$del_usrgrpids = array_diff_key($del_usrgrpids, $ins_usrgrpids);

		if (!$del_usrgrpids) {
			return;
		}

		$db_usrgrps = DBselect(
			'SELECT ag.adusrgrpid,ag.name,count(agg.usrgrpid) as usrgrp_num'.
			' FROM adusrgrp ag,adgroups_groups agg'.
			' WHERE ag.adusrgrpid=agg.adusrgrpid'.
				' AND '.dbConditionInt('agg.usrgrpid', array_keys($del_usrgrpids)).
			' GROUP BY u.userid,u.alias'
		);

		while ($db_usrgrp = DBfetch($db_usrgrps)) {
			if ($db_usrgrp['usrgrp_num'] == $del_usrgrpids[$db_usrgrp['usrgrpid']]) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('AD group "%1$s" cannot be without user group.', $db_usrgrp['name'])
				);
			}
		}
	}

	/**
	 * Update table "adgroups_groups".
	 *
	 * @param array  $adusrgrps
	 * @param string $method
	 */
	private function updateAdGroupsGroups(array $adusrgrps, $method) {
		$adgroups_groups = [];

		foreach ($adusrgrps as $adusrgrp) {
			if (array_key_exists('usrgrps', $adusrgrp)) {
				$adgroups_groups[$adusrgrp['adusrgrpid']] = [];

				foreach ($adusrgrp['usrgrps'] as $usrgrp) {
					$adgroups_groups[$adusrgrp['adusrgrpid']][$usrgrp['usrgrpid']] = true;
				}
			}
		}

		if (!$adgroups_groups) {
			return;
		}

		$db_adgroups_groups = ($method === 'update')
			? DB::select('adgroups_groups', [
				'output' => ['id', 'usrgrpid', 'adusrgrpid'],
				'filter' => ['adusrgrpid' => array_keys($adgroups_groups)]
			])
			: [];

		$ins_adgroups_groups = [];
		$del_ids = [];

		foreach ($db_adgroups_groups as $db_adgroup_group) {
			if (array_key_exists($db_adgroup_group['usrgrpid'], $adgroups_groups[$db_adgroup_group['adusrgrpid']])) {
				unset($adgroups_groups[$db_adgroup_group['adusrgrpid']][$db_adgroup_group['usrgrpid']]);
			}
			else {
				$del_ids[] = $db_adgroup_group['id'];
			}
		}

		foreach ($adgroups_groups as $adusrgrpid => $usrgrpids) {
			foreach (array_keys($usrgrpids) as $usrgrpid) {
				$ins_adgroups_groups[] = [
					'adusrgrpid' => $adusrgrpid,
					'usrgrpid' => $usrgrpid
				];
			}
		}

		if ($ins_adgroups_groups) {
			DB::insertBatch('adgroups_groups', $ins_adgroups_groups);
		}

		if ($del_ids) {
			DB::delete('adgroups_groups', ['id' => $del_ids]);
		}
	}

	/**
	 * @param array $adusrgrpids
	 *
	 * @return array
	 */
	public function delete(array $adusrgrpids) {
		$this->validateDelete($adusrgrpids, $db_adusrgrps);

		DB::delete('adgroups_groups', ['adusrgrpid' => $adusrgrpids]);
		DB::delete('adusrgrp', ['adusrgrpid' => $adusrgrpids]);

		$this->addAuditBulk(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_AD_GROUP, $db_adusrgrps);

		return ['adusrgrpids' => $adusrgrpids];
	}

	/**
	 * @throws APIException
	 *
	 * @param array $adusrgrpids
	 * @param array $db_adusrgrps
	 */
	protected function validateDelete(array &$adusrgrpids, array &$db_adusrgrps = null) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('Only Super Admins can delete AD groups.'));
		}

		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];
		if (!CApiInputValidator::validate($api_input_rules, $adusrgrpids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_adusrgrps = DB::select('adusrgrp', [
			'output' => ['adusrgrpid', 'name'],
			'adusrgrpids' => $adusrgrpids,
			'preservekeys' => true
		]);

		$adusrgrps = [];

		foreach ($adusrgrpids as $adusrgrpid) {
			// Check if this AD group exists.
			if (!array_key_exists($adusrgrpid, $db_adusrgrps)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			$adusrgrps[] = [
				'adusrgrpid' => $adusrgrpid,
				'userids' => []
			];
		}

		$this->checkAdGroupsWithoutUserGroups($adusrgrps);
	}
}
