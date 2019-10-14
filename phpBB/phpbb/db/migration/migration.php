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

namespace phpbb\db\migration;

/**
 * Abstract base class for database migrations.
 *
 * Each migration consists of a set of schema and data changes to be implemented in a subclass.
 * This class provides various utility methods to simplify editing a phpBB installation.
 */
abstract class migration implements migration_interface
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\connection */
	protected $db;

	/** @var \phpbb\db\tools */
	protected $db_tools;

	/** @var string php File extension */
	protected $root_path;

	/** @var string phpBB root path */
	protected $table_prefix;

	/** @var string */
	protected $php_ext;

	/** @var array Errors, if any occurred */
	protected $errors;

	/** @var array List of queries executed through $this->sql_query() */
	protected $queries = [];

	/**
	 * Constructor
	 *
	 * @param \phpbb\config\config		$config				Config object
	 * @param \phpbb\db\connection		$db					Database object
	 * @param \phpbb\db\tools			$db_tools			Database tools object
	 * @param string					$root_path			phpBB root path
	 * @param string					$php_ext			php File extension
	 * @param string					$table_prefix		Table prefix
	 */
	public function __construct(
		\phpbb\config\config $config,
		\phpbb\db\connection $db,
		\phpbb\db\tools $db_tools,
		$root_path,
		$php_ext,
		$table_prefix
	)
	{
		$this->config		= $config;
		$this->db			= $db;
		$this->db_tools		= $db_tools;

		$this->root_path	= $root_path;
		$this->php_ext		= $php_ext;
		$this->table_prefix	= $table_prefix;

		$this->errors		= [];
	}

	/**
	 * {@inheritdoc}
	 */
	public function effectively_installed()
	{
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	static public function depends_on()
	{
		return [];
	}


	/**
	 * {@inheritdoc}
	 */
	public function update_data()
	{
		return [];
	}

	/**
	 * {@inheritdoc}
	 */
	public function revert_data()
	{
		return [];
	}

	/**
	 * {@inheritdoc}
	 */
	public function update_schema()
	{
		return [];
	}

	/**
	 * {@inheritdoc}
	 */
	public function revert_schema()
	{
		return [];
	}

	/**
	 * Get the array of ran queries.
	 *
	 * @return array			The ran queries
	 */
	public function get_queries()
	{
		return $this->queries;
	}

	/**
	 * Wrapper for running queries to generate user feedback on updates.
	 *
	 * @see \phpbb\db\connection::sql_query()
	 * @see \phpbb\db\connection::sql_transation()
	 *
	 * @param string		$sql	SQL query to run on the database
	 * @return mixed				Query result from $db->sql_query()
	 */
	protected function sql_query($sql)
	{
		$this->queries[] = $sql;

		$this->db->sql_return_on_error(true);

		if ($sql === 'begin')
		{
			$result = $this->db->sql_transaction('begin');
		}
		else if ($sql === 'commit')
		{
			$result = $this->db->sql_transaction('commit');
		}
		else
		{
			$result = $this->db->sql_query($sql);

			if ($this->db->get_sql_error_triggered())
			{
				$this->errors[] = [
					'sql'	=> $this->db->get_sql_error_sql(),
					'code'	=> $this->db->get_sql_error_returned(),
				];
			}
		}

		$this->db->sql_return_on_error(false);

		return $result;
	}
}
