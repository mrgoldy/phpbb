<?php

namespace phpbb\acp\menu;

class acp_phpbb_help extends acp_phpbb
{
	public function auth()
	{
		return parent::auth();
	}

	public function route()
	{
		return [
			'path'		=> '/phpbb/help',
			'defaults'	=> [
				'_controller'	=> 'acp.help_phpbb:main',
			],
		];
	}
}


