<?php

namespace phpbb\db;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Types\Type;
use phpbb\db\driver\driver_interface;

/**
 * Doctrine DBAL Connection wrapper.
 *
 * Implemented for Backwards Compatibility (BC).
 */
class wrapper extends Connection implements driver_interface
{
	public function get_sql_layer(): string
	{
		return $this->getDriver()->getName();
	}

	public function sql_server_info($raw = false, $use_cache = true): string
	{
		return $this->getDriver()->getName() . ' ' . $this->_conn->getServerVersion();
	}

	public function get_db_name(): string
	{
		return $this->getDatabase();
	}

	public function sql_connect($sqlserver, $sqluser, $sqlpassword, $database, $port = false, $persistency = false, $new_link = false): bool
	{
		return $this->connect();
	}

	public function sql_close(): void
	{
		$this->close();
	}

	public function get_any_char(): string
	{
		// Do not change this please! This variable is used to easy the use of it - and is hardcoded.
		return chr(0) . '%';
	}

	public function get_one_char(): string
	{
		// Do not change this please! This variable is used to easy the use of it - and is hardcoded.
		return chr(0) . '_';
	}

	public function get_db_connect_id(): DriverConnection
	{
		return $this->getWrappedConnection();
	}

	public function get_multi_insert(): bool
	{
		return false;
	}

	public function set_multi_insert($multi_insert): void
	{
	}



	/******************************************************
	 * Query handling
	 ******************************************************/




	public function sql_nextid(): string
	{
		return $this->lastInsertId();
	}

	public function get_transaction(): bool
	{
		return $this->isTransactionActive();
	}

	public function sql_transaction($action = 'begin')
	{
		switch ($action)
		{
			case 'begin':
				$this->beginTransaction();
			break;
			case 'commit':
				$this->commit();
			break;
			case 'rollback':
				$this->rollBack();
			break;

			default:
				return false;
		}

		return true;
	}

	public function sql_buffer_nested_transactions(): bool
	{
		return false;
	}

	public function sql_multi_insert($table, $rowset)
	{
		foreach ($rowset as $row)
		{
			$result = $this->insert($table, $row);

			if (!$result)
			{
				return false;
			}
		}

		return true;
	}

	public function sql_build_query($query, $array)
	{
		// TODO: Implement sql_build_query() method.
	}

	public function sql_build_array($query, $assoc_ary = [])
	{
		// TODO: Implement sql_build_array() method.
	}

	public function sql_query_limit($query, $total, $offset = 0, $cache_ttl = 0)
	{
		// TODO: Implement sql_query_limit() method.
	}

	public function sql_query($query = '', $cache_ttl = 0)
	{
		// TODO: Implement sql_query() method.
	}

	public function sql_fetchfield($field, $rownum = false, $query_id = false)
	{
		// TODO: Implement sql_fetchfield() method.
	}

	public function sql_fetchrow($query_id = false)
	{
		// TODO: Implement sql_fetchrow() method.
	}

	public function sql_fetchrowset($query_id = false)
	{
		// TODO: Implement sql_fetchrowset() method.
	}

	public function sql_rowseek($rownum, &$query_id)
	{
		// TODO: Implement sql_rowseek() method.
	}

	public function sql_affectedrows()
	{
		// TODO: Implement sql_affectedrows() method.
	}

	public function sql_freeresult($query_id = false)
	{
		// TODO: Implement sql_freeresult() method.
	}

	public function get_row_count($table_name): int
	{
		return (int) $this->executeQuery(sprintf('SELECT COUNT(*) AS total FROM %s', $table_name))->fetchColumn(0);
	}

	public function get_estimated_row_count($table_name)
	{
		return $this->get_row_count($table_name);
	}


/******************************************************
 * Value handling
******************************************************/


	public function cast_expr_to_bigint($expression)
	{
		// TODO: Implement cast_expr_to_bigint() method.
	}

	public function cast_expr_to_string($expression)
	{
		// TODO: Implement cast_expr_to_string() method.
	}

