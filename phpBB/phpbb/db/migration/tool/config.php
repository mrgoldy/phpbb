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
 * Migration tool: config
 *
 * config.add
 * config.remove
 * config.update
 * config.update_if_equals
 */
class config implements tool_interface
{
	/** @var \phpbb\config\config */
	protected $config;

	/**
	 * Constructor.
	 *
	 * @param \phpbb\config\config		$config		Config object
	 * @return void
	 */
	public function __construct(\phpbb\config\config $config)
	{
		$this->config = $config;
	}

	/**
	 * {@inheritdoc}
	 */
	static public function get_name()
	{
		return 'config';
	}

	/**
	 * Add a config setting.
	 *
	 * @param string	$config_name		The config name
	 * @param string	$config_value		The config value
	 * @param bool		$is_dynamic			TRUE if it is dynamic (changes very often) and should not be cached
	 *                            			FALSE otherwise.
	 * @return void
	 */
	public function add($config_name, $config_value, $is_dynamic = false)
	{
		if (!isset($this->config[$config_name]))
		{
			$this->config->set($config_name, $config_value, !$is_dynamic);
		}
	}

	/**
	 * Remove a config setting.
	 *
	 * @param string	$config_name		THe config name
	 * @return void
	 */
	public function remove($config_name)
	{
		if (isset($this->config[$config_name]))
		{
			$this->config->delete($config_name);
		}
	}

	/**
	 * Update a config setting.
	 *
	 * @param string	$config_name		The config name
	 * @param mixed		$config_value		The config value
	 * @throws migration_exception			If the config does not exist
	 * @return void
	 */
	public function update($config_name, $config_value)
	{
		if (!isset($this->config[$config_name]))
		{
			throw new migration_exception('CONFIG_NOT_EXIST', $config_name);
		}

		$this->config->set($config_name, $config_value);
	}

	/**
	 * Update a config setting if it equals to the comparison.
	 *
	 * @param mixed		$comparison			The comparison value
	 * @param string	$config_name		The config name
	 * @param mixed		$config_value		The config value
	 * @throws migration_exception
	 * @return void
	 */
	public function update_if_equals($comparison, $config_name, $config_value)
	{
		if (!isset($this->config[$config_name]))
		{
			throw new migration_exception('CONFIG_NOT_EXIST', $config_name);
		}

		$this->config->set_atomic($config_name, $comparison, $config_value);
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

			case 'update_if_equals':
				$call = 'update_if_equals';

				// Set to the original value if the current value is what we compared to originally
				$arguments = [
					$arguments[2],
					$arguments[1],
					$arguments[0],
				];
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
