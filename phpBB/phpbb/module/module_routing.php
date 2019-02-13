<?php

namespace phpbb\module;

class module_routing
{
	protected $helper;

	public function __construct(\phpbb\controller\helper $helper)
	{
		$this->helper = $helper;
	}

	public function route($class, $slug, array $params = [])
	{
		return $this->build($class, $slug, $params);
	}

	public function pagination($class, $slug, $page, array $params = [])
	{
		return $this->build($class, $slug, $params, $page);
	}

	protected function build($class, $slug, array $params, $page = 0)
	{
		$class = (string) $class;
		$slug = (string) $slug;
		$page = (int) $page;

		$params['slug'] = $slug;
		$route = 'phpbb_' . $class . '_';

		switch ($page)
		{
			case 0:
				$route .= 'controller';
			break;

			default:
				$route .= 'pagination';
				$params['page'] = $page;
			break;
		}

		switch ($slug)
		{
			case '':
				return $this->helper->route($route);
			break;

			default:
				return $this->helper->route($route, $params);
			break;
		}
	}
}
