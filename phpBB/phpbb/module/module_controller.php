<?php

namespace phpbb\module;

use phpbb\exception\http_exception;
use phpbb\module\exception\module_exception;
use Symfony\Component\DependencyInjection\ContainerInterface;

class module_controller
{
	protected $container;
	protected $module_helper;
	protected $module_render;
	protected $module_routing;

	public function __construct(ContainerInterface $container, module_helper $module_helper, module_render $module_render, module_routing $module_routing)
	{
		$this->container = $container;
		$this->module_helper = $module_helper;
		$this->module_render = $module_render;
		$this->module_routing = $module_routing;
	}

	public function build($class, $slug, $forum_id = 0)
	{
		// @todo
		$this->module_helper->update($this->container);

		$this->module_helper->set_class($class);

		$this->module_helper->set_forum($forum_id);

		$this->module_helper->set_modules();

		$this->module_helper->build_modules();

		try
		{
			$this->module_helper->set_active($slug);
		}
		catch (module_exception $e)
		{
			$this->build_navigation($class);

			throw new module_exception('1'); // @todo text / code
		}

		$this->build_navigation($class);
	}

	public function load()
	{
		$module = $this->module_helper->get_module();

		$object = $this->get_object($module['module_basename']);

		if ($object === null)
		{
			throw new http_exception(400, '2'); // @todo text / code
		}

		$this->set_action($object, $module['module_class'], $module['module_slug']);

		return $this->call_function($object, $module['module_mode']);
	}

	public function build_navigation($class)
	{
		$active = $this->module_helper->get_active();

		$category_id = (int) end($active);

		$categories = $this->module_helper->get_categories();

		$children = $this->module_helper->get_children($category_id);

		$this->module_render->navigation($categories, $children, $active, $class);
	}

	public function get_object($basename)
	{
		try
		{
			$object = $this->container->get($basename);

			return $object;
		}
		catch (\Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException $e)
		{
			if (class_exists($basename))
			{
				$object = new $basename;

				return $object;
			}
		}

		return null;
	}

	public function set_action($object, $class, $slug)
	{
		if (method_exists($object, 'module_action'))
		{
			$u_action = $this->module_routing->route($class, $slug);

			$object->module_action($u_action);
		}
		else if (method_exists($object, 'set_page_url'))
		{
			$u_action = $this->module_routing->route($class, $slug);

			$object->set_page_url($u_action);
		}
	}

	public function call_function($object, $mode)
	{
		if (method_exists($object, $mode))
		{
			return $object->$mode();
		}
		else if (method_exists($object, 'main'))
		{
			return $object->main($mode);
		}
		else
		{
			throw new http_exception(400, '3'); // @todo text / code
		}
	}
}
