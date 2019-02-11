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

use phpbb\exception\http_exception;
use phpbb\module\exception\module_not_found_exception;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

use Symfony\Component\DependencyInjection\ContainerInterface;
use phpbb\db\driver\driver_interface as db;
use phpbb\language\language;
use phpbb\template\template;
use phpbb\controller\helper;

class cp_manager
{
	/** @var ContainerInterface */
	protected $container;

	/** @var db */
	protected $db;

	/** @var helper */
	protected $helper;

	/** @var language */
	protected $lang;

	/** @var module_auth */
	protected $module_auth;

	/** @var template */
	protected $template;

	/** @var string */
	protected $table;

	/** @var array Default CP pages */
	protected $default = array(
		'acp' => 'index',
		'mcp' => 'index', // @todo
		'ucp' => 'index', // @todo
	);

	/** @var array */
	protected $actives;

	/** @var array */
	protected $module;

	/** @var array */
	protected $modules;

	/** @var array */
	protected $parents;

	/** @var string */
	protected $class;

	/** @var string */
	protected $slug;

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface	$container		Service container object
	 * @param db					$db				Database object
	 * @param helper				$helper			Controller helper object
	 * @param language				$lang			Language object
	 * @param module_auth			$module_auth	Module auth object
	 * @param template				$template		Template object
	 * @param string				$modules_table	Modules table
	 */
	public function __construct(
		ContainerInterface $container,
		db $db,
		helper $helper,
		language $lang,
		module_auth $module_auth,
		template $template,
		$modules_table
	)
	{
		$this->container	= $container;
		$this->db			= $db;
		$this->helper		= $helper;
		$this->lang			= $lang;
		$this->module_auth	= $module_auth;
		$this->template		= $template;
		$this->table		= $modules_table;
	}

	public function build($class, $slug)
	{
		$this->update();

		$this->lang->add_lang('acp/modules');

		$module = $index = array();
		$modules = $parents = array();
		$first = array();
		$allowed = array(
			'categories'	=> array(),
			'subcategories'	=> array(),
		);
		$show = array(
			'modes'			=> array(),
			'categories'	=> array(),
			'subcategories'	=> array(),
		);

		$sql = 'SELECT *
				FROM ' . $this->table . '
				WHERE module_class = "' . $this->db->sql_escape($class) . '"
				ORDER by left_id ASC';
		$result = $this->db->sql_query($sql);
		$rowset = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		foreach ($rowset as $row)
		{
			$module_id = (int) $row['module_id'];
			$parent_id = (int) $row['parent_id'];

			$modules[$module_id] = $row;
			$parents[$parent_id][$module_id] = $row;

			// Grab the default *CP page
			if ($row['module_slug'] === $this->default[$class])
			{
				$index = $row;
			}

			$check = $this->module_check($row);
			$type = $this->module_type($row);

			if ($row['module_slug'] === $slug && in_array($type, array('category', 'mode')))
			{
				$module = $row;
			}

			if (empty($check))
			{
				switch ($type)
				{
					case 'category':
						$allowed['categories'][] = $module_id;
					break;

					case 'subcategory':
						if (in_array($parent_id, $allowed['categories']))
						{
							$allowed['subcategories'][] = $module_id;
						}
					break;

					case 'mode':
						// Parent's parent id
						$category_id = $modules[$parent_id]['parent_id'];

						if (
							(!empty($category_id) && in_array($category_id, $allowed['categories']) && in_array($parent_id, $allowed['subcategories']))
							|| (empty($category_id) && in_array($parent_id, $allowed['categories']))
						)
						{
							$show['modes'][] = $module_id;

							if (!empty($category_id))
							{
								$show['categories'][] = $category_id;
								$show['subcategories'][] = $parent_id;
							}
							else
							{
								$show['categories'][] = $parent_id;
							}

							$first_id = $category_id ? $category_id : $parent_id;

							if (empty($first[$first_id]))
							{
								$first[$first_id] = $module_id;
							}
						}
					break;
				}
			}
		}

		if (!empty($module))
		{
			// Default to first mode if the module is category
			if ($this->module_type($module) === 'category')
			{
				if (!empty($modules[$first[$module['module_id']]]))
				{
					$module = $modules[$first[$module['module_id']]];
				}
				else
				{
					$module = array();
				}
			}
		}

		if (!empty($module))
		{
			$tree = $this->module_parents($module, $modules);

			// Check the module and its parents
			foreach ($tree as $modules_check)
			{
				if ($this->module_check($modules_check, false))
				{
					$module = array();

					break;
				}
			}
		}

		$active = !empty($module) ? $module : $index;
		$tree = !empty($module) && !empty($tree) ? $tree : $this->module_parents($active, $modules);

		// Get active category and subcategory
		$active_category = $tree['category']['module_id'];
		$active_subcategory = !empty($tree['subcategory']) ? $tree['subcategory']['module_id'] : 0;

		// Build categories
		foreach ($parents[0] as $category_id => $category)
		{
			if (!in_array($category_id, $allowed['categories']))
			{
				continue;
			}

			if (!in_array($category_id, $show['categories']))
			{
				continue;
			}

			$this->template->assign_block_vars($class . '_categories', $this->module_variables($category, $active_category));
		}

		// Build subcategories and modes
		foreach ($parents[$active_category] as $child_id => $child)
		{
			$active_id = 0;

			switch ($this->module_type($child))
			{
				case 'subcategory':
					if (!in_array($child_id, $allowed['subcategories']))
					{
						continue 2;
					}

					if (!in_array($child_id, $show['subcategories']))
					{
						continue 2;
					}

					$active_id = $active_subcategory;
				break;

				case 'mode':
					if (!in_array($child_id, $show['modes']))
					{
						continue 2;
					}

					$active_id = $active['module_id'];
				break;
			}

			$this->template->assign_block_vars($class . '_menu', $this->module_variables($child, $active_id));

			if (!empty($parents[$child_id]))
			{
				foreach ($parents[$child_id] as $mode_id => $mode)
				{
					if (!in_array($mode_id, $show['modes']))
					{
						continue;
					}

					$this->template->assign_block_vars($class . '_menu.modes', $this->module_variables($mode, $active['module_id']));
				}
			}
		}

		// If the module was not found
		if (empty($module))
		{
			throw new module_not_found_exception('NO_MODULE');
		}

		// Check the module and its parents
		foreach ($this->module_parents($module, $modules) as $modules_check)
		{
			if ($exception = $this->module_check($modules_check, false))
			{
				throw new http_exception(403, $exception);
			}
		}

		$base = $module['module_basename'];
		$mode = $module['module_mode'];

		try
		{
			// Try to get the basename as a service declaration
			$object = $this->container->get($base);
		}
		catch (ServiceNotFoundException $e)
		{
			// If the service declaration was not found,
			// Try to find it as a class
			if (class_exists($base))
			{
				$object = new $base;
			}
			else
			{
				throw new http_exception(400, "No service or class could be found for: “{$base}”"); // @todo
			}
		}

		// Check if the controller needs the slug
		if (method_exists($object, 'module_slug'))
		{
			$object->module_slug($this->module['module_slug']);
		}

		// Send it off to the controller
		if (method_exists($object, $mode))
		{
			// If the mode is a function in the object
			return $object->$mode();
		}
		else if (method_exists($object, 'main'))
		{
			// If main() is a function in the object
			return $object->main($mode);
		}
		else
		{
			throw new http_exception(400, "The object “{$base}” does not have a required function: “{$mode}()” or “main(\$mode)”"); // @todo
		}
	}

