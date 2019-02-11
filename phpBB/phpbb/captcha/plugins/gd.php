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

class gd extends captcha_abstract
{
	var $captcha_vars = array(
		'captcha_gd_x_grid'				=> 'CAPTCHA_GD_X_GRID',
		'captcha_gd_y_grid'				=> 'CAPTCHA_GD_Y_GRID',
		'captcha_gd_foreground_noise'	=> 'CAPTCHA_GD_FOREGROUND_NOISE',
//		'captcha_gd'					=> 'CAPTCHA_GD_PREVIEWED',
		'captcha_gd_wave'				=> 'CAPTCHA_GD_WAVE',
		'captcha_gd_3d_noise'			=> 'CAPTCHA_GD_3D_NOISE',
		'captcha_gd_fonts'				=> 'CAPTCHA_GD_FONTS',
	);

	public function is_available()
	{
		return @extension_loaded('gd');
	}

	/**
	* @return string the name of the class used to generate the captcha
	*/
	function get_generator_class()
	{
		return '\\phpbb\\captcha\\gd';
	}

	/**
	*  API function
	*/
	function has_config()
	{
		return true;
	}

	public function get_name()
	{
		return 'CAPTCHA_GD';
	}

	function acp_page($id)
	{
		global $user, $template, $phpbb_log, $request;
		global $config, $phpbb_container;

		$helper = $phpbb_container->get('controller.helper');
		$language = $phpbb_container->get('language');

		$user->add_lang('acp/board');

		$form_key = 'acp_captcha';
		add_form_key($form_key);

		$submit = $request->variable('submit', '');

		if ($submit && check_form_key($form_key))
		{
			$captcha_vars = array_keys($this->captcha_vars);
			foreach ($captcha_vars as $captcha_var)
			{
				$value = $request->variable($captcha_var, 0);

				if ($value >= 0)
				{
					$config->set($captcha_var, $value);
				}
			}

			$phpbb_log->add('admin', $user->data['user_id'], $user->ip, 'LOG_CONFIG_VISUAL');

			$helper->message($language->lang('CONFIG_UPDATED') . adm_back_link($helper->get_current_url()));
		}
		else if ($submit)
		{
			throw new http_exception(400, $language->lang('FORM_INVALID') . adm_back_link($helper->get_current_url()));
		}
		else
		{
			foreach ($this->captcha_vars as $captcha_var => $template_var)
			{
				$var = (isset($_REQUEST[$captcha_var])) ? $request->variable($captcha_var, 0) : $config[$captcha_var];
				$template->assign_var($template_var, $var);
			}

			$template->assign_vars(array(
				'CAPTCHA_PREVIEW'	=> $this->get_demo_template($id),
				'CAPTCHA_NAME'		=> $this->get_service_name(),
				'U_ACTION'			=> $helper->get_current_url(),
			));
		}

		return $helper->render('captcha_gd_acp.html', $language->lang('ACP_VC_SETTINGS'));
	}

	function execute_demo()
	{
		global $config, $request;

		$config_old = $config;

		$config = new \phpbb\config\config(array());
		foreach ($config_old as $key => $value)
		{
			$config->set($key, $value);
		}

		foreach ($this->captcha_vars as $captcha_var => $template_var)
		{
			$config->set($captcha_var, $request->variable($captcha_var, (int) $config[$captcha_var]));
		}
		parent::execute_demo();
		$config = $config_old;
	}

}
