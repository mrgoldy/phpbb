<?php

namespace phpbb\acp\menu;

class acp_topic_icons extends acp_posting
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_icons');
	}

	public function route()
	{
		return [
			'path'		=> '/topic_icons',
			'defaults'	=> [
				'_controller'	=> 'acp.icons:main',
				'mode'			=> 'icons',
				'page'			=> 1,
			],
		];
	}
}
