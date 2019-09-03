<?php

namespace phpbb\acp\menu;

class acp_bbcodes extends acp_posting
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_bbcode');
	}

	public function route()
	{
		return [
			'path'		=> '/bbcodes',
			'defaults'	=> [
				'_controller'	=> 'acp.bbcodes:main',
				'page'			=> 1,
			],
		];
	}
}
