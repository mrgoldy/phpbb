<?php

namespace phpbb\acp\menu;

class acp_forums_manage extends acp_management_users
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_forum');
	}

	public function route()
	{
		return [
			'path'		=> '/forums/manage/{p}/{action}/{f}',
			'defaults'	=> [
				'_controller'	=> 'acp.forums:main',
				'action'		=> '',
				'p'				=> 0,
				'f'				=> 0,
			],
		];
	}
}
