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
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;

class tools
{
	/** @var connection */
	protected $db;

	/** @var \Doctrine\DBAL\Platforms\AbstractPlatform */
	protected $platform;

	/** @var \Doctrine\DBAL\Schema\Schema */
	protected $schema;

	/** @var bool Whether statements should be returned or executed */
	protected $return_statements = false;

	/** @var array Mapping phpBB migration types to Doctrine types */
	static protected $types_map = [
		'BINT'		=> ['type' => Type::BIGINT],
		'USINT'		=> ['type' => Type::SMALLINT, 'unsigned' => true],
		'ULINT'		=> ['type' => Type::INTEGER, 'unsigned' => true],
		'UINT'		=> ['type' => Type::INTEGER, 'unsigned' => true],
		'TINT'		=> ['type' => Type::INTEGER],
		'INT'		=> ['type' => Type::INTEGER],
		'TIMESTAMP'	=> ['type' => Type::INTEGER, 'unsigned' => true],
		'BOOL'		=> ['type' => Type::BOOLEAN, 'unsigned' => true],
		'DECIMAL'	=> ['type' => Type::DECIMAL, 'precision' => 5, 'scale' => 2],
		'PDECIMAL'	=> ['type' => Type::DECIMAL, 'precision' => 6, 'scale' => 3],
		'CHAR'		=> ['type' => Type::STRING],
		'VCHAR'		=> ['type' => Type::STRING],
		'VCHAR_UNI'	=> ['type' => Type::STRING],
		'VCHAR_CI'	=> ['type' => Type::STRING],
		'VARBINARY'	=> ['type' => Type::BINARY, 'length' => 255],
		'XSTEXT'	=> ['type' => Type::TEXT, 'length' => 100],
		'XSTEXT_UNI'=> ['type' => Type::TEXT, 'length' => 100],
		'STEXT'		=> ['type' => Type::TEXT, 'length' => 255],
		'STEXT_UNI'	=> ['type' => Type::TEXT, 'length' => 255],
		'TEXT'		=> ['type' => Type::TEXT, 'length' => 65535],
		'TEXT_UNI'	=> ['type' => Type::TEXT, 'length' => 65535],
		'MTEXT'		=> ['type' => Type::TEXT, 'length' => 16777215],
		'MTEXT_UNI'	=> ['type' => Type::TEXT, 'length' => 16777215],
	];

	/**
	 * Constructor.
	 *
	 * @param connection		$db		Database object
	 * @return void
	 */
	public function __construct(connection $db)
	{
		$this->db		= $db;
		$this->schema	= $db->getSchemaManager()->createSchema();

		try
		{
			$this->platform = $db->getDatabasePlatform();
		}
		catch (DBALException $e)
		{
		}
	}

	/**
	 * Set whether statements should be returned or executed.
	 *
	 * @param bool		$return_statements		True if SQL should not be executed but returned as strings
	 * @return void
	 */
	public function set_return_statements($return_statements)
	{
		$this->return_statements = $return_statements;
	}

