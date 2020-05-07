<?php

namespace phpbb\db;

use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\DebugStack;
use phpbb\config_php_file;

/**
 * Doctrine DBAL Connection Factory.
 */
class factory
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
	 * Get doctrine driver.
	 *
	 * @param $phpbb_driver
	 * @return mixed|string
	 * @static
	 */
	public static function get_doctrine_driver(string $phpbb_driver): string
	{
		return self::$drivers_map[str_replace('phpbb\\db\\driver\\', '', $phpbb_driver)] ?? $phpbb_driver;
	}

	/**
	 * Get params from config.
	 *
	 * @param config_php_file $config
	 * @return array
	 * @static
	 */
	public static function get_params_from_config(config_php_file $config): array
	{
		return [
			'dbname'	=> $config->get('dbname'),
			'user'		=> $config->get('dbuser'),
			'password'	=> $config->get('dbpasswd'),
			'host'		=> $config->get('dbhost'),
			'port'		=> $config->get('dbport'),
			'driver'	=> $config->get('dbms'),
		];
	}

	/**
	 * Get connection.
	 *
	 * @param array       $params
	 * @param string|null $cache_dir
	 * @param bool        $debug
	 * @throws DBALException
	 * @return wrapper
	 * @static
	 */
	public static function get_connection(array $params, ?string $cache_dir = null, bool $debug = false): DriverConnection
	{
		$params = array_merge([
			'dbname'		=> '',
			'user'			=> '',
			'password'		=> '',
			'host'			=> '',
			'port'			=> '',
		], $params, [
			'driver'		=> self::get_doctrine_driver($params['driver']),
			'wrapperClass'	=> wrapper::class,
		]);

		$config = new Configuration();
		$cache = new FilesystemCache($cache_dir ?? self::get_cache_dir());

		$config->setResultCacheImpl($cache);

		if ($debug)
		{
			$config->setSQLLogger(new DebugStack());
		}

		return DriverManager::getConnection($params, $config);
	}

	/**
	 * Get cache directory.
	 *
	 * @return string
	 * @static
	 */
	private static function get_cache_dir(): string
	{
		return dirname(__DIR__, 2) . '/cache/db/';
	}
}
