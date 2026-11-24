<?php
/**
 * File utilities for FE CSV Import & Export
 *
 * @since 0.9.0
 * @package FE_CSV_Import_Export
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * File utilities
 *
 * @since 0.9.0
 * @package FE_CSV_Import_Export
 */
class FE_CSV_Import_Export_File_Util {
	/**
	 * Create temporary directory for CSV uploads.
	 *
	 * Access protection files (.htaccess and index.php) are written on every
	 * call so the directory stays protected even when it is created outside
	 * the plugin activation flow (e.g. multisite sub-sites).
	 *
	 * @since 0.9.0
	 * @return string Temporary directory path.
	 */
	public static function create_temp_directory(): string {
		$upload_dir = wp_upload_dir();
		$temp_dir   = $upload_dir['basedir'] . '/fe-csv-import-export-temp';

		if ( ! file_exists( $temp_dir ) ) {
			wp_mkdir_p( $temp_dir );
		}

		self::write_access_protection_files( $temp_dir );

		return $temp_dir;
	}

	/**
	 * Write files that block direct web access to the temporary directory.
	 *
	 * Creates an .htaccess covering Apache 2.4 and 2.2, plus an index.php to
	 * suppress directory listing. On servers that ignore .htaccess (e.g.
	 * Nginx) protection relies on the unpredictable temp file names and the
	 * index.php fallback.
	 *
	 * @since 1.0.3
	 * @param string $temp_dir Temporary directory path.
	 * @return void
	 */
	private static function write_access_protection_files( string $temp_dir ): void {
		if ( ! is_dir( $temp_dir ) ) {
			return;
		}

		$htaccess_file = $temp_dir . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$contents  = "<IfModule mod_authz_core.c>\n";
			$contents .= "Require all denied\n";
			$contents .= "</IfModule>\n";
			$contents .= "<IfModule !mod_authz_core.c>\n";
			$contents .= "Order deny,allow\n";
			$contents .= "Deny from all\n";
			$contents .= "</IfModule>\n";
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $htaccess_file, $contents );
		}

		$index_file = $temp_dir . '/index.php';
		if ( ! file_exists( $index_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
		}
	}

	/**
	 * Delete orphaned CSV files left in the public uploads directory.
	 *
	 * Versions before 1.0.3 routed imports through wp_handle_upload(), which
	 * stored the uploaded CSV under uploads/YYYY/MM/ and never removed it.
	 * This removes files matching the plugin export filename pattern
	 * (fe_csv_export_*.csv) that are not registered as media attachments.
	 *
	 * @since 1.0.3
	 * @return void
	 */
	public static function purge_orphaned_csv_uploads(): void {
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) || empty( $upload_dir['basedir'] ) || empty( $upload_dir['baseurl'] ) ) {
			return;
		}

		$base_dir = $upload_dir['basedir'];
		$dirs     = [ $base_dir ];

		foreach ( (array) glob( $base_dir . '/[0-9][0-9][0-9][0-9]/[0-9][0-9]', GLOB_ONLYDIR ) as $dir ) {
			$dirs[] = $dir;
		}

		foreach ( $dirs as $dir ) {
			foreach ( (array) glob( $dir . '/fe_csv_export_*.csv' ) as $file_path ) {
				if ( ! is_string( $file_path ) || ! is_file( $file_path ) ) {
					continue;
				}

				// Keep files that are registered as media attachments.
				$file_url = $upload_dir['baseurl'] . substr( $file_path, strlen( $base_dir ) );
				if ( attachment_url_to_postid( $file_url ) ) {
					continue;
				}

				wp_delete_file( $file_path );
			}
		}
	}

	/**
	 * Generate temporary file path
	 *
	 * Uses a cryptographically random name so the file cannot be guessed from
	 * the request time while it lives in the uploads directory tree.
	 *
	 * @since 0.9.0
	 * @param string $temp_dir Temporary directory path.
	 * @return string Temporary file path.
	 */
	public static function generate_temp_file_path( string $temp_dir ): string {
		return $temp_dir . '/ajax-import-' . wp_generate_password( 32, false, false ) . '.csv';
	}

	/**
	 * Cleanup old temporary files
	 *
	 * Removes all ajax-import temporary files except the current one.
	 *
	 * @since 0.9.8
	 * @param string $temp_dir Temporary directory path.
	 * @param string $current_file Optional current file to preserve.
	 * @return void
	 */
	public static function cleanup_old_temp_files( string $temp_dir, string $current_file = '' ): void {
		if ( ! is_dir( $temp_dir ) ) {
			return;
		}

		$files = scandir( $temp_dir );

		foreach ( $files as $file ) {
			if ( '.' === $file || '..' === $file ) {
				continue;
			}

			$file_path = $temp_dir . '/' . $file;

			if ( strpos( $file, 'ajax-import-' ) !== 0 && strpos( $file, 'dry-run-diff-' ) !== 0 ) {
				continue;
			}

			if ( $current_file && basename( $current_file ) === $file ) {
				continue;
			}

			if ( is_file( $file_path ) ) {
				wp_delete_file( $file_path );
			}
		}
	}

	/**
	 * Cron hook name for the periodic temp file cleanup.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	public const CRON_HOOK_TEMP_CLEANUP = 'fe_csv_import_export_temp_cleanup';

	/**
	 * Register wp-cron hook for temp file cleanup.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function register_temp_cleanup_cron(): void {
		add_action( self::CRON_HOOK_TEMP_CLEANUP, [ __CLASS__, 'cron_cleanup_expired_temp_files' ] );
	}

	/**
	 * Schedule daily temp cleanup if not already scheduled.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function maybe_schedule_temp_cleanup(): void {
		if ( wp_next_scheduled( self::CRON_HOOK_TEMP_CLEANUP ) ) {
			return;
		}
		wp_schedule_event( time(), 'daily', self::CRON_HOOK_TEMP_CLEANUP );
	}

	/**
	 * Clear scheduled temp cleanup.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function clear_temp_cleanup_schedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK_TEMP_CLEANUP );
	}

	/**
	 * Cron callback: remove expired dry run diff files.
	 *
	 * Resolves the temp directory without creating it, so a site that never
	 * imported does not gain an empty directory from the daily sweep.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function cron_cleanup_expired_temp_files(): void {
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) || empty( $upload_dir['basedir'] ) ) {
			return;
		}
		self::cleanup_expired_temp_files( $upload_dir['basedir'] . '/fe-csv-import-export-temp' );
	}

	/**
	 * Delete expired temporary files in the temp directory.
	 *
	 * Two independent lifetimes apply:
	 * - dry-run-diff-*.jsonl feed the downloadable Dry Run diff CSV, so they
	 *   are kept for a fixed lifetime instead of relying solely on the next
	 *   upload sweep.
	 * - ajax-import-*.csv are normally removed when the import completes,
	 *   errors out, or a new file is uploaded. The longer TTL only sweeps
	 *   files left behind by abandoned import sessions, so it deliberately
	 *   stays generous to keep paused imports resumable.
	 *
	 * @since 1.1.0
	 * @param string $temp_dir Temporary directory path.
	 * @return int Number of deleted files.
	 */
	public static function cleanup_expired_temp_files( string $temp_dir ): int {
		if ( ! is_dir( $temp_dir ) ) {
			return 0;
		}

		/**
		 * Filters the lifetime of dry run diff JSONL files in seconds.
		 *
		 * @since 1.1.0
		 * @param int $ttl Seconds a diff file is kept. Default 7 days.
		 */
		$diff_ttl = (int) apply_filters( 'fe_csv_import_export_dry_run_diff_ttl', 7 * DAY_IN_SECONDS );

		/**
		 * Filters the lifetime of uploaded import CSV temp files in seconds.
		 *
		 * Only files left behind by abandoned import sessions reach this age;
		 * completed imports delete the upload on their final batch.
		 *
		 * @since 1.1.0
		 * @param int $ttl Seconds an upload is kept. Default 30 days.
		 */
		$import_ttl = (int) apply_filters( 'fe_csv_import_export_import_temp_ttl', 30 * DAY_IN_SECONDS );

		$deleted  = self::delete_files_older_than( $temp_dir . '/dry-run-diff-*.jsonl', $diff_ttl );
		$deleted += self::delete_files_older_than( $temp_dir . '/ajax-import-*.csv', $import_ttl );

		return $deleted;
	}

	/**
	 * Delete files matching a glob pattern that are older than a TTL.
	 *
	 * @since 1.1.0
	 * @param string $pattern Glob pattern matching the files to sweep.
	 * @param int    $ttl     Maximum file age in seconds.
	 * @return int Number of deleted files.
	 */
	private static function delete_files_older_than( string $pattern, int $ttl ): int {
		$cutoff  = time() - max( 0, $ttl );
		$deleted = 0;

		foreach ( (array) glob( $pattern ) as $file_path ) {
			$mtime = is_string( $file_path ) && is_file( $file_path ) ? filemtime( $file_path ) : false;
			if ( false !== $mtime && $mtime < $cutoff && wp_delete_file( $file_path ) ) {
				++$deleted;
			}
		}

		return $deleted;
	}
}
