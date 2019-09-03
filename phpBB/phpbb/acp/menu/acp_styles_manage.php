<?php

namespace phpbb\acp\menu;

class acp_styles_manage extends acp_styles_management
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_styles');
	}

	public function route()
	{
		return [
			'path'		=> '/styles/manage',
			'defaults'	=> [
				'_controller'	=> 'acp.styles:main',
				'mode'			=> 'manage',
			],
		];
	}
}
