<?php

namespace phpbb\acp\menu;

class acp_database_restore extends acp_database
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_backup');
	}

	public function route()
	{
		return [
			'path'		=> '/database/restore',
			'defaults'	=> [
				'_controller'	=> 'acp.database:main',
				'mode'			=> 'restore',
			],
		];
	}
}


