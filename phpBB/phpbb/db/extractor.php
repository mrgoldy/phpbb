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
use phpbb\db\exception\extractor_not_initialized_exception;
use phpbb\db\exception\invalid_format_exception;

class extractor
{
	/** @var connection */
	protected $db;

	/** @var \phpbb\filesystem\temp */
	protected $temp;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var bool Whether or not the back up should be stored */
	protected $download;

	/** @var bool Whether or not the back up should be downloaded */
	protected $store;

	/** @var int The time of initiation */
	protected $time;

	/** @var string The format to use for storage (text|bzip2|gzip) */
	protected $format;

	/** @var \Doctrine\DBAL\Platforms\AbstractPlatform */
	protected $platform;

	/** @var \Doctrine\DBAL\Schema\Schema */
	protected $schema;

	/** @var string The SQL comment character */
	protected $comment;

	/** @var resource The backup file */
	protected $fp;

	/** @var string The binary safe file write function */
	protected $write;

	/** @var string The binary safe file close function */
	protected $close;

	/** @var bool @todo */
	protected $run_comp;

	/** @var bool Whether or not this extractor is initialized */
	protected $is_initialized;

	/**
	 * Constructor.
	 *
	 * @param \phpbb\db\connection			$db				Database object
	 * @param \phpbb\filesystem\temp		$temp			Temp filesystem object
	 * @param \phpbb\request\request		$request		Request object
	 */
	public function __construct(connection $db, \phpbb\filesystem\temp $temp, \phpbb\request\request $request)
	{
		$this->db				= $db;
		$this->temp 			= $temp;
		$this->request			= $request;

		$this->fp				= null;
		$this->is_initialized   = false;
	}

	/**
	 * Start the extraction of the database.
	 *
	 * This function initializes the database extraction.
	 * It is required to call this function before calling any other extractor functions.
	 *
	 * @param string	$format						The format (text|bzip2|gzip)
	 * @param string	$filename					The filename
	 * @param int		$time						The initiation time
	 * @param bool		$download					The download indicator
	 * @param bool		$store						The store indicator
	 * @throws invalid_format_exception				When the format is invalid
	 * @return void
	 */
	public function init_extractor($format, $filename, $time, $download = false, $store = false)
	{
		$this->download	= $download;
		$this->store	= $store;
		$this->time		= $time;
		$this->format	= $format;

		$this->schema	= $this->db->getSchemaManager()->createSchema();

		try
		{
			$this->platform	= $this->db->getDatabasePlatform();
			$this->comment	= $this->platform->getSqlCommentStartString();
		}
		catch (DBALException $e)
		{
			return;
		}

		switch ($format)
		{
			case 'text':
				$extension = '.sql';
				$mime_type = 'text/x-sql';
				$open_file = 'fopen';

				$this->write = 'fwrite';
				$this->close = 'fclose';
			break;

			case 'bzip2':
				$extension = '.sql.bz2';
				$mime_type = 'application/x-bzip2';
				$open_file = 'bzopen';

				$this->write = 'bzwrite';
				$this->close = 'bzclose';
			break;

			case 'gzip':
				$extension = '.sql.gz';
				$mime_type = 'application/x-gzip';
				$open_file = 'gzopen';

				$this->write = 'gzwrite';
				$this->close = 'gzclose';
			break;

			default:
				throw new invalid_format_exception();
			break;
		}

		if ($download === true)
		{
			$name = $filename . $extension;

			header('Cache-Control: private, no-cache');
			header('Content-Type: ' . $mime_type . ';  name="' . $name . '"');
			header('Content-disposition: attachment; filename=' . $name);

			switch ($format)
			{
				case 'bzip2':
					ob_start();
				break;

				case 'gzip':
					if (strpos($this->request->header('Accept-Encoding'), 'gzip') !== false
						&& strpos(strtolower($this->request->header('User-Agent')), 'msie') === false)
					{
						ob_start('ob_gzhandler');
					}
					else
					{
						$this->run_comp = true;
					}
				break;
			}
		}

		if ($store === true)
		{
			$file = $this->temp->get_dir() . '/' . $filename . $extension;

			$this->fp = $open_file($file, 'w');

			if (!$this->fp)
			{
				throw new \phpbb\exception\runtime_exception('FILE_WRITE_FAIL');
			}
		}

		$this->is_initialized = true;
	}

