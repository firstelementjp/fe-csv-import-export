<?php
/**
 * Dry run diff computation and persistence for FE CSV Import & Export.
 *
 * @since 1.0.4
 * @package FE_CSV_Import_Export
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computes and stores per-row diffs between the current WordPress data and the
 * data that a CSV import would write during a Dry Run.
 *
 * Diff entries use a uniform shape:
 * - fields[ field_name ] = array{ old: ?string, new: ?string }
 *   `old === null` means the field has no previous value (new post/field).
 *   `new === ''` for a meta field means all stored values would be removed.
 * - Post fields keep their CSV header names, meta fields use the `cf_` prefix
 *   and taxonomies use the `tax_` prefix, matching the CSV column convention.
 *
 * @since 1.0.4
 * @package FE_CSV_Import_Export
 */
class FE_CSV_Import_Export_Import_Dry_Run_Diff {

	/**
	 * Post fields that are always rewritten on update and excluded from diffs.
	 *
	 * @since 1.0.4
	 * @var array<int, string>
	 */
	private const POST_DIFF_IGNORED_FIELDS = [ 'post_modified', 'post_modified_gmt', 'ID' ];

	/**
	 * Compute a post field diff for an update row.
	 *
	 * Compares the post data that would be written against the currently stored
	 * post row using strict string comparison.
	 *
	 * @since 1.0.4
	 * @param int   $post_id Target post ID.
	 * @param array $post_data Post data that would be written (wp_posts columns).
	 * @return array<string, array{old: ?string, new: ?string}> Changed fields keyed by column name.
	 */
	public static function diff_post_fields( int $post_id, array $post_data ): array {
		$current = get_post( $post_id, ARRAY_A );
		if ( ! is_array( $current ) ) {
			return [];
		}

		$diff = [];
		foreach ( $post_data as $key => $new_value ) {
			if ( in_array( $key, self::POST_DIFF_IGNORED_FIELDS, true ) ) {
				continue;
			}
			if ( ! array_key_exists( $key, $current ) ) {
				continue;
			}

			$old_value = (string) $current[ $key ];
			$new_value = (string) $new_value;
			if ( $old_value === $new_value ) {
				continue;
			}

			$diff[ $key ] = [
				'old' => $old_value,
				'new' => $new_value,
			];
		}

		return $diff;
	}

	/**
	 * Fetch existing meta values for all (post_id, meta_key) pairs in a batch.
	 *
	 * @since 1.0.4
	 * @param wpdb  $wpdb WordPress database handler.
	 * @param array $items Prepared batch items.
	 * @return array<int, array<string, array<int, string>>> Meta values keyed by post ID then meta key, ordered by meta_id.
	 */
	public static function get_existing_meta_values_for_batch( wpdb $wpdb, array $items ): array {
		$post_ids  = [];
		$meta_keys = [];

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$post_id = (int) ( $item['post_id'] ?? 0 );
			if ( $post_id <= 0 ) {
				continue;
			}

			$meta_fields = isset( $item['meta_fields'] ) && is_array( $item['meta_fields'] ) ? $item['meta_fields'] : [];
			foreach ( $meta_fields as $key => $value ) {
				$post_ids[]  = $post_id;
				$meta_keys[] = (string) $key;
			}
		}

		$post_ids  = array_values( array_unique( array_map( 'intval', $post_ids ) ) );
		$meta_keys = array_values( array_unique( array_map( 'strval', $meta_keys ) ) );
		if ( empty( $post_ids ) || empty( $meta_keys ) ) {
			return [];
		}

		$post_placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
		$key_placeholders  = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
		$query_args        = array_merge( $post_ids, $meta_keys );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT post_id, meta_key, meta_value
			FROM {$wpdb->postmeta}
			WHERE post_id IN ({$post_placeholders})
			AND meta_key IN ({$key_placeholders})
			ORDER BY meta_id ASC";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $query_args ), ARRAY_A );
		if ( empty( $rows ) ) {
			return [];
		}

		$existing = [];
		foreach ( $rows as $row ) {
			$post_id    = (int) ( $row['post_id'] ?? 0 );
			$meta_key   = (string) ( $row['meta_key'] ?? '' );
			$meta_value = (string) ( $row['meta_value'] ?? '' );
			if ( $post_id <= 0 || '' === $meta_key ) {
				continue;
			}
			$existing[ $post_id ][ $meta_key ][] = $meta_value;
		}

