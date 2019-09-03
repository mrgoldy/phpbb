<?php

namespace phpbb\acp\menu;

class acp_smilies extends acp_posting
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_icons');
	}

	public function route()
	{
		return [
			'path'		=> '/smilies',
			'defaults'	=> [
				'_controller'	=> 'acp.icons:main',
				'mode'			=> 'smilies',
				'page'			=> 1,
			],
		];
	}
}
