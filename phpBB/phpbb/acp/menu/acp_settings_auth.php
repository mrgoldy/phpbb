<?php

namespace phpbb\acp\menu;

class acp_settings_auth extends acp_configuration_client
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_server');
	}

	public function route()
	{
		return [
			'path'		=> '/settings/auth',
			'defaults'	=> [
				'_controller'	=> 'acp.board:main',
				'mode'			=> 'auth',
			],
		];
	}
}
