<?php

namespace phpbb\acp\menu;

class acp_phpbb_update extends acp_phpbb
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_board');
	}

	public function route()
	{
		return [
			'path'		=> '/phpbb/update',
			'defaults'	=> [
				'_controller'	=> 'acp.update:main',
			],
		];
	}
}


