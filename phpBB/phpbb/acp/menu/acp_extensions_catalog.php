<?php

namespace phpbb\acp\menu;

class acp_extensions_catalog extends acp_extensions_management
{
	public function auth()
	{
		return parent::auth() && $this->auth->acl_get('a_extensions');
	}

	public function route()
	{
		return [
			'path'		=> '/extensions/catalog/{action}',
			'defaults'	=> [
				'_controller'	=> 'acp.extensions:catalog',
				'action'		=> 'list',
				'page'			=> 1,
			],
		];
	}
}
