<?php

namespace phpbb\acp\menu;

class acp_reasons extends acp_general_tasks
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_reasons');
	}

	public function route()
	{
		return [
			'path'		=> '/reasons',
			'defaults'	=> [
				'_controller'	=> 'acp.reasons:main',
			],
		];
	}
}
