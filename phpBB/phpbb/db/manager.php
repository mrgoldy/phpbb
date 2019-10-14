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

namespace phpbb\db;

use Doctrine\DBAL\DriverManager;
use phpbb\config_php_file;

class manager
{
	/** @var array Mapping phpBB drivers to Doctrine drivers */
	static protected $drivers_map = [
		'mssql_odbc'	=> 'pdo_sqlsrv',
		'mssqlnative'	=> 'pdo_sqlsrv',
		'mysql'			=> 'pdo_mysql',
		'mysqli'		=> 'mysqli',
		'oracle'		=> 'pdo_oci',
		'postgres'		=> 'pdo_pgsql',
		'sqlite3'		=> 'pdo_sqlite',
	];

	/**
	 * Create a connection object
	 *
	 * @param config_php_file	$config_php_file		Config.php file instance
	 * @throws \Doctrine\DBAL\DBALException
	 * @return \Doctrine\DBAL\Connection|connection		Connection object
	 */
	public function connect(config_php_file $config_php_file)
	{
		$config	= new \Doctrine\DBAL\Configuration();
		$cache	= new \Doctrine\Common\Cache\ArrayCache();

		$config->setResultCacheImpl($cache);

		if (defined('DEBUG'))
		{
			$config->setSQLLogger(new \Doctrine\DBAL\Logging\DebugStack());
		}

		$params = [
			'dbname'		=> $config_php_file->get('dbname'),
			'user'			=> $config_php_file->get('dbuser'),
			'password'		=> $config_php_file->get('dbpasswd'),
			'host'			=> $config_php_file->get('dbhost'),
			'port'			=> $config_php_file->get('dbport'),
			'driver'		=> $this->get_doctrine_driver($config_php_file->get('dbms')),
		];

		$params['wrapperClass'] = connection::class;

		return DriverManager::getConnection($params, $config);
	}

	/**
	 * Get the Doctrine driver from the specified phpBB driver.
	 *
	 * @param string	$phpbb_driver	The phpBB driver
	 * @return string					The doctrine driver
	 */
	public function get_doctrine_driver($phpbb_driver)
	{
		$driver = str_replace('phpbb\\db\\driver\\', '', $phpbb_driver);

		return self::$drivers_map[$driver];
	}
}
