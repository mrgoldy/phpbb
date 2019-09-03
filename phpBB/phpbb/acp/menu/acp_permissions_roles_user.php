<?php

namespace phpbb\acp\menu;

class acp_permissions_roles_user extends acp_permissions_roles
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_roles') && $this->auth->acl_get('a_uauth');
	}

	public function route()
	{
		return [
			'path'		=> '/permissions/roles/user',
			'defaults'	=> [
				'_controller'	=> 'acp.permission_roles:main',
				'mode'			=> 'user_roles',
			],
		];
	}
}


