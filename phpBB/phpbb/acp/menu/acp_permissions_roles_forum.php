<?php

namespace phpbb\acp\menu;

class acp_permissions_roles_forum extends acp_permissions_roles
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_roles') && $this->auth->acl_get('a_fauth');
	}

	public function route()
	{
		return [
			'path'		=> '/permissions/roles/forum',
			'defaults'	=> [
				'_controller'	=> 'acp.permission_roles:main',
				'mode'			=> 'forum_roles',
			],
		];
	}
}


