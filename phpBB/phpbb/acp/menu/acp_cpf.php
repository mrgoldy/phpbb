<?php

namespace phpbb\acp\menu;

class acp_cpf extends acp_profiles
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_profile');
	}

	public function route()
	{
		return [
			'path'		=> '/cpf',
			'defaults'	=> [
				'_controller'	=> 'acp.profile:main',
			],
		];
	}
}
