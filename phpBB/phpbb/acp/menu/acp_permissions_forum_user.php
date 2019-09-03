<?php

namespace phpbb\acp\menu;

class acp_permissions_forum_user extends acp_permissions_forums
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_authusers') && (
			$this->auth->acl_get('a_mauth') ||
			$this->auth->acl_get('a_fauth')
		);
	}

	public function route()
	{
		return [
			'path'		=> '/permissions/forum/user',
			'defaults'	=> [
				'_controller'	=> 'acp.permissions:main',
				'mode'			=> 'setting_user_local',
			],
		];
	}
}


