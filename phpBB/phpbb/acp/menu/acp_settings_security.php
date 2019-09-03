<?php

namespace phpbb\acp\menu;

class acp_settings_security extends acp_configuration_server
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_server');
	}

	public function route()
	{
		return [
			'path'		=> '/settings/security',
			'defaults'	=> [
				'_controller'	=> 'acp.board:main',
				'mode'			=> 'security',
			],
		];
	}
}
