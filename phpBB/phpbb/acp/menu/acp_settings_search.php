<?php

namespace phpbb\acp\menu;

class acp_settings_search extends acp_configuration_server
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_server');
	}

	public function route()
	{
		return [
			'path'		=> '/settings/search',
			'defaults'	=> [
				'_controller'	=> 'acp.board:main',
				'mode'			=> 'search',
			],
		];
	}
}
