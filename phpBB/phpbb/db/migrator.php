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

use phpbb\db\exception\migration_exception;
use phpbb\db\migration\output\null_output;
use phpbb\db\migration\output\output_interface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The migrator is responsible for applying new migrations in the correct order.
 */
class migrator
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var ContainerInterface  */
	protected $container;

	/** @var \phpbb\db\connection */
	protected $db;

	/** @var \phpbb\db\tools */
	protected $db_tools;

	/** @var \phpbb\event\dispatcher */
	protected $dispatcher;

	/** @var migration\helper\helper */
	protected $helper;

	/** @var string Migrations table */
	protected $migrations_table;

	/** @var string Table prefix */
	protected $table_prefix;

	/** @var string phpBB root path */
	protected $root_path;

	/** @var string php File extension */
	protected $php_ext;

	/** @var migration\tool\tool_interface[] */
	protected $tools;

	/** @var array */
	protected $migration_last;

	/** @var array */
	protected $migrations;

	/** @var array */
	protected $migrations_states;

	/** @var array */
	protected $migrations_fulfillable;

	/** @var null_output */
	protected $output_handler;

	/**
	 * Constructor.
	 *
	 * @param \phpbb\config\config				$config				Config object
	 * @param ContainerInterface				$container			Service container object
	 * @param \phpbb\db\connection				$db					Database object
	 * @param \phpbb\db\tools					$db_tools			Database tools object
	 * @param \phpbb\event\dispatcher			$dispatcher			Event dispatcher object
	 * @param migration\helper\helper			$helper				Migration helper object
	 * @param string							$migrations_table	Module table
	 * @param string							$table_prefix		Table prefix
	 * @param string							$root_path			phpBB root path
	 * @param string							$php_ext			php File extension
	 * @param migration\tool\tool_interface[]	$tools				Migrations tools array
	 * @return void
	 */
	public function __construct(
		\phpbb\config\config $config,
		ContainerInterface $container,
		connection $db,
		tools $db_tools,
		\phpbb\event\dispatcher $dispatcher,
		migration\helper\helper $helper,
		$migrations_table,
		$table_prefix,
		$root_path,
		$php_ext,
		$tools
	)
	{
		$this->config			= $config;
		$this->container		= $container;
		$this->db				= $db;
		$this->db_tools			= $db_tools;
		$this->dispatcher		= $dispatcher;
		$this->helper			= $helper;

		$this->migrations_table	= $migrations_table;
		$this->table_prefix		= $table_prefix;
		$this->root_path		= $root_path;
		$this->php_ext			= $php_ext;

		/** @var \phpbb\db\migration\tool\tool_interface $tool */
		foreach ($tools as $tool)
		{
			$this->tools[$tool->get_name()] = $tool;
		}

		$this->tools['dbtools'] = $db_tools;

		$this->output_handler = new null_output();

		$this->load_migrations_states();
	}

	/**
	 * X
	 *
	 * @param string	$migration		The migration class
	 * @return bool						TRUE if class is a migration, FALSE otherwise.
	 * @static
	 */
	static public function is_migration($migration)
	{
		if (class_exists($migration))
		{
			try
			{
				$reflector = new \ReflectionClass($migration);
			}
			catch (\ReflectionException $e)
			{
				return false;
			}

			/**
			 * @see \phpbb\db\migration\migration_interface
			 * @see \phpbb\db\migration\migration
			 * @see \phpbb\db\migration\container_aware_migration
			 *
			 * Migration classes should be instantiable and extend either
			 * the abstract class \phpbb\migration\container_aware_migration
			 * or the abstract class \phpbb\migration\migration
			 * which implement the \phpbb\migration\migration_interface
			 */
			if ($reflector->implementsInterface('\phpbb\db\migration\migration_interface') && $reflector->isInstantiable())
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Set the output handler.
	 *
	 * @param output_interface		$handler	The output handler
	 * @return void
	 */
	public function set_output_handler(output_interface $handler)
	{
		$this->output_handler = $handler;
	}

	/**
	 * Get the last ran migration.
	 *
	 * The array contains 'name', 'class' and 'state'. 'effectively_installed' is set
	 * and set to true if the last migration was effectively_installed.
	 *
	 * @return array					The last ran migration data
	 * @access public
	 */
	public function get_last_run_migration()
	{
		return $this->migration_last;
	}

	/**
	 * Get the list of available migration classes.
	 *
	 * @return array					Array of all migrations available to be run
	 */
	public function get_migrations()
	{
		return $this->migrations;
	}

	/**
	 * Sets the list of available migration classes of the given array.
	 *
	 * Classes are verified to be migrations,
	 * @see self::is_migration()
	 *
	 * @param array		$classes		The available migration classes
	 * @return void
	 */
	public function set_migrations(array $classes)
	{
		foreach ($classes as $key => $class)
		{
			if (!self::is_migration($class))
			{
				unset($classes[$key]);
			}
		}

		$this->migrations = $classes;
	}

	/**
	 * Get the list of available and not installed migration class names.
	 *
	 * @return array					The available uninstalled migration classes.
	 */
	public function get_installable_migrations()
	{
		$unfinished_migrations = [];

		foreach ($this->migrations as $name)
		{
			if (!isset($this->migration_state[$name]) ||
				!$this->migrations_states[$name]['migration_schema_done'] ||
				!$this->migrations_states[$name]['migration_data_done'])
			{
				$unfinished_migrations[] = $name;
			}
		}

		return $unfinished_migrations;
	}

	/**
	 * Loads all migrations and their application state from the database.
	 *
	 * Called upon constructing this class
	 * @see self::__constructor()
	 *
	 * @return void
	 */
	public function load_migrations_states()
	{
		$this->migrations_states = [];

		// Prevent exceptions in case the table does not exist yet
		$this->db->sql_return_on_error(false);

		$sql = 'SELECT * FROM ' . $this->migrations_table;
		$result = $this->db->sql_query($sql);

		if (!$this->db->get_sql_error_triggered())
		{
			while ($migration = $this->db->sql_fetchrow($result))
			{
				$this->migrations_states[$migration['migration_name']] = $migration;

				$this->migrations_states[$migration['migration_name']]['migration_depends_on'] = unserialize($migration['migration_depends_on']);
				$this->migrations_states[$migration['migration_name']]['migration_data_state'] = !empty($migration['migration_data_state']) ? unserialize($migration['migration_data_state']) : '';
			}
		}

		$this->db->sql_freeresult($result);

		$this->db->sql_return_on_error(false);
	}

	/**
	 * Checks if a migration's dependencies can even theoretically be satisfied.
	 *
	 * @param string	$name		The migration class name
	 * @return bool|string			FALSE if fulfillable, string of missing migration name if unfulfillable
	 */
	public function unfulfillable($name)
	{
		$name = $this->get_valid_name($name);

		if (isset($this->migrations_states[$name]) || isset($this->migrations_fulfillable[$name]))
		{
			return false;
		}

		if (!class_exists($name))
		{
			return $name;
		}

		/** @var \phpbb\db\migration\migration_interface $migration */
		$migration = $this->get_migration($name);
		$depends = $migration->depends_on();

		foreach ($depends as $depend)
		{
			$depend = $this->get_valid_name($depend);
			$unfulfillable = $this->unfulfillable($depend);

			if ($unfulfillable !== false)
			{
				return $unfulfillable;
			}
		}

		$this->migrations_fulfillable[$name] = true;

		return false;
	}

	/**
	 * Checks whether all available, fulfillable migrations have been applied.
	 *
	 * @return bool					Whether or not the migrations have been applied
	 */
	public function finished()
	{
		foreach ($this->migrations as $migration)
		{
			if (!isset($this->migrations_states[$migration]))
			{
				// skip unfulfillable migrations, but fulfillable mean we are not finished yet
				if ($this->unfulfillable($migration) !== false)
				{
					continue;
				}

				return false;
			}

			$state = $this->migrations_states[$migration];

			if (!$state['migration_schema_done'] || !$state['migration_data_done'])
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Gets a migration state.
	 *
	 * Whether it is installed and to what extend.
	 *
	 * @param string	$migration		Migration name to check if it is installed
	 * @return bool|array				Array of migration state data,
	 * 									FALSE if the migration has not at all been installed
	 */
	public function migration_state($migration)
	{
		if (!isset($this->migration_state[$migration]))
		{
			return false;
		}

		return $this->migrations_states[$migration];
	}

	/**
	 * This function adds all migrations sent to it to the migrations table.
	 *
	 * THIS SHOULD NOT GENERALLY BE USED! THIS IS FOR THE PHPBB INSTALLER.
	 * THIS WILL THROW ERRORS IF MIGRATIONS ALREADY EXIST IN THE TABLE, DO NOT CALL MORE THAN ONCE!
	 *
	 * @param array		$migrations		Array of migrations (names) to add to the migrations table
	 * @return void
	 */
	public function populate_migrations(array $migrations)
	{
		/** @var \phpbb\db\migration\migration_interface $migration */
		foreach ($migrations as $migration)
		{
			if ($this->migration_state($migration) === false)
			{
				$this->set_migration_state($migration, [
					'migration_depends_on'	=> $migration->depends_on(),
					'migration_schema_done' => true,
					'migration_data_done'	=> true,
					'migration_data_state'	=> '',
					'migration_start_time'	=> time(),
					'migration_end_time'	=> time(),
				]);
			}
		}
	}

	/**
	 * Creates the migrations table if it does not yet exist.
	 *
	 * @return void
	 */
	public function create_migrations_table()
	{
		// Make sure migrations have been installed.
		if (!$this->db_tools->sql_table_exists($this->migrations_table))
		{
			$this->db_tools->sql_create_table($this->migrations_table, [
				'COLUMNS'		=> [
					'migration_name'			=> ['VCHAR', ''],
					'migration_depends_on'		=> ['TEXT', ''],
					'migration_schema_done'		=> ['BOOL', 0],
					'migration_data_done'		=> ['BOOL', 0],
					'migration_data_state'		=> ['TEXT', ''],
					'migration_start_time'		=> ['TIMESTAMP', 0],
					'migration_end_time'		=> ['TIMESTAMP', 0],
				],
				'PRIMARY_KEY'	=> 'migration_name',
			]);
		}
	}

	/**
	 * Runs a single update step from the next migration to be applied.
	 *
	 * The update step can either be a schema or a (partial) data update.
	 * To check if update() needs to be called again use the finished() method,
	 * @see self::finished()
	 *
	 * @throws migration_exception		If migration dependencies are missing
	 * @return void
	 */
	public function update()
	{
		$this->dispatcher->disable();

		$this->update_do();

		$this->dispatcher->enable();
	}

	/**
	 * Runs a single revert step from the last migration installed.
	 *
	 * YOU MUST ADD/SET ALL MIGRATIONS THAT COULD BE DEPENDENT ON THE MIGRATION TO REVERT TO BEFORE CALLING THIS METHOD!
	 * The revert step can either be a schema or a (partial) data revert.
	 * To check if revert() needs to be called again use the migration_state() method,
	 * @see self::migration_state()
	 *
	 * @param string	$migration		Migration name to revert (including any that depend on this migration)
	 * @throws migration_exception		If the migration has invalid data
	 * @return void
	 */
	public function revert($migration)
	{
		$this->dispatcher->disable();

		$this->revert_do($migration);

		$this->dispatcher->enable();
	}

	/**
	 * Effectively runs a single update step from the next migration to be applied.
	 *
	 * @throws migration_exception        If migration dependencies are missing
	 * @return void
	 */
	protected function update_do()
	{
		foreach ($this->migrations as $name)
		{
			$name = $this->get_valid_name($name);

			if (!isset($this->migrations_states[$name]) ||
				!$this->migrations_states[$name]['migration_schema_done'] ||
				!$this->migrations_states[$name]['migration_data_done']
			)
			{
				if (!$this->try_apply($name))
				{
					continue;
				}
				else
				{
					return;
				}
			}
			else
			{
				$this->output_handler->write(['MIGRATION_EFFECTIVELY_INSTALLED', $name], output_interface::VERBOSITY_DEBUG);
			}
		}
	}

	/**
	 * Attempts to apply a step of the given migration or one of its dependencies.
	 *
	 * @param string $name The class name of the migration
	 * @throws migration_exception        If migration dependencies are missing
	 * @return bool                        Whether or not any update step successfully ran
	 */
	protected function try_apply($name)
	{
		if (!class_exists($name))
		{
			$this->output_handler->write(['MIGRATION_NOT_VALID', $name], output_interface::VERBOSITY_DEBUG);

			return false;
		}

		/** @var \phpbb\db\migration\migration_interface $migration */
		$migration = $this->get_migration($name);

		if (isset($this->migrations_states[$name]))
		{
			$state = $this->migrations_states[$name];
		}
		else
		{
			$state = [
				'migration_depends_on'	=> $migration->depends_on(),
				'migration_schema_done' => false,
				'migration_data_done'	=> false,
				'migration_data_state'	=> '',
				'migration_start_time'	=> 0,
				'migration_end_time'	=> 0,
			];
		}

		if (!empty($state['migration_depends_on']))
		{
			$this->output_handler->write(['MIGRATION_APPLY_DEPENDENCIES', $name], output_interface::VERBOSITY_DEBUG);
		}

		foreach ($state['migration_depends_on'] as $depend)
		{
			$depend = $this->get_valid_name($depend);

			// Test all possible namings before throwing exception
			if ($this->unfulfillable($depend) !== false)
			{
				throw new migration_exception('MIGRATION_NOT_FULFILLABLE', $name, $depend);
			}

			if (!isset($this->migrations_states[$depend]) ||
				!$this->migrations_states[$depend]['migration_schema_done'] ||
				!$this->migrations_states[$depend]['migration_data_done']
			)
			{
				return $this->try_apply($depend);
			}
		}

		$this->migration_last = [
			'name'	=> $name,
			'class'	=> $migration,
			'state'	=> $state,
			'task'	=> '',
		];

		if (!isset($this->migration_state[$name]))
		{
			if ($state['migration_start_time'] == 0 && $migration->effectively_installed())
			{
				$state = [
					'migration_depends_on'	=> $migration->depends_on(),
					'migration_schema_done' => true,
					'migration_data_done'	=> true,
					'migration_data_state'	=> '',
					'migration_start_time'	=> 0,
					'migration_end_time'	=> 0,
				];

				$this->migration_last['effectively_installed'] = true;

				$this->output_handler->write(['MIGRATION_EFFECTIVELY_INSTALLED', $name], output_interface::VERBOSITY_VERBOSE);
			}
			else
			{
				$state['migration_start_time'] = time();
			}
		}

		$this->set_migration_state($name, $state);

		if (!$state['migration_schema_done'])
		{

			$this->output_handler->write(['MIGRATION_SCHEMA_RUNNING', $name], $this->get_verbosity(empty($state['migration_data_state'])));

			$this->migration_last['task'] = 'process_schema_step';

			$s_time_total	= is_array($state['migration_data_state']) && isset($state['migration_data_state']['_total_time']);
			$time_total		= $s_time_total ? $state['migration_data_state']['_total_time'] : 0.0;
			$time_elapsed	= microtime(true);

			$steps	= $this->helper->get_schema_steps($migration->update_schema());
			$result	= $this->process_data_step($steps, $state['migration_data_state']);

			$time_elapsed = microtime(true) - $time_elapsed;
			$time_total += $time_elapsed;

			if (is_array($result))
			{
				$result['_total_time'] = $time_total;
			}

			$state['migration_data_state']	= $result === true ? '' : $result;
			$state['migration_schema_done']	= $result === true;

			if ($state['migration_schema_done'])
			{
				$this->output_handler->write(['MIGRATION_SCHEMA_DONE', $name, $time_total], output_interface::VERBOSITY_NORMAL);
			}
			else
			{
				$this->output_handler->write(['MIGRATION_SCHEMA_IN_PROGRESS', $name, $time_elapsed], output_interface::VERBOSITY_VERY_VERBOSE);
			}
		}
		else if (!$state['migration_data_done'])
		{
			try
			{
				$this->output_handler->write(['MIGRATION_DATA_RUNNING', $name], $this->get_verbosity(empty($state['migration_data_state'])));

				$this->migration_last['task'] = 'process_data_step';

				$s_time_total = is_array($state['migration_data_state']) && isset($state['migration_data_state']['_total_time']);
				$time_total = $s_time_total ? $state['migration_data_state']['_total_time'] : 0.0;
				$time_elapsed = microtime(true);

				$result = $this->process_data_step($migration->update_data(), $state['migration_data_state']);

				$time_elapsed = microtime(true) - $time_elapsed;
				$time_total += $time_elapsed;

				if (is_array($result))
				{
					$result['_total_time'] = $time_total;
				}

				$state['migration_data_state']	= $result === true ? '' : $result;
				$state['migration_data_done']	= $result === true;
				$state['migration_end_time']	= $result === true ? time() : 0;

				if ($state['migration_data_done'])
				{
					$this->output_handler->write(['MIGRATION_DATA_DONE', $name, $time_total], output_interface::VERBOSITY_NORMAL);
				}
				else
				{
					$this->output_handler->write(['MIGRATION_DATA_IN_PROGRESS', $name, $time_elapsed], output_interface::VERBOSITY_VERY_VERBOSE);
				}
			}
			catch (migration_exception $e)
			{
				// Reset data state and revert the schema changes
				$state['migration_data_state'] = '';
				$this->set_migration_state($name, $state);

				$this->revert_do($name);

				throw $e;
			}
		}

		$this->set_migration_state($name, $state);

		return true;
	}

	/**
	 * Effectively runs a single revert step from the last migration installed.
	 *
	 * @param string	$migration		Migration name to revert (including any that depend on this migration)
	 * @throws migration_exception		If the migration has invalid data
	 * @return void
	 */
	protected function revert_do($migration)
	{
		if (!isset($this->migration_state[$migration]))
		{
			// Not installed
			return;
		}

		foreach ($this->migrations as $name)
		{
			$state = $this->migration_state($name);

			if ($state && in_array($migration, $state['migration_depends_on']) && ($state['migration_schema_done'] || $state['migration_data_done']))
			{
				$this->revert_do($name);
				return;
			}
		}

		$this->try_revert($migration);
	}

	/**
	 * Attempts to revert a step of the given migration or one of its dependencies
	 *
	 * @param string	$name			The migration class name
	 * @throws migration_exception		If the migration has invalid data
	 * @return bool						Whether or not any update step successfully ran
	 */
	protected function try_revert($name)
	{
		if (!class_exists($name))
		{
			return false;
		}

		/** @var \phpbb\db\migration\migration_interface $migration */
		$migration = $this->get_migration($name);

		$state = $this->migrations_states[$name];

		$this->migration_last = [
			'name'	=> $name,
			'class'	=> $migration,
			'task'	=> '',
		];

		if ($state['migration_data_done'])
		{
			$this->output_handler->write(['MIGRATION_REVERT_DATA_RUNNING', $name], $this->get_verbosity(empty($state['migration_data_state'])));

			$s_time_total	= is_array($state['migration_data_state']) && isset($state['migration_data_state']['_total_time']);
			$time_total		= $s_time_total ? $state['migration_data_state']['_total_time'] : 0.0;
			$time_elapsed	= microtime(true);

			$steps	= array_merge($this->helper->reverse_update_data($migration->update_data()), $migration->revert_data());
			$result	= $this->process_data_step($steps, $state['migration_data_state']);

			$time_elapsed = microtime(true) - $time_elapsed;
			$time_total += $time_elapsed;

			if (is_array($result))
			{
				$result['_total_time'] = $time_total;
			}

			$state['migration_data_state']	= $result === true ? '' : $result;
			$state['migration_data_done']	= $result === true ? false : true;

			$this->set_migration_state($name, $state);

			if (!$state['migration_data_done'])
			{
				$this->output_handler->write(['MIGRATION_REVERT_DATA_DONE', $name, $time_total], output_interface::VERBOSITY_NORMAL);
			}
			else
			{
				$this->output_handler->write(['MIGRATION_REVERT_DATA_IN_PROGRESS', $name, $time_elapsed], output_interface::VERBOSITY_VERY_VERBOSE);
			}
		}
		else if ($state['migration_schema_done'])
		{
			$this->output_handler->write(['MIGRATION_REVERT_SCHEMA_RUNNING', $name], $this->get_verbosity(empty($state['migration_data_state'])));

			$s_time_total	= is_array($state['migration_data_state']) && isset($state['migration_data_state']['_total_time']);
			$time_total		= $s_time_total ? $state['migration_data_state']['_total_time'] : 0.0;
			$time_elapsed	= microtime(true);

			$steps	= $this->helper->get_schema_steps($migration->revert_schema());
			$result	= $this->process_data_step($steps, $state['migration_data_state']);

			$time_elapsed = microtime(true) - $time_elapsed;
			$time_total += $time_elapsed;

			if (is_array($result))
			{
				$result['_total_time'] = $time_total;
			}

			$state['migration_data_state']	= $result === true ? '' : $result;
			$state['migration_schema_done']	= $result === true ? false : true;

			if (!$state['migration_schema_done'])
			{
				$sql = 'DELETE FROM ' . $this->migrations_table . "
					WHERE migration_name = '" . $this->db->sql_escape($name) . "'";
				$this->db->sql_query($sql);

				$this->migration_last = [];
				unset($this->migrations_states[$name]);

				$this->output_handler->write(['MIGRATION_REVERT_SCHEMA_DONE', $name, $time_total], output_interface::VERBOSITY_NORMAL);
			}
			else
			{
				$this->set_migration_state($name, $state);

				$this->output_handler->write(['MIGRATION_REVERT_SCHEMA_IN_PROGRESS', $name, $time_elapsed], output_interface::VERBOSITY_VERY_VERBOSE);
			}
		}

		return true;
	}

	/**
	 * Process the data step(s) of the migration
	 *
	 * @param array		$steps			The migration step(s)
	 * @param mixed		$state			Current state of the migration
	 * @param bool		$revert			TRUE to revert the data step(s)
	 * @throws migration_exception		If the migration has invalid data
	 * @return array|true				The migration state; TRUE if completed, serialized array if not finished
	 */
	protected function process_data_step(array $steps, $state, $revert = false)
	{
		if (count($steps) === 0)
		{
			return true;
		}

		$state = is_array($state) ? $state : false;

		// reverse order of steps if reverting
		if ($revert === true)
		{
			$steps = array_reverse($steps);
		}

		$step = $last_result = 0;
		if ($state)
		{
			$step = $state['step'];

			// We send the result from last time to the callable function
			$last_result = $state['result'];
		}

		try
		{
			// Result will be null or true if everything completed correctly
			// Stop after each update step, to let the updater control the script runtime
			$result = $this->run_step($steps[$step], $last_result, $revert);
			$s_result = $result !== null && $result !== true;

			if ($s_result || $step + 1 < count($steps))
			{
				// Move on if the last call finished
				return [
					'result'	=> $result,
					'step'		=> $s_result ? $step : $step + 1,
				];
			}
		}
		catch (migration_exception $e)
		{
			// We should try rolling back here
			foreach ($steps as $reverse_step_identifier => $reverse_step)
			{
				// If we've reached the current step we can break because we reversed everything that was run
				if ($reverse_step_identifier == $step)
				{
					break;
				}

				// Reverse the step that was run
				$this->run_step($reverse_step, false, !$revert);
			}

			throw $e;
		}

		return true;
	}

	/**
	 * Run a single step.
	 *
	 * An exception should be thrown if an error occurs.
	 *
	 * @param array		$step			The migration data step
	 * @param int		$last_result	Result to pass to the callable (only for 'custom' method)
	 * @param bool		$reverse		TRUE to revert the data step
	 * @throws migration_exception		If the migration has invalid data
	 * @return mixed|null
	 */
	protected function run_step($step, $last_result = 0, $reverse = false)
	{
		$callable_and_parameters = $this->get_callable_from_step($step, $last_result, $reverse);

		if ($callable_and_parameters === false)
		{
			return null;
		}

		$callable = $callable_and_parameters[0];
		$parameters = $callable_and_parameters[1];

		return call_user_func_array($callable, $parameters);
	}

	/**
	 * Get a callable statement from a data step.
	 *
	 * @param array		$step			The migration data step
	 * @param int		$last_result	Result to pass to the callable (only for 'custom' method)
	 * @param bool		$reverse		TRUE to revert the data step
	 * @throws migration_exception		If the migration has invalid data
	 * @return array|bool				Callable as first value followed by optional parameters
	 */
	protected function get_callable_from_step(array $step, $last_result = 0, $reverse = false)
	{
		$type = $step[0];
		$parameters = $step[1];

		$parts = explode('.', $type);

		$tool = $parts[0];
		$method = false;

		if (isset($parts[1]))
		{
			$method = $parts[1];
		}

		switch ($tool)
		{
			case 'if':
				if (!isset($parameters[0]))
				{
					throw new migration_exception('MIGRATION_INVALID_DATA_MISSING_CONDITION', $step);
				}

				if (!isset($parameters[1]))
				{
					throw new migration_exception('MIGRATION_INVALID_DATA_MISSING_STEP', $step);
				}

				if ($reverse)
				{
					// We might get unexpected results when trying to revert this, so just avoid it
					return false;
				}

				$condition = $parameters[0];

				if (!$condition || (is_array($condition) && !$this->run_step($condition, $last_result, $reverse)))
				{
					return false;
				}

				$step = $parameters[1];

				return $this->get_callable_from_step($step);
			break;

			case 'custom':
				if (!is_callable($parameters[0]))
				{
					throw new migration_exception('MIGRATION_INVALID_DATA_CUSTOM_NOT_CALLABLE', $step);
				}

				if ($reverse)
				{
					return false;
				}
				else
				{
					$parameter_2 = isset($parameters[1]) ? array_merge($parameters[1], [$last_result]) : [$last_result];

					return [$parameters[0], $parameter_2];
				}
			break;

			default:
				if (!$method)
				{
					throw new migration_exception('MIGRATION_INVALID_DATA_UNKNOWN_TYPE', $step);
				}

				if (!isset($this->tools[$tool]))
				{
					print_r($tool);
					throw new migration_exception('MIGRATION_INVALID_DATA_UNDEFINED_TOOL', $step);
				}

				if (!method_exists(get_class($this->tools[$tool]), $method))
				{
					throw new migration_exception('MIGRATION_INVALID_DATA_UNDEFINED_METHOD', $step);
				}

				// Attempt to reverse operations
				if ($reverse)
				{
					array_unshift($parameters, $method);

					return [[$this->tools[$tool], 'reverse'], $parameters];
				}

				return [[$this->tools[$tool], $method], $parameters];
			break;
		}
	}

	/**
	 * Insert/Update migration row into the database.
	 *
	 * @param string	$name			The migration name
	 * @param array		$state			The migration state
	 * @return void
	 */
	protected function set_migration_state($name, array $state)
	{
		$migration_row = $state;
		$migration_row['migration_depends_on'] = serialize($state['migration_depends_on']);
		$migration_row['migration_data_state'] = !empty($state['migration_data_state']) ? serialize($state['migration_data_state']) : '';

		if (isset($this->migrations_states[$name]))
		{
			$sql = 'UPDATE ' . $this->migrations_table . '
				SET ' . $this->db->sql_build_array('UPDATE', $migration_row) . "
				WHERE migration_name = '" . $this->db->sql_escape($name) . "'";
			$this->db->sql_query($sql);
		}
		else
		{
			$migration_row['migration_name'] = $name;
			$sql = 'INSERT INTO ' . $this->migrations_table . '
				' . $this->db->sql_build_array('INSERT', $migration_row);
			$this->db->sql_query($sql);
		}

		$this->migrations_states[$name] = $state;

		$this->migration_last['state'] = $state;
	}

	/**
	 * Get a valid migration name from the migration state array
	 * in case the supplied name is not in the migration state list.
	 *
	 * @param string	$name			The migration name
	 * @return string					The migration name
	 */
	protected function get_valid_name($name)
	{
		// Try falling back to a valid migration name with or without leading backslash
		if (!isset($this->migrations_states[$name]))
		{
			$prepended_name = ($name[0] == '\\' ? '' : '\\') . $name;
			$prefixless_name = $name[0] == '\\' ? substr($name, 1) : $name;

			if (isset($this->migrations_states[$prepended_name]))
			{
				$name = $prepended_name;
			}
			else if (isset($this->migrations_states[$prefixless_name]))
			{
				$name = $prefixless_name;
			}
		}

		return $name;
	}

	/**
	 * Get a migration object.
	 *
	 * @param string	$class			The migration class
	 * @return migration\migration		The migration object
	 */
	protected function get_migration($class)
	{
		/** @var migration\migration $migration */
		$migration = new $class($this->config, $this->db, $this->db_tools, $this->root_path, $this->php_ext, $this->table_prefix);

		if ($migration instanceof ContainerAwareInterface)
		{
			$migration->setContainer($this->container);
		}

		return $migration;
	}

	/**
	 * Get the output handler verbosity.
	 *
	 * @param bool		$state
	 * @return int						The verbosity
	 */
	protected function get_verbosity($state)
	{
		return $state ? output_interface::VERBOSITY_VERBOSE : output_interface::VERBOSITY_DEBUG;
	}
}
