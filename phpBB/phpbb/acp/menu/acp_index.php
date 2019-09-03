<?php

namespace phpbb\acp\menu;

class acp_index extends acp_configuration
{
	public function auth()
	{
		return parent::auth();
	}

	public function route()
	{
		return [
			'path'		=> '/index',
			'defaults'	=> [
				'_controller'	=> 'acp.main:main',
			],
		];
	}
}
