<?php

namespace phpbb\acp\menu;

class acp_attachments_manage extends acp_management_attachments
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_attach');
	}

	public function route()
	{
		return [
			'path'		=> '/attachments/manage',
			'defaults'	=> [
				'_controller'	=> 'acp.attachments:main',
				'mode'			=> 'manage',
				'page'			=> 1,
			],
		];
	}
}
