<?php

namespace phpbb\acp;

use phpbb\controller\helper;
use phpbb\language\language;
use phpbb\module\cp_manager as modules;

class controller
{
	protected $helper;
	protected $lang;
	protected $modules;
	protected $root_path;
	protected $php_ext;

	public function __construct(helper $helper, language $lang, modules $modules, $root_path, $php_ext)
	{
		$this->helper		= $helper;
		$this->lang			= $lang;
		$this->modules		= $modules;
		$this->root_path	= $root_path;
		$this->php_ext		= $php_ext;
	}

	public function handle($category, $mode)
	{
		// Language
		$this->lang->add_lang('acp/common');
		# Extension languages

		// Functions
		include ($this->root_path . 'includes/functions_acp.' . $this->php_ext);
		include ($this->root_path . 'includes/functions_admin.' . $this->php_ext);

		// Define controller in admin
		$this->helper->set_in_admin(true);

		// Build navigation
		$this->modules->build('acp', $category, $mode);

		// Display mode
		return $this->modules->display();
	}
}
