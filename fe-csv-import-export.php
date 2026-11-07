<?php
/**
 * Plugin Name:  FE CSV Import & Export
 * Plugin URI:   https://github.com/firstelementjp/fe-csv-import-export
 * Description:  Simple yet powerful CSV import/export plugin for WordPress. Supports custom post types, custom taxonomies, and custom fields.
 * Version:      1.0.2
 * Author:       FirstElement K.K., Daijiro Miyazawa
 * Author URI:   https://www.firstelement.co.jp/
 * License:      GPL-2.0+
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:  fe-csv-import-export
 * Domain Path:  /languages
 *
 * @package      FE_CSV_Import_Export
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define plugin constants.
define( 'FE_CSV_IMPORT_EXPORT_VERSION', '1.0.2' );
define( 'FE_CSV_IMPORT_EXPORT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FE_CSV_IMPORT_EXPORT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FE_CSV_IMPORT_EXPORT_BASENAME', plugin_basename( __FILE__ ) );
define( 'FE_CSV_IMPORT_EXPORT_PRO_URL', 'https://www.firstelement.co.jp/en/products/fe-csv-import-export-plugin/' );
define( 'FE_CSV_IMPORT_EXPORT_DOCS_URL', 'https://firstelementjp.github.io/fe-csv-import-export/#/' );
define( 'FE_CSV_IMPORT_EXPORT_DEEPWIKI_URL', 'https://deepwiki.com/firstelementjp/fe-csv-import-export/' );

/**
 * Custom autoloader for FE CSV Import & Export classes
 *
 * Uses consistent naming convention for reliable class loading.
 * Class: FE_CSV_Import_Export_Admin_Assets → File: admin/class-fe-csv-import-export-admin-assets.php
 *
 * @since 0.9.8
 * @param string $class_name Class name to load.
 */
