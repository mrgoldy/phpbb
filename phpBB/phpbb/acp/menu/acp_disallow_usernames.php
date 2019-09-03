<?php

namespace phpbb\acp\menu;

class acp_disallow_usernames extends acp_profiles
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_names');
	}

	public function route()
	{
		return [
			'path'		=> '/disallow/usernames',
			'defaults'	=> [
				'_controller'	=> 'acp.disallow:main',
			],
		];
	}
}