	/**
	 * Handle passed database update array.
	 *
	 * Expected structure...
	 * Key being one of the following:
	 *	drop_tables:		Drop tables
	 *	add_tables:			Add tables
	 *	change_columns:		Column changes (only type, not name)
	 *	add_columns:		Add columns to a table
	 *	drop_keys:			Dropping keys
	 *	drop_columns:		Removing/Dropping columns
	 *	add_primary_keys:	Adding primary keys
	 *	add_unique_index:	Adding an unique index
	 *	add_index:			Adding an index (can be column:index_size if you need to provide size)
	 *
	 * The values are in this format:
	 *	$table_name		=> [
	 *		$column_name		=> [$column_type, $default_value, $option_variables],
	 *		$key/index_name		=> [$column_names],
	 *	]
	 *
	 *
	 * @param array		$changes		The schema changes
	 * @throws DBALException
	 * @return array|bool				Array with schema changes
	 *                       			TRUE if all statements were executed
	 *                       			FALSE if the changes array is empty
	 */
	public function perform_schema_changes(array $changes)
	{
		if (empty($changes))
		{
			return false;
		}

		$queries = [];

		// Drop tables
		if (!empty($changes['drop_tables']))
		{
			foreach ($changes['drop_tables'] as $table)
			{
				// only drop table if it exists
				if ($this->sql_table_exists($table))
				{
					$result = $this->sql_table_drop($table);

					$queries = array_merge($queries, (array) $result);
				}
			}
		}

		// Add tables
		if (!empty($changes['add_tables']))
		{
			foreach ($changes['add_tables'] as $table => $table_data)
			{
				$result = $this->sql_create_table($table, $table_data);

				$queries = array_merge($queries, (array) $result);
			}
		}

		// Change columns
		if (!empty($changes['change_columns']))
		{
			foreach ($changes['change_columns'] as $table => $columns)
			{
				$table = $this->schema->getTable($table);
				$columns = [];

				foreach ($columns as $column_name => $column_data)
				{
					// If the column does not exists, add it to the add_columns queue
					if ($table->hasColumn($column_name))
					{
						$changes['add_columns'][$table][$column_name] = $column_data;

						continue;
					}

					$options = $this->sql_prepare_column_data($column_data);
					$column = $table->changeColumn($column_name, $options)
						->getColumn($column_name);

					$columns[] = new ColumnDiff($column_name, $column);
				}


				$table_diff = new TableDiff($table);
				$table_diff->changedColumns = $columns;

				$result = $this->platform->getAlterTableSQL($table_diff);

				$queries = array_merge($queries, (array) $this->_sql_run_sql($result));
			}
		}

		// Add columns
		if (!empty($changes['add_columns']))
		{
			foreach ($changes['add_columns'] as $table => $columns)
			{
				$table = $this->schema->getTable($table);
				$columns = [];

				foreach ($columns as $column_name => $column_data)
				{
					if ($table->hasColumn($column_name))
					{
						continue;
					}

					$options = $this->sql_prepare_column_data($column_data);
					$columns[] = $table->addColumn($column_name, $options['type'], $options);
				}

				$table_diff = new TableDiff($table);
				$table_diff->addedColumns = $columns;

				$result = $this->platform->getAlterTableSQL($table_diff);

				$queries = array_merge($queries, (array) $this->_sql_run_sql($result));
			}
		}

		// Remove keys
		if (!empty($changes['drop_keys']))
		{
			foreach ($changes['drop_keys'] as $table => $index_names)
			{
				$table = $this->schema->getTable($table);
				$indices = [];

				foreach ($index_names as $index_name)
				{
					if (!$table->hasIndex($index_name))
					{
						continue;
					}

					$indices[] = $table->getIndex($index_name);
				}

				$table_diff = new TableDiff($table);
				$table_diff->removedIndexes = $indices;

				$result = $this->platform->getAlterTableSQL($table_diff);

				$queries = array_merge($queries, (array) $this->_sql_run_sql($result));
			}
		}

		// Drop columns
		if (!empty($changes['drop_columns']))
		{
			foreach ($changes['drop_columns'] as $table => $column_names)
			{
				$table = $this->schema->getTable($table);
				$columns = [];

				foreach ($column_names as $column_name)
				{
					if (!$table->hasColumn($column_name))
					{
						continue;
					}

					$columns[] = $table->getColumn($column_name);
				}

				$table_diff = new TableDiff($table);
				$table_diff->removedColumns = $columns;

				$result = $this->platform->getAlterTableSQL($table_diff);

				$queries = array_merge($queries, (array) $this->_sql_run_sql($result));
			}
		}

		// Add primary keys
		if (!empty($changes['add_primary_keys']))
		{
			foreach ($changes['add_primary_keys'] as $table => $columns)
			{
				$table = $this->schema->getTable($table);
				$index = $table->setPrimaryKey($columns)
					->getPrimaryKey();

				$table_diff = new TableDiff($table);
				$table_diff->addedIndexes = [$index];

				$result = $this->platform->getAlterTableSQL($table_diff);

				$queries = array_merge($queries, (array) $this->_sql_run_sql($result));
			}
		}

		// Add unique indexes
		if (!empty($changes['add_unique_index']))
		{
			foreach ($changes['add_unique_index'] as $table => $index_array)
			{
				$table = $this->schema->getTable($table);
				$indices = [];

				foreach ($index_array as $index_name => $column)
				{
					if ($table->hasIndex($index_name))
					{
						$index = $table->getIndex($index_name);

						if ($index->isUnique() && !$index->isPrimary())
						{
							continue;
						}
					}

					$indices[] = $table->addUniqueIndex((array) $column, $index_name)
						->getIndex($index_name);
				}

				$table_diff = new TableDiff($table);
				$table_diff->addedIndexes = $indices;

				$result = $this->platform->getAlterTableSQL($table_diff);

				$queries = array_merge($queries, (array) $this->_sql_run_sql($result));
			}
		}

		// Add indexes
		if (!empty($changes['add_index']))
		{
			foreach ($changes['add_index'] as $table => $index_array)
			{
				$table = $this->schema->getTable($table);
				$indices = [];

				foreach ($index_array as $index_name => $column)
				{
					if ($table->hasIndex($index_name))
					{
						continue;
					}

					$indices[] = $table->addIndex((array) $column, $index_name)
						->getIndex($index_name);
				}

				$table_diff = new TableDiff($table);
				$table_diff->addedIndexes = $indices;

				$result = $this->platform->getAlterTableSQL($table_diff);

				$queries = array_merge($queries, (array) $this->_sql_run_sql($result));
			}
		}

		return $this->return_statements ? $queries : true;
	}

