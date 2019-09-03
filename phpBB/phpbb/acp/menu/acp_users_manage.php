<?php

namespace phpbb\acp\menu;

class acp_users_manage extends acp_management_users
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_user');
	}

	public function route()
	{
		return [
			'path'		=> '/users/manage/{mode}/{u}',
			'defaults'	=> [
				'_controller'	=> 'acp.users:main',
				'mode'			=> 'overview',
				'u'				=> 0,
			],
		];
	}
}
