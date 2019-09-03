<?php

namespace phpbb\acp\menu;

class acp_languages_manage extends acp_languages_management
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_language');
	}

	public function route()
	{
		return [
			'path'		=> '/languages/manage',
			'defaults'	=> [
				'_controller'	=> 'acp.language:main',
			],
		];
	}
}
