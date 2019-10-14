<?php
/**
 *
 * This file is part of the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 * For full copyright and license information, please see
 * the docs/CREDITS.txt file.
 *
 */

namespace phpbb\db\migration\tool;

use phpbb\cache\driver\driver_interface as cache_driver;
use phpbb\db\exception\migration_exception;

/**
 * Migration tool: permission
 *
 * permission.add
 * permission.remove
 * permission.permission_set
 * permission.permission_unset
 * permission.role_add
 * permission.role_remove
 * permission.role_update
 */
class permission implements tool_interface
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var cache_driver */
	protected $cache;

	/** @var \phpbb\db\connection */
	protected $db;

	/** @var string ACL Groups data table */
	protected $auth_groups_table;

	/** @var string ACL Options table */
	protected $auth_options_table;

	/** @var string ACL Roles table */
	protected $auth_roles_table;

	/** @var string ACL Roles data table */
	protected $auth_roles_data_table;

	/** @var string ACL Users data table */
	protected $auth_users_table;

	/** @var string phpBB root path */
	protected $root_path;

	/** @var string php File extension */
	protected $php_ext;

	/**
	 * Constructor.
	 *
	 * @param \phpbb\auth\auth		$auth					Auth object
	 * @param cache_driver			$cache					Cache driver object
	 * @param \phpbb\db\connection	$db						Database object
	 * @param string				$auth_groups_table		ACL Groups data table
	 * @param string				$auth_options_table		ACL Options table
	 * @param string				$auth_roles_table		ACL Roles table
	 * @param string				$auth_roles_data_table	ACL Roles data table
	 * @param string				$auth_users_table		ACL Users data table
	 * @param string				$root_path				phpBB root path
	 * @param string				$php_ext				php File extension
	 * @return void
	 */
	public function __construct(
		\phpbb\auth\auth $auth,
		cache_driver $cache,
		\phpbb\db\connection $db,
		$auth_groups_table,
		$auth_options_table,
		$auth_roles_table,
		$auth_roles_data_table,
		$auth_users_table,
		$root_path,
		$php_ext
	)
	{
		$this->auth			= $auth;
		$this->cache		= $cache;
		$this->db			= $db;

		$this->auth_groups_table		= $auth_groups_table;
		$this->auth_options_table		= $auth_options_table;
		$this->auth_roles_table			= $auth_roles_table;
		$this->auth_roles_data_table	= $auth_roles_data_table;
		$this->auth_users_table			= $auth_users_table;

		$this->root_path	= $root_path;
		$this->php_ext		= $php_ext;
	}

	/**
	 * {@inheritdoc}
	 */
	static public function get_name()
	{
		return 'permission';
	}

	/**
	 * Add a permission (auth) option.
	 *
	 * @param string		$auth_option	The name of the permission (auth) option
	 * @param bool			$global			TRUE for checking a global permission setting,
	 * 										FALSE for a local permission setting
	 * @param bool|string	$copy_from		If set, contains the id of the permission from which to copy the new one.
	 * @return void
	 */
	public function add($auth_option, $global = true, $copy_from = false)
	{
		if (!$this->exists($auth_option, $global))
		{
			if (!class_exists('auth_admin'))
			{
				include($this->root_path . 'includes/acp/auth.' . $this->php_ext);
			}

			$auth_admin = new \auth_admin();

			/**
			 * @todo
			 *
			 * We have to add a check to see if the !$global (if global, local, and if local, global)
			 * permission already exists.
			 * If it does, acl_add_option currently has a bug which would break the ACL system,
			 * so we are having a work-around here.
			 */
			if ($this->exists($auth_option, !$global))
			{
				$sql = 'UPDATE ' . $this->auth_options_table . '
					SET ' . $this->db->sql_build_array('UPDATE', [
						'is_global'	=> 1,
						'is_local'	=> 1,
					]) . "
					WHERE auth_option = '" . $this->db->sql_escape($auth_option) . "'";
				$this->db->sql_query($sql);
			}
			else
			{
				if ($global)
				{
					$auth_admin->acl_add_option(['global' => [$auth_option]]);
				}
				else
				{
					$auth_admin->acl_add_option(['local' => [$auth_option]]);
				}
			}

			// The permission has been added, now we can copy it if needed
			if ($copy_from && isset($auth_admin->acl_options['id'][$copy_from]))
			{
				$old_id = $auth_admin->acl_options['id'][$copy_from];
				$new_id = $auth_admin->acl_options['id'][$auth_option];

				$tables = [$this->auth_groups_table, $this->auth_roles_data_table, $this->auth_users_table];

				foreach ($tables as $table)
				{
					$rowset = [];

					$sql = 'SELECT *
						FROM ' . $table . '
						WHERE auth_option_id = ' . $old_id;
					$result = $this->db->sql_query($sql);
					while ($row = $this->db->sql_fetchrow($result))
					{
						$row['auth_option_id'] = $new_id;
						$rowset[] = $row;
					}
					$this->db->sql_freeresult($result);

					if (!empty($rowset))
					{
						$this->db->sql_multi_insert($table, $rowset);
					}
				}

				$auth_admin->acl_clear_prefetch();
			}
		}
	}

	/**
	 * Remove a permission (auth) option.
	 *
	 * @param string		$auth_option	The name of the permission (auth) option
	 * @param bool			$global			TRUE for checking a global permission setting,
	 * 										FALSE for a local permission setting
	 * @return void
	 */
	public function remove($auth_option, $global = true)
	{
		if ($this->exists($auth_option, $global))
		{
			if ($global)
			{
				$type_sql = ' AND is_global = 1';
			}
			else
			{
				$type_sql = ' AND is_local = 1';
			}

			$sql = 'SELECT auth_option_id, is_global, is_local
				FROM ' . $this->auth_options_table . "
				WHERE auth_option = '" . $this->db->sql_escape($auth_option) . "'" .
					$type_sql;
			$result = $this->db->sql_query($sql);
			$row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			$id = (int) $row['auth_option_id'];

			// If it is a local and global permission, do not remove the row! :P
			if ($row['is_global'] && $row['is_local'])
			{
				$sql = 'UPDATE ' . $this->auth_options_table . '
					SET ' . ($global ? 'is_global = 0' : 'is_local = 0') . '
					WHERE auth_option_id = ' . $id;
				$this->db->sql_query($sql);
			}
			else
			{
				// Delete time
				$tables = [$this->auth_groups_table, $this->auth_roles_data_table, $this->auth_users_table, $this->auth_options_table];
				foreach ($tables as $table)
				{
					$sql = 'DELETE FROM ' . $table . ' WHERE auth_option_id = ' . $id;
					$this->db->sql_query($sql);
				}
			}

			// Purge the auth cache
			$this->cache->destroy('_acl_options');
			$this->auth->acl_clear_prefetch();
		}
	}

	/**
	 * Add a new permission role.
	 *
	 * @param string	$role_name			The new role name
	 * @param string	$role_type			The type (u_, m_, a_)
	 * @param string	$role_description	Description of the new role
	 * @return void
	 */
	public function role_add($role_name, $role_type, $role_description = '')
	{
		$sql = 'SELECT role_id
			FROM ' . $this->auth_roles_table . "
			WHERE role_name = '" . $this->db->sql_escape($role_name) . "'";
		$result = $this->db->sql_query($sql);
		$role_id = (int) $this->db->sql_fetchfield('role_id');
		$this->db->sql_freeresult($result);

		if (!$role_id)
		{
			$max_order = $this->db->getDatabasePlatform()->getMaxExpression('role_order');

			$sql = 'SELECT ' . $max_order . ' AS max_role_order
				FROM ' . $this->auth_roles_table . "
				WHERE role_type = '" . $this->db->sql_escape($role_type) . "'";
			$result = $this->db->sql_query($sql);
			$role_order = (int) $this->db->sql_fetchfield('max_role_order');
			$role_order = !$role_order ? 1 : $role_order + 1;
			$this->db->sql_freeresult($result);

			$role_data = [
				'role_name'			=> $role_name,
				'role_description'	=> $role_description,
				'role_type'			=> $role_type,
				'role_order'		=> $role_order,
			];

			$sql = 'INSERT INTO ' . $this->auth_roles_table . ' ' . $this->db->sql_build_array('INSERT', $role_data);
			$this->db->sql_query($sql);
		}
	}

	/**
	 * Remove a permission role.
	 *
	 * @param string	$role_name			The new role name
	 * @return void
	 */
	public function role_remove($role_name)
	{
		$sql = 'SELECT role_id
			FROM ' . $this->auth_roles_table . "
			WHERE role_name = '" . $this->db->sql_escape($role_name) . "'";
		$result = $this->db->sql_query($sql);
		$role_id = (int) $this->db->sql_fetchfield('role_id');
		$this->db->sql_freeresult($result);

		if ($role_id)
		{
			$sql = 'DELETE FROM ' . $this->auth_roles_data_table . '
				WHERE role_id = ' . $role_id;
			$this->db->sql_query($sql);

			$sql = 'DELETE FROM ' . $this->auth_roles_table . '
				WHERE role_id = ' . $role_id;
			$this->db->sql_query($sql);

			$this->auth->acl_clear_prefetch();
		}
	}

	/**
	 * Update the name of a permission role.
	 *
	 * @param string	$old_role_name		The old role name
	 * @param string	$new_role_name		The new role name
	 * @throws migration_exception			If the role does not exist
	 * @return void
	 */
	public function role_update($old_role_name, $new_role_name)
	{
		$sql = 'SELECT role_id
			FROM ' . $this->auth_roles_table . "
			WHERE role_name = '" . $this->db->sql_escape($old_role_name) . "'";
		$result = $this->db->sql_query($sql);
		$role_id = (int) $this->db->sql_fetchfield('role_id');
		$this->db->sql_freeresult($result);

		if (!$role_id)
		{
			throw new migration_exception('ROLE_NOT_EXIST', $old_role_name);
		}

		$sql = 'UPDATE ' . $this->auth_roles_table . "
			SET role_name = '" . $this->db->sql_escape($new_role_name) . "'
			WHERE role_name = '" . $this->db->sql_escape($old_role_name) . "'";
		$this->db->sql_query($sql);
	}

	/**
	 * Set permission(s) for a certain group/role.
	 *
	 * @param string		$name				The name of the role/group
	 * @param string|array	$auth_option		The auth_option or array of auth_options to be set
	 * @param string		$type				The type (role|group)
	 * @param bool			$has_permission		TRUE if you want to give them permission,
	 * 											FALSE if you want to deny them permission
	 * @throws migration_exception				If the role/group does not exist
	 * @return void
	 */
	public function permission_set($name, $auth_option, $type = 'role', $has_permission = true)
	{
		if (!is_array($auth_option))
		{
			$auth_option = [$auth_option];
		}

		$new_auth = [];

		$sql = 'SELECT auth_option_id
			FROM ' . $this->auth_options_table . '
			WHERE ' . $this->db->sql_in_set('auth_option', $auth_option);
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$new_auth[] = (int) $row['auth_option_id'];
		}
		$this->db->sql_freeresult($result);

		if (empty($new_auth))
		{
			$type = '';
		}

		$current_auth = [];

		// Prevent PHP bug.
		$type = (string) $type;

		switch ($type)
		{
			case 'role':
				$sql = 'SELECT role_id
					FROM ' . $this->auth_roles_table . "
					WHERE role_name = '" . $this->db->sql_escape($name) . "'";
				$this->db->sql_query($sql);
				$role_id = (int) $this->db->sql_fetchfield('role_id');

				if (!$role_id)
				{
					throw new migration_exception('ROLE_NOT_EXIST', $name);
				}

				$sql = 'SELECT auth_option_id, auth_setting
					FROM ' . $this->auth_roles_data_table . '
					WHERE role_id = ' . $role_id;
				$result = $this->db->sql_query($sql);
				while ($row = $this->db->sql_fetchrow($result))
				{
					$current_auth[$row['auth_option_id']] = $row['auth_setting'];
				}
				$this->db->sql_freeresult($result);

				$rowset = [];

				foreach ($new_auth as $auth_option_id)
				{
					if (!isset($current_auth[$auth_option_id]))
					{
						$rowset[] = [
							'role_id'			=> $role_id,
							'auth_option_id'	=> $auth_option_id,
							'auth_setting'		=> $has_permission,
						];
					}
				}

				$this->db->sql_multi_insert($this->auth_roles_data_table, $rowset);

				$this->auth->acl_clear_prefetch();
			break;

			case 'group':
				$sql = 'SELECT group_id
					FROM ' . GROUPS_TABLE . "
					WHERE group_name = '" . $this->db->sql_escape($name) . "'";
				$this->db->sql_query($sql);
				$group_id = (int) $this->db->sql_fetchfield('group_id');

				if (!$group_id)
				{
					throw new migration_exception('GROUP_NOT_EXIST', $name);
				}

				// If the group has a role set for them we will add the requested permissions to that role.
				$sql = 'SELECT auth_role_id
					FROM ' . $this->auth_groups_table . '
					WHERE group_id = ' . $group_id . '
						AND auth_role_id <> 0
						AND forum_id = 0';
				$result = $this->db->sql_query($sql);
				$role_id = (int) $this->db->sql_fetchfield('auth_role_id');
				$this->db->sql_freeresult($result);

				if ($role_id)
				{
					$sql = 'SELECT role_name, role_type
						FROM ' . $this->auth_roles_table . '
						WHERE role_id = ' . $role_id;
					$result = $this->db->sql_query($sql);
					$role_data = $this->db->sql_fetchrow($result);
					$this->db->sql_freeresult($result);

					$role_name = $role_data['role_name'];
					$role_type = $role_data['role_type'];

					// Filter new auth options to match the role type: a_ | f_ | m_ | u_
					// Set new auth options to the role only if options matching the role type were found
					$auth_option = array_filter($auth_option, function ($option) use ($role_type)
					{
						return strpos($option, $role_type) === 0;
					});

					if (!empty($auth_option))
					{
						$this->permission_set($role_name, $auth_option, 'role', $has_permission);

						break;
					}
				}

				$sql = 'SELECT auth_option_id, auth_setting
					FROM ' . $this->auth_groups_table . '
					WHERE group_id = ' . $group_id;
				$result = $this->db->sql_query($sql);
				while ($row = $this->db->sql_fetchrow($result))
				{
					$current_auth[$row['auth_option_id']] = $row['auth_setting'];
				}
				$this->db->sql_freeresult($result);

				$rowset = [];

				foreach ($new_auth as $auth_option_id)
				{
					if (!isset($current_auth[$auth_option_id]))
					{
						$rowset[] = [
							'group_id'			=> $group_id,
							'auth_option_id'	=> $auth_option_id,
							'auth_setting'		=> $has_permission,
						];
					}
				}

				$this->db->sql_multi_insert($this->auth_groups_table, $rowset);

				$this->auth->acl_clear_prefetch();
			break;
		}
	}

	/**
	 * Unset (remove) permission(s) for a certain group/role.
	 *
	 * @param string		$name				The name of the role/group
	 * @param string|array	$auth_option		The auth_option or array of auth_options to be set
	 * @param string		$type				The type (role|group)
	 * @throws migration_exception				If the role/group does not exist
	 * @return void
	 */
	public function permission_unset($name, $auth_option, $type = 'role')
	{
		if (!is_array($auth_option))
		{
			$auth_option = [$auth_option];
		}

		$to_remove = [];

		$sql = 'SELECT auth_option_id
			FROM ' . $this->auth_options_table . '
			WHERE ' . $this->db->sql_in_set('auth_option', $auth_option);
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$to_remove[] = (int) $row['auth_option_id'];
		}
		$this->db->sql_freeresult($result);

		if (empty($to_remove))
		{
			$type = '';
		}

		// Prevent PHP bug.
		$type = (string) $type;

		switch ($type)
		{
			case 'role':
				$sql = 'SELECT role_id
					FROM ' . $this->auth_roles_table . "
					WHERE role_name = '" . $this->db->sql_escape($name) . "'";
				$result = $this->db->sql_query($sql);
				$role_id = (int) $this->db->sql_fetchfield('role_id');
				$this->db->sql_freeresult($result);

				if (!$role_id)
				{
					throw new migration_exception('ROLE_NOT_EXIST', $name);
				}

				$sql = 'DELETE FROM ' . $this->auth_roles_data_table . '
					WHERE ' . $this->db->sql_in_set('auth_option_id', $to_remove) . '
						AND role_id = ' . (int) $role_id;
				$this->db->sql_query($sql);

				$this->auth->acl_clear_prefetch();
			break;

			case 'group':
				$sql = 'SELECT group_id
					FROM ' . GROUPS_TABLE . "
					WHERE group_name = '" . $this->db->sql_escape($name) . "'";
				$result = $this->db->sql_query($sql);
				$group_id = (int) $this->db->sql_fetchfield('group_id');
				$this->db->sql_freeresult($result);

				if (!$group_id)
				{
					throw new migration_exception('GROUP_NOT_EXIST', $name);
				}

				// If the group has a role set for them we will remove the requested permissions from that role.
				$sql = 'SELECT auth_role_id
					FROM ' . $this->auth_groups_table . '
					WHERE group_id = ' . $group_id . '
						AND auth_role_id <> 0';
				$result = $this->db->sql_query($sql);
				$role_id = (int) $this->db->sql_fetchfield('auth_role_id');
				$this->db->sql_freeresult($result);

				if ($role_id)
				{
					$sql = 'SELECT role_name
						FROM ' . $this->auth_roles_table . '
						WHERE role_id = ' . $role_id;
					$result = $this->db->sql_query($sql);
					$role_name = $this->db->sql_fetchfield('role_name');
					$this->db->sql_freeresult($result);

					$this->permission_unset($role_name, $auth_option, 'role');

					break;
				}

				$sql = 'DELETE FROM ' . $this->auth_groups_table . '
					WHERE ' . $this->db->sql_in_set('auth_option_id', $to_remove);
				$this->db->sql_query($sql);

				$this->auth->acl_clear_prefetch();
			break;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function reverse()
	{
		$call = false;
		$arguments = func_get_args();
		$original_call = array_shift($arguments);

		switch ($original_call)
		{
			case 'add':
				$call = 'remove';
			break;

			case 'remove':
				$call = 'add';
			break;

			case 'permission_set':
				$call = 'permission_unset';
			break;

			case 'permission_unset':
				$call = 'permission_set';
			break;

			case 'role_add':
				$call = 'role_remove';
			break;

			case 'role_remove':
				$call = 'role_add';
			break;

			case 'role_update':
				// Set to the original value if the current value is what we compared to originally
				$arguments = [
					$arguments[1],
					$arguments[0],
				];
			break;

			case 'reverse':
				// Reversing a reverse is just the call itself
				$call = array_shift($arguments);
			break;
		}

		if ($call)
		{
			call_user_func_array([&$this, $call], $arguments);
		}
	}

	/**
	 * Check if a permission (auth) setting exists.
	 *
	 * @param string	$auth_option	The name of the permission (auth) option
	 * @param bool		$global			TRUE for checking a global permission setting,
	 * 									FALSE for a local permission setting
	 * @return bool						TRUE if it exists, FALSE if not
	 */
	protected function exists($auth_option, $global = true)
	{
		if ($global)
		{
			$type_sql = ' AND is_global = 1';
		}
		else
		{
			$type_sql = ' AND is_local = 1';
		}

		$sql = 'SELECT auth_option_id
			FROM ' . $this->auth_options_table . "
			WHERE auth_option = '" . $this->db->sql_escape($auth_option) . "'"
			. $type_sql;
		$result = $this->db->sql_query($sql);

		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if ($row)
		{
			return true;
		}

		return false;
	}
}
