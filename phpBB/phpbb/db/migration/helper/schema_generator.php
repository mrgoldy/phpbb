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

namespace phpbb\db\migration\helper;

/**
 * The schema generator generates the schema based on the existing migrations.
 */
class schema_generator
{
	/** @var array */
	protected $class_names;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\connection */
	protected $db;

	/** @var \phpbb\db\tools */
	protected $db_tools;

	/** @var string phpBB root path */
	protected $root_path;

	/** @var string php File extension */
	protected $php_ext;

	/** @var string Table prefix */
	protected $table_prefix;

	/** @var array */
	protected $tables;

	/** @var array */
	protected $dependencies = [];

	/**
	 * Constructor.
	 *
	 * @param array					$class_names	Migration class names array
	 * @param \phpbb\config\config	$config			Config object
	 * @param \phpbb\db\connection	$db				Database object
	 * @param \phpbb\db\tools		$db_tools		Database tools object
	 * @param string				$root_path		phpBB root path
	 * @param string				$php_ext		php File extension
	 * @param string				$table_prefix	Table prefix
	 * @return void
	 */
	public function __construct(
		array $class_names,
		\phpbb\config\config $config,
		\phpbb\db\connection $db,
		\phpbb\db\tools $db_tools,
		$root_path,
		$php_ext,
		$table_prefix
	)
	{
		$this->class_names	= $class_names;
		$this->config		= $config;
		$this->db			= $db;
		$this->db_tools		= $db_tools;

		$this->root_path	= $root_path;
		$this->php_ext		= $php_ext;
		$this->table_prefix	= $table_prefix;
	}

	/**
	 * Loads all migrations and their application state from the database.
	 *
	 * @return array
	 */
	public function get_schema()
	{
		if (!empty($this->tables))
		{
			return $this->tables;
		}

		$tree = [];
		$check_dependencies = true;

		$migrations = $this->class_names;

		while (!empty($migrations))
		{
			foreach ($migrations as $key => $migration_class)
			{
				// Unset classes that are not a valid migration
				if (\phpbb\db\migrator::is_migration($migration_class) === false)
				{
					unset($migrations[$key]);
					continue;
				}

				$open_dependencies = array_diff($migration_class::depends_on(), $tree);

				if (empty($open_dependencies))
				{
					$tree[] = $migration_class;

					/** @var \phpbb\db\migration\migration_interface $migration */
					$migration = new $migration_class($this->config, $this->db, $this->db_tools, $this->root_path, $this->php_ext, $this->table_prefix);
					$migration_key = array_search($migration_class, $migrations);

					foreach ($migration->update_schema() as $change_type => $data)
					{
						if ($change_type === 'add_tables')
						{
							foreach ($data as $table => $table_data)
							{
								$this->tables[$table] = $table_data;
							}
						}
						else if ($change_type === 'drop_tables')
						{
							foreach ($data as $table)
							{
								unset($this->tables[$table]);
							}
						}
						else if ($change_type === 'add_columns')
						{
							foreach ($data as $table => $add_columns)
							{
								foreach ($add_columns as $column => $column_data)
								{
									if (isset($column_data['after']))
									{
										$columns = $this->tables[$table]['COLUMNS'];
										$offset = array_search($column_data['after'], array_keys($columns));
										unset($column_data['after']);

										if ($offset === false)
										{
											$this->tables[$table]['COLUMNS'][$column] = array_values($column_data);
										}
										else
										{
											$this->tables[$table]['COLUMNS'] = array_merge(array_slice($columns, 0, $offset + 1, true), [$column => array_values($column_data)], array_slice($columns, $offset));
										}
									}
									else
									{
										$this->tables[$table]['COLUMNS'][$column] = $column_data;
									}
								}
							}
						}
						else if ($change_type === 'change_columns')
						{
							foreach ($data as $table => $change_columns)
							{
								foreach ($change_columns as $column => $column_data)
								{
									$this->tables[$table]['COLUMNS'][$column] = $column_data;
								}
							}
						}
						else if ($change_type === 'drop_columns')
						{
							foreach ($data as $table => $drop_columns)
							{
								if (is_array($drop_columns))
								{
									foreach ($drop_columns as $column)
									{
										unset($this->tables[$table]['COLUMNS'][$column]);
									}
								}
								else
								{
									unset($this->tables[$table]['COLUMNS'][$drop_columns]);
								}
							}
						}
						else if ($change_type === 'add_unique_index')
						{
							foreach ($data as $table => $add_index)
							{
								foreach ($add_index as $key => $index_data)
								{
									$this->tables[$table]['KEYS'][$key] = ['UNIQUE', $index_data];
								}
							}
						}
						else if ($change_type === 'add_index')
						{
							foreach ($data as $table => $add_index)
							{
								foreach ($add_index as $key => $index_data)
								{
									$this->tables[$table]['KEYS'][$key] = ['INDEX', $index_data];
								}
							}
						}
						else if ($change_type === 'drop_keys')
						{
							foreach ($data as $table => $drop_keys)
							{
								foreach ($drop_keys as $key)
								{
									unset($this->tables[$table]['KEYS'][$key]);
								}
							}
						}
						else
						{
							var_dump($change_type);
						}
					}
					unset($migrations[$migration_key]);
				}
				else if ($check_dependencies)
				{
					$this->dependencies = array_merge($this->dependencies, $open_dependencies);
				}
			}

			// Only run this check after the first run
			if ($check_dependencies)
			{
				$this->check_dependencies();
				$check_dependencies = false;
			}
		}

		ksort($this->tables);
		return $this->tables;
	}

	/**
	 * Check if one of the migrations files' dependencies can't be resolved
	 * by the supplied list of migrations.
	 *
	 * @throws \UnexpectedValueException		If a dependency can't be resolved
	 * @return void
	 */
	protected function check_dependencies()
	{
		// Strip duplicate values from array
		$this->dependencies = array_unique($this->dependencies);

		foreach ($this->dependencies as $dependency)
		{
			if (!in_array($dependency, $this->class_names))
			{
				throw new \UnexpectedValueException("Unable to resolve the dependency '$dependency'");
			}
		}
	}
}
