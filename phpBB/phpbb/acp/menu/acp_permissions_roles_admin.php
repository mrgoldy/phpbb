<?php

namespace phpbb\acp\menu;

class acp_permissions_roles_admin extends acp_permissions_roles
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_roles') && $this->auth->acl_get('a_aauth');
	}

	public function route()
	{
		return [
			'path'		=> '/permissions/roles/admin',
			'defaults'	=> [
				'_controller'	=> 'acp.permission_roles:main',
				'mode'			=> 'admin_roles',
			],
		];
	}
}


