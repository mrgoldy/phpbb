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

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Types\Type;

class connection extends \Doctrine\DBAL\Connection
{
	/** @var string */
	protected $query_text;

	/** @var Statement */
	protected $query_result;

	/** @var string The SQL query that triggered an error */
	protected $sql_error_query = '';

	/** @var array The SQL Error code and message */
	protected $sql_error_returned = [];

	/** @var bool Whether or not an error occurred */
	protected $sql_error_triggered = false;

	/** @var bool Whether or not an exception should be thrown */
	protected $sql_return_on_error = false;

	/** @var bool Whether or not to show the complete SQL error */
	protected $debug_sql_explain = false;

	/**
	 * Set Whether or not to show the complete SQL error.
	 *
	 * @param bool		$debug		The debug indicator
	 * @return void
	 */
	public function set_debug_sql_explain($debug)
	{
		$this->debug_sql_explain = $debug;
	}

	/**
	 * Set whether or not an exception should be thrown on a SQL error.
	 *
	 * If set to false, an exception will be thrown.
	 * If set to true, the error information will be returned.
	 *
	 * @todo prefix with set_
	 *
	 * @param bool		$return		The return indicator
	 * @return void
	 */
	public function sql_return_on_error($return = false)
	{
		$this->sql_error_query = '';
		$this->sql_error_triggered = false;
		$this->sql_return_on_error = $return;
	}

	/**
	 * Get whether or not a SQL error was triggered.
	 *
	 * @return bool					The SQL error indicator
	 */
	public function get_sql_error_triggered()
	{
		return (bool) $this->sql_error_triggered;
	}

	/**
	 * Get the SQL query that triggered an error.
	 *
	 * @return string				The SQL query text
	 */
	public function get_sql_error_sql()
	{
		return (string) $this->sql_error_query;
	}

	/**
	 * Get the SQL error information about the triggered error.
	 *
	 * The SQLSTATE (error code) and extended error information (error message)
	 * associated with the last database operation.
	 * [
	 *  'message'	=> '',
	 *  'code'		=> 0,
	 * ]
	 *
	 * @return array				The SQL error information
	 */
	public function get_sql_error_returned()
	{
		return (array) $this->sql_error_returned;
	}

	/**
	 * Get the wildcard for any characters used within LIKE expressions.
	 *
	 * @return string				The wildcard
	 */
	public function get_any_char()
	{
		// Do not change this please! This variable is used to easy the use of it - and is hardcoded.
		return chr(0) . '%';
	}

	/**
	 * Get the wildcard for exactly one character within LIKE expressions.
	 *
	 * @return string				The wildcard
	 */
	public function get_one_char()
	{
		// Do not change this please! This variable is used to easy the use of it - and is hardcoded.
		return chr(0) . '_';
	}

	/**
	 * Indicates if we are within a transaction.
	 *
	 * @return bool					The transaction indicator
	 */
	public function get_transaction()
	{
		return self::isTransactionActive();
	}

	/**
	 * Return number of SQL queries and cached SQL queries performed.
	 *
	 * @todo prefix with get_
	 *
	 * @param bool		$cached		Whether to return normal queries or cached queries
	 * @return int					Number of queries that have been executed
	 */
	function sql_num_queries($cached = false)
	{
		/** @var \Doctrine\DBAL\Logging\DebugStack $logger */
		$logger = self::getConfiguration()->getSQLLogger();

		if ($logger === null)
		{
			return 0;
		}

		// @todo cache
		return $cached ? 0 : $logger->currentQuery;
	}

	/**
	 * Gets the time spent into SQL queries.
	 *
	 * @return float				The time spent
	 */
	public function get_sql_time()
	{
		/** @var \Doctrine\DBAL\Logging\DebugStack $logger */
		$logger = self::getConfiguration()->getSQLLogger();

		if ($logger === null)
		{
			return 0.00;
		}

		$time_query = 0.00;

		foreach ($logger->queries as $query_id => $query)
		{
			$time_query += $query['executionMS'];
		}

		return $time_query;
	}

	/**
	 * Gets the name of the driver.
	 *
	 * @return string				The name of the driver.
	 */
	public function get_sql_layer()
	{
		return self::getDriver()->getName();
	}

