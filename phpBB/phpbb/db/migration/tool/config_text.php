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

namespace phpbb\db\migration\tool;

use phpbb\db\exception\migration_exception;

/**
 * Migration tool: config_text
 *
 * config_text.add
 * config_text.remove
 * config_text.update
 */
class config_text implements tool_interface
{
	/** @var \phpbb\config\db_text */
	protected $config_text;

	/**
	 * Constructor.
	 *
	 * @param \phpbb\config\db_text		$config_text	Config text object
	 * @return void
	 */
	public function __construct(\phpbb\config\db_text $config_text)
	{
		$this->config_text = $config_text;
	}

	/**
	 * {@inheritdoc}
	 */
	static public function get_name()
	{
		return 'config_text';
	}

	/**
	 * Add a config_text setting.
	 *
	 * @param string	$config_name		The config name
	 * @param string	$config_value		The config value
	 * @return void
	 */
	public function add($config_name, $config_value)
	{
		if (is_null($this->config_text->get($config_name)))
		{
			$this->config_text->set($config_name, $config_value);
		}
	}

	/**
	 * Remove a config_text setting.
	 *
	 * @param string	$config_name		The config name
	 * @return void
	 */
	public function remove($config_name)
	{
		if (!is_null($this->config_text->get($config_name)))
		{
			$this->config_text->delete($config_name);
		}
	}

	/**
	 * Update a config_text setting.
	 *
	 * @param string	$config_name		The config name
	 * @param mixed		$config_value		The config value
	 * @throws migration_exception			If the config does not exist
	 * @return void
	 */
	public function update($config_name, $config_value)
	{
		if (is_null($this->config_text->get($config_name)))
		{
			throw new migration_exception('CONFIG_NOT_EXIST', $config_name);
		}

		$this->config_text->set($config_name, $config_value);
	}

	/**
	 * {@inheritdoc}
	 */
	public function reverse()
	{
		$call = false;
		$arguments = func_get_args();
		$original_call = array_shift($arguments);

		switch ($original_call)
		{
			case 'add':
				$call = 'remove';
			break;

			case 'remove':
				$call = 'add';

				if (count($arguments) === 1)
				{
					$arguments[] = '';
				}
			break;

			case 'reverse':
				// Reversing a reverse is just the call itself
				$call = array_shift($arguments);
			break;
		}

		if ($call)
		{
			call_user_func_array([&$this, $call], $arguments);
		}
	}
}
