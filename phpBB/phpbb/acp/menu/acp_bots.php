<?php

namespace phpbb\acp\menu;

class acp_bots extends acp_general_tasks
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_bots');
	}

	public function route()
	{
		return [
			'path'		=> '/bots',
			'defaults'	=> [
				'_controller'	=> 'acp.bots:main',
			],
		];
	}
}