	/**
	 * Gets the name of the database this connection is connected to.
	 *
	 * @return string				The database name
	 */
	public function get_db_name()
	{
		return self::getDatabase();
	}

	/**
	 * Gets the wrapped driver connection.
	 *
	 * @return \Doctrine\DBAL\Driver\Connection
	 */
	public function get_db_connect_id()
	{
		return self::getWrappedConnection();
	}

	/**
	 * Returns the version number of the database server connected to.
	 *
	 * @param bool		$raw		Only return the fetched sql_server_version
	 * @param bool		$use_cache	Whether or not to retrieve the version from cache
	 * @return string				SQL server version
	 */
	public function sql_server_info($raw = false, $use_cache = false)
	{
		try
		{
			$name = $this->getDatabasePlatform()->getReservedKeywordsList()->getName();
		}
		catch (DBALException $e)
		{
			$name = self::getDriver()->getName();
		}

		return $name . ' ' . $this->_conn->getServerVersion();
	}

	/**
	 * Establishes the connection with the database.
	 *
	 * @return bool					TRUE if the connection was successfully established,
	 * 								FALSE if the connection is already open.
	 */
	public function sql_connect()
	{
		return self::connect();
	}

	/**
	 * Closes the connection.
	 *
	 * @return void
	 */
	public function sql_close()
	{
		self::close();
	}

	/**
	 * Returns the ID of the last inserted row, or the last value from a sequence object,
	 * depending on the underlying driver.
	 *
	 * NOTE: This method may not return a meaningful or consistent result across different drivers,
	 * because the underlying database may not even support the notion of AUTO_INCREMENT/IDENTITY
	 * columns or sequences.
	 *
	 * @return string				A string representation of the last inserted ID.
	 */
	public function sql_nextid()
	{
		return self::lastInsertId();
	}

	/**
	 * Run binary AND operator on DB column.
	 * Results in sql statement: "{$column_name} & (1 << {$bit}) {$compare}"
	 *
	 * @param string	$value1		The column name to use
	 * @param int		$value2		The value to use for the AND operator,
	 * 								will be converted to (1 << $value1).
	 * 								Is used by options, using the number schema: 0, 1, 2...29
	 * @param string	$compare	Any custom SQL code after the check (for example "= 0")
	 * @return string				A SQL statement like: "{$value1} & (1 << {$value2}) {$compare}"
	 */
	public function sql_bit_and($value1, $value2, $compare = '')
	{
		$value2 = 1 << $value2;

		$statement = self::getDatabasePlatform()->getBitAndComparisonExpression($value1, $value2);
		$statement .= $compare ? " $compare" : '';

		return $statement;
	}

	/**
	 * Run binary OR operator on DB column.
	 * Results in sql statement: "{$column_name} | (1 << {$bit}) {$compare}"
	 *
	 * @param string	$value1		The column name to use
	 * @param int		$value2		The value to use for the OR operator,
	 * 								will be converted to (1 << $bit).
	 * 								Is used by options, using the number schema... 0, 1, 2...29
	 * @param string	$compare	Any custom SQL code after the check (e.g. "= 0")
	 * @return string				A SQL statement like "$column | (1 << $bit) {$compare}"
	 */
	public function sql_bit_or($value1, $value2, $compare = '')
	{
		$value2 = 1 << $value2;

		$statement = self::getDatabasePlatform()->getBitOrComparisonExpression($value1, $value2);
		$statement .= $compare ? " $compare" : '';

		return $statement;
	}

	/**
	 * Build a CASE expression.
	 *
	 * Note: The two statements, $action_true and $action_false,
	 * must have the same data type (int, vchar, ...) in the database!
	 *
	 * @param string		$condition		The condition to check for
	 * @param string		$action_true	SQL expression that is used, if the condition is true
	 * @param string|false	$action_false	SQL expression that is used, if the condition is false
	 * @return string						CASE expression including the condition and statements
	 */
	public function sql_case($condition, $action_true, $action_false = false)
	{
		$sql_case = 'CASE WHEN ' . $condition;
		$sql_case .= ' THEN ' . $action_true;
		$sql_case .= $action_false !== false ? ' ELSE ' . $action_false : '';
		$sql_case .= ' END';

		return $sql_case;
	}

