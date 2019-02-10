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
	protected $container;
	protected $db;
	protected $helper;
	protected $lang;
	protected $module_auth;
	protected $template;
	protected $table;

	protected $default = array(
		'acp' => 'index',
		'mcp' => '', // @todo
		'ucp' => '', // @todo
	);

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

		// Default to index page if no module was found
		$active = $module ? $module : $index;

		// Default to first mode if the slug is a category
		$active = $this->module_type($active) !== 'category' ? $active : $modules[$first[$active['module_id']]];
		$module = $this->module_type($module) !== 'category' ? $module : $modules[$first[$module['module_id']]];

		// Default to index page if mode is not accessible
		$active = !$this->module_check($active, false) ? $active : $index;

		// Get active category and subcategory
		$active_category = (int) $active['parent_id'];
		$active_subcategory = 0;

		if (!empty($modules[$active_category]['parent_id']))
		{
			$active_subcategory = (int) $modules[$active_category]['module_id'];
			$active_category = (int) $modules[$active_category]['parent_id'];
		}

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

			$this->template->assign_block_vars($class . '_categories', $this->assign_tpl_vars($category, $active_category));
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

			$this->template->assign_block_vars($class . '_menu', $this->assign_tpl_vars($child, $active_id));

			if (!empty($parents[$child_id]))
			{
				foreach ($parents[$child_id] as $mode_id => $mode)
				{
					if (!in_array($mode_id, $show['modes']))
					{
						continue;
					}

					$this->template->assign_block_vars($class . '_menu.modes', $this->assign_tpl_vars($mode, $active['module_id']));
				}
			}
		}

		// If the module was not found
		if (empty($module))
		{
			throw new module_not_found_exception('NO_MODULE');
		}

		// Check the module and its parents
		$check_modules = array($modules['parent_id'], $module);

		if (!empty($modules[$module['parent_id']]['parent_id']))
		{
			$category_id = $modules[$module['parent_id']]['parent_id'];

			// Add the category to the top, so the check is top to bottom
			array_unshift($check_modules, $modules[$category_id]);
		}

		foreach ($check_modules as $check_module)
		{
			if ($exception = $this->module_check($check_module, false))
			{
				throw new http_exception(403, $exception);
			}
		}

		// Get the controller and function
		$basename = $module['module_basename'];
		$function = $module['module_mode'];
		$object = null;

		try
		{
			// Try to get the basename as a service declaration
			$object = $this->container->get($basename);
		}
		catch (ServiceNotFoundException $e)
		{
			// If the service declaration was not found,
			// Try to find it as a class
			if (class_exists($basename))
			{
				$object = new $basename;
			}
			else
			{
				throw new http_exception(400, "No service or class could be found for: “{$basename}”"); // @todo
			}
		}

		if (!method_exists($object, $function))
		{
			throw new http_exception(400, "The object “{$basename}” does not have a required function: “{$function}”"); // @todo
		}

		// Send it off to the controller
		return $object->$function();
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

	protected function assign_tpl_vars($module, $selected)
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
			$function = 'module_title';

			if (method_exists($module['module_basename'], $function))
			{
				if (class_exists($module['module_basename']))
				{
					$object = new $module['module_basename'];
				}
				else
				{
					try
					{
						$object = $this->container->get($module['module_basename']);
					}
					catch (\Exception $e)
					{
						return $title;
					}
				}

				$title = $object->$function();
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