	/**
	 * Gets a list of tables in the database.
	 *
	 * @return array			Array of table names  (all lower case)
	 */
	public function sql_list_tables()
	{
		$tables = $this->schema->getTableNames();

		return array_combine($tables, $tables);
	}

	/**
	 * Check if table exists.
	 *
	 * @param string	$table_name		The table name to check for
	 * @return bool						TRUE if table exists, FALSE otherwise
	 */
	public function sql_table_exists($table_name)
	{
		return $this->schema->hasTable($table_name);
	}

	/**
	 * Create a table.
	 *
	 * @param string	$table_name		The table name to create
	 * @param array		$table_data		Array containing table data.
	 * @return array|bool				Statements to run,
	 * 									TRUE if the statements have been executed,
	 *                       			FALSE if no columns were specified
	 */
	public function sql_create_table($table_name, $table_data)
	{
		if ($this->sql_table_exists($table_name))
		{
			return $this->return_statements ? [] : true;
		}

		if (empty($table_data['COLUMNS']))
		{
			return $this->return_statements ? [] : false;
		}

		$table = $this->schema->createTable($table_name);

		foreach ($table_data['COLUMNS'] as $column => $data)
		{
			$options = $this->sql_prepare_column_data($data);

			/** @var Type $type */
			$type = $options['type'];

			/**
			 * @todo Doctrine doesn't support 'after'
			if (isset($column_data['after']))
			{
				$return_array['after'] = $column_data['after'];
			}
			*/

			$table->addColumn($column, $type->getName(), $options);
		}

		if (isset($table_data['PRIMARY_KEY']))
		{
			$table->setPrimaryKey((array) $table_data['PRIMARY_KEY']);
		}

		if (isset($table_data['KEYS']))
		{
			foreach ($table_data['KEYS'] as $key => $data)
			{
				$data[1] = (array) $data[1];

				if ($data[0] === 'UNIQUE')
				{
					$table->addUniqueIndex($data[1], $key);
				}
				else
				{
					$table->addIndex($data[1], $key);
				}
			}
		}

		$queries = [];

		try
		{
			$queries = $this->platform->getCreateTableSQL($table);
		}
		catch (DBALException $e)
		{
			// Never thrown, existance and columns already checked
		}

		return $this->_sql_run_sql($queries);
	}

