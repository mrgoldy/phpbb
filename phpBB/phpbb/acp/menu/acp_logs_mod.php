<?php

namespace phpbb\acp\menu;

class acp_logs_mod extends acp_logs
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_viewlogs');
	}

	public function route()
	{
		return [
			'path'		=> '/logs/mod',
			'defaults'	=> [
				'_controller'	=> 'acp.logs:main',
				'mode'			=> 'mod',
				'page'			=> 1,
			],
		];
	}
}


