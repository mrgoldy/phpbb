<?php

namespace phpbb\acp\menu;

class acp_ranks extends acp_profiles
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_ranks');
	}

	public function route()
	{
		return [
			'path'		=> '/ranks',
			'defaults'	=> [
				'_controller'	=> 'acp.ranks:main',
			],
		];
	}
}
