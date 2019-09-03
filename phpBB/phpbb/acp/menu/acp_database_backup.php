<?php

namespace phpbb\acp\menu;

class acp_database_backup extends acp_database
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_backup');
	}

	public function route()
	{
		return [
			'path'		=> '/database/backup',
			'defaults'	=> [
				'_controller'	=> 'acp.database:main',
				'mode'			=> 'backup',
			],
		];
	}
}


