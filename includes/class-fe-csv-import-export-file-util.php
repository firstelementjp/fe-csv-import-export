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

			if ( strpos( $file, 'ajax-import-' ) !== 0 ) {
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
}
