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

use phpbb\module\exception\module_exception;
use phpbb\db\exception\migration_exception;

/**
 * Migration tool: module
 *
 * module.add
 * module.remove
 */
class module implements tool_interface
{
	/** @var \phpbb\db\connection */
	protected $db;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\log\log */
	protected $log;

	/** @var \phpbb\module\module_manager */
	protected $manager;

	/** @var \phpbb\user */
	protected $user;

	/** @var string Modules table */
	protected $modules_table;

	/** @var array Module categories */
	protected $module_categories = [];

	/**
	 * Constructor.
	 *
	 * @param \phpbb\db\connection			$db					Database object
	 * @param \phpbb\language\language		$language			Language object
	 * @param \phpbb\log\log				$log				Log object
	 * @param \phpbb\module\module_manager	$manager			Module manager object
	 * @param \phpbb\user					$user				User object
	 * @param string						$modules_table		Modules table
	 * @return void
	 */
	public function __construct(
		\phpbb\db\connection $db,
		\phpbb\language\language $language,
		\phpbb\log\log $log,
		\phpbb\module\module_manager $manager,
		\phpbb\user $user,
		$modules_table
	)
	{
		$this->db				= $db;
		$this->language			= $language;
		$this->log				= $log;
		$this->manager			= $manager;
		$this->user				= $user;

		$this->modules_table	= $modules_table;
	}

	/**
	 * {@inheritdoc}
	 */
	static public function get_name()
	{
		return 'module';
	}

	/**
	 * Add a module.
	 *
	 * @param string		$class		The module class
	 * @param int|string	$parent		The module parent identifier (id|langname)
	 * @param array|string	$data		The module data (or langname)
	 * @throws migration_exception
	 * @return void
	 */
	public function add($class, $parent = 0, $data = [])
	{
		// allow sending the name as a string in $data to create a category
		if (!is_array($data))
		{
			$data = ['module_langname' => $data];
		}

		$parents = (array) $this->get_parent_module_id($parent);

		if (!isset($data['module_langname']))
		{
			// The "automatic" way
			$basename = isset($data['module_basename']) ? $data['module_basename'] : '';
			$module = $this->get_module_info($class, $basename);

			foreach ($module['modes'] as $mode => $module_info)
			{
				if (!isset($data['modes']) || in_array($mode, $data['modes']))
				{
					$new_module = [
						'module_basename'	=> $basename,
						'module_langname'	=> $module_info['title'],
						'module_mode'		=> $mode,
						'module_auth'		=> $module_info['auth'],
						'module_display'	=> isset($module_info['display']) ? $module_info['display'] : true,
						'before'			=> isset($module_info['before']) ? $module_info['before'] : false,
						'after'				=> isset($module_info['after']) ? $module_info['after'] : false,
					];

					// Run the "manual" way with the data we've collected.
					foreach ($parents as $parent)
					{
						$this->add($class, $parent, $new_module);
					}
				}
			}
		}
		else
		{
			foreach ($parents as $parent)
			{
				$data['parent_id'] = $parent;

				// The "manual" way
				if (!$this->exists($class, false, $parent))
				{
					throw new migration_exception('MODULE_NOT_EXIST', $parent);
				}

				if ($this->exists($class, $parent, $data['module_langname']))
				{
					throw new migration_exception('MODULE_EXISTS', $data['module_langname']);
				}

				$module_data = [
					'module_class'		=> $class,
					'module_enabled'	=> isset($data['module_enabled']) ? $data['module_enabled'] : 1,
					'module_display'	=> isset($data['module_display']) ? $data['module_display'] : 1,
					'module_basename'	=> isset($data['module_basename']) ? $data['module_basename'] : '',
					'module_langname'	=> isset($data['module_langname']) ? $data['module_langname'] : '',
					'module_mode'		=> isset($data['module_mode']) ? $data['module_mode'] : '',
					'module_auth'		=> isset($data['module_auth']) ? $data['module_auth'] : '',
					'parent_id'			=> (int) $parent,
				];

				try
				{
					$this->manager->update_module_data($module_data);

					// Success
					$module_name = $this->language->lang($data['module_langname']);
					$user_id = isset($this->user->data['user_id']) ? (int) $this->user->data['user_id'] : ANONYMOUS;

					$this->log->add('admin', $user_id, $this->user->ip, 'LOG_MODULE_ADD', false, [$module_name]);

					// Move the module if requested above/below an existing one
					if (isset($data['before']) && $data['before'])
					{
						$before_mode = $before_langname = '';

						if (is_array($data['before']))
						{
							// Restore legacy-legacy behaviour from phpBB 3.0
							list($before_mode, $before_langname) = $data['before'];
						}
						else
						{
							// Legacy behaviour from phpBB 3.1+
							$before_langname = $data['before'];
						}

						$sql = 'SELECT left_id
							FROM ' . $this->modules_table . "
							WHERE module_class = '" . $this->db->sql_escape($class) . "'
								AND parent_id = " . (int) $parent . "
								AND module_langname = '" . $this->db->sql_escape($before_langname) . "'"
								. ($before_mode ? " AND module_mode = '" . $this->db->sql_escape($before_mode) . "'" : '');
						$result = $this->db->sql_query($sql);
						$to_left = (int) $this->db->sql_fetchfield('left_id');
						$this->db->sql_freeresult($result);

						$sql = 'UPDATE ' . $this->modules_table . "
							SET left_id = left_id + 2, right_id = right_id + 2
							WHERE module_class = '" . $this->db->sql_escape($class) . "'
								AND left_id >= $to_left
								AND left_id < {$module_data['left_id']}";
						$this->db->sql_query($sql);

						$sql = 'UPDATE ' . $this->modules_table . "
							SET left_id = $to_left, right_id = " . ($to_left + 1) . "
							WHERE module_class = '" . $this->db->sql_escape($class) . "'
								AND module_id = {$module_data['module_id']}";
						$this->db->sql_query($sql);
					}
					else if (isset($data['after']) && $data['after'])
					{
						$after_mode = $after_langname = '';

						if (is_array($data['after']))
						{
							// Restore legacy-legacy behaviour from phpBB 3.0
							list($after_mode, $after_langname) = $data['after'];
						}
						else
						{
							// Legacy behaviour from phpBB 3.1+
							$after_langname = $data['after'];
						}

						$sql = 'SELECT right_id
							FROM ' . $this->modules_table . "
							WHERE module_class = '" . $this->db->sql_escape($class) . "'
								AND parent_id = " . (int) $parent . "
								AND module_langname = '" . $this->db->sql_escape($after_langname) . "'"
								. ($after_mode ? " AND module_mode = '" . $this->db->sql_escape($after_mode) . "'" : '');
						$result = $this->db->sql_query($sql);
						$to_right = (int) $this->db->sql_fetchfield('right_id');
						$this->db->sql_freeresult($result);

						$sql = 'UPDATE ' . $this->modules_table . "
							SET left_id = left_id + 2, right_id = right_id + 2
							WHERE module_class = '" . $this->db->sql_escape($class) . "'
								AND left_id >= $to_right
								AND left_id < {$module_data['left_id']}";
						$this->db->sql_query($sql);

						$sql = 'UPDATE ' . $this->modules_table . '
							SET left_id = ' . ($to_right + 1) . ', right_id = ' . ($to_right + 2) . "
							WHERE module_class = '" . $this->db->sql_escape($class) . "'
								AND module_id = {$module_data['module_id']}";
						$this->db->sql_query($sql);
					}
				}
				catch (module_exception $e)
				{
					throw new migration_exception('MODULE_ERROR', $e->getMessage());
				}
			}

			// Clear the Modules Cache
			$this->manager->remove_cache_file($class);
		}
	}

