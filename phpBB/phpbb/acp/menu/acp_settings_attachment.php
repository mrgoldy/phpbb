<?php

namespace phpbb\acp\menu;

class acp_settings_attachment extends acp_configuration_board
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_attach');
	}

	public function route()
	{
		return [
			'path'		=> '/settings/attachment',
			'defaults'	=> [
				'_controller'	=> 'acp.attachments:main',
				'mode'			=> 'attach',
			],
		];
	}
}
