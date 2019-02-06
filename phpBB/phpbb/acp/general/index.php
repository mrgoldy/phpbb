<?php

namespace phpbb\acp\general;

use phpbb\controller\helper;

class index
{
	protected $helper;

	public function __construct(helper $helper)
	{
		$this->helper = $helper;
	}

	public function main()
	{
		return $this->helper->render('acp/index.html', 'Overview');
	}
}
