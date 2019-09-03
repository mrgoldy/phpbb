<?php

namespace phpbb\acp\menu;

class acp_permissions_index extends acp_permissions
{
	public function auth()
	{
		return parent::auth() && (
			$this->auth->acl_get('a_authusers') ||
			$this->auth->acl_get('a_authgroups') ||
			$this->auth->acl_get('a_viewauth')
		);
	}

	public function route()
	{
		return [
			'path'			=> '/permissions/{mode}',
			'defaults'		=> [
				'_controller'	=> 'acp.permissions:main',
				'mode'			=> 'intro',
			],
			'requirements'	=> [
				'mode'			=> 'intro|trace',
			],
		];
	}
}


