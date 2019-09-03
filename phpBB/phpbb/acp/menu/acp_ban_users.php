<?php

namespace phpbb\acp\menu;

class acp_ban_users extends acp_management_bans
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_ban');
	}

	public function route()
	{
		return [
			'path'		=> '/ban/users',
			'defaults'	=> [
				'_controller'	=> 'acp.ban:main',
				'mode'			=> 'user',
			],
		];
	}
}
