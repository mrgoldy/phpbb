<?php

namespace phpbb\acp\menu;

class acp_permissions_global_user extends acp_permissions_global
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_authusers') && (
			$this->auth->acl_get('a_aauth') ||
			$this->auth->acl_get('a_mauth') ||
			$this->auth->acl_get('a_uauth')
		);
	}

	public function route()
	{
		return [
			'path'		=> '/permissions/global/user',
			'defaults'	=> [
				'_controller'	=> 'acp.permissions:main',
				'mode'			=> 'setting_user_global',
			],
		];
	}
}


