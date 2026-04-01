<?php

if (!defined('QA_VERSION')) {
	header('Location: ../../');
	exit;
}

class qa_network_apply_page
{
	/**
	 * Apply key-value pairs to a table across all network sites.
	 * @param array  $options    Associative array of name => value pairs
	 * @param string $table_suffix Table name without prefix (default: 'options')
	 * @param string $key_col    Column name for the key (default: 'title')
	 * @param string $val_col    Column name for the value (default: 'content')
	 * @return array Results per site
	 */
	static function apply_to_network($options, $table_suffix = 'options', $key_col = 'title', $val_col = 'content')
	{
		// Validate column names: alphanumeric and underscores only
		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key_col) ||
			!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $val_col) ||
			!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table_suffix)) {
			return array('error' => 'Invalid table or column name');
		}

		// Gather network site prefixes
		$prefixes = array();
		$idx = 0;
		while (qa_opt('network_site_' . $idx . '_url')) {
			$prefix = qa_opt('network_site_' . $idx . '_prefix');
			$title = qa_opt('network_site_' . $idx . '_title');
			if (strlen($prefix)) {
				$prefixes[] = array('prefix' => $prefix, 'title' => $title);
			}
			$idx++;
		}

		if (empty($prefixes)) {
			return array('error' => 'No network sites configured');
		}

		$results = array();

		foreach ($prefixes as $site) {
			$table = $site['prefix'] . $table_suffix;
			$applied = 0;
			$errors = array();

			foreach ($options as $name => $value) {
				// Validate option name: alphanumeric, hyphens, underscores only
				if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) {
					$errors[] = 'Invalid option name: ' . $name;
					continue;
				}

				try {
					qa_db_query_raw(
						"INSERT INTO " . qa_db_escape_string($table) .
						" (" . $key_col . ", " . $val_col . ") VALUES ('" . qa_db_escape_string($name) .
						"', '" . qa_db_escape_string($value) .
						"') ON DUPLICATE KEY UPDATE " . $val_col . " = VALUES(" . $val_col . ")"
					);
					$applied++;
				} catch (Exception $e) {
					$errors[] = $name . ': ' . $e->getMessage();
				}
			}

			$results[] = array(
				'site' => $site['title'],
				'applied' => $applied,
				'errors' => $errors,
			);
		}

		return array('status' => 'ok', 'results' => $results);
	}

	function match_request($request)
	{
		return $request === 'network-apply-settings';
	}

	function process_request($request)
	{
		header('Content-Type: application/json; charset=utf-8');

		if (!qa_is_logged_in() || qa_get_logged_in_level() < QA_USER_LEVEL_SUPER) {
			echo json_encode(['error' => 'Super admin access required']);
			return null;
		}

		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			echo json_encode(['error' => 'POST required']);
			return null;
		}

		$raw = qa_post_text('options');
		if ($raw === null || $raw === '') {
			echo json_encode(['error' => 'No options provided']);
			return null;
		}

		$options = json_decode($raw, true);
		if (!is_array($options) || empty($options)) {
			echo json_encode(['error' => 'Invalid options']);
			return null;
		}

		// Optional: custom table suffix (default: 'options')
		$table_suffix = qa_post_text('table');
		if ($table_suffix === null || $table_suffix === '') {
			$table_suffix = 'options';
		}

		$key_col = qa_post_text('key_col');
		if ($key_col === null || $key_col === '') {
			$key_col = 'title';
		}

		$val_col = qa_post_text('val_col');
		if ($val_col === null || $val_col === '') {
			$val_col = 'content';
		}

		$result = self::apply_to_network($options, $table_suffix, $key_col, $val_col);
		echo json_encode($result);
		return null;
	}
}