		return $existing;
	}

	/**
	 * Canonicalize a meta value for strict comparison.
	 *
	 * Serialized values are normalized to their canonical serialized form so
	 * that equivalent data stored with a different representation (e.g. the
	 * unserialized form produced by maybe_unserialize()) does not produce a
	 * false positive. Plain strings are compared as-is.
	 *
	 * @since 1.0.4
	 * @param string $value Meta value.
	 * @return string Canonical form for comparison.
	 */
	public static function canonicalize_meta_value( string $value ): string {
		if ( is_serialized( $value ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize,WordPress.PHP.NoSilencedErrors.Discouraged -- Object instantiation is disabled; used only to normalize values for comparison.
			$unserialized = @unserialize( $value, [ 'allowed_classes' => false ] );
			if ( false !== $unserialized || 'b:0;' === $value ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
				return 'S:' . serialize( $unserialized );
			}
		}

		return 'R:' . $value;
	}

	/**
	 * Check whether two meta value lists differ under strict comparison.
	 *
	 * Both lists are compared element-wise in storage order after
	 * canonicalization.
	 *
	 * @since 1.0.4
	 * @param array<int, string> $old_values Currently stored values.
	 * @param array<int, string> $new_values Values that would be written.
	 * @return bool True when the lists differ.
	 */
	public static function meta_values_differ( array $old_values, array $new_values ): bool {
		if ( count( $old_values ) !== count( $new_values ) ) {
			return true;
		}

		$count = count( $old_values );
		for ( $i = 0; $i < $count; $i++ ) {
			$old_canonical = self::canonicalize_meta_value( (string) ( $old_values[ $i ] ?? '' ) );
			$new_canonical = self::canonicalize_meta_value( (string) ( $new_values[ $i ] ?? '' ) );
			if ( $old_canonical !== $new_canonical ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Format a value list for diff display.
	 *
	 * @since 1.0.4
	 * @param array<int, string>|string|null $values Value list, single value, or null for "no previous value".
	 * @return string|null Display string (null preserves the "new value" marker).
	 */
	public static function format_diff_value( $values ): ?string {
		if ( null === $values ) {
			return null;
		}

		if ( is_array( $values ) ) {
			if ( empty( $values ) ) {
				return '';
			}
			$values = implode( ' | ', array_map( 'strval', $values ) );
		}

		$value     = (string) $values;
		$max_chars = (int) apply_filters( 'fe_csv_import_export_dry_run_diff_value_max_length', 500 );
		$max_chars = max( 10, $max_chars );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $value ) > $max_chars ) {
			$value = mb_substr( $value, 0, $max_chars ) . '…';
		} elseif ( ! function_exists( 'mb_strlen' ) && strlen( $value ) > $max_chars ) {
			$value = substr( $value, 0, $max_chars ) . '…';
		}

		return $value;
	}

	/**
	 * Resolve term names for a list of term IDs.
	 *
	 * @since 1.0.4
	 * @param string          $taxonomy Taxonomy name.
	 * @param array<int, int> $term_ids Term IDs.
	 * @return array<int, string> Term names in the same order as the given IDs.
	 */
	public static function resolve_term_names( string $taxonomy, array $term_ids ): array {
		$names = [];
		foreach ( $term_ids as $term_id ) {
			$term = get_term( (int) $term_id, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				$names[] = (string) $term->name;
			} else {
				$names[] = '#' . (int) $term_id;
			}
		}

		return $names;
	}

	/**
	 * Build a per-batch diff summary from diff row entries.
	 *
	 * @since 1.0.4
	 * @param array $rows Diff rows keyed by row number.
	 * @return array{changed: int, unchanged: int, created: int, fields: array<string, int>} Summary counters.
	 */
	public static function build_summary( array $rows ): array {
		$summary = [
			'changed'   => 0,
			'unchanged' => 0,
			'created'   => 0,
			'fields'    => [],
		];

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			if ( 'create' === ( $row['action'] ?? '' ) ) {
				++$summary['created'];
				continue;
			}

			$fields = isset( $row['fields'] ) && is_array( $row['fields'] ) ? $row['fields'] : [];
			if ( empty( $fields ) ) {
				++$summary['unchanged'];
				continue;
			}

			++$summary['changed'];
			foreach ( $fields as $field_name => $field_diff ) {
				if ( ! is_string( $field_name ) || '' === $field_name ) {
					continue;
				}
				$summary['fields'][ $field_name ] = (int) ( $summary['fields'][ $field_name ] ?? 0 ) + 1;
			}
		}

		return $summary;
	}

	/**
	 * Get the diff log file path for an import session.
	 *
	 * @since 1.0.4
	 * @param string $import_session Import session identifier.
	 * @return string Absolute file path inside the protected temp directory.
	 */
	public static function get_diff_file_path( string $import_session ): string {
		$temp_dir = FE_CSV_Import_Export_File_Util::create_temp_directory();
		return $temp_dir . '/dry-run-diff-' . sanitize_key( $import_session ) . '.jsonl';
	}

	/**
	 * Append diff rows to the session diff file as JSON lines.
	 *
	 * The file accumulates the full diff across batches so it can be downloaded
	 * after the Dry Run completes (used by the Pro CSV download feature).
	 *
	 * @since 1.0.4
	 * @param string $import_session Import session identifier.
	 * @param array  $rows Diff rows for the current batch.
	 * @return void
	 */
	public static function append_rows_to_file( string $import_session, array $rows ): void {
		if ( empty( $rows ) ) {
			return;
		}

		$file_path = self::get_diff_file_path( $import_session );
		$lines     = '';
		foreach ( $rows as $row ) {
			$encoded = wp_json_encode( $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			if ( false === $encoded ) {
				continue;
			}
			$lines .= $encoded . "\n";
		}

		if ( '' === $lines ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file_path, $lines, FILE_APPEND | LOCK_EX );
	}
}