	/**
	 * Build a concatenated expression.
	 *
	 * @param string	$expr1		Base SQL expression where we append the second one
	 * @param string	$expr2		SQL expression that is appended to the first expression
	 * @return string				Concatenated string
	 */
	public function sql_concatenate($expr1, $expr2)
	{
		return self::getDatabasePlatform()->getConcatExpression($expr1, $expr2);
	}

	/**
	 * Run LOWER() on DB column of type text (i.e. neither varchar nor char).
	 *
	 * @param string	$str		The column name to use
	 * @return string				A SQL statement like "LOWER($column_name)"
	 */
	public function sql_lower_text($str)
	{
		return self::getDatabasePlatform()->getLowerExpression($str);
	}

	/**
	 * Escape string used in sql query
	 *
	 * @param string	$input		String to be escaped
	 * @return string				Escaped version of $msg
	 */
	public function sql_escape($input)
	{
		return trim(self::quote($input, gettype($input)), "'");
	}

	/**
	 * Correctly adjust LIKE expression for special characters.
	 * Some DBMS are handling them in a different way.
	 *
	 * @param string	$expr		The expression to use. Every wildcard is escaped,
	 *								except $this->get_any_char and $this->get_one_char
	 * @return string				A SQL statement like: "LIKE 'bertie_%'"
	 */
	public function sql_like_expression($expr)
	{
		$expr = str_replace(['_', '%'], ["\_", "\%"], $expr);
		$expr = str_replace([chr(0) . "\_", chr(0) . "\%"], ['_', '%'], $expr);

		$expr = self::quote($expr, 'string');

		$like = self::getExpressionBuilder()->like('', $expr);
		$like = trim($like);

		return $like;
	}

	/**
	 * Correctly adjust NOT LIKE expression for special characters.
	 * Some DBMS are handling them in a different way.
	 *
	 * @param string	$expr		The expression to use. Every wildcard is escaped,
	 *								except $this->get_any_char and $this->get_one_char
	 * @return string				A SQL statement like: "NOT LIKE 'bertie_%'"
	 */
	public function sql_not_like_expression($expr)
	{
		$expr = str_replace(['_', '%'], ["\_", "\%"], $expr);
		$expr = str_replace([chr(0) . "\_", chr(0) . "\%"], ['_', '%'], $expr);

		$expr = self::quote($expr, 'string');

		$like = self::getExpressionBuilder()->notLike('', $expr);
		$like = trim($like);

		return $like;
	}

	/**
	 * Build IN or NOT IN comparison string,
	 * uses <> or = on single element arrays to improve comparison speed.
	 *
	 * @param string	$field			Name of the SQL column that will be compared
	 * @param array		$array			Array of values that are (not) allowed
	 * @param bool		$negate			TRUE for NOT IN (), FALSE for IN ()
	 * @param bool		$allow_empty	If TRUE, allow $array to be empty,
	 *									this function will return 1=1 or 1=0 then.
	 * @return string					A SQL statement like: "IN (1, 2, 3, 4)" or "= 1"
	 */
	public function sql_in_set($field, $array, $negate = false, $allow_empty = false)
	{
		$array = (array) $array;

		if (!count($array))
		{
			if (!$allow_empty)
			{
				// Print the backtrace to help identifying the location of the problematic code
				$this->sql_error('No values specified for SQL IN comparison');
			}
			else
			{
				// NOT IN () actually means everything so use a tautology
				if ($negate)
				{
					return self::getExpressionBuilder()->eq(1, 1);
				}

				// IN () actually means nothing so use a contradiction
				return self::getExpressionBuilder()->eq(1, 0);
			}
		}

		if (count($array) === 1)
		{
			@reset($array);
			$var = current($array);

			if ($negate)
			{
				return self::getExpressionBuilder()->neq($field, $this->validate_sql_value($var));
			}

			return self::getExpressionBuilder()->eq($field, $this->validate_sql_value($var));

		}
		else
		{
			$array = array_map([$this, 'validate_sql_value'], $array);

			if ($negate)
			{
				return self::getExpressionBuilder()->notIn($field, $array);
			}

			return self::getExpressionBuilder()->in($field, $array);
		}
	}

