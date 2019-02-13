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

class module_render
{
	/** @var \phpbb\event\dispatcher */
	protected $dispatcher;

	/** @var \phpbb\language\language */
	protected $lang;

	/** @var \phpbb\module\module_routing */
	protected $module_routing;

	/** @var \phpbb\template\template */
	protected $template;

	/**
	 * Constructor.
	 *
	 * @param \phpbb\event\dispatcher		$dispatcher			Event dispatcher object
	 * @param \phpbb\language\language		$lang				Language object
	 * @param \phpbb\module\module_routing	$module_routing		Module routing object
	 * @param \phpbb\template\template		$template			Template object
	 */
	public function __construct(\phpbb\event\dispatcher $dispatcher, \phpbb\language\language $lang, module_routing $module_routing, \phpbb\template\template $template)
	{
		$this->dispatcher		= $dispatcher;
		$this->lang				= $lang;
		$this->module_routing	= $module_routing;
		$this->template			= $template;
	}

	/**
	 * Assign navigation variables to the template.
	 *
	 * @param array		$categories	Array with module data for the categories
	 * @param array		$children	Array with module data for the subcategories and modes
	 * @param array		$active		Array with active module identifiers
	 * @param string	$class		The module class
	 * @return void
	 */
	public function navigation(array $categories, array $children, array $active, $class)
	{
		$class = (string) $class;

		$block_cats = $class . '_categories';
		$block_menu = $class . '_menu';

		foreach ($categories as $category)
		{
			$this->template->assign_block_vars($block_cats, $this->get_template_vars($category, $active, $class));
		}

		$this->template->assign_vars([
			$block_menu => $this->build_menu($children, $active, $class),
		]);
	}

	/**
	 * Get module menu template.
	 *
	 * @param array		$modules	Array with modules data
	 * @param array		$active		Array with active module identifiers
	 * @param string	$class		The module class
	 * @return array				The module class menu template
	 */
	protected function build_menu(array $modules, array $active, $class)
	{
		$menu = [];

		foreach ($modules as $module)
		{
			$variables = $this->get_template_vars($module, $active, $class);

			if (!empty($module['module_children']))
			{
				$variables['CHILDREN'] = $this->build_menu($module['module_children'], $active, $class);
			}

			$menu[(int) $module['module_id']] = $variables;
		}

		return $menu;
	}

	/**
	 * Get module template variables.
	 *
	 * @param array		$module		The module data array
	 * @param array		$active		Array with active module identifiers
	 * @param string	$class		The module class
	 * @return array				The module template variables
	 */
	protected function get_template_vars(array $module, array $active, $class)
	{
		return [
			'ID'			=> (int) $module['module_id'],

			'L_TITLE'		=> (string) $this->get_title($module),
			'S_DISPLAY'		=> (bool) $module['module_display'],
			'S_SELECTED'	=> (bool) in_array($module['module_id'], $active),
			'U_VIEW'		=> (string) $this->module_routing->route($class, $module['module_slug']),
		];
	}

	/**
	 * Get module title.
	 *
	 * @param array		$module		The module data array
	 * @return string				The module title
	 */
	protected function get_title(array $module)
	{
		$title = $this->lang->lang($module['module_langname']);

		/**
		 * This event allows to modify the modules title when building navigation.
		 *
		 * @event core.modify_module_title
		 * @var	string		title		The module title
		 * @var	array		module		The module data array
		 * @since 4.0.0 @todo
		 */
		$vars = array('title', 'module');
		extract($this->dispatcher->trigger_event('core.modify_module_title', compact($vars)));

		return (string) $title;
	}
}
