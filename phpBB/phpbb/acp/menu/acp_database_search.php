<?php

namespace phpbb\acp\menu;

class acp_database_search extends acp_database
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_search');
	}

	public function route()
	{
		return [
			'path'		=> '/database/search',
			'defaults'	=> [
				'_controller'	=> 'acp.search:main',
				'mode'			=> 'index',
			],
		];
	}
}