spl_autoload_register(
	function ( $class_name ) {
		$prefix   = 'FE_CSV_Import_Export_';
		$base_dir = FE_CSV_IMPORT_EXPORT_PLUGIN_DIR . 'includes/';

		// Only handle classes with FE_CSV_Import_Export_ prefix.
		if ( strncmp( $prefix, $class_name, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		// Convert class name to file path.
		$relative_class = substr( $class_name, strlen( $prefix ) );
		$file_name      = 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';

		// Determine subdirectory based on class naming.
		$sub_dir = '';
		if ( strpos( $relative_class, 'Admin' ) !== false ||
			strpos( $relative_class, 'License_Handler' ) !== false ||
			strpos( $relative_class, 'Settings' ) !== false ||
			strpos( $relative_class, 'Settings_Helper' ) !== false ||
			strpos( $relative_class, 'Encryption_Utils' ) !== false ) {
			$sub_dir = 'admin/';
		} elseif ( strpos( $relative_class, 'Export' ) !== false ) {
			$sub_dir = 'export/';
		} elseif ( strpos( $relative_class, 'Import' ) !== false ) {
			$sub_dir = 'import/';
		}

		$file_path = $base_dir . $sub_dir . $file_name;

		// Preload base classes if needed.
		$base_classes = [
			'FE_CSV_Import_Export_Export_Base' => $base_dir . 'export/class-fe-csv-import-export-export-base.php',
			'FE_CSV_Import_Export_Import_Base' => $base_dir . 'import/class-fe-csv-import-export-import-base.php',
			'FE_CSV_Import_Export_Import_Batch_Processor_Base' => $base_dir . 'import/class-fe-csv-import-export-import-batch-processor-base.php',
		];

		foreach ( $base_classes as $base_class => $base_file ) {
			if ( 0 === strpos( $class_name, $base_class ) &&
				$base_class !== $class_name &&
				file_exists( $base_file ) ) {
				require_once $base_file;
			}
		}

		// Load the class file if exists.
		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}
);

// Register plugin hooks.
register_activation_hook( __FILE__, 'fe_csv_import_export_activate' );
register_deactivation_hook( __FILE__, 'fe_csv_import_export_deactivate' );

// Initialize plugin.
add_action( 'plugins_loaded', 'fe_csv_import_export_init', 10 );

/**
 * Initialize FE CSV Import & Export plugin
 *
 * Creates instances of all main plugin classes and sets up hooks.
 * This function is called on 'plugins_loaded' action.
 *
 * @since 0.9.0
 * @return void
 */
function fe_csv_import_export_init() {
	// Initialize settings helper and migrate legacy options.
	if ( class_exists( 'FE_CSV_Import_Export_Settings_Helper' ) ) {
		FE_CSV_Import_Export_Settings_Helper::migrate_legacy_options();
	}

	if ( class_exists( 'FE_CSV_Import_Export_License_Handler' ) ) {
		FE_CSV_Import_Export_License_Handler::register_license_resync_cron();
		add_action( 'admin_init', [ 'FE_CSV_Import_Export_License_Handler', 'maybe_schedule_license_resync' ] );
	}

	if ( is_admin() ) {
		new FE_CSV_Import_Export_Admin();
		add_action( 'admin_init', 'fe_csv_import_export_purge_legacy_uploads_once' );
	}
	new FE_CSV_Import_Export_Ajax_Import_Unified();
	new FE_CSV_Import_Export_Ajax_Export_Unified();
}

/**
 * Remove CSV files left in the public uploads directory.
 *
 * Versions before 1.0.3 stored the uploaded import CSV under
 * uploads/YYYY/MM/ via wp_handle_upload() and never deleted it. Runs once
 * per site, tracked by an option flag.
 *
 * @since 1.0.3
 * @return void
 */
function fe_csv_import_export_purge_legacy_uploads_once() {
	if ( get_option( 'fe_csv_import_export_purged_legacy_uploads' ) ) {
		return;
	}

	// Set the flag first so a failure cannot repeat the scan on every request.
	update_option( 'fe_csv_import_export_purged_legacy_uploads', 1 );

	if ( class_exists( 'FE_CSV_Import_Export_File_Util' ) ) {
		FE_CSV_Import_Export_File_Util::purge_orphaned_csv_uploads();
	}
}

/**
 * Plugin activation hook
 *
 * Creates necessary directories and sets up initial plugin state.
 * This function runs only when the plugin is activated.
 *
 * @since 0.9.0
 * @return void
 */
function fe_csv_import_export_activate() {
	// Clean up any orphaned cron jobs from previous installations.
	wp_clear_scheduled_hook( 'fe_csv_import_export_process_batch' );
	if ( class_exists( 'FE_CSV_Import_Export_License_Handler' ) ) {
		FE_CSV_Import_Export_License_Handler::clear_license_resync_schedule();
		FE_CSV_Import_Export_License_Handler::register_license_resync_cron();
		FE_CSV_Import_Export_License_Handler::maybe_schedule_license_resync();
	}

	// Create temp directory (with web access protection files) and cleanup old files.
	$temp_dir = FE_CSV_Import_Export_File_Util::create_temp_directory();

	// Cleanup old temp files (older than 24 hours).
	$files = glob( $temp_dir . '/*.csv' );
	if ( $files ) {
		foreach ( $files as $file ) {
			if ( time() - filemtime( $file ) > 86400 ) { // 24 hours
				wp_delete_file( $file );
			}
		}
	}
}

/**
 * Plugin deactivation hook
 *
 * Cleans up when the plugin is deactivated.
 *
 * @since 0.9.0
 * @return void
 */
function fe_csv_import_export_deactivate() {
	// Clean up all scheduled cron jobs.
	wp_clear_scheduled_hook( 'fe_csv_import_export_process_batch' );
	if ( class_exists( 'FE_CSV_Import_Export_License_Handler' ) ) {
		FE_CSV_Import_Export_License_Handler::clear_license_resync_schedule();
	}
}
