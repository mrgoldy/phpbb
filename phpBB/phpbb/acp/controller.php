<?php

namespace phpbb\acp;

use phpbb\exception\http_exception;

use phpbb\auth\auth;
use phpbb\controller\helper;
use phpbb\language\language;
use phpbb\module\cp_manager;
use phpbb\path_helper;
use phpbb\template\template;
use phpbb\user;

/**
 * ACP Controller
 */
class controller
{
	/** @var auth */
	protected $auth;

	/** @var helper */
	protected $helper;

	/** @var language */
	protected $lang;

	/** @var cp_manager */
	protected $modules;

	/** @var path_helper */
	protected $path_helper;

	/** @var template */
	protected $template;

	/** @var user */
	protected $user;

	/** @var string */
	protected $admin_path;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $php_ext;

	/**
	 * Constructor.
	 *
	 * @param auth			$auth
	 * @param helper		$helper
	 * @param language		$lang
	 * @param cp_manager	$modules
	 * @param path_helper	$path_helper
	 * @param template		$template
	 * @param user			$user
	 */
	public function __construct(auth $auth, helper $helper, language $lang, cp_manager $modules, path_helper $path_helper, template $template, user $user)
	{
		$this->auth			= $auth;
		$this->helper		= $helper;
		$this->lang			= $lang;
		$this->modules		= $modules;
		$this->path_helper	= $path_helper;
		$this->template		= $template;
		$this->user			= $user;

		$this->root_path	= $path_helper->get_phpbb_root_path();
		$this->php_ext		= $path_helper->get_php_ext();
		$this->admin_path	= $this->root_path . $path_helper->get_adm_relative_path();
	}

	/**
	 * Handle ACP pages.
	 *
	 * @param string	$slug		The module slug
	 * @param string	$page		The page number for pagination
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function handle($slug, $page)
	{
		define('ADMIN_START', true);
		define('NEED_SID', true);

		// Language
		$this->lang->add_lang('acp/common');
		# Extension languages // @todo

		// Functions
		include ($this->root_path . 'includes/functions_acp.' . $this->php_ext);
		include ($this->root_path . 'includes/functions_admin.' . $this->php_ext);

		// Have they authenticated (again) as an admin for this session?
		if (!isset($this->user->data['session_admin']) || !$this->user->data['session_admin'])
		{
			login_box('', $this->lang->lang('LOGIN_ADMIN_CONFIRM'), $this->lang->lang('LOGIN_ADMIN_SUCCESS'), true, false);
		}

		// Is user any type of admin? No, then stop here, each script needs to
		// check specific permissions but this is a catchall
		if (!$this->auth->acl_get('a_'))
		{
			throw new http_exception(403, 'NO_ADMIN');
		}

		// We define the admin variables now, because the user is now able to use the admin related features...
		define('IN_ADMIN', true);

		// Set custom style for admin area
		$this->template->set_custom_style(array(
			array(
				'name' 		=> 'adm',
				'ext_path' 	=> 'style',
			),
		), $this->admin_path . 'style');

		$this->template->assign_var('T_ASSETS_PATH', $this->path_helper->update_web_root_path($this->root_path) . 'assets');
		$this->template->assign_var('T_TEMPLATE_PATH', $this->path_helper->update_web_root_path($this->admin_path) . 'style');

		// Define controller in admin
		$this->helper->set_in_admin(true);

		// Build navigation and display mode
		return $this->modules->build('acp', $slug);
	}
}