	/**
	 * Returns whether results of a query need to be buffered
	 * to run a transaction while iterating over them.
	 *
	 * @return bool					Whether or not buffering is required.
	 */
	public function sql_buffer_nested_transactions()
	{
		return false;
	}

	/**
	 * SQL Transactions.
	 *
	 * begin: Starts a transaction by suspending auto-commit mode.
	 * commit: Commits the current transaction.
	 * rollback: Cancels any database changes done during the current transaction.
	 *
	 * @param string	$action		The transaction action
	 * @return bool|array
	 */
	public function sql_transaction($action = 'begin')
	{
		try
		{
			switch ($action)
			{
				case 'begin':
					self::beginTransaction();
				break;

				case 'commit':
					self::commit();
				break;

				case 'rollback';
					self::rollBack();
				break;

				default:
					return false;
			}

		}
		catch (DBALException $e)
		{
			return $this->sql_error('', $e);
		}

		return true;
	}

	/**
	 * Build SQL statement from an array.
	 *
	 * Query types: INSERT, INSERT_SELECT, UPDATE, SELECT, DELETE
	 *
	 * @param string	$type		The query type
	 * @param array		$data		Array with "column => value" pairs
	 * @return string				A SQL statement like "c1 = 'a' AND c2 = 'b'"
	 */
	public function sql_build_array($type, $data)
	{
		if (!is_array($data))
		{
			return false;
		}

		if (in_array($type, ['INSERT', 'INSERT_SELECT']))
		{
			$fields = $values = [];

			foreach ($data as $field => $value)
			{
				$fields[] = $field;

				if (is_array($value) && is_string($value[0]))
				{
					// This is used for INSERT_SELECT(s)
					$values[] = $value[0];
				}
				else
				{
					$values[] = $this->validate_sql_value($value);
				}
			}

			if ($type === 'INSERT')
			{
				return '(' . implode(', ', $fields) . ') VALUES (' . implode(', ', $values) . ')';
			}

			return '(' . implode(', ', $fields) . ') SELECT ' . implode(', ', $values);
		}
		else if (in_array($type, ['DELETE', 'SELECT', 'UPDATE']))
		{
			$values = [];

			foreach ($data as $field => $value)
			{
				$values[] = $field . ' = ' . $this->validate_sql_value($value);
			}

			$glue = $type === 'UPDATE' ? ', ' : ' AND ';

			return implode($glue, $values);
		}

		return false;
	}

	/**
	 * Build SQL statement from an array for SELECT and SELECT DISTINCT statements.
	 *
	 * Query types: SELECT, SELECT_DISTINCT
	 * Query data:
	 * [
	 *					SELECT		A comma imploded list of columns to select
	 *					FROM		Array with "table => alias" pairs,
	 *								(alias can also be an array)
	 *		Optional:	LEFT_JOIN	Array of join entries:
	 *						FROM		Table that should be joined
	 *						ON			Condition for the join
	 *		Optional:	WHERE		Where SQL statement
	 *		Optional:	GROUP_BY	Group by SQL statement
	 *		Optional:	ORDER_BY	Order by SQL statement
	 * ]
	 *
	 * @param string	$type		The query type
	 * @param array		$data		The query data
	 * @return string				A SQL statement ready for execution
	 */
	public function sql_build_query($type, $data)
	{
		$builder = self::createQueryBuilder();

		if ($type === 'SELECT_DISTINCT')
		{
			$data['SELECT'] = 'DISTINCT ' . $data['SELECT'];
		}

		$builder->select($data['SELECT']);

		$from_table = key($data['FROM']);
		$from_alias = array_shift($data['FROM']);

		$builder->from($from_table, $from_alias);

		foreach ($data['FROM'] as $table => $alias)
		{
			/** @noinspection PhpParamsInspection */
			$builder->add('join', [
				$from_alias => [
					'joinType'      => 'cross',
					'joinTable'     => $table,
					'joinAlias'     => $alias,
					'joinCondition' => true
				]
			], true);
		}

		if (!empty($data['LEFT_JOIN']))
		{
			foreach ($data['LEFT_JOIN'] as $left_join)
			{
				$builder->leftJoin($from_alias, key($left_join['FROM']), current($left_join['FROM']), $left_join['ON']);
			}
		}

		if (!empty($data['WHERE']))
		{
			$builder->where($data['WHERE']);
		}

		if (!empty($data['GROUP_BY']))
		{
			$builder->groupBy($data['GROUP_BY']);
		}

		if (!empty($data['ORDER_BY']))
		{
			$builder->add('orderBy', $data['ORDER_BY']);
		}

		return $builder->getSQL();
	}

