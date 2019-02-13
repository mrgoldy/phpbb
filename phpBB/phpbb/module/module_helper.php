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

namespace phpbb\module;

use phpbb\module\exception\module_exception;
use phpbb\module\exception\module_not_found_exception;

class module_helper
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\module\module_auth */
	protected $module_auth;

	/** @var string */
	protected $table;

	/** @var array */
	protected $checked;

	/** @var array */
	protected $modules;

	/** @var array */
	protected $module;

	/** @var string */
	protected $class;

	/** @var int */
	protected $forum;

	/** @var array */
	protected $tree;

	/**
	 * Constructor.
	 *
	 * @param \phpbb\db\driver\driver_interface	$db				Database object
	 * @param \phpbb\module\module_auth			$module_auth	Module auth object
	 * @param string							$table			Modules table
	 */
	public function __construct(\phpbb\db\driver\driver_interface $db, module_auth $module_auth, $table)
	{
		$this->db = $db;
		$this->module_auth = $module_auth;
		$this->table = $table;
	}

	/**
	 * Set the modules class.
	 *
	 * @param string	$class		The modules class
	 * @return void
	 */
	public function set_class($class)
	{
		$class = (string) $class;

		if (!in_array($class, ['acp', 'mcp', 'ucp']))
		{
			throw new module_exception(); // @todo text / code
		}

		$this->class = $class;
	}

	/**
	 * Set the forum identifier.
	 *
	 * @param int		$forum_id	The forum identifier
	 * @return void
	 */
	public function set_forum($forum_id)
	{
		$this->forum = (int) $forum_id;
	}

	/**
	 * Set the modules for this class.
	 *
	 * @return void
	 */
	public function set_modules()
	{
		$sql = 'SELECT *
				FROM ' . $this->table . '
				WHERE module_class = "' . $this->db->sql_escape($this->class) . '"
				ORDER BY left_id ASC';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$module_id = (int) $row['module_id'];
			$parent_id = (int) $row['parent_id'];
			$slug = (string) $row['module_slug'];

			$this->modules['modules'][$module_id]	= $row;
			$this->modules['parents'][$parent_id][]	= $row;
			$this->modules['slugs'][$slug]			= $module_id;
		}
		$this->db->sql_freeresult($result);
	}

	/**
	 * Build the modules tree for this class.
	 *
	 * @return void
	 */
	public function build_modules()
	{
		$tree = $this->build_tree();

		$this->tree = $tree;
	}

	/**
	 * Set the active module for this class.
	 *
	 * @param string	$slug		The module slug
	 * @return void
	 */
	public function set_active($slug)
	{
		if (empty($this->modules['slugs'][$slug]))
		{
			throw new module_not_found_exception(); // @todo text / code
		}

		$module_id = (int) $this->modules['slugs'][$slug];
		$module = $this->modules['modules'][$module_id];

		if (empty($module['module_basename']))
		{
			$module = $this->get_active_child($module['module_id'], $module['left_id'], $module['right_id']);

			if (empty($module))
			{
				throw new module_exception(); // @todo text / code
			}
		}

		if (!in_array($module['module_id'], $this->checked))
		{
			if (!$module['module_enabled'])
			{
				throw new module_exception(); // @todo text / code
			}
			else
			{
				throw new module_exception(); // @todo text / code
			}
		}

		$this->module = $module;
	}

	/**
	 * Get active (selected) module identifiers.
	 *
	 * The top module category (parent_id = 0) is last in the array.
	 *
	 * @return array				Array with active module identifiers
	 */
	public function get_active()
	{
		if (empty($this->module))
		{
			reset($this->tree);
			$key = (int) key($this->tree);

			return [$key];
		}

		$module = $this->module;

		$active = [(int) $module['module_id']];

		while (!empty($module['parent_id']))
		{
			$active[] = (int) $module['parent_id'];

			$module = $this->modules['modules'][(int) $module['parent_id']];
		}

		return $active;
	}

	/**
	 * Get categories module data.
	 *
	 * @return array				Array with module data for the categories
	 */
	public function get_categories()
	{
		$categories = [];

		foreach ($this->tree as $category_id => $category)
		{
			$categories[$category_id] = $category;
		}

		return $categories;
	}

	/**
	 * Get subcategories and modes module data.
	 *
	 * @param int		$category_id	The module category identifier
	 * @return array					Array with module data for the subcategories and modes
	 * @access public
	 */
	public function get_children($category_id)
	{
		$category_id = (int) $category_id;

		return !empty($this->tree[$category_id]['module_children']) ? $this->tree[$category_id]['module_children'] : [];
	}

	/**
	 * Get module data array.
	 *
	 * @return array					The module data array
	 */
	public function get_module()
	{
		return $this->module;
	}

	/**
	 * Build a binary module tree.
	 *
	 * @param int		$parent_id		The module parent identifier
	 * @return array					Array with module data for the children
	 */
	protected function build_tree($parent_id = 0)
	{
		$branch = [];

		if (!empty($this->modules['parents'][$parent_id]))
		{
			foreach ($this->modules['parents'][$parent_id] as $child)
			{
				if ($child['module_enabled'] && $this->module_auth->check_auth($child['module_auth'], $this->forum))
				{
					$this->checked[] = (int) $child['module_id'];

					if (empty($child['module_basename']))
					{
						$children = $this->build_tree((int) $child['module_id']);

						if ($children)
						{
							$child['module_children'] = $children;

							$branch[$child['module_id']] = $child;
						}
					}
					else
					{
						$branch[$child['module_id']] = $child;
					}
				}
				else
				{
					return $branch;
				}
			}
		}
		else
		{
			return $branch;
		}

		return $branch;
	}

	/**
	 * Get first "active" child for a module (sub)category.
	 *
	 * Where "active" means the module is enabled and
	 * the user is authorised to access the module.
	 *
	 * @param int		$module_id		The module identifier
	 * @param int		$left_id		The module left identifier
	 * @param int		$right_id		The module right identifier
	 * @return array					The module data array
	 */
	protected function get_active_child($module_id, $left_id, $right_id)
	{
		$offset = array_search($module_id, array_keys($this->modules['modules']));

		// Exclude this module
		$offset++;

		$modules = array_slice($this->modules['modules'], $offset);

		foreach ($modules as $module)
		{
			if ($module['left_id'] > $left_id && $module['left_id'] < $right_id)
			{
				if (
					!empty($module['module_basename']) &&
					!empty($module['module_enabled']) &&
					in_array($module['module_id'], $this->checked)
				)
				{
					return $module;
				}
			}
			else
			{
				// Outside of scope and nothing found
				return [];
			}
		}

		return [];
	}

	/** @todo migration */
	public function update($container)
	{
		/** @var \phpbb\db\tools\tools_interface $db_tools */
		$db_tools = $container->get('dbal.tools');

		if ($db_tools->sql_column_exists($this->table, 'module_slug'))
		{
			return;
		}

		$db_tools->sql_column_add($this->table, 'module_slug', array('VCHAR:255', ''), true);

		$sql = 'UPDATE ' . $this->table . '
				SET module_slug = 
				LOWER(
					REPLACE(
						REPLACE(
							REPLACE(
								REPLACE(
									REPLACE(module_langname, "ACP_", ""),
								"MCP_", ""),
							"UCP_", ""), 
						"CAT_", ""), 
					"_", "-")
				)
				WHERE module_basename != ""
					OR parent_id = 0';
		$this->db->sql_query($sql);
	}
}
