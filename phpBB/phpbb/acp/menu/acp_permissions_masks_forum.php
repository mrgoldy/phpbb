<?php

namespace phpbb\acp\menu;

class acp_permissions_masks_forum extends acp_permissions_masks
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_viewauth');
	}

	public function route()
	{
		return [
			'path'		=> '/permissions/masks/forum',
			'defaults'	=> [
				'_controller'	=> 'acp.permission:main',
				'mode'			=> 'view_forum_global',
			],
		];
	}
}


