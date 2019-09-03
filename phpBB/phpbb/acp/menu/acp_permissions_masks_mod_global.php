<?php

namespace phpbb\acp\menu;

class acp_permissions_masks_mod_global extends acp_permissions_masks
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_viewauth');
	}

	public function route()
	{
		return [
			'path'		=> '/permissions/masks/mod/global',
			'defaults'	=> [
				'_controller'	=> 'acp.permission:main',
				'mode'			=> 'view_mod_global',
			],
		];
	}
}


