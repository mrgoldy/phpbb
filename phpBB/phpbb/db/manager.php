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
use phpbb\install\helper\config;

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
	 * Create a connection object.
	 *
	 * @param array		$params		The connection parameters
	 * @param
	 * @throws \Doctrine\DBAL\DBALException
	 * @return \Doctrine\DBAL\Connection|connection			Connection object
	 */
	public function get_connection(array $params)
	{
		$params = array_merge([
			'dbname'		=> '',
			'user'			=> '',
			'password'		=> '',
			'host'			=> '',
			'port'			=> '',
			'driver'		=> '',
		], $params, [
			'wrapperClass'	=> connection::class,
		]);

		$params['driver'] = $this->get_doctrine_driver($params['driver']);

		$config	= new \Doctrine\DBAL\Configuration();
		$cache	= new \Doctrine\Common\Cache\ArrayCache();

		$config->setResultCacheImpl($cache);

		if (defined('DEBUG'))
		{
			$config->setSQLLogger(new \Doctrine\DBAL\Logging\DebugStack());
		}

		return DriverManager::getConnection($params, $config);
	}

	/**
	 * Create a connection object from a config file.
	 *
	 * @param config_php_file|config	$config_php_file	Config.php file instance
	 * @param
	 * @throws \Doctrine\DBAL\DBALException
	 * @return \Doctrine\DBAL\Connection|connection			Connection object
	 */
	public function get_connection_from_config($config_php_file)
	{
		$params = [
			'dbname'		=> $config_php_file->get('dbname'),
			'user'			=> $config_php_file->get('dbuser'),
			'password'		=> $config_php_file->get('dbpasswd'),
			'host'			=> $config_php_file->get('dbhost'),
			'port'			=> $config_php_file->get('dbport'),
			'driver'		=> $config_php_file->get('dbms'),
		];

		return $this->get_connection($params);
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