	/**
	 * Drop a table.
	 *
	 * @param string	$table_name		The table name to drop
	 * @return array|true				Statements to run,
	 * 									TRUE if the statements have been executed
	 */
	public function sql_table_drop($table_name)
	{
		if (!$this->sql_table_exists($table_name))
		{
			return $this->return_statements ? [] : true;
		}

		$query = '';

		try
		{
			$table = $this->schema->getTable($table_name);
			$query = $this->platform->getDropTableSQL($table);
		}
		catch (DBALException $e)
		{
			// Never thrown, existance is already checked
		}


		return $this->_sql_run_sql((array) $query);
	}

	/**
	 * Gets a list of columns of a table.
	 *
	 * @param string	$table_name			Table name
	 * @return array						Array of column names (all lower case)
	 */
	public function sql_list_columns($table_name)
	{
		if (!$this->sql_table_exists($table_name))
		{
			return [];
		}

		$table = $this->get_table($table_name);
		$columns = array_keys($table->getColumns());

		return array_combine($columns, $columns);
	}

	/**
	 * Check whether a specified column exist in a table.
	 *
	 * @param string	$table_name		Table to check
	 * @param string	$column_name	Column to check
	 * @return bool						TRUE if column exists, FALSE otherwise
	 */
	public function sql_column_exists($table_name, $column_name)
	{
		if (!$this->sql_table_exists($table_name))
		{
			return false;
		}

		$table = $this->get_table($table_name);

		return $table->hasColumn($column_name);
	}

	/**
	 * Add new column to a table.
	 *
	 * @param string	$table_name		Table to modify
	 * @param string	$column_name	Name of the column to add
	 * @param array		$column_data	Column data
	 * @return array|true				Statements to run, or TRUE if the statements have been executed
	 */
	public function sql_column_add($table_name, $column_name, $column_data)
	{
		if (!$this->sql_table_exists($table_name))
		{
			return $this->return_statements ? [] : true;
		}

		$table = $this->get_table($table_name);
		$options = $this->sql_prepare_column_data($column_data);
		$column = $table->addColumn($column_name, $options['type'], $options);

		$table_diff = new TableDiff($table_name);
		$table_diff->addedColumns = [$column];

		$queries = [];

		try
		{
			$queries = $this->platform->getAlterTableSQL($table_diff);
		}
		catch (DBALException $e)
		{
		}

		return $this->_sql_run_sql($queries);
	}

	/**
	 * Change column type (not name!).
	 *
	 * @param string	$table_name		Table to modify
	 * @param string	$column_name	Name of the column to modify
	 * @param array		$column_data	Column data
	 * @return array|bool				Statements to run,
	 * 									TRUE if the statements have been executed,
	 * 									FALSE if the column does not exist
	 */
	public function sql_column_change($table_name, $column_name, $column_data)
	{
		if (!$this->sql_table_exists($table_name))
		{
			return $this->return_statements ? [] : true;
		}

		$table = $this->get_table($table_name);

		if (!$table->hasColumn($column_name))
		{
			return $this->return_statements ? [] : false;
		}

		$queries = [];

		try
		{
			$options = $this->sql_prepare_column_data($column_data);
			$column = $table->changeColumn($column_name, $options)
				->getColumn($column_name);

			$column_diff = new ColumnDiff($column_name, $column);
			$table_diff = new TableDiff($table_name);
			$table_diff->changedColumns = [$column_diff];

			$queries = $this->platform->getAlterTableSQL($table_diff);
		}
		catch (DBALException $e)
		{
		}

		return $this->_sql_run_sql($queries);
	}

	/**
	 * Drop a column.
	 *
	 * @param string	$table_name		Table to modify
	 * @param string	$column_name	Name of the column to drop
	 * @return array|true				Statements to run, or TRUE if the statements have been executed
	 */
	public function sql_column_remove($table_name, $column_name)
	{
		if (!$this->sql_table_exists($table_name))
		{
			return $this->return_statements ? [] : true;
		}

		$table = $this->get_table($table_name);

		if (!$table->hasColumn($column_name))
		{
			return $this->return_statements ? [] : true;
		}

		$queries = [];

		try
		{
			$column = $table->getColumn($column_name);

			$table_diff = new TableDiff($table_name);
			$table_diff->removedColumns = [$column];

			$queries = $this->platform->getAlterTableSQL($table_diff);
		}
		catch (DBALException $e)
		{
		}

		return $this->_sql_run_sql($queries);
	}

