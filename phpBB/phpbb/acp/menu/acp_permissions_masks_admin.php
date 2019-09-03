<?php

namespace phpbb\acp\menu;

class acp_permissions_masks_admin extends acp_permissions_masks
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_viewauth');
	}

	public function route()
	{
		return [
			'path'		=> '/permissions/masks/admin',
			'defaults'	=> [
				'_controller'	=> 'acp.permission:main',
				'mode'			=> 'view_admin_global',
			],
		];
	}
}


