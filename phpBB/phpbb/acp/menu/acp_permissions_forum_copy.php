<?php

namespace phpbb\acp\menu;

class acp_permissions_forum_copy extends acp_permissions_forums
{
	public function auth()
	{
		return parent::auth() &&
			$this->auth->acl_get('a_fauth') &&
			$this->auth->acl_get('a_mauth') &&
			$this->auth->acl_get('a_authusers') &&
			$this->auth->acl_get('a_authgroups');
	}

	public function route()
	{
		return [
			'path'		=> '/permissions/forum/copy',
			'defaults'	=> [
				'_controller'	=> 'acp.permissions:main',
				'mode'			=> 'setting_forum_copy',
			],
		];
	}
}