	/**
	 * List all of the indices that belong to a table.
	 *
	 * NOTE: This does not list
	 * - UNIQUE indices
	 * - PRIMARY keys
	 *
	 * @param string	$table_name		Table to check
	 * @return array					Array with index names
	 */
	public function sql_list_index($table_name)
	{
		if (!$this->sql_table_exists($table_name))
		{
			return [];
		}

		$table = $this->get_table($table_name);
		$indices = [];

		foreach ($table->getIndexes() as $index)
		{
			if (!$index->isUnique())
			{
				$indices[] = $index->getName();
			}
		}

		return $indices;
	}

	/**
	 * Check if a specified index exists in table.
	 * Does not return PRIMARY KEY and UNIQUE indexes.
	 *
	 * @param string	$table_name		Table to check the index at
	 * @param string	$index_name		The index name to check
	 * @return bool						TRUE if index exists, FALSE otherwise
	 */
	public function sql_index_exists($table_name, $index_name)
	{
		if (!$this->sql_table_exists($table_name))
		{
			return false;
		}

		$table = $this->get_table($table_name);

		return $table->hasIndex($index_name);
	}

	/**
	 * Check if a specified index exists in table.
	 *
	 * NOTE: Does not return normal and PRIMARY KEY indices
	 *
	 * @param string	$table_name		Table to check the index at
	 * @param string	$index_name		The index name to check
	 * @return bool						TRUE if index exists, FALSE otherwise
	 */
	public function sql_unique_index_exists($table_name, $index_name)
	{
		if (!$this->sql_table_exists($table_name))
		{
			return false;
		}

		$table = $this->get_table($table_name);

		if (!$table->hasIndex($index_name))
		{
			return false;
		}

		try
		{
			$index = $table->getIndex($index_name);
		}
		catch (DBALException $e)
		{
			return false;
		}

		return $index->isUnique() && !$index->isPrimary();
	}

	/**
	 * Add an index.
	 *
	 * @param string		$table_name		Table to modify
	 * @param string		$index_name		Name of the index to create
	 * @param string|array	$column			Either a string with a column name, or an array with columns
	 * @return array|true					Statements to run, or TRUE if the statements have been executed
	 */
	public function sql_create_index($table_name, $index_name, $column)
	{
		if (!$this->sql_table_exists($table_name))
		{
			return $this->return_statements ? [] : false;
		}

		$table = $this->get_table($table_name);

		if ($table->hasIndex($index_name))
		{
			return $this->return_statements ? [] : false;
		}

		$queries = [];

		try
		{
			$index = $table->addIndex((array) $column, $index_name)
				->getIndex($index_name);

			$table_diff = new TableDiff($table_name);
			$table_diff->addedIndexes = [$index];

			$queries = $this->platform->getAlterTableSQL($table_diff);
		}
		catch (DBALException $e)
		{
		}

		return $this->_sql_run_sql($queries);
	}

	/**
	 * Add an unique index.
	 *
	 * @param string		$table_name		Table to modify
	 * @param string		$index_name		Name of the unique index to create
	 * @param string|array	$column			Either a string with a column name, or an array with columns
	 * @return array|true					Statements to run, or TRUE if the statements have been executed
	 */
	public function sql_create_unique_index($table_name, $index_name, $column)
	{
		if (!$this->sql_table_exists($table_name))
		{
			return $this->return_statements ? [] : false;
		}

		if ($this->sql_unique_index_exists($table_name, $index_name))
		{
			return $this->return_statements ? [] : true;
		}

		$queries = [];

		try
		{
			$table = $this->get_table($table_name);
			$index = $table->addUniqueIndex((array) $column, $index_name)
				->getIndex($index_name);

			$table_diff = new TableDiff($table_name);
			$table_diff->addedIndexes = [$index];

			$queries = $this->platform->getAlterTableSQL($table_diff);
		}
		catch (DBALException $e)
		{
		}

		return $this->_sql_run_sql($queries);
	}

