<?php

namespace phpbb\acp\menu;

class acp_groups_position extends acp_management_users
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_group');
	}

	public function route()
	{
		return [
			'path'		=> '/groups/position',
			'defaults'	=> [
				'_controller'	=> 'acp.groups:main',
				'mode'			=> 'position',
			],
		];
	}
}
