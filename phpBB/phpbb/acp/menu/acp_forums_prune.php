<?php

namespace phpbb\acp\menu;

class acp_forums_prune extends acp_management_users
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_prune');
	}

	public function route()
	{
		return [
			'path'		=> '/forums/prune',
			'defaults'	=> [
				'_controller'	=> 'acp.prune:main',
				'mode'			=> 'forums',
			],
		];
	}
}
