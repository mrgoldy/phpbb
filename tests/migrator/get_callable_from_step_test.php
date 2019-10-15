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

class get_callable_from_step_test extends phpbb_database_test_case
{
	public function setUp(): void
	{
		global $phpbb_root_path, $php_ext, $table_prefix, $phpbb_log;

		parent::setUp();

		$phpbb_log = $this->getMockBuilder('\phpbb\log\log')->disableOriginalConstructor()->getMock();
		$db = $this->new_dbal();
		$lang_loader = new \phpbb\language\language_file_loader($phpbb_root_path, $php_ext);
		$lang = new \phpbb\language\language($lang_loader);
		$user = $this->getMockBuilder('\phpbb\user')->disableOriginalConstructor()->getMock();
		$module_manager = new \phpbb\module\module_manager(
			$this->getMockBuilder('\phpbb\cache\driver\dummy')->disableOriginalConstructor()->getMock(),
			$db,
			new phpbb_mock_extension_manager($phpbb_root_path),
			'phpbb_modules',
			$phpbb_root_path,
			$php_ext
		);

		$module_tool = new \phpbb\db\migration\tool\module($db, $lang, $phpbb_log, $module_manager, $user, 'phpbb_modules');
		$this->migrator = new \phpbb\db\migrator(
			new \phpbb\config\config(array()),
			new phpbb_mock_container_builder(),
			$db,
			new \phpbb\db\tools($db),
			new phpbb_mock_event_dispatcher(),
			new \phpbb\db\migration\helper\helper(),
			'phpbb_migrations',
			$table_prefix,
			$phpbb_root_path,
			$php_ext,
			array($module_tool)
		);

		if (!$module_tool->exists('acp', 0, 'new_module_langname'))
		{
			$module_tool->add('acp', 0, array(
				'module_basename'	=> 'new_module_basename',
				'module_langname'	=> 'new_module_langname',
				'module_mode'		=> 'settings',
				'module_auth'		=> '',
				'module_display'	=> true,
				'before'			=> false,
				'after'				=> false,
			));
			$this->module_added = true;
		}
	}

	public function getDataSet()
	{
		return $this->createXMLDataSet(dirname(__FILE__).'/../dbal/fixtures/migrator.xml');
	}

	public function get_callable_from_step_provider()
	{
		return array(
			array(
				array('if', array(
					false,
					array('permission.add', array('some_data')),
				)),
				true, // expects false
			),
			array(
				array('if', array(
					array('module.exists', array(
						'mcp',
						'RANDOM_PARENT',
						'RANDOM_MODULE'
					)),
					array('permission.add', array('some_data')),
				)),
				true, // expects false
			),
			array(
				array('if', array(
					array('module.exists', array(
						'acp',
						0,
						'new_module_langname'
					)),
					array('module.add', array(
						'acp',
						0,
						'module_basename'	=> 'new_module_basename2',
						'module_langname'	=> 'new_module_langname2',
						'module_mode'		=> 'settings',
						'module_auth'		=> '',
						'module_display'	=> true,
						'before'			=> false,
						'after'				=> false,
					)),
				)),
				false, // expects false
			),
		);
	}

	/**
	 * @dataProvider get_callable_from_step_provider
	 */
	public function test_get_callable_from_step($step, $expects_false)
	{
		if ($expects_false)
		{
			$this->assertFalse($this->call_get_callable_from_step($step));
		}
		else
		{
			$this->assertNotFalse($this->call_get_callable_from_step($step));
		}
	}

	protected function call_get_callable_from_step($step)
	{
		$class = new ReflectionClass($this->migrator);
		$method = $class->getMethod('get_callable_from_step');
		$method->setAccessible(true);
		return $method->invokeArgs($this->migrator, array($step));
	}
}
