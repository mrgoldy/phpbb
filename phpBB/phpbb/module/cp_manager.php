<?php

namespace phpbb\module;

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
	protected $template;
	protected $table;

	protected $class	= '';
	protected $category = '';
	protected $mode		= '';
	protected $mode_id	= 0;
	protected $modules	= array();
	protected $parents	= array();

	public function __construct(ContainerInterface $container, db $db, helper $helper, language $lang, template $template, $modules_table)
	{
		$this->container	= $container;
		$this->db			= $db;
		$this->helper		= $helper;
		$this->lang			= $lang;
		$this->template		= $template;
		$this->table		= $modules_table;
	}

	public function build($class, $category, $mode)
	{
		$this->update();

		$this->class	= (string) $class;
		$this->category = (string) $category;
		$this->mode		= (string) $mode;

		# Check class existance

		$this->get_modules();

		# Check category existance
		# Check category enabled
		# Check category empty
		# Check category authentication

		# Check mode existance
		# Check mode enabled
		# Check mode authentication

		$this->build_navigation();
	}

	public function display()
	{
		$module = $this->modules[$this->mode_id];
		$service = $module['module_basename'];
		$function = $module['module_mode'];

		return $this->container->get($service)->$function();
	}

	public function get_modules()
	{
		$sql = 'SELECT *
				FROM ' . $this->table . '
				WHERE module_class = "' . $this->db->sql_escape($this->class) . '"
				ORDER BY left_id ASC';
		$result = $this->db->sql_query($sql);
		while($row = $this->db->sql_fetchrow($result))
		{
			$pid = (int) $row['parent_id'];
			$mid = (int) $row['module_id'];

			$this->modules[$mid] = $row;
			$this->parents[$pid][$mid] = $row;
		}
		$this->db->sql_freeresult($result);
	}

	public function build_navigation()
	{
		$category_id = 0;

		# Categories
		foreach ($this->parents[0] as $category)
		{
			# Enabled and Display
			if (!$category['module_enabled'] || !$category['module_display'])
			{
				continue;
			}

			# Empty
			if ($this->is_empty($category['module_id']))
			{
				continue;
			}

			# Authorised
			if (!true)
			{
				continue;
			}

			# Assign vars
			$this->template->assign_block_vars($this->class . '_categories', $this->assign_tpl_vars($category, 'category'));

			if ($category['module_slug'] == $this->category)
			{
				$category_id = $category['module_id'];
			}
		}

		# Subcategories
		if ($category_id)
		{
			foreach ($this->parents[$category_id] as $subcategory)
			{
				if ($this->mode == $subcategory['module_slug'])
				{
					$this->mode_id = (int) $subcategory['module_id'];
				}

				# Enabled and Display
				if (!$subcategory['module_enabled'] || !$subcategory['module_display'])
				{
					continue;
				}

				# Empty
				if ($this->is_empty($subcategory['module_id']))
				{
					continue;
				}

				# Authorised
				if (!true)
				{
					continue;
				}

				$this->template->assign_block_vars($this->class . '_subcategories', $this->assign_tpl_vars($subcategory, 'subcategory'));

				foreach ($this->parents[$subcategory['module_id']] as $mode)
				{
					if ($this->mode == $mode['module_slug'])
					{
						$this->mode_id = (int) $mode['module_id'];
					}

					# Enabled and Display
					if (!$mode['module_enabled'] || !$mode['module_display'])
					{
						continue;
					}

					# Authorised
					if (!true)
					{
						continue;
					}

					$this->template->assign_block_vars($this->class . '_subcategories.modes', $this->assign_tpl_vars($mode, 'mode'));
				}
			}
		}
	}

	public function is_empty($module_id)
	{
		if (!empty($this->parents[$module_id]))
		{
			foreach ($this->parents[$module_id] as $module)
			{
				if (!$module['module_enabled'] || !$module['module_display'])
				{
					continue;
				}

				if (!empty($this->parents[$module['module_id']]))
				{
					$this->is_empty($module['module_id']);
				}

				return false;
			}
		}

		return true;
	}

	public function assign_tpl_vars($module, $type)
	{
		return array(
			'ID'			=> $module['module_id'],
			'L_TITLE'		=> (string) $this->get_title($module),
			'S_SELECTED'	=> (bool) $this->is_selected($module, $type),
			'U_VIEW'		=> (string) $this->get_route($module, $type),
		);
	}

	public function get_title($module)
	{
		$title = $this->lang->lang($module['module_langname']);

		$function = 'module_title_' . utf8_strtolower($module['module_langname']);

		if ($module['module_basename'])
		{
			if (method_exists($module['module_basename'], $function))
			{
				$object = new $module['module_basename'];

				$title = $object->$function();
			}
		}

		return $title;
	}

	public function is_selected($module, $type)
	{
		switch ($type)
		{
			case 'category':
				$s_selected = $this->category === $module['module_slug'];
			break;

			case 'subcategory':
				# Get all slugs from the modes belonging to this subcategory
				$slugs = array_column($this->parents[$module['module_id']], 'module_slug');

				$s_selected = in_array($this->mode, $slugs);
			break;

			case 'mode':
				$s_selected = $this->mode === $module['module_slug'];
			break;

			default:
				$s_selected = false;
			break;
		}

		return $s_selected;
	}

	public function get_route($module, $type)
	{
		$controller = 'phpbb_' . $this->class . '_controller';

		$slug = $module['module_slug'];

		switch ($type)
		{
			case 'category':
				$u_view = $this->helper->route($controller, array('category' => $slug));
			break;

			case 'mode':
				$subcategory_id = $module['parent_id'];
				$category_id = $this->modules[$subcategory_id]['parent_id'];
				$category = $this->modules[$category_id]['module_slug'];

				if (empty($module['module_slug']))
				{
					print_r($module['module_langname']);
				}

				$u_view = $this->helper->route($controller, array('category' => $category, 'mode' => $slug));
			break;

			default:
				$u_view = '';
			break;
		}

		return $u_view;
	}

	/** @todo migration */
	public function update()
	{
		global $phpbb_container;

		/** @var \phpbb\db\tools\tools_interface $db_tools */
		$db_tools = $phpbb_container->get('dbal.tools');

		if ($db_tools->sql_column_exists($this->table, 'module_slug'))
		{
			return;
		}

		$db_tools->sql_column_add($this->table, 'module_slug', array('VCHAR:255', ''), true);

		$sql = 'UPDATE ' . $this->table . '
				SET module_slug = LOWER(REPLACE(REPLACE(REPLACE(module_langname, "ACP_", ""), "CAT_", ""), "_", "-"))
				WHERE 1 = 1';
		$this->db->sql_query($sql);
	}
}
