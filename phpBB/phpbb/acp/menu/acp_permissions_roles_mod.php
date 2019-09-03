<?php

namespace phpbb\acp\menu;

class acp_permissions_roles_mod extends acp_permissions_roles
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_roles') && $this->auth->acl_get('a_mauth');
	}

	public function route()
	{
		return [
			'path'		=> '/permissions/roles/mod',
			'defaults'	=> [
				'_controller'	=> 'acp.permission_roles:main',
				'mode'			=> 'mod_roles',
			],
		];
	}
}