	/**
	 * Remove a module.
	 *
	 * @param string			$class		The module class
	 * @param int|string		$parent		The module parent identifier
	 * @param int|string|array	$module		The module identifier (id|langname|basename)
	 * @throws migration_exception
	 * @return void
	 */
	public function remove($class, $parent = 0, $module = '')
	{
		// Imitation of module_add's "automatic" and "manual" method
		// so the uninstaller works from the same set of instructions for umil_auto
		if (is_array($module))
		{
			if (isset($module['module_langname']))
			{
				// Manual Method
				$this->remove($class, $parent, $module['module_langname']);
			}
			else
			{
				// Failed.
				if (!isset($module['module_basename']))
				{
					throw new migration_exception('MODULE_NOT_EXIST');
				}

				// Automatic method
				$basename = $module['module_basename'];
				$module_info = $this->get_module_info($class, $basename);

				foreach ($module_info['modes'] as $mode => $info)
				{
					if (!isset($module['modes']) || in_array($mode, $module['modes']))
					{
						$this->remove($class, $parent, $info['title']);
					}
				}
			}
		}
		else if ($this->exists($class, $parent, $module, true))
		{
			$parent_sql = '';
			$module_ids = [];

			if ($parent !== false)
			{
				$parents = (array) $this->get_parent_module_id($parent);
				$parent_sql = 'AND ' . $this->db->sql_in_set('parent_id', $parents);
			}

			if (!is_numeric($module))
			{
				$sql = 'SELECT module_id
					FROM ' . $this->modules_table . "
					WHERE module_langname = '" . $this->db->sql_escape($module) . "'
						AND module_class = '" . $this->db->sql_escape($class) . "'
						$parent_sql";
				$result = $this->db->sql_query($sql);
				while ($module_id = $this->db->sql_fetchfield('module_id'))
				{
					$module_ids[] = (int) $module_id;
				}
				$this->db->sql_freeresult($result);
			}
			else
			{
				$module_ids[] = (int) $module;
			}

			foreach ($module_ids as $module_id)
			{
				$this->manager->delete_module($module_id, $class);
			}

			$this->manager->remove_cache_file($class);
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
	 * Check if a module exists.
	 *
	 * @param string			$class		The module class
	 * @param int|string|bool	$parent		The module parent identifier (id|langname)
	 * @param int|string		$module		The module identifier (id|langname)
	 * @param bool				$lazy		Checks lazily if the module exists.
	 * 										Returns true if it exists in at least one given parent.
	 * @return bool							TRUE if module exists in *all* given parents,
	 * 										FALSE if not in any given parent.
	 * 										TRUE if ignoring parent check and module exists class wide,
	 * 										FALSE if not found at all.
	 */
	protected function exists($class, $parent, $module, $lazy = false)
	{
		// the main root directory should return true
		if (!$module)
		{
			return true;
		}

		$parent_array = [];

		if ($parent !== false)
		{
			// Exceptions are not thrown, but returned
			$parents = $this->get_parent_module_id($parent, false);

			if ($parents === false)
			{
				return false;
			}

			foreach ((array) $parents as $parent_id)
			{
				$parent_array[] = 'AND parent_id = ' . (int) $parent_id;
			}
		}
		else
		{
			$parent_array[] = '';
		}

		foreach ($parent_array as $parent_sql)
		{
			$sql = 'SELECT module_id
				FROM ' . $this->modules_table . "
				WHERE module_class = '" . $this->db->sql_escape($class) . "'
					$parent_sql
					AND " . (is_numeric($module) ? 'module_id = ' . (int) $module : "module_langname = '" . $this->db->sql_escape($module) . "'");
			$result = $this->db->sql_query($sql);
			$module_id = $this->db->sql_fetchfield('module_id');
			$this->db->sql_freeresult($result);

			if (!$lazy && !$module_id)
			{
				return false;
			}

			if ($lazy && $module_id)
			{
				return true;
			}
		}

		// Returns true, if modules exist in all parents and false otherwise
		return !$lazy;
	}

	/**
	 * Get a module's information.
	 *
	 * @param string		$class				The module info class
	 * @param string		$basename			The module basename
	 * @throws migration_exception				If the module info class does not exist
	 * @return array							The module information
	 */
	protected function get_module_info($class, $basename)
	{
		$module = $this->manager->get_module_infos($class, $basename, true);

		if (empty($module))
		{
			throw new migration_exception('MODULE_INFO_FILE_NOT_EXIST', $class, $basename);
		}

		return array_pop($module);
	}

	/**
	 * Get a module's parent identifier.
	 *
	 * @param int|string	$parent_id			The parent identifier
	 * @param bool			$throw_exception	Whether or not to throw exceptions
	 * @throws migration_exception				If the parent module does not exist
	 * @return array|bool|int					The module identifier(s),
	 *                            				FALSE if the module does not exists and exceptions should not be thrown
	 */
	protected function get_parent_module_id($parent_id, $throw_exception = true)
	{
		// Allow '' to be sent as 0
		$parent_id = $parent_id ? $parent_id : 0;

		if (!is_numeric($parent_id))
		{
			// Refresh the $module_categories array
			$this->get_categories_list();

			// Search for the parent module_langname
			$ids = array_keys($this->module_categories, $parent_id);

			switch (count($ids))
			{
				// No parent with the given module_langname exist
				case 0:
					if ($throw_exception)
					{
						throw new migration_exception('MODULE_NOT_EXIST', $parent_id);
					}

					return false;

				// Return the module id
				case 1:
					return (int) $ids[0];

				// This represents the old behaviour of phpBB 3.0
				default:
					return $ids;
			}
		}

		return $parent_id;
	}

	/**
	 * Get the list of installed module categories.
	 *
	 * Index the top level categories
	 * and the 2nd level of (sub)categories.
	 * [module_id => module_langname]
	 *
	 * @return void
	 */
	protected function get_categories_list()
	{
		// Select the top level categories
		// and 2nd level [sub]categories
		$sql = 'SELECT m2.module_id, m2.module_langname
			FROM ' . $this->modules_table . ' m1, ' . $this->modules_table . " m2
			WHERE m1.parent_id = 0
				AND (m1.module_id = m2.module_id OR m2.parent_id = m1.module_id)
			ORDER BY m1.module_id, m2.module_id ASC";
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$this->module_categories[(int) $row['module_id']] = $row['module_langname'];
		}
		$this->db->sql_freeresult($result);
	}
}