	/**
	 * Executes an SQL statement, returning a result set as a Statement object.
	 *
	 * @param string	$query		The SQL query to execute
	 * @param int		$cache_ttl	Either 0 to avoid caching or
	 * 								the time in seconds which the result shall be kept in cache
	 * @return Statement|bool|array
	 */
	public function sql_query($query, $cache_ttl = 0)
	{
		$qcp = null;

		if ($cache_ttl !== 0)
		{
			$qcp = new \Doctrine\DBAL\Cache\QueryCacheProfile(
				$cache_ttl,
				'sql_' . md5(preg_replace('/[\n\r\s\t]+/', ' ', $query))
			);
		}

		try
		{
			$this->query_text = $query;
			$this->query_result = self::executeQuery($query, [], [], $qcp);
		}
		catch (DBALException $e)
		{
			return $this->sql_error($query, $e);
		}

		if (!$this->query_result)
		{
			return false;
		}

		return $this->query_result;
	}

	/**
	 * Build driver-specific LIMIT query.
	 *
	 * @param string	$query		The SQL query to execute
	 * @param int		$limit		The number of rows to select
	 * @param int		$offset		The number of rows to start from
	 * @param int		$cache_ttl	Either 0 to avoid caching or
	 * 								the time in seconds which the result shall be kept in cache
	 * @return Statement|bool|array
	 */
	public function sql_query_limit($query, $limit, $offset = null, $cache_ttl = 0)
	{
		$limit = $limit < 0 ? 0 : $limit;

		if ($offset !== null)
		{
			$offset = $offset < 0 ? 0 : $offset;
		}

		try
		{
			$query = self::getDatabasePlatform()->modifyLimitQuery($query, $limit, $offset);
		}
		catch (DBALException $e)
		{
			$this->sql_error($query, $e);
		}

		return $this->sql_query($query, $cache_ttl);
	}

