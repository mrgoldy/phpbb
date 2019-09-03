<?php

namespace phpbb\acp\menu;

class acp_settings_board extends acp_configuration_board
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_board');
	}

	public function route()
	{
		return [
			'path'		=> '/settings/board',
			'defaults'	=> [
				'_controller'	=> 'acp.board:main',
				'mode'			=> 'board',
			],
		];
	}
}
