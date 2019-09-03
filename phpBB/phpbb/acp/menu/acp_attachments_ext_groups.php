<?php

namespace phpbb\acp\menu;

class acp_attachments_ext_groups extends acp_management_attachments
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_attach');
	}

	public function route()
	{
		return [
			'path'		=> '/attachments/extension_groups',
			'defaults'	=> [
				'_controller'	=> 'acp.attachments:main',
				'mode'			=> 'ext_groups',
			],
		];
	}
}