	/**
	 * Run more than one INSERT statement.
	 *
	 * Doctrine\DBAL does not support multi inserts.
	 *
	 * @param string	$table		Table name to run the statements on
	 * @param array		$rowset		Multi-dimensional array holding the statement data
	 * @return bool|array			FALSE if no statements were executed
	 */
	public function sql_multi_insert($table, $rowset)
	{
		// Multi insert not supported by Doctrine\DBAL
		foreach ($rowset as $row)
		{
			if (!is_array($row))
			{
				// Normalize output: no row means we return false
				return false;
			}

			try
			{
				$result = self::insert($table, $row);
			}
			catch (DBALException $e)
			{
				$query = '';

				/** @var \Doctrine\DBAL\Logging\DebugStack $logger */
				$logger = self::getConfiguration()->getSQLLogger();

				if ($logger !== null)
				{
					$query = $logger->queries[$logger->currentQuery]['sql'];
				}

				return $this->sql_error($query, $e);
			}

			// Normalize output: no result means we return false
			if (!$result)
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Fetches the next row from a result set.
	 *
	 * @param Statement|false	$query_id	Already executed query to get the rows from,
	 *										if FALSE the last query will be used
	 * @return array|false					Array with the current row,
	 *										FALSE if the row does not exist
	 */
	public function sql_fetchrow($query_id = false)
	{
		if ($query_id === false)
		{
			$query_id = $this->query_result;
		}

		if ($query_id instanceof Statement || $query_id instanceof ResultStatement)
		{
			$row = $query_id->fetch();

			// Normalize output: no row means we return false
			return $row !== null ? $row : false;
		}

		// Normalize output: no result means we return false
		return false;
	}

	/**
	 * Fetch all rows
	 *
	 * @param Statement|false	$query_id	Already executed query to get the rows from,
	 *										if FALSE the last query will be used
	 * @return array|false					Multidimensional array of all the rows,
	 * 										FALSE if the query had no rows
	 */
	public function sql_fetchrowset($query_id = false)
	{
		if ($query_id === false)
		{
			$query_id = $this->query_result;
		}

		if ($query_id instanceof Statement || $query_id instanceof ResultStatement)
		{
			return $query_id->fetchAll();
		}

		// Normalize output: no result means we return false
		return false;
	}

	/**
	 * Returns a single column from the result set.
	 *
	 * If the row index is FALSE, the current row is used,
	 * else it should point to the row (zero-based).
	 *
	 * @param string			$field		The column name
	 * @param false|int			$index		The row index
	 * @param Statement|false	$query_id	Already executed query to get the rows from,
	 *										if FALSE the last query will be used.
	 * @return mixed						String value of the field in the selected row,
	 *										FALSE if the row does not exist
	 */
	public function sql_fetchfield($field, $index = false, $query_id = false)
	{
		if ($query_id === false)
		{
			$query_id = $this->query_result;
		}

		if ($query_id instanceof Statement || $query_id instanceof ResultStatement)
		{
			if ($index !== false)
			{
				$this->sql_rowseek($index, $query_id);
			}

			$row = $query_id->fetch();

			// Normalize output: no field means we return false
			return isset($row[$field]) ? $row[$field] : false;
		}

		// Normalize output: no result means we return false
		return false;
	}

	/**
	 * Seek to given row index.
	 *
	 * @param int				$index		Row number the cursor should point to
	 *										NOTE: $index is 0 based
	 * @param Statement|false	$query_id	ID of the query to set the row cursor on
	 *										if FALSE the last query will be used and
	 *										$query_id will then be set correctly
	 * @return bool							TRUE on success, FALSE on error
	 */
	public function sql_rowseek($index, &$query_id)
	{
		if ($query_id === false)
		{
			$query_id = $this->query_result;
		}

		if (!$query_id instanceof Statement && !$query_id instanceof ResultStatement)
		{
			return false;
		}

		$query_id->closeCursor();

		$query_id = $this->sql_query($this->query_text);

		if (!$query_id)
		{
			return false;
		}

		// We do not fetch the row for index === 0
		// because then the next result would be the second row
		for ($i = 0; $i < $index; $i++)
		{
			if ($query_id->fetch() === false)
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Closes the cursor, freeing the database resources used by this statement.
	 *
	 * @param Statement|bool	$query_id	Already executed query result,
	 *										if FALSE the last query will be used
	 * @return bool							TRUE on success, FALSE on error
	 */
	public function sql_freeresult($query_id)
	{
		if ($query_id === false)
		{
			$query_id = $this->query_result;
		}

		if ($query_id === true)
		{
			return true;
		}

		if (!$query_id instanceof Statement && !$query_id instanceof ResultStatement)
		{
			return false;
		}

		return $query_id->closeCursor();
	}

	/**
	 * Return the number of rows affected.
	 *
	 * @param Statement|false	$query_id	Already executed query result,
	 *                                  	if FALSE the last query will be used
	 * @return int|false					Number of the affected rows by the last query
	 * 										FALSE if no query has been run before
	 */
	public function sql_affectedrows($query_id = false)
	{
		if ($query_id === false)
		{
			$query_id = $this->query_result;
		}

		if (!$query_id instanceof Statement && !$query_id instanceof ResultStatement)
		{
			return false;
		}

		return $query_id->rowCount();
	}

	/**
	 * Gets the exact number of rows in a specified table.
	 *
	 * @param string	$table_name		The table name
	 * @return int						Exact number of table rows
	 */
	public function get_estimated_row_count($table_name)
	{
		return $this->get_row_count($table_name);
	}

	/**
	 * Gets the exact number of rows in a specified table.
	 *
	 * @param string	$table_name		The table name
	 * @return int						Exact number of table rows
	 */
	public function get_row_count($table_name)
	{
		$sql = 'SELECT ' . self::getDatabasePlatform()->getCountExpression('*') . ' AS total
			FROM ' . $table_name;
		$result = $this->sql_query($sql);
		$total = $result->fetchColumn(0);
		$result->closeCursor();

		return (int) $total;
	}

	/**
	 * Returns SQL string to cast a string expression to an integer.
	 *
	 * @param string	$expr		An expression evaluating to string
	 * @return string				Expression returning an integer
	 */
	public function cast_expr_to_bigint($expr)
	{
		$driver = $this->getDriver()->getName();

		if ($driver === 'pdo_sqlsrv' || $driver === 'sqlsrv')
		{
			// MSSQL
			return sprintf('CONVERT(%s, %s)', $this->getDatabasePlatform()->getBigIntTypeDeclarationSQL([]), $expr);
		}
		else if ($driver === 'pdo_pgsql')
		{
			// PostgreSQL
			return sprintf('CAST(%s as %s)', $expr, $this->getDatabasePlatform()->getDecimalTypeDeclarationSQL(['precision' => 255]));
		}


		return $expr;
	}

	/**
	 * Returns SQL string to cast an integer expression to a string.
	 *
	 * @param string	$expr		An expression evaluating to int
	 * @return string				Expression returning a string
	 */
	public function cast_expr_to_string($expr)
	{
		if ($this->getDriver()->getName() === 'pdo_pgsql')
		{
			// PostgreSQL
			return sprintf('CAST(%s as %s)', $expr, $this->getDatabasePlatform()->getVarcharTypeDeclarationSQL(['length' => 255]));
		}

		return $expr;
	}

	/**
	 * Trigger a SQL error.
	 *
	 * @param string				$query		The SQL query that caused the error
	 * @param DBALException|null	$e			The DBAL Exception
	 * @return array|string						Array if $return_on_error is set to true,
	 *                        					else a triggered error message.
	 */
	public function sql_error($query = '', DBALException $e = null)
	{
		$this->sql_error_query = $query;
		$this->sql_error_triggered = true;
		$this->sql_error_returned = [
			'message'	=> $e !== null ? $e->getMessage() : self::errorInfo(),
			'code'		=> $e !== null ? $e->getCode() : self::errorCode(),
		];

		if (!$this->sql_return_on_error)
		{
			/**
			 * @var \phpbb\auth\auth $auth
			 * @var \phpbb\config\config $config
			 * @var \phpbb\user $user
			 */
			global $auth, $config, $user;

			$message = 'SQL ERROR [ ' . self::getDriver()->getName() . ' ]<br /><br />' . $this->sql_error_returned['message'] . ' [' . $this->sql_error_returned['code'] . ']';

			/**
			 * Show complete SQL error and path to Administrators only.
			 * Additionally show complete error on installation or if the extended debug mode is enabled.
			 * The DEBUG constant is for development only!
			 */
			if (!empty($query) && ((isset($auth) && $auth->acl_get('a_')) || defined('IN_INSTALL') || $this->debug_sql_explain))
			{
				$message .= '<br /><br />SQL<br /><br />' . htmlspecialchars($query);
			}
			else
			{
				/**
				 * If the error occurs within initiating the session, we need to use a pre-defined language string.
				 * This could happen if the connection could not be established for example.
				 * (Then we are not able to grab the default language)
				 */
				if (!isset($user->lang['SQL_ERROR_OCCURRED']))
				{
					$message .= '<br /><br />An sql error occurred while fetching this page. Please contact an administrator if this problem persists.';
				}
				else
				{
					if (!empty($config['board_contact']))
					{
						$message .= '<br /><br />' . $user->lang('SQL_ERROR_OCCURRED', '<a href="mailto:' . htmlspecialchars($config['board_contact']) . '">', '</a>');
					}
					else
					{
						$message .= '<br /><br />' . $user->lang('SQL_ERROR_OCCURRED', '', '');
					}
				}
			}

			if (self::isTransactionActive())
			{
				try
				{
					self::rollBack();
				}
				catch (DBALException $e)
				{
				}
			}

			if (strlen($message) > 1024)
			{
				// We need to define $msg_long_text here to circumvent text stripping.
				global $msg_long_text;
				$msg_long_text = $message;

				return trigger_error(false, E_USER_ERROR);
			}

			return trigger_error($message, E_USER_ERROR);
		}

		if (self::isTransactionActive())
		{
			try
			{
				self::rollBack();
			}
			catch (DBALException $e)
			{
			}
		}

		return $this->sql_error_returned;
	}

	/**
	 * Display a SQL report.
	 *
	 * @return bool|string			The SQL report
	 */
	public function sql_report()
	{
		/** @var \phpbb\request\request		$request	Request object */
		global $request;

		if (!is_object($request) || !$request->variable('explain', false))
		{
			return false;
		}

		/**
		 * @var \phpbb\cache\driver\driver_interface	$cache				Cache driver object
		 * @var \phpbb\path_helper						$phpbb_path_helper	Path helper object
		 * @var string									$phpbb_root_path	phpBB root path
		 * @var double									$starttime			Start time of the page
		 */
		global $cache, $phpbb_path_helper, $phpbb_root_path, $starttime;

		if (!empty($cache))
		{
			$cache->unload();
		}

		$this->sql_close();

		/** @var \Doctrine\DBAL\Logging\DebugStack $logger */
		$logger = self::getConfiguration()->getSQLLogger();

		if ($logger === null)
		{
			return false;
		}

		$time_micro = explode(' ', microtime());
		$time_total = $time_micro[0] + $time_micro[1] - $starttime;
		$time_query = 0;

		$sql_report = '';

		foreach ($logger->queries as $query_id => $query)
		{
			$time_query += $query['executionMS'];

			$sql_report .= '<table cellspacing="1">
					<thead>
					<tr>
						<th>Query #' . $query_id . '</th>
					</tr>
					</thead>
					<tbody>
					<tr>
						<td class="row3"><textarea style="font-family:\'Courier New\',monospace;width:99%" rows="5" cols="10">' . preg_replace('/\t(AND|OR)(\W)/', "\$1\$2", htmlspecialchars(preg_replace('/[\s]*[\n\r\t]+[\n\r\s\t]*/', "\n", $query['sql']))) . '</textarea></td>
					</tr>
					</tbody>
					</table>
					<p style="text-align: center;">Elapsed: <b>' . round($query['executionMS'], 5) . 's</p>
					<br /><br />';
		}

		echo '<!DOCTYPE html>
					<html dir="ltr" lang="en">
					<head>
						<meta charset="utf-8">
						<meta http-equiv="X-UA-Compatible" content="IE=edge">
						<title>SQL Report</title>
						<link href="' . htmlspecialchars($phpbb_path_helper->update_web_root_path($phpbb_root_path) . $phpbb_path_helper->get_adm_relative_path()) . 'style/admin.css" rel="stylesheet" type="text/css" media="screen" />
					</head>
					<body id="errorpage">
					<div id="wrap">
						<div id="page-header">
							<a href="' . build_url('explain') . '">Return to previous page</a>
						</div>
						<div id="page-body">
							<div id="acp">
							<div class="panel">
								<span class="corners-top"><span></span></span>
								<div id="content">
									<h1>SQL Report</h1>
									<br />
									<p><b>Page generated in ' . round($time_total, 4) . " seconds with {$logger->currentQuery} queries" . // @todo (($this->num_queries['cached']) ? " + {$this->num_queries['cached']} " . (($this->num_queries['cached'] == 1) ? 'query' : 'queries') . ' returning data from cache' : '') . '</b></p>

									'<p>Time spent on ' . self::getDriver()->getName() . ' queries: <b>' . round($time_query, 5) . 's</b> | Time spent on PHP: <b>' . round($time_total - $time_query, 5) . 's</b></p>

									<br /><br />
									' . $sql_report . '
								</div>
								<span class="corners-bottom"><span></span></span>
							</div>
							</div>
						</div>
						<div id="page-footer">
							Powered by <a href="https://www.phpbb.com/">phpBB</a>&reg; Forum Software &copy; phpBB Limited
						</div>
					</div>
					</body>
					</html>';

		return exit_handler();
	}

	/**
	 * Validate a SQL value and prepare it.
	 *
	 * @param mixed		$value		The SQL value
	 * @return mixed|string			The validated SQL value
	 */
	protected function validate_sql_value($value)
	{
		if (is_null($value))
		{
			return 'NULL';
		}
		else if (is_string($value))
		{
			try
			{
				return self::quote(Type::getType(Type::STRING)->convertToDatabaseValue($value, self::getDatabasePlatform()), Type::STRING);
			}
			catch (DBALException $e)
			{
			}
		}
		else if (is_bool($value))
		{
			try
			{
				return Type::getType(Type::BOOLEAN)->convertToDatabaseValue($value, self::getDatabasePlatform());
			}
			catch (DBALException $e)
			{
			}

		}

		return $value;
	}
}
