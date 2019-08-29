<?php

namespace phpbb\cp;

class item
{
	protected $auth;
	protected $icon;
	protected $route;
	protected $parent;
	protected $before;
	protected $page;

	public function __construct($auth = '', $icon = '', $route = '', $parent = '', $before = '', $page = '')
	{
		$this->auth 	= $auth;
		$this->icon		= $icon;
		$this->route	= $route;
		$this->parent	= $parent;
		$this->before	= $before;
		$this->page		= $page;
	}

	public function get_auth()
	{
		return $this->auth;
	}

	public function get_icon()
	{
		return $this->icon;
	}

	public function get_parent()
	{
		return $this->parent;
	}

	public function get_before()
	{
		return $this->before;
	}

	public function get_route()
	{
		return $this->route;
	}

	public function get_page()
	{
		return $this->page;
	}

/*
	public function get_route($service_name)
	{
		return is_string($this->route) ? $this->route : $service_name;
	}

	public function get_routes_for_creation($service_name)
	{
		$routes = [];

		if (is_array($this->route))
		{
			$routes[$service_name] = $this->route;

			if ($this->page !== '')
			{
				$pagination = $this->route;

				$pagination['path'] .= '/{page}';

				unset($pagination['defaults'][$this->page]);

				$routes[$service_name . '_pagination'] = $pagination;
			}
		}

		return $routes;
	}
*/
}
