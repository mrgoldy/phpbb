<?php

namespace phpbb\acp\menu;

class acp_users_prune extends acp_management_users
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_userdel');
	}

	public function route()
	{
		return [
			'path'		=> '/users/prune',
			'defaults'	=> [
				'_controller'	=> 'acp.prune:main',
				'mode'			=> 'users',
			],
		];
	}
}
