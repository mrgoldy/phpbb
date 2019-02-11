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

namespace phpbb\acp\general;

use phpbb\exception\http_exception;

use phpbb\config\config;
use phpbb\captcha\factory;
use phpbb\controller\helper;
use phpbb\language\language;
use phpbb\log\log;
use phpbb\request\request;
use phpbb\template\template;
use phpbb\user;

/**
 * ACP Controller: Captcha
 */
class captcha
{
	/** @var config */
	protected $config;

	/** @var factory */
	protected $factory;

	/** @var helper */
	protected $helper;

	/** @var language */
	protected $lang;

	/** @var log */
	protected $log;

	/** @var request */
	protected $request;

	/** @var template */
	protected $template;

	/** @var user */
	protected $user;

	/** @var string */
	protected $slug;

	/**
	 * Constructor.
	 *
	 * @param \phpbb\config\config		$config		Config object
	 * @param \phpbb\captcha\factory	$factory	Captcha factory object
	 * @param \phpbb\controller\helper	$helper		Controller helper object
	 * @param \phpbb\language\language	$lang		Language object
	 * @param \phpbb\log\log			$log		Log object
	 * @param \phpbb\request\request	$request	Request object
	 * @param \phpbb\template\template	$template	Template object
	 * @param \phpbb\user				$user		User object
	 */
	public function __construct(
		config $config,
		factory $factory,
		helper $helper,
		language $lang,
		log $log,
		request $request,
		template $template,
		user $user
	)
	{
		$this->config	= $config;
		$this->factory	= $factory;
		$this->helper	= $helper;
		$this->lang		= $lang;
		$this->log		= $log;
		$this->request	= $request;
		$this->template	= $template;
		$this->user		= $user;
	}

	/**
	 * Set the module slug.
	 *
	 * @param string	$slug	The module slug
	 * @return void
	 */
	public function module_slug($slug)
	{
		$this->slug = $slug;
	}

	public function visual()
	{
		$this->lang->add_lang('acp/board');

		$captchas = $this->factory->get_captcha_types();

		$selected = $this->request->variable('select_captcha', $this->config['captcha_plugin']);
		$selected = (isset($captchas['available'][$selected]) || isset($captchas['unavailable'][$selected])) ? $selected : $this->config['captcha_plugin'];
		$configure = $this->request->variable('configure', false);

		// Oh, they are just here for the view
		if (isset($_GET['captcha_demo']))
		{
			$this->deliver_demo($selected);
		}

		// Delegate
		if ($configure)
		{
			$config_captcha = $this->factory->get_instance($selected);

			return $config_captcha->acp_page($this->slug);
		}
		else
		{
			$config_vars = array(
				'enable_confirm'		=> array(
					'tpl'		=> 'REG_ENABLE',
					'default'	=> false,
					'validate'	=> 'bool',
					'lang'		=> 'VISUAL_CONFIRM_REG',
				),
				'enable_post_confirm'	=> array(
					'tpl'		=> 'POST_ENABLE',
					'default'	=> false,
					'validate'	=> 'bool',
					'lang'		=> 'VISUAL_CONFIRM_POST',
				),
				'confirm_refresh'		=> array(
					'tpl'		=> 'CONFIRM_REFRESH',
					'default'	=> false,
					'validate'	=> 'bool',
					'lang'		=> 'VISUAL_CONFIRM_REFRESH',
				),
				'max_reg_attempts'		=> array(
					'tpl'		=> 'REG_LIMIT',
					'default'	=> 0,
					'validate'	=> 'int:0:99999',
					'lang'		=> 'REG_LIMIT',
				),
				'max_login_attempts'	=> array(
					'tpl'		=> 'MAX_LOGIN_ATTEMPTS',
					'default'	=> 0,
					'validate'	=> 'int:0:99999',
					'lang'		=> 'MAX_LOGIN_ATTEMPTS',
				),
			);

			add_form_key('acp_captcha');

			$submit = $this->request->variable('main_submit', false);
			$errors = $cfg_array = array();

			if ($submit)
			{
				foreach ($config_vars as $config_var => $options)
				{
					$cfg_array[$config_var] = $this->request->variable($config_var, $options['default']);
				}

				validate_config_vars($config_vars, $cfg_array, $errors);

				if (!check_form_key('acp_captcha'))
				{
					$errors[] = $this->lang->lang('FORM_INVALID');
				}
			}

			if ($submit && empty($errors))
			{
				foreach ($cfg_array as $key => $value)
				{
					$this->config->set($key, $value);
				}

				if ($selected !== $this->config['captcha_plugin'])
				{
					// Sanity check
					if (isset($captchas['available'][$selected]))
					{
						/** @var \phpbb\captcha\plugins\captcha_abstract $old_captcha */
						$old_captcha = $this->factory->get_instance($this->config['captcha_plugin']);
						$old_captcha->uninstall();

						$this->config->set('captcha_plugin', $selected);

						/** @var \phpbb\captcha\plugins\captcha_abstract $old_captcha */
						$new_captcha = $this->factory->get_instance($this->config['captcha_plugin']);
						$new_captcha->install();

						$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_CONFIG_VISUAL');
					}
					else
					{
						throw new http_exception(503, $this->lang->lang('CAPTCHA_UNAVAILABLE') . adm_back_link($this->helper->get_current_url()));
					}
				}

				return $this->helper->message($this->lang->lang('CONFIG_UPDATED') . adm_back_link($this->helper->get_current_url()));
			}
			else
			{
				$captcha_select = '';

				foreach ($captchas['available'] as $value => $title)
				{
					$current = ($selected !== false && $value == $selected) ? '" selected="selected' : '';
					$captcha_select .= '<option value="' . $value . $current . '">' . $this->lang->lang($title) . '</option>';
				}

				foreach ($captchas['unavailable'] as $value => $title)
				{
					$current = ($selected !== false && $value == $selected) ? '" selected="selected' : '';
					$captcha_select .= '<option value="' . $value . $current . '" class="disabled-option">' . $this->lang->lang($title) . '</option>';
				}

				$demo_captcha = $this->factory->get_instance($selected);

				foreach ($config_vars as $config_var => $options)
				{
					$this->template->assign_var($options['tpl'], (isset($_POST[$config_var])) ? $this->request->variable($config_var, $options['default']) : $this->config[$config_var]) ;
				}

				$s_error = (bool) count($errors);

				$this->template->assign_vars(array(
					'CAPTCHA_PREVIEW_TPL'	=> $demo_captcha->get_demo_template($this->slug),
					'S_CAPTCHA_HAS_CONFIG'	=> $demo_captcha->has_config(),
					'CAPTCHA_SELECT'		=> $captcha_select,

					'S_ERROR'				=> $s_error,
					'ERROR_MSG'				=> $s_error ? implode('<br />', $errors) : '',

					'U_ACTION'				=> $this->helper->get_current_url(),
				));
			}
		}

		return $this->helper->render('acp_captcha.html', $this->lang->lang('ACP_VC_SETTINGS'));
	}

	/**
	 * Entry point for delivering image CAPTCHAs in the ACP.
	 *
	 * @param string	$selected	The selected captcha plugin
	 * @return void
	 */
	protected function deliver_demo($selected)
	{
		$captcha = $this->factory->get_instance($selected);
		$captcha->init(CONFIRM_REG);
		$captcha->execute_demo();

		garbage_collection();
		exit_handler();
	}
}
