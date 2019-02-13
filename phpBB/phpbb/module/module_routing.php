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

class module_routing
{
	/** @var \phpbb\controller\helper */
	protected $helper;

	/**
	 * Constructor.
	 *
	 * @param \phpbb\controller\helper	$helper		Controller helper object
	 */
	public function __construct(\phpbb\controller\helper $helper)
	{
		$this->helper = $helper;
	}

	/**
	 * Generate a route for a module.
	 *
	 * @param string	$class		The module class
	 * @param string	$slug		The module slug
	 * @param array		$params		Array with additional parameters
	 * @return string				The module route
	 */
	public function route($class, $slug, array $params = [])
	{
		return $this->build($class, $slug, $params);
	}

	/**
	 * Generate a pagination route for a module.
	 *
	 * @param string	$class		The module class
	 * @param string	$slug		The module slug
	 * @param int		$page		The pagination's page number
	 * @param array		$params		Array with additional parameters
	 * @return string				The module pagination route
	 */
	public function pagination($class, $slug, $page, array $params = [])
	{
		return $this->build($class, $slug, $params, $page);
	}

	/**
	 * Build a route for a module.
	 *
	 * @param string	$class		The module class
	 * @param string	$slug		The module slug
	 * @param array		$params		Array with additional parameters
	 * @param int		$page		The pagination's page number
	 * @return string				The module route
	 */
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
