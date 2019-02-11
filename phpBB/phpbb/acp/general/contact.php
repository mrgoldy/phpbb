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

use phpbb\config\config;
use phpbb\config\db_text;
use phpbb\controller\helper;
use phpbb\language\language;
use phpbb\request\request;
use phpbb\template\template;

/**
 * ACP Controller: Contact page
 */
class contact
{
	/** @var config */
	protected $config;

	/** @var db_text */
	protected $config_text;

	/** @var helper */
	protected $helper;

	/** @var language */
	protected $lang;

	/** @var request */
	protected $request;

	/** @var template */
	protected $template;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $php_ext;

	/**
	 * Constructor.
	 *
	 * @param config	$config			Config object
	 * @param db_text	$config_text	Config text object
	 * @param helper	$helper			Controller helper object
	 * @param language	$lang			Language object
	 * @param request	$request		Request object
	 * @param template	$template		Template object
	 * @param string	$root_path		phpBB root path
	 * @param string	$php_ext		php File extension
	 */
	public function __construct(
		config $config,
		db_text $config_text,
		helper $helper,
		language $lang,
		request $request,
		template $template,
		$root_path,
		$php_ext
	)
	{
		$this->config		= $config;
		$this->config_text	= $config_text;
		$this->helper		= $helper;
		$this->lang			= $lang;
		$this->request		= $request;
		$this->template		= $template;

		$this->root_path	= $root_path;
		$this->php_ext		= $php_ext;
	}

	public function contact()
	{
		// Load posting language file for the BBCode editor
		$this->lang->add_lang(array('acp/board', 'posting'));

		// Create a form key for preventing CSRF attacks
		add_form_key('acp_contact');

		// Set current action
		$u_action = $this->helper->get_current_url();

		// Load the config text
		$data = $this->config_text->get_array(array(
			'contact_admin_info',
			'contact_admin_info_uid',
			'contact_admin_info_bitfield',
			'contact_admin_info_flags',
		));

		// Request form's POST actions (submit or preview)
		$submit = $this->request->is_set_post('submit');
		$preview = $this->request->is_set_post('preview');

		// Request contact page enable status
		$enabled = $this->request->variable('contact_admin_form_enable', (bool) $this->config['contact_admin_form_enable']);

		// If the form is submitted
		if ($submit || $preview)
		{
			// Test if submitted form is valid
			if (!check_form_key('acp_contact'))
			{
				return $this->helper->message($this->lang->lang('FORM_INVALID') . adm_back_link($u_action), array(), 'INFORMATION', 500);
			}

			// Request the form's data
			$data['contact_admin_info'] = $this->request->variable('contact_admin_info', '', true);

			// Generate text for storage
			generate_text_for_storage(
				$data['contact_admin_info'],
				$data['contact_admin_info_uid'],
				$data['contact_admin_info_bitfield'],
				$data['contact_admin_info_flags'],
				!$this->request->variable('disable_bbcode', false),
				!$this->request->variable('disable_magic_url', false),
				!$this->request->variable('disable_smilies', false)
			);

			if ($preview)
			{
				// Generate text for display
				$preview_text = generate_text_for_display(
					$data['contact_admin_info'],
					$data['contact_admin_info_uid'],
					$data['contact_admin_info_bitfield'],
					$data['contact_admin_info_flags']
				);
			}

			if ($submit)
			{
				// Set the config value
				$this->config->set('contact_admin_form_enable', $enabled);

				// Set the config text values
				$this->config_text->set_array($data);

				// Display success message and provide a link back to the previous page
				return $this->helper->message($this->lang->lang('CONTACT_US_INFO_UPDATED') . adm_back_link($u_action));
			}
		}

		// Generate text for edit
		$edit_data = generate_text_for_edit(
			$data['contact_admin_info'],
			$data['contact_admin_info_uid'],
			$data['contact_admin_info_flags']
		);

		// Include display functions for displaying custom BBCodes
		if (!function_exists('display_custom_bbcodes'))
		{
			include($this->root_path . 'includes/functions_display.' . $this->php_ext);
		}

		// Display custom bbcodes
		display_custom_bbcodes();

		// Set output variables for display in the template
		$this->template->assign_vars(array(
			'CONTACT_ENABLED'	=> (bool) $enabled,

			'CONTACT_US_INFO'			=> $edit_data['text'],
			'CONTACT_US_INFO_PREVIEW'	=> !empty($preview_text) ? $preview_text : '',

			'S_BBCODE_DISABLE_CHECKED'		=> !$edit_data['allow_bbcode'],
			'S_SMILIES_DISABLE_CHECKED'		=> !$edit_data['allow_smilies'],
			'S_MAGIC_URL_DISABLE_CHECKED'	=> !$edit_data['allow_urls'],

			'BBCODE_STATUS'			=> $this->lang->lang('BBCODE_IS_ON', '<a href="' . $this->helper->route('phpbb_help_bbcode_controller') . '">', '</a>'),
			'SMILIES_STATUS'		=> $this->lang->lang('SMILIES_ARE_ON'),
			'IMG_STATUS'			=> $this->lang->lang('IMAGES_ARE_ON'),
			'FLASH_STATUS'			=> $this->lang->lang('FLASH_IS_ON'),
			'URL_STATUS'			=> $this->lang->lang('URL_IS_ON'),

			'S_BBCODE_ALLOWED'		=> true,
			'S_SMILIES_ALLOWED'		=> true,
			'S_BBCODE_IMG'			=> true,
			'S_BBCODE_FLASH'		=> true,
			'S_LINKS_ALLOWED'		=> true,
		));

		// Render the page
		return $this->helper->render('acp_contact.html', $this->lang->lang('ACP_CONTACT_SETTINGS'));
	}
}
