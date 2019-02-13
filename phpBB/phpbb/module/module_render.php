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

use Symfony\Component\DependencyInjection\ContainerInterface;

class module_render
{
	/** @var ContainerInterface */
	protected $container;

	/** @var \phpbb\language\language */
	protected $lang;

	/** @var \phpbb\module\module_routing */
	protected $module_routing;

	/** @var \phpbb\template\template */
	protected $template;

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface			$container			Service container objects
	 * @param \phpbb\language\language		$lang				Language object
	 * @param \phpbb\module\module_routing	$module_routing		Module routing object
	 * @param \phpbb\template\template		$template			Template object
	 */
	public function __construct(ContainerInterface $container, \phpbb\language\language $lang, module_routing $module_routing, \phpbb\template\template $template)
	{
		$this->container		= $container;
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
			'ID'		=> (int) $module['module_id'],

			'L_TITLE'		=> (string) $this->get_title($module),
			'S_SELECTED'	=> (bool) in_array($module['module_id'], $active),
			'U_VIEW'		=> (string) $this->module_routing->route($class, $module['module_slug']),
		];
	}

	/**
	 * Get module title.
	 *
	 * Looks for the function in the  module object:
	 * 	- module_title()
	 *
	 * @param array		$module		The module data array
	 * @return string
	 */
	protected function get_title(array $module)
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
					return (string) $title;
				}
			}

			if (method_exists($object, 'module_title'))
			{
				$title = $object->module_title();
			}
		}

		return (string) $title;
	}
}
