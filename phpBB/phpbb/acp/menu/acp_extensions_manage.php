<?php

namespace phpbb\acp\menu;

class acp_extensions_manage extends acp_extensions_management
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_extensions');
	}

	public function route()
	{
		return [
			'path'			=> '/extensions/manage/{action}/{ext}',
			'defaults'		=> [
				'_controller'	=> 'acp.extensions:manage',
				'action'		=> 'list',
				'ext'			=> '',
			],
			'requirements'	=> [
				'ext'			=> '[^?]+',
			],
		];
	}
}
