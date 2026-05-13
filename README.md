# Swift CSV

![Swift CSV Banner](https://github.com/firstelementjp/swift-csv/blob/main/assets/images/swift-csv-banner.jpeg?raw=true)

[![Version](https://img.shields.io/badge/version-0.9.9.3-green.svg)](https://github.com/firstelementjp/swift-csv/releases)
[![License](https://img.shields.io/badge/License-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/WordPress-6.6%2B-blue.svg)](https://wordpress.org/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/firstelementjp/swift-csv)

=== Swift CSV ===
Contributors: firstelementjp, dxd5001
Tags: csv, import, export, data, migration, bulk, content
Requires at least: 6.6
Tested up to: 6.7
Stable tag: 0.9.9.3
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Swift CSV is an easy-to-use WordPress plugin for importing and exporting CSV data.
It combines a simple admin experience with extensibility through WordPress hooks.

== Description ==

Swift CSV provides:

- CSV import/export for WordPress content
- Support for custom post types, taxonomies, and custom fields
- Batch processing for large CSV files
- Real-time progress logging
- Hook-based extensibility for developers

This repository contains the plugin source code, tests, and developer resources.
For end-user guides and full documentation, see the links below.

== Installation ==

=== From WordPress Admin ===

1. Download the latest release ZIP from the [Releases page](https://github.com/firstelementjp/swift-csv/releases)
2. Go to **Plugins → Add New → Upload Plugin**
3. Select the ZIP file and upload it
4. Activate the plugin

=== Manual Installation ===

1. Download the latest release ZIP from the [Releases page](https://github.com/firstelementjp/swift-csv/releases)
2. Extract the ZIP file
3. Upload the `swift-csv/` directory to `/wp-content/plugins/`
4. Activate the plugin from the WordPress admin screen

> [!IMPORTANT]
> Use the release ZIP file for installation, not **Source code (zip)**.

== Frequently Asked Questions ==

=== Does this plugin support custom post types? ===

Yes, Swift CSV supports all standard and custom post types, taxonomies, and custom fields.

=== Can I import large CSV files? ===

Yes, the plugin uses batch processing to handle large CSV files efficiently with memory optimization.

=== Is this plugin compatible with the latest WordPress version? ===

Yes, Swift CSV is tested and compatible with WordPress 6.6 and higher.

== Screenshots ==

1. Import interface with CSV upload and configuration options
2. Export interface with post type and field selection
3. Progress monitoring during import/export operations
4. Settings page for configuration options

== Changelog ==

=== 0.9.9.2 ===

- Reduced memory usage for large CSV imports by streaming batch reads
- Improved import log tab behavior and preserved recent logs
- Fixed CSV quote parsing for edge cases involving backslash-escaped double quotes
- Cleaned up local release workflow

=== 0.9.9.1 ===

- Enhanced batch processing performance
- Added better error handling for malformed CSV files
- Improved progress logging visibility

=== 0.9.9.0 ===

- Major performance improvements for large datasets
- Added support for Gutenberg block content preservation
- Enhanced hook system for developer extensibility

== Upgrade Notice ==

=== 0.9.9.2 ===
Performance improvements and bug fixes. Recommended upgrade for all users.

== Arbitrary sections ==

This section can be used for additional information about the plugin that doesn't fit in other sections.

== A brief Markdown Example ==

You can use markdown formatting in your readme files:

`Code blocks` and **bold text**

- Lists
- Of items

== Additional Information ==

For detailed documentation and examples, visit: https://firstelementjp.github.io/swift-csv/

---

## For Developers

This section contains detailed information for developers working with or contributing to Swift CSV.

Swift CSV is an easy-to-use WordPress plugin for importing and exporting CSV data.
It combines a simple admin experience with extensibility through WordPress hooks.

This repository contains the plugin source code, tests, and developer resources.
For end-user guides and full documentation, see the links below.

## ✨ Recent Highlights

- Reduced memory usage for large CSV imports by streaming batch reads and reusing file offsets
- Improved import log tab behavior and preserved recent logs at completion
- Fixed CSV quote parsing for edge cases involving backslash-escaped double quotes in legacy data
- Cleaned up local release workflow so `test-release` and generated minified assets do not remain tracked in Git

## 📖 Overview

Swift CSV provides:

- CSV import/export for WordPress content
- Support for custom post types, taxonomies, and custom fields
- Batch processing for large CSV files
- Real-time progress logging
- Hook-based extensibility for developers

## ⚡ Quick Links

- **Documentation**: https://firstelementjp.github.io/swift-csv/
- **Installation Guide**: https://firstelementjp.github.io/swift-csv/#/installation
- **Getting Started**: https://firstelementjp.github.io/swift-csv/#/getting-started
- **Configuration**: https://firstelementjp.github.io/swift-csv/#/configuration
- **Examples**: https://firstelementjp.github.io/swift-csv/#/examples
- **Troubleshooting**: https://firstelementjp.github.io/swift-csv/#/troubleshooting
- **Releases**: https://github.com/firstelementjp/swift-csv/releases
- **Issues**: https://github.com/firstelementjp/swift-csv/issues

## ⚙️ Requirements

- WordPress 6.6 or higher
- PHP 8.1 or higher
- OpenSSL extension

## 🚀 Installation

### From WordPress Admin

1. Download the latest release ZIP from the [Releases page](https://github.com/firstelementjp/swift-csv/releases)
2. Go to **Plugins → Add New → Upload Plugin**
3. Select the ZIP file and upload it
4. Activate the plugin

### Manual Installation

1. Download the latest release ZIP from the [Releases page](https://github.com/firstelementjp/swift-csv/releases)
2. Extract the ZIP file
3. Upload the `swift-csv/` directory to `/wp-content/plugins/`
4. Activate the plugin from the WordPress admin screen

> [!IMPORTANT]
> Use the release ZIP file for installation, not **Source code (zip)**.

## 💻 Local Development

```bash
git clone https://github.com/firstelementjp/swift-csv.git
cd swift-csv
composer install
npm install
```

After installing dependencies, place the plugin in your local WordPress environment and activate it from the admin dashboard.

## 🧪 Testing

PHPUnit for Swift CSV is designed to run in a local WordPress environment.

### PHPUnit setup for Local by Flywheel

1. Copy `wp-tests-config.php.example` to `wp-tests-config.php`
2. If you use `direnv`, copy or create a `.envrc` file for `swift-csv`
3. Set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, and `MYSQL_UNIX_PORT` for your Local site
4. Approve the environment with `direnv allow`
5. Run `composer install`

Example setup command:

```bash
cp wp-tests-config.php.example wp-tests-config.php
```

Example environment values:

```bash
export DB_HOST=localhost
export DB_NAME=local
export DB_USER=root
export DB_PASSWORD=root
export MYSQL_UNIX_PORT="/Users/your-name/Library/Application Support/Local/run/xxxxxxx/mysql/mysqld.sock"
```

Verify the MySQL socket path from the **Database** tab in Local before running tests.

Run PHPUnit from the plugin root:

```bash
cd wp-content/plugins/swift-csv
```

Run all tests:

```bash
composer test
```

The equivalent direct PHPUnit command is:

```bash
./vendor/bin/phpunit
```

Run coverage

```bash
composer run test-coverage
```

Run specific test suites

```bash
composer run test-unit
composer run test-integration
composer run test-standalone
```

Coverage reports are generated in:

```
tests/coverage/
```

If the test database connection fails in Local, confirm that `MYSQL_UNIX_PORT` is loaded in the current shell:

```bash
printenv | grep MYSQL_UNIX_PORT
```

If `.envrc` was updated, reload it before running PHPUnit:

```bash
direnv allow
direnv reload
composer test-unit
```

## 🛠️ Development Commands

```bash
# PHP/Composer
composer test              # Run test suite
composer phpcs             # Check coding standards
composer phpcbf            # Fix coding standards automatically

# Node.js/npm
npm run build              # Build/minify frontend assets
npm run lint:js            # Check JavaScript coding standards
npm run lint:js:fix        # Fix JavaScript coding standards
npm run dev                # Development build with source maps
npm run watch:all          # Watch for changes and rebuild automatically

# Release
./test-release.sh          # Create a release ZIP locally
```

See `package.json` for the complete list of available npm scripts.

`./test-release.sh` now builds release assets, assembles the ZIP, and then removes temporary release artifacts from the working tree.

See the project documentation for detailed development and release workflows.

## 📝 Developer Notes

- Import/export operations are designed to work in batches for large datasets.
- Gutenberg block content is preserved during import/export.
- The plugin provides extensibility through WordPress hooks.

For detailed implementation notes, see:

- [Developer documentation](https://firstelementjp.github.io/swift-csv/#/developer)
- [Architecture overview](https://firstelementjp.github.io/swift-csv/#/architecture)
- [Hooks documentation](https://firstelementjp.github.io/swift-csv/#/hooks)

## 🤝 Contributing

Contributions are welcome.

Basic workflow:

- Fork the repository
- Create a feature branch from develop
- Make changes and run tests
- Open a Pull Request against develop

Please check existing issues before opening a new feature request or bug report.

## 📄 License

GPLv2+

See LICENSE for details.

## 👨‍💻 Authors

- [Daijiro Miyazawa](https://x.com/dxd5001)
- [FirstElement K.K.](https://www.firstelement.co.jp)
