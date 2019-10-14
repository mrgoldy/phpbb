<?php
/**
 *
 * This file is part of the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 * For full copyright and license information, please see
 * the docs/CREDITS.txt file.
 *
 */

namespace phpbb\db\exception;

class migration_exception extends \Exception
{
	protected $parameters;

	public function __construct()
	{
		$parameters = func_get_args();
		$message = array_shift($parameters);
		parent::__construct($message);

		$this->parameters = $parameters;
	}

	public function __toString()
	{
		return $this->message . ': ' . var_export($this->parameters, true);
	}

	public function getParameters()
	{
		return $this->parameters;
	}

	public function getLocalisedMessage(\phpbb\user $user)
	{
		$parameters = $this->getParameters();
		array_unshift($parameters, $this->getMessage());

		return call_user_func_array([$user, 'lang'], $parameters);
	}
}
