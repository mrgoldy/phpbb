<?php

namespace phpbb\acp\menu;

use phpbb\cp\menu\item_interface;

class acp implements item_interface
{
	protected $auth;
	protected $config;
	protected $request;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\config\config $config,
		\phpbb\request\request $request
	)
	{
		$this->auth		= $auth;
		$this->config	= $config;
		$this->request	= $request;

	}

	public function name()
	{
		return 'acp';
	}

	public function auth()
	{
		return $this->auth->acl_get('a_');
	}

	public function route()
	{
		return '';
	}
}
