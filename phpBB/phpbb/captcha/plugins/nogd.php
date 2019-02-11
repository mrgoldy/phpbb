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

namespace phpbb\captcha\plugins;

use phpbb\exception\http_exception;

class nogd extends captcha_abstract
{
	public function is_available()
	{
		return true;
	}

	public function get_name()
	{
		return 'CAPTCHA_NO_GD';
	}

	/**
	* @return string the name of the class used to generate the captcha
	*/
	function get_generator_class()
	{
		return '\\phpbb\\captcha\\non_gd';
	}

	function acp_page($id)
	{
		global $phpbb_container;

		/** @var \phpbb\controller\helper $helper */
		$helper = $phpbb_container->get('controller.helper');

		/** @var \phpbb\language\language $language */
		$language = $phpbb_container->get('language');

		throw new http_exception(404, $language->lang('CAPTCHA_NO_OPTIONS') . adm_back_link($helper->route('phpbb_acp_controller', array('slug' => $id))));
	}
}
