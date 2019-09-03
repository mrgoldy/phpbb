<?php

namespace phpbb\acp\menu;

class acp_permissions_global_group extends acp_permissions_global
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_authgroup') && (
			$this->auth->acl_get('a_aauth') ||
			$this->auth->acl_get('a_mauth') ||
			$this->auth->acl_get('a_uauth')
		);
	}

	public function route()
	{
		return [
			'path'		=> '/permissions/global/group',
			'defaults'	=> [
				'_controller'	=> 'acp.permissions:main',
				'mode'			=> 'setting_group_global',
			],
		];
	}
}


