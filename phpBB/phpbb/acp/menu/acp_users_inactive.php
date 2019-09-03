<?php

namespace phpbb\acp\menu;

class acp_users_inactive extends acp_management_users
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_user');
	}

	public function route()
	{
		return [
			'path'		=> '/users/inactive',
			'defaults'	=> [
				'_controller'	=> 'acp.inactive:main',
				'page'			=> 1,
			],
		];
	}
}