	/**
	 * Add a primary key.
	 *
	 * @param string		$table_name		Table to modify
	 * @param string|array	$column			Either a string with a column name, or an array with columns
	 * @return array|true					Statements to run, or TRUE if the statements have been executed
	 */
	public function sql_create_primary_key($table_name, $column)
	{
		if (!$this->sql_table_exists($table_name))
		{
			return $this->return_statements ? [] : false;
		}

		$table = $this->get_table($table_name);
		$index = $table->setPrimaryKey((array) $column)
			->getPrimaryKey();

		$table_diff = new TableDiff($table_name);
		$table_diff->addedIndexes = [$index];

		$queries = [];

		try
		{
			$queries = $this->platform->getAlterTableSQL($table_diff);
		}
		catch (DBALException $e)
		{
		}

		return $this->_sql_run_sql($queries);
	}

	/**
	 * Drop an index.
	 *
	 * @param string	$table_name		Table to modify
	 * @param string	$index_name		Name of the index to delete
	 * @return array|true				Statements to run, or TRUE if the statements have been executed
	 */
	public function sql_index_drop($table_name, $index_name)
	{
		if (!$this->sql_table_exists($table_name))
		{
			return $this->return_statements ? [] : true;
		}

		$table = $this->get_table($table_name);

		if (!$table->hasIndex($index_name))
		{
			return $this->return_statements ? [] : true;
		}

		$queries = [];

		try
		{
			$index = $table->getIndex($index_name);

			$table_diff = new TableDiff($table_name);
			$table_diff->removedIndexes = [$index];

			$queries = $this->platform->getAlterTableSQL($table_diff);
		}
		catch (DBALException $e)
		{
		}

		return $this->_sql_run_sql($queries);
	}

	/**
	 * Prepare column data for ease of use.
	 *
	 * @param array		$data		The column data
	 * @return array				The column data
	 */
	protected function sql_prepare_column_data(array $data)
	{
		$type_array = explode(':', $data[0]);
		$phpbb_type = $type_array[0];
		$length = isset($type_array[1]) ? $type_array[1] : null;

		$options = self::$types_map[$phpbb_type];

		try
		{
			$options['type'] = Type::getType($options['type']);
		}
		catch (DBALException $e)
		{
			$options['type'] = null;
		}

		switch ($phpbb_type)
		{
			case 'DECIMAL':
				$options['precision'] = !empty($length) ? $length : $options['precision'];
			break;
			case 'PDECIMAL':
				$options['precision'] = !empty($length) ? $length : $options['precision'];
			break;
			default:
				$options['length'] = !empty($length) ? $length : (!empty($options['length']) ? $options['length'] : null);
			break;
		}

		$options['default'] = is_array($data[1]) ? $data[1]['default'] : $data[1];

		if (isset($data[2]))
		{
			if ($data[2] === 'auto_increment')
			{
				$options['autoincrement'] = true;
			}
			else if ($data[2] === 'true_sort' && $this->db->getDriver()->getName() === 'mysqli')
			{
				$options['collation'] = 'utf8_unicode_ci';
			}
		}

		return $options;
	}

	/**
	 * Get a table.
	 *
	 * @param string	$table_name		The table name
	 * @return \Doctrine\DBAL\Schema\Table
	 */
	protected function get_table($table_name)
	{
		$table = null;

		try
		{
			$table = $this->schema->getTable($table_name);
		}
		catch (DBALException $e)
		{
			// Never thrown, existance should of already been checked
		}

		return $table;
	}

	/**
	 * Private method for handling the return statements
	 *
	 * Either execute the SQL statements or return the SQL statements as array.
	 *
	 * @param array		$queries		The SQL queries
	 * @return array|true				Statements to run, or TRUE if the statements have been executed
	 */
	protected function _sql_run_sql(array $queries)
	{
		if ($this->return_statements)
		{
			return (array) $queries;
		}
		else
		{
			foreach ($queries as $query)
			{
				$this->db->sql_query($query);
			}

			return true;
		}
	}
}