	/**
	 * Writes header comments to the database backup.
	 *
	 * @param string	$table_prefix				prefix of phpBB database tables
	 * @throws extractor_not_initialized_exception	when calling this function before init_extractor()
	 * @return void
	 */
	public function write_start($table_prefix)
	{
		$data = [];

		$data .= "{$this->comment} \n";
		$data .= "{$this->comment} phpBB Backup Script\n";
		$data .= "{$this->comment} Dump of tables for $table_prefix\n";
		$data .= "{$this->comment} DATE : " . gmdate('d-m-Y H:i:s', $this->time) . " GMT\n";
		$data .= "{$this->comment} \n";
		$data .= "BEGIN TRANSACTION;\n";

		$this->flush($data);
	}

	/**
	 * Closes file and/or dumps download data.
	 *
	 * @throws extractor_not_initialized_exception	when calling this function before init_extractor()
	 * @return void
	 */
	public function write_end()
	{
		static $close;

		if (!$this->is_initialized)
		{
			throw new extractor_not_initialized_exception();
		}

		$this->flush("COMMIT;\n");

		if ($this->store)
		{
			if ($close === null)
			{
				$close = $this->close;
			}

			$close($this->fp);
		}

		// bzip2 must be written all the way at the end
		if ($this->download && $this->format === 'bzip2')
		{
			$c = ob_get_clean();

			echo bzcompress($c);
		}
	}

	/**
	 * Extracts database table structure.
	 *
	 * @param string	$table_name					name of the database table
	 * @throws extractor_not_initialized_exception	when calling this function before init_extractor()
	 * @return void
	 */
	public function write_table($table_name)
	{
		if (!$this->is_initialized)
		{
			throw new extractor_not_initialized_exception();
		}

		try
		{
			$table = $this->schema->getTable($table_name);

			$data = '';
			$data .= "{$this->comment} \n";
			$data .= "{$this->comment} Table: {$table->getName()}\n";
			$data .= "{$this->comment} \n";
			$data .= $this->platform->getDropTableSQL($table) . ";\n";

			foreach ($this->platform->getCreateTableSQL($table) as $query)
			{
				$data .= $query . ";\n";
			}

			$this->flush($data);
		}
		catch (DBALException $e)
		{
		}
	}

	/**
	 * Extracts data from database table.
	 *
	 * @param string	$table_name					name of the database table
	 * @throws extractor_not_initialized_exception	when calling this function before init_extractor()
	 * @return void
	 */
	public function write_data($table_name)
	{
		if (!$this->is_initialized)
		{
			throw new extractor_not_initialized_exception();
		}

		$data = '';

		// Grab all of the data from current table.
		$sql = 'SELECT * FROM ' . $table_name;
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$data .= 'INSERT INTO ' . $table_name . ' ' . $this->db->sql_build_array('INSERT', $row) . ";\n";
		}
		$this->db->sql_freeresult($result);

		$this->flush($data);
	}

	/**
	 * Writes data to file/download content.
	 *
	 * @param string	$data
	 * @throws extractor_not_initialized_exception	when calling this function before init_extractor()
	 * @return void
	 */
	public function flush($data)
	{
		static $write;

		if (!$this->is_initialized)
		{
			throw new extractor_not_initialized_exception();
		}

		if ($this->store === true)
		{
			if ($write === null)
			{
				$write = $this->write;
			}

			$write($this->fp, $data);
		}

		if ($this->download === true)
		{
			if ($this->format === 'bzip2' || $this->format === 'text' || ($this->format === 'gzip' && !$this->run_comp))
			{
				echo $data;
			}

			// we can write the gzip data as soon as we get it
			if ($this->format === 'gzip')
			{
				if ($this->run_comp)
				{
					echo gzencode($data);
				}
				else
				{
					ob_flush();
					flush();
				}
			}
		}
	}
}
