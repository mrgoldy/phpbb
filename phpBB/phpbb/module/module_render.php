<?php

namespace phpbb\module;

use Symfony\Component\DependencyInjection\ContainerInterface;

class module_render
{
	protected $container;
	protected $lang;
	protected $module_routing;
	protected $template;

	public function __construct(ContainerInterface $container, \phpbb\language\language $lang, module_routing $module_routing, \phpbb\template\template $template)
	{
		$this->container = $container;
		$this->lang = $lang;
		$this->module_routing = $module_routing;
		$this->template = $template;
	}

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

	protected function get_template_vars(array $module, array $active, $class)
	{
		return [
			'ID'		=> (int) $module['module_id'],

			'L_TITLE'		=> (string) $this->get_title($module),
			'S_SELECTED'	=> (bool) in_array($module['module_id'], $active),
			'U_VIEW'		=> (string) $this->module_routing->route($class, $module['module_slug']),
		];
	}

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
}
