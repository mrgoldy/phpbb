<?php

namespace phpbb\acp\menu;

class acp_permissions_global_admins extends acp_permissions_global
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_aauth') && (
			$this->auth->acl_get('a_authusers') ||
			$this->auth->acl_get('a_authgroups')
		);
	}

	public function route()
	{
		return [
			'path'		=> '/permissions/global/admin',
			'defaults'	=> [
				'_controller'	=> 'acp.permissions:main',
				'mode'			=> 'setting_admin_global',
			],
		];
	}
}


