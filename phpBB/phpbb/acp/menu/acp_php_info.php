<?php

namespace phpbb\acp\menu;

class acp_php_info extends acp_phpbb
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_phpinfo');
	}

	public function route()
	{
		return [
			'path'		=> '/php',
			'defaults'	=> [
				'_controller'	=> 'acp.php_info:main',
			],
		];
	}
}


