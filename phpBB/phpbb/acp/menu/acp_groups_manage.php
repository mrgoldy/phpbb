<?php

namespace phpbb\acp\menu;

class acp_groups_manage extends acp_management_users
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_group');
	}

	public function route()
	{
		return [
			'path'		=> '/groups/manage/{action}/{g}',
			'defaults'	=> [
				'_controller'	=> 'acp.groups:main',
				'mode'			=> 'manage',
				'action'		=> '',
				'g'				=> 0,
				'page'			=> 1,
			],
		];
	}
}
