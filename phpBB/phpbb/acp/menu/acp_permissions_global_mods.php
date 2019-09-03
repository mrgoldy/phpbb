<?php

namespace phpbb\acp\menu;

class acp_permissions_global_mods extends acp_permissions_global
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_mauth') && (
			$this->auth->acl_get('a_authusers') ||
			$this->auth->acl_get('a_authgroups')
		);
	}

	public function route()
	{
		return [
			'path'		=> '/permissions/global/mod',
			'defaults'	=> [
				'_controller'	=> 'acp.permissions:main',
				'mode'			=> 'setting_mod_global',
			],
		];
	}
}