	public function sql_bit_and($column_name, $bit, $compare = ''): string
	{
		$statement = $this->getDatabasePlatform()->getBitAndComparisonExpression($column_name, 1 << $bit);
		$statement .= $compare ? " $compare" : '';

		return $statement;
	}

	public function sql_bit_or($column_name, $bit, $compare = '')
	{
		$statement = $this->getDatabasePlatform()->getBitOrComparisonExpression($column_name, 1 << $bit);
		$statement .= $compare ? " $compare" : '';

		return $statement;
	}

	public function sql_case($condition, $action_true, $action_false = false)
	{
		return sprintf('CASE WHEN %s THEN %s%s END', $condition, $action_true, $action_false !== false ? ' ELSE ' . $action_false : '');
	}

	public function sql_concatenate($expr1, $expr2)
	{
		return $this->getDatabasePlatform()->getConcatExpression($expr1, $expr2);
	}

	public function sql_lower_text($column_name): string
	{
		return $this->getDatabasePlatform()->getLowerExpression($column_name);
	}

	public function sql_escape($input): string
	{
		return trim($this->quote($input, 'string'), "'");
	}

	private function escape_expr($expression): string
	{
		$expr = str_replace(['_', '%', chr(0) . "\_", chr(0) . "\%"], ["\_", "\%", '_', '%'], $expression);

		return $this->quote($expr, 'string');
	}

	public function sql_like_expression($expression): string
	{
		return trim($this->getExpressionBuilder()->like('', $this->escape_expr($expression)));
	}

	public function sql_not_like_expression($expression): string
	{
		return trim($this->getExpressionBuilder()->notLike('', $this->escape_expr($expression)));
	}

	public function sql_in_set($field, $array, $negate = false, $allow_empty_set = false)
	{
		$array = (array) $array;
		$_expr = $this->getExpressionBuilder();

		if (empty($array))
		{
			if ($allow_empty_set)
			{
				return $negate ? $_expr->eq(1, 1) : $_expr->eq(1, 0);
			}

			throw new DBALException();
		}

		$array = array_map([$this, 'validate_sql_value'], $array);

		if (count($array) === 1)
		{
			return $negate ? $_expr->neq($field, current($array)) : $_expr->eq($field, current($array));
		}

		return $negate ? $_expr->notIn($field, $array) : $_expr->in($field, $array);
	}

	private function validate_sql_value($value)
	{
		switch (gettype($value))
		{
			case 'NULL':
				return 'NULL';

			case 'string':
				return $this->quote(Type::getType(Type::STRING)->convertToDatabaseValue($value, $this->getDatabasePlatform(), Type::STRING));

			case 'boolean':
				return Type::getType(Type::BOOLEAN)->convertToDatabaseValue($value, $this->getDatabasePlatform());

			default:
				return $value;
		}
	}

	/*************************************************
	 * Error handling
	 ************************************************/


	protected $debug_load_time = false;
	protected $debug_sql_explain = false;
	protected $return_on_error = false;

	public function set_debug_load_time($value): void
	{
		$this->debug_load_time = $value;
	}

	public function set_debug_sql_explain($value): void
	{
		$this->debug_sql_explain = $value;
	}

	public function sql_return_on_error($fail = false)
	{
		$this->return_on_error = $fail;
	}

	public function sql_report($mode, $query = '')
	{
		// TODO: Implement sql_report() method.
	}

	public function sql_error($sql = '')
	{
		// TODO: Implement sql_error() method.
	}

	public function get_sql_error_returned()
	{
		// TODO: Implement get_sql_error_returned() method.
	}

	public function get_sql_error_triggered()
	{
		// TODO: Implement get_sql_error_triggered() method.
	}

	public function get_sql_error_sql()
	{
		// TODO: Implement get_sql_error_sql() method.
	}

	public function sql_num_queries($cached = false)
	{
		// TODO: Implement sql_num_queries() method.
	}

	public function sql_add_num_queries($cached = false)
	{
		// TODO: Implement sql_add_num_queries() method.
	}

	public function get_sql_time()
	{
		// TODO: Implement get_sql_time() method.
	}
}
