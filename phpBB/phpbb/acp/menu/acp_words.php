<?php

namespace phpbb\acp\menu;

class acp_words extends acp_posting
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_words');
	}

	public function route()
	{
		return [
			'path'		=> '/disallow/words',
			'defaults'	=> [
				'_controller'	=> 'acp.words:main',
			],
		];
	}
}