	protected function module_type($module)
	{
		if (empty($module['parent_id']))
		{
			return 'category';
		}
		else if (empty($module['module_basename']))
		{
			return 'subcategory';
		}
		else
		{
			return 'mode';
		}
	}

	protected function module_check($module, $check_display = true)
	{
		# Enabled
		if ($module['module_disabled'])
		{
			return 'MODULE_NOT_ACCESS';
		}

		# Display
		if (!$module['module_display'] && $check_display)
		{
			return true;
		}

		# Authorised
		if (!$this->module_auth->check_auth($module['module_auth']))
		{
			return 'NOT_AUTHORISED';
		}

		return false;
	}

	protected function module_parents($module, $modules)
	{
		$array = array('mode' => $module);

		if ($modules[$module['parent_id']])
		{
			$array['category'] = $parent = $modules[$module['parent_id']];
		}

		if (!empty($parent) && !empty($modules[$parent['parent_id']]))
		{
			$array['subcategory'] = $array['category'];
			$array['category'] = $modules[$parent['parent_id']];
		}

		return $array;
	}

	protected function module_variables($module, $selected)
	{
		return array(
			'ID'			=> (int) $module['module_id'],
			'L_TITLE'		=> (string) $this->module_title($module),
			'S_SELECTED'	=> (bool) ($module['module_id'] == $selected),
			'U_VIEW'		=> (string) $this->module_route($module),
		);
	}

	protected function module_title($module)
	{
		$title = $this->lang->lang($module['module_langname']);

		if ($module['module_basename'])
		{
			try
			{
				$object = $this->container->get($module['module_basename']);
			}
			catch (\Exception $e)
			{
				if (class_exists($module['module_basename']))
				{
					$object = new $module['module_basename'];
				}
				else
				{
					return $title;
				}
			}

			if (method_exists($object, 'module_title'))
			{
				$title = $object->module_title();
			}
		}

		return $title;
	}

	protected function module_route($module)
	{
		if ($this->module_type($module) === 'subcategory')
		{
			return '';
		}

		if ($module['module_slug'] === '')
		{
			trigger_error(''); // @todo
		}

		return $this->helper->route('phpbb_acp_controller', array('slug' => $module['module_slug']));
	}

	/** @todo migration */
	protected function update()
	{
		/** @var \phpbb\db\tools\tools_interface $db_tools */
		$db_tools = $this->container->get('dbal.tools');

		if ($db_tools->sql_column_exists($this->table, 'module_slug'))
		{
			return;
		}

		$db_tools->sql_column_add($this->table, 'module_slug', array('VCHAR:255', ''), true);

		$sql = 'UPDATE ' . $this->table . '
				SET module_slug = LOWER(REPLACE(REPLACE(REPLACE(module_langname, "ACP_", ""), "CAT_", ""), "_", "-"))
				WHERE module_basename != ""
					OR parent_id = 0';
		$this->db->sql_query($sql);
	}
}
