<?php

namespace phpbb\acp\menu;

class acp_mass_email extends acp_general_tasks
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_email') && $this->config['email_enable'];
	}

	public function route()
	{
		return [
			'path'		=> '/mass_email',
			'defaults'	=> [
				'_controller'	=> 'acp.email:main',
			],
		];
	}
}
