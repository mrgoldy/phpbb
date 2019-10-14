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

abstract class profilefield_base_migration extends container_aware_migration
{
	/** @var int The profile field conversion limit */
	const CONVERT_LIMIT = 250;

	/** @var string The profile field name */
	protected $profilefield_name;

	/** @var string The profile field database type */
	protected $profilefield_database_type;

	/** @var array The profile field data */
	protected $profilefield_data;

	/**
	 * Language data should be in array -> each language_data in separate key
	 * [
	 *	[
	 *		'option_id'		=> value,
	 *		'field_type'	=> value,
	 *		'lang_value'	=> value,
	 *	],
	 *	[
	 *		'option_id'		=> value,
	 *		'field_type'	=> value,
	 *		'lang_value'	=> value,
	 *	],
	 * ]
	 */
	protected $profilefield_language_data;

	/** @var string The profile field's users table column name */
	protected $user_column_name;

	/**
	 * {@inheritdoc}
	 */
	public function effectively_installed()
	{
		return $this->db_tools->sql_column_exists($this->table_prefix . 'profile_fields_data', 'pf_' . $this->profilefield_name);
	}

	/**
	 * {@inheritdoc}
	 */
	public function update_schema()
	{
		return [
			'add_columns'	=> [
				$this->table_prefix . 'profile_fields_data'	=> [
					'pf_' . $this->profilefield_name	=> $this->profilefield_database_type,
				],
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function revert_schema()
	{
		return [
			'drop_columns'	=> [
				$this->table_prefix . 'profile_fields_data'	=> [
					'pf_' . $this->profilefield_name,
				],
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function update_data()
	{
		return [
			['custom', [[$this, 'create_custom_field']]],
			['custom', [[$this, 'convert_user_field_to_custom_field']]],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function revert_data()
	{
		return [
			['custom', [[$this, 'delete_custom_profile_field_data']]],
		];
	}

	/**
	 * Create custom profile field.
	 *
	 * @return void
	 */
	public function create_custom_field()
	{
		$max_order = $this->db->getDatabasePlatform()->getMaxExpression('field_order');

		$sql = 'SELECT ' . $max_order . ' as max_field_order
			FROM ' . $this->table_prefix . 'profile_fields';
		$result = $this->db->sql_query($sql);
		$max_field_order = (int) $this->db->sql_fetchfield('max_field_order');
		$this->db->sql_freeresult($result);

		$pf_data = array_merge($this->profilefield_data, ['field_order' => $max_field_order + 1]);

		$sql = 'INSERT INTO ' . $this->table_prefix . 'profile_fields ' . $this->db->sql_build_array('INSERT', $pf_data);
		$this->db->sql_query($sql);
		$field_id = (int) $this->db->sql_nextid();

		$lang_name = $this->profilefield_name;
		$lang_name = strpos($lang_name, 'phpbb_') === 0 ? substr($lang_name, 6) : $lang_name;
		$lang_name = utf8_strtoupper($lang_name);

		$sql = 'SELECT lang_id
			FROM ' . $this->table_prefix . 'lang';
		$result = $this->db->sql_query($sql);
		while ($lang_id = (int) $this->db->sql_fetchfield('lang_id'))
		{
			$this->db->insert($this->table_prefix . 'profile_lang', [
				'field_id'				=> (int) $field_id,
				'lang_id'				=> (int) $lang_id,
				'lang_name'				=> $lang_name,
				'lang_explain'			=> '',
				'lang_default_value'	=> '',
			]);
		}
		$this->db->sql_freeresult($result);
	}

	/**
	 * Create custom profile fields language entries.
	 *
	 * @return void
	 */
	public function create_language_entries()
	{
		$field_id = $this->get_custom_profile_field_id();

		$sql = 'SELECT lang_id
			FROM ' . LANG_TABLE;
		$result = $this->db->sql_query($sql);
		while ($lang_id = (int) $this->db->sql_fetchfield('lang_id'))
		{
			foreach ($this->profilefield_language_data as $language_data)
			{
				$this->db->insert($this->table_prefix . 'profile_fields_lang', array_merge([
					'field_id'	=> (int) $field_id,
					'lang_id'	=> (int) $lang_id,
				], $language_data));
			}
		}
		$this->db->sql_freeresult($result);
	}

	/**
	 * Clean database when reverting the migration.
	 *
	 * @return void
	 */
	public function delete_custom_profile_field_data()
	{
		$field_id = $this->get_custom_profile_field_id();

		$sql = 'DELETE FROM ' . $this->table_prefix . 'profile_fields
			WHERE field_id = ' . (int) $field_id;
		$this->db->sql_query($sql);

		$sql = 'DELETE FROM ' . $this->table_prefix . 'profile_lang
			WHERE field_id = ' . (int) $field_id;
		$this->db->sql_query($sql);

		$sql = 'DELETE FROM ' . $this->table_prefix . 'profile_fields_lang
			WHERE field_id = ' . (int) $field_id;
		$this->db->sql_query($sql);
	}

	/**
	 * Get custom profile field identifier.
	 *
	 * @return int					The custom profile field identifier
	 */
	public function get_custom_profile_field_id()
	{
		$sql = 'SELECT field_id
			FROM ' . $this->table_prefix . "profile_fields
			WHERE field_name = '" . $this->profilefield_name . "'";
		$result = $this->db->sql_query($sql);
		$field_id = $this->db->sql_fetchfield('field_id');
		$this->db->sql_freeresult($result);

		return (int) $field_id;
	}

	/**
	 * Convert user profile fields to custom profile fields.
	 *
	 * @param int		$start		Start of staggering step
	 * @return int|null				Integer start for the next step,
	 * 								Null if the end was reached
	 */
	public function convert_user_field_to_custom_field($start)
	{
		$start = $start ? $start : 0;
		$converted_users = 0;

		$sql = 'SELECT user_id, ' . $this->user_column_name . '
			FROM ' . $this->table_prefix . 'users
			WHERE ' . $this->user_column_name . " <> ''
			ORDER BY user_id";
		$result = $this->db->sql_query_limit($sql, self::CONVERT_LIMIT, $start);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$converted_users++;

			$cp_data = ['pf_' . $this->profilefield_name => $row[$this->user_column_name]];

			$sql = 'UPDATE ' . $this->table_prefix . 'profile_fields_data
				SET ' . $this->db->sql_build_array('UPDATE', $cp_data) . '
				WHERE user_id = ' . (int) $row['user_id'];
			$this->db->sql_query($sql);

			if (!$this->db->sql_affectedrows())
			{
				$cp_data['user_id'] = (int) $row['user_id'];
				$cp_data = array_merge($this->get_insert_sql_array(), $cp_data);

				$this->db->insert($this->table_prefix . 'profile_fields_data', $cp_data);
			}
		}
		$this->db->sql_freeresult($result);

		if ($converted_users < self::CONVERT_LIMIT)
		{
			// No more users left, we are done...
			return null;
		}

		return $start + self::CONVERT_LIMIT;
	}

	/**
	 * Get the array for user insertion into the custom profile fields table.
	 *
	 * @return array
	 */
	protected function get_insert_sql_array()
	{
		static $profile_row;

		if ($profile_row === null)
		{
			/* @var \phpbb\profilefields\manager $pf_manager */
			$pf_manager = $this->container->get('profilefields.manager');
			$profile_row = $pf_manager->build_insert_sql_array([]);
		}

		return $profile_row;
	}
}
