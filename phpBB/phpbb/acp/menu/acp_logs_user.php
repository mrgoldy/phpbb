<?php

namespace phpbb\acp\menu;

class acp_logs_user extends acp_logs
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_viewlogs');
	}

	public function route()
	{
		return [
			'path'		=> '/logs/user',
			'defaults'	=> [
				'_controller'	=> 'acp.logs:main',
				'mode'			=> 'user',
				'page'			=> 1,
			],
		];
	}
}


