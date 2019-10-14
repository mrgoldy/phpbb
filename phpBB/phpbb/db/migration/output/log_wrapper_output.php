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

namespace phpbb\db\migration\output;

class log_wrapper_output implements output_interface
{
	/** @var resource|bool  */
	protected $file_handle = false;

	/** @var \phpbb\filesystem\filesystem */
	protected $filesystem;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var output_interface */
	protected $migrator;

	/**
	 * Constructor
	 *
	 * @param string								$log_file		File to log to
	 * @param \phpbb\filesystem\filesystem			$filesystem		phpBB filesystem object
	 * @param \phpbb\language\language				$language		Language object
	 * @param output_interface						$migrator		Migrator output handler
	 */
	public function __construct(
		$log_file,
		\phpbb\filesystem\filesystem $filesystem,
		\phpbb\language\language $language,
		output_interface $migrator
	)
	{
		$this->filesystem	= $filesystem;
		$this->language		= $language;
		$this->migrator		= $migrator;

		$this->file_open($log_file);
	}

	/**
	 * Open file for logging
	 *
	 * @param string $file File to open
	 */
	protected function file_open($file)
	{
		if ($this->filesystem->is_writable(dirname($file)))
		{
			$this->file_handle = fopen($file, 'w');
		}
		else
		{
			throw new \RuntimeException('Unable to write to migrator log file');
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function write($message, $verbosity)
	{
		$this->migrator->write($message, $verbosity);

		if ($this->file_handle !== false)
		{
			$translated_message = $this->language->lang_array(array_shift($message), $message);

			if ($verbosity <= output_interface::VERBOSITY_NORMAL)
			{
				$translated_message = '[INFO] ' . $translated_message;
			}
			else
			{
				$translated_message = '[DEBUG] ' . $translated_message;
			}

			fwrite($this->file_handle, $translated_message . "\n");
			fflush($this->file_handle);
		}
	}
}
