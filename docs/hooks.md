# FE CSV Import & Export Hooks

This document lists the hooks available in FE CSV Import & Export **as implemented in the current codebase**.

It focuses on:

- Accurate hook names and signatures
- Practical usage patterns
- Examples that mirror real-world extensions (WooCommerce, custom business rules, etc.)

## Table of Contents

- [Export Hooks](#export-hooks)
    - [Header generation](#header-generation)
    - [Row generation](#row-generation)
    - [Batch / performance](#batch--performance)
- [Import Hooks](#import-hooks)
    - [Permission and validation](#permission-and-validation)
    - [Field preparation and mapping](#field-preparation-and-mapping)
    - [Batch processing](#batch-processing)
    - [Scheduled execution seams](#scheduled-execution-seams)
    - [Dry Run preview and temporary files](#dry-run-preview-and-temporary-files)
    - [Logging and diagnostics](#logging-and-diagnostics)
- [Admin / UI Hooks](#admin--ui-hooks)
- [Feature Flags](#feature-flags)
- [Best Practices](#best-practices)

---

## Export Hooks

### Header generation

#### `fe_csv_import_export_export_filter_taxonomy_objects`

Filter taxonomy objects used for building `tax_{taxonomy}` headers.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_export_filter_taxonomy_objects', array $taxonomies, array $args ): array
```

**Parameters:**

- `$taxonomies` (`array`) Taxonomy objects returned by `get_object_taxonomies( $post_type, 'objects' )`.
- `$args` (`array`) Contextual arguments.
    - `post_type` (`string`)
    - `export_scope` (`string`)
    - `include_private_meta` (`bool`)
    - `context` (`string`) Currently `taxonomy_objects_filter`

**Example:** (exclude internal taxonomies)

```php
add_filter( 'fe_csv_import_export_export_filter_taxonomy_objects', 'my_swiftcsv_filter_taxonomies', 10, 2 );

function my_swiftcsv_filter_taxonomies( $taxonomies, $args ) {
    if ( ! is_array( $taxonomies ) ) {
        return [];
    }

    foreach ( $taxonomies as $key => $tax ) {
        if ( ! isset( $tax->name ) ) {
            continue;
        }

        // Example: hide a taxonomy from exports.
        if ( 'post_format' === $tax->name ) {
            unset( $taxonomies[ $key ] );
        }
    }

    return $taxonomies;
}
```

#### `fe_csv_import_export_export_sample_query_args`

Filter the WP query args used to pick a "sample post" for meta key discovery.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_export_sample_query_args', array $query_args, array $args ): array
```

**Parameters:**

- `$query_args` (`array`) The WP query args used to fetch sample IDs.
- `$args` (`array`) Contextual arguments.
    - `post_type` (`string`)
    - `context` (`string`) Currently `meta_discovery`

**Example:** (prefer recent posts with meta)

```php
add_filter( 'fe_csv_import_export_export_sample_query_args', 'my_swiftcsv_sample_query_args', 10, 2 );

function my_swiftcsv_sample_query_args( $query_args, $args ) {
    // Example: prefer posts that are likely to have custom fields.
    $query_args['orderby'] = 'modified';
    $query_args['order'] = 'DESC';
    return $query_args;
}
```

#### `fe_csv_import_export_export_classify_meta_keys`

Classify meta keys discovered from the sample post.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_export_classify_meta_keys', array $all_meta_keys, array $args ): array
```

**Parameters:**

- `$all_meta_keys` (`array<string>`) Raw discovered meta keys.
- `$args` (`array`) Context.
    - `post_type` (`string`)
    - `export_scope` (`string`)
    - `include_private_meta` (`bool`)
    - `context` (`string`) Currently `meta_key_classification`

**Expected return:**

```php
[
  'regular' => array<string>,
  'private' => array<string>,
]
```

**Example:** (exclude noisy keys)

```php
add_filter( 'fe_csv_import_export_export_classify_meta_keys', 'my_swiftcsv_classify_meta_keys', 10, 2 );

function my_swiftcsv_classify_meta_keys( $all_meta_keys, $args ) {
    $regular = [];
    $private = [];

    foreach ( (array) $all_meta_keys as $key ) {
        $key = (string) $key;
        if ( '' === $key ) {
            continue;
        }

        // Example: drop WordPress internal keys.
        if ( in_array( $key, [ '_edit_lock', '_edit_last' ], true ) ) {
            continue;
        }

        if ( 0 === strpos( $key, '_' ) ) {
            $private[] = $key;
        } else {
            $regular[] = $key;
        }
    }

    return [
        'regular' => $regular,
        'private' => $private,
    ];
}
```

#### `fe_csv_import_export_export_generate_custom_field_headers`

Generate custom-field (meta) headers from classified meta keys.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_export_generate_custom_field_headers', array $headers, array $classified_meta_keys, array $args ): array
```

**Parameters:**

- `$headers` (`array<string>`) Starts as an empty array.
- `$classified_meta_keys` (`array`) Result of `fe_csv_import_export_export_classify_meta_keys`.
- `$args` (`array`) Context.
    - `post_type` (`string`)
    - `export_scope` (`string`)
    - `include_private_meta` (`bool`)
    - `context` (`string`) Currently `custom_field_headers_generation`

**Example:** (only allow-listed meta keys)

```php
add_filter( 'fe_csv_import_export_export_generate_custom_field_headers', 'my_swiftcsv_custom_field_headers', 10, 3 );

function my_swiftcsv_custom_field_headers( $headers, $classified_meta_keys, $args ) {
    $allow = [ 'price', 'color', 'size' ];
    $out = [];

    foreach ( (array) ( $classified_meta_keys['regular'] ?? [] ) as $meta_key ) {
        $meta_key = (string) $meta_key;
        if ( in_array( $meta_key, $allow, true ) ) {
            $out[] = 'cf_' . $meta_key;
        }
    }

    return $out;
}
```

#### `fe_csv_import_export_export_headers`

Filter the final header list.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_export_headers', array $headers, array $config, string $context ): array
```

**Parameters:**

- `$headers` (`array<string>`) Final headers.
- `$config` (`array`) Export config.
- `$context` (`string`) Currently `standard` (WP compatible) or `direct_sql`.

**Example:** (add a custom computed column)

```php
add_filter( 'fe_csv_import_export_export_headers', 'my_swiftcsv_add_custom_header', 10, 3 );

function my_swiftcsv_add_custom_header( $headers, $config, $context ) {
    // Add a non-standard header. Its value will be provided by fe_csv_import_export_export_process_custom_header.
    $headers[] = 'my_permalink';
    return $headers;
}
```

#### `fe_csv_import_export_export_phase_headers`

Action fired after headers are finalized.

**Type:** action

**Signature:**

```php
do_action( 'fe_csv_import_export_export_phase_headers', array $headers, array $config, string $context ): void
```

**Example:** (log headers)

```php
add_action( 'fe_csv_import_export_export_phase_headers', 'my_swiftcsv_log_headers', 10, 3 );

function my_swiftcsv_log_headers( $headers, $config, $context ) {
    error_log( '[FE CSV Import & Export] Export headers finalized (' . $context . '): ' . implode( ',', (array) $headers ) );
}
```

### Row generation

#### `fe_csv_import_export_export_row`

Filter each row during export generation.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_export_row', array $row, int $post_id, array $config, string $context ): array
```

**Parameters:**

- `$row` (`array`) Row data.
    - For WP compatible export: typically an indexed array aligned to headers.
    - For Direct SQL export: an associative array is often used before converting to CSV.
- `$post_id` (`int`) Post ID.
- `$config` (`array`) Export config.
- `$context` (`string`) `wp_compatible`, `direct_sql`, or a scope value.

**Example:** (format product prices)

```php
add_filter( 'fe_csv_import_export_export_row', 'my_swiftcsv_export_row_format', 10, 4 );

function my_swiftcsv_export_row_format( $row, $post_id, $config, $context ) {
    // WP compatible export usually passes an indexed row aligned to headers.
    // Direct SQL export often passes an associative row.

    if ( 'direct_sql' === $context && is_array( $row ) && isset( $row['cf_price'] ) ) {
        $row['cf_price'] = number_format( (float) $row['cf_price'], 2, '.', '' );
        return $row;
    }

    // Example: for wp_compatible, update by index (keep the shape intact).
    // If you need header-aware edits here, also hook fe_csv_import_export_export_headers to locate indexes.
    if ( 'wp_compatible' === $context && is_array( $row ) && isset( $row[0] ) ) {
        // No-op example: return row as-is.
        return $row;
    }

    return $row;
}
```

#### `fe_csv_import_export_export_process_custom_header`

Provide a value for custom headers that are not standard `post_*`, `tax_*`, `cf_*`, or `ID`.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_export_process_custom_header', string $value, string $header, int $post_id, array $args ): string
```

**Parameters:**

- `$value` (`string`) Default empty.
- `$header` (`string`) Header name.
- `$post_id` (`int`) Post ID.
- `$args` (`array`) Context.
    - `post_type` (`string`)
    - `context` (`string`) Currently `export_data_processing`

**Example:** (implement `my_permalink` header)

```php
add_filter( 'fe_csv_import_export_export_process_custom_header', 'my_swiftcsv_custom_header_value', 10, 4 );

function my_swiftcsv_custom_header_value( $value, $header, $post_id, $args ) {
    if ( 'my_permalink' === $header ) {
        return (string) get_permalink( $post_id );
    }

    return (string) $value;
}
```

### Batch / performance

#### `fe_csv_import_export_export_batch_size`

Filter the export batch size.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_export_batch_size', int $batch_size, int $total_count, string $post_type, array $config ): int
```

**Example:**

```php
add_filter( 'fe_csv_import_export_export_batch_size', 'my_swiftcsv_export_batch_size', 10, 4 );

function my_swiftcsv_export_batch_size( $batch_size, $total_count, $post_type, $config ) {
    // Example: reduce batch size for heavy post types.
    if ( 'product' === $post_type ) {
        return max( 100, min( (int) $batch_size, 500 ) );
    }
    return (int) $batch_size;
}
```

#### `fe_csv_import_export_export_batch_prepare`

Action fired before each WP-compatible export batch is processed, with the post objects already loaded.

**Type:** action

**Signature:**

```php
do_action( 'fe_csv_import_export_export_batch_prepare', array $post_ids, array $posts, array $headers, array $config, string $context ): void
```

**Parameters:**

- `$post_ids` (`array<int>`) Post IDs in this batch.
- `$posts` (`array`) Loaded `WP_Post` objects for the batch.
- `$headers` (`array<string>`) Export headers.
- `$config` (`array`) Export config.
- `$context` (`string`) Currently `wp_compatible`.

**Example:** (preload external data once per batch)

```php
add_action( 'fe_csv_import_export_export_batch_prepare', 'my_swiftcsv_export_batch_prepare', 10, 5 );

function my_swiftcsv_export_batch_prepare( $post_ids, $posts, $headers, $config, $context ) {
    // Example: prime a custom lookup table for the whole batch.
    my_prime_external_cache( $post_ids );
}
```

---

## Import Hooks

### Permission and validation

#### `fe_csv_import_export_user_can_import`

Filter user permission to perform imports.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_user_can_import', bool $can_import ): bool
```

**Parameters:**

- `$can_import` (`bool`) Default permission based on `current_user_can('import')`.

**Example:** (require custom capability)

```php
add_filter( 'fe_csv_import_export_user_can_import', 'my_swiftcsv_import_permission', 10, 1 );

function my_swiftcsv_import_permission( $can_import ) {
    return current_user_can( 'manage_options' ) || current_user_can( 'import_csv' );
}
```

#### `fe_csv_import_export_pre_ajax_import`

Filter pre-import validation result.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_pre_ajax_import', bool|WP_Error $result, array $post_data ): bool|WP_Error
```

**Parameters:**

- `$result` (`bool|WP_Error`) Validation result.
- `$post_data` (`array`) POST data from import request.

**Example:** (validate business hours)

```php
add_filter( 'fe_csv_import_export_pre_ajax_import', 'my_swiftcsv_business_hours_check', 10, 2 );

function my_swiftcsv_business_hours_check( $result, $post_data ) {
    $hour = (int) date( 'H' );
    if ( $hour < 9 || $hour > 17 ) {
        return new WP_Error( 'business_hours', 'Import only allowed during business hours (9AM-5PM)' );
    }
    return $result;
}
```

### Field preparation and mapping

#### `fe_csv_import_export_prepare_import_fields`

Filter prepared meta fields before import.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_prepare_import_fields', array $meta_fields, int $post_id, array $args ): array
```

**Parameters:**

- `$meta_fields` (`array`) Prepared meta fields.
- `$post_id` (`int`) Post ID being imported.
- `$args` (`array`) Context arguments including post_type and context.

**Example:** (process custom field formats)

```php
add_filter( 'fe_csv_import_export_prepare_import_fields', 'my_swiftcsv_process_custom_fields', 10, 3 );

function my_swiftcsv_process_custom_fields( $meta_fields, $post_id, $args ) {
    foreach ( $meta_fields as $key => $value ) {
        if ( strpos( $key, 'price_' ) === 0 ) {
            $meta_fields[$key] = floatval( $value );
        }
    }
    return $meta_fields;
}
```

#### `fe_csv_import_export_filter_sample_posts`

Filter the sample post IDs used for import field detection.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_filter_sample_posts', array $post_ids, array $args ): array
```

**Parameters:**

- `$post_ids` (`array<int>`) Sample post IDs (empty by default).
- `$args` (`array`) Context.
    - `post_type` (`string`)
    - `context` (`string`) Currently `import_field_detection`

#### `fe_csv_import_export_allow_protected_meta_import`

Allow users **without** the `unfiltered_html` capability to import meta keys that start with an underscore (protected meta). Disabled by default.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_allow_protected_meta_import', bool $allow ): bool
```

**Parameters:**

- `$allow` (`bool`) Default `false`.

This gate only applies to importers lacking `unfiltered_html` (e.g. multisite admins, `DISALLOW_UNFILTERED_HTML` installs, or widened `user_can_import` rules). For those users, `_`-prefixed meta keys are dropped and remaining values are sanitized with `wp_kses_post()`. Users who have `unfiltered_html` — typical single-site administrators — already import protected meta without this filter.

**⚠️ Security Note:** Protected meta includes plugin internals (e.g. `_edit_lock`, ACF field keys). Only enable this for trusted migrations.

#### `fe_csv_import_export_import_phase_map_prepared`

Action fired after field mapping is prepared.

**Type:** action

**Signature:**

```php
do_action( 'fe_csv_import_export_import_phase_map_prepared', int $post_id, array $prepared_fields, array $args ): void
```

#### `fe_csv_import_export_import_phase_post_persist`

Action fired after post data is persisted.

**Type:** action

**Signature:**

```php
do_action( 'fe_csv_import_export_import_phase_post_persist', int $post_id, array $prepared_fields, array $args ): void
```

### Batch processing

#### `fe_csv_import_export_import_batch_size`

Filter import batch size.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_import_batch_size', int $batch_size, int $total_rows, array $config ): int
```

**Parameters:**

- `$batch_size` (`int`) Calculated batch size.
- `$total_rows` (`int`) Total rows to process.
- `$config` (`array`) Import configuration.

**Example:** (optimize for server performance)

```php
add_filter( 'fe_csv_import_export_import_batch_size', 'my_swiftcsv_optimize_batch_size', 10, 3 );

function my_swiftcsv_optimize_batch_size( $batch_size, $total_rows, $config ) {
    // Reduce batch size for memory-constrained servers
    $memory_limit = ini_get( 'memory_limit' );
    if ( $memory_limit === '128M' ) {
        return min( 5, $batch_size );
    }
    return $batch_size;
}
```

#### `fe_csv_import_export_import_max_batch_size`

Filter the hard upper bound applied to the import batch size.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_import_max_batch_size', int $max_batch_size, int $total_rows, array $config ): int
```

**Parameters:**

- `$max_batch_size` (`int`) Maximum batch size cap (default 500).
- `$total_rows` (`int`) Total rows to process.
- `$config` (`array`) Import configuration.

#### `fe_csv_import_export_import_batch_completed`

Action fired after each import batch is persisted.

**Type:** action — **since 1.1.0**

**Signature:**

```php
do_action( 'fe_csv_import_export_import_batch_completed', array $post_ids, array $context ): void
```

**Parameters:**

- `$post_ids` (`array<int, int>`) Post IDs persisted in this batch.
- `$context` (`array`) Batch context.
    - `post_type` (`string`)
    - `import_session` (`string`)
    - `start_row` (`int`)
    - `dry_run` (`bool`)
    - `batch_size` (`int`)

Import writes posts via direct queries, so `save_post` never fires for imported rows. Integrations that react to post changes (e.g. search indexers) should hook here instead.

**Example:** (reindex imported posts)

```php
add_action( 'fe_csv_import_export_import_batch_completed', 'my_swiftcsv_reindex_batch', 10, 2 );

function my_swiftcsv_reindex_batch( $post_ids, $context ) {
    if ( ! empty( $context['dry_run'] ) ) {
        return;
    }
    my_search_indexer_update( $post_ids );
}
```

### Scheduled execution seams

These extension points (all **since 1.1.0**) let FE CSV Import & Export Pro run one-time scheduled imports and exports. They are primarily consumed by the Pro plugin rather than by site code.

#### `fe_csv_import_export_response_handler`

Filter the handler invoked before a JSON response is sent. Returning a callable replaces the default `wp_send_json()` flow.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_response_handler', callable|null $handler, array $payload ): callable|null
```

**Parameters:**

- `$handler` (`callable|null`) Response handler callable or `null`.
- `$payload` (`array`) JSON payload about to be sent.

The Pro scheduled job runner installs a callable that captures engine responses (via a thrown exception) inside a single PHP process, where `wp_send_json()`/`wp_die()` must not run.

#### `fe_csv_import_export_import_file_path`

Filter to inject a pre-staged import file path, skipping the upload flow and nonce verification.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_import_file_path', string $file_path ): string
```

**Parameters:**

- `$file_path` (`string`) Pre-staged file path (empty by default). Returning a non-empty readable path bypasses nonce verification and file upload.

The Pro scheduled import runner returns the job's staged CSV path here.

**⚠️ Security Note:** Only return a path from trusted, server-side code. This filter bypasses upload-time nonce verification by design.

#### `fe_csv_import_export_import_csv_store_ttl`

Filter the TTL of the transient that stores parsed CSV metadata between import batches.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_import_csv_store_ttl', int $ttl ): int
```

**Parameters:**

- `$ttl` (`int`) Transient TTL in seconds (default `3600`).

The Pro scheduled import runner increases this so long-running background jobs do not lose their stream offsets.

### Dry Run preview and temporary files

#### `fe_csv_import_export_dry_run_log_limit`

Filter the maximum number of per-row log entries kept during a dry run.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_dry_run_log_limit', int $limit ): int
```

**Parameters:**

- `$limit` (`int`) Default `50`.

#### `fe_csv_import_export_dry_run_diff_row_limit`

Filter the maximum number of diff rows rendered in the admin Diff preview.

**Type:** filter — **since 1.1.0**

**Signature:**

```php
apply_filters( 'fe_csv_import_export_dry_run_diff_row_limit', int $limit ): int
```

**Parameters:**

- `$limit` (`int`) Default `100`. Affects preview display only; the stored diff data always covers every row.

#### `fe_csv_import_export_dry_run_diff_value_max_length`

Filter the maximum length of before/after values stored in the dry run diff.

**Type:** filter — **since 1.1.0**

**Signature:**

```php
apply_filters( 'fe_csv_import_export_dry_run_diff_value_max_length', int $max_chars ): int
```

**Parameters:**

- `$max_chars` (`int`) Default `500` characters. Longer values are truncated in the diff output.

#### `fe_csv_import_export_dry_run_diff_ttl`

Filter the lifetime of dry run diff JSONL files.

**Type:** filter — **since 1.1.0**

**Signature:**

```php
apply_filters( 'fe_csv_import_export_dry_run_diff_ttl', int $ttl ): int
```

**Parameters:**

- `$ttl` (`int`) Seconds a diff file is kept. Default `7 * DAY_IN_SECONDS`.

#### `fe_csv_import_export_import_temp_ttl`

Filter the lifetime of uploaded import CSV temp files.

**Type:** filter — **since 1.1.0**

**Signature:**

```php
apply_filters( 'fe_csv_import_export_import_temp_ttl', int $ttl ): int
```

**Parameters:**

- `$ttl` (`int`) Seconds an upload is kept. Default `30 * DAY_IN_SECONDS`. Only files left behind by abandoned import sessions reach this age; completed imports delete the upload on their final batch.

### Logging and diagnostics

#### `fe_csv_import_export_max_log_entries`

Filter maximum log entries to store.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_max_log_entries', int $max_entries ): int
```

**Parameters:**

- `$max_entries` (`int`) Default 30.

**Example:** (increase logging for debugging)

```php
add_filter( 'fe_csv_import_export_max_log_entries', 'my_swiftcsv_increase_logging', 10, 1 );

function my_swiftcsv_increase_logging( $max_entries ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        return 100;
    }
    return $max_entries;
}
```

### Phased import actions

FE CSV Import & Export import exposes a phased model via `do_action`.

- `fe_csv_import_export_import_phase_normalize`
- `fe_csv_import_export_import_phase_validate`
- `fe_csv_import_export_import_phase_map`
- `fe_csv_import_export_import_phase_map_prepared`
- `fe_csv_import_export_import_phase_post_persist`

These are intended for observability and integrations.

#### `fe_csv_import_export_import_phase_normalize`

**Type:** action

**Signature (current implementation):**

```php
do_action( 'fe_csv_import_export_import_phase_normalize', array $filtered_data, array $context ): void
```

**Example:**

```php
add_action( 'fe_csv_import_export_import_phase_normalize', 'my_swiftcsv_phase_normalize', 10, 2 );

function my_swiftcsv_phase_normalize( $filtered_data, $context ) {
    error_log( '[FE CSV Import & Export] normalize phase for post_type=' . (string) ( $context['post_type'] ?? '' ) );
}
```

#### `fe_csv_import_export_import_phase_validate`

**Type:** action

**Signature (current implementation):**

```php
do_action( 'fe_csv_import_export_import_phase_validate', array $row_validation, array $row_context, array $context ): void
```

**Example:**

```php
add_action( 'fe_csv_import_export_import_phase_validate', 'my_swiftcsv_phase_validate', 10, 3 );

function my_swiftcsv_phase_validate( $row_validation, $row_context, $context ) {
    if ( ! empty( $row_validation['errors'] ) ) {
        error_log( '[FE CSV Import & Export] validation errors: ' . implode( '; ', (array) $row_validation['errors'] ) );
    }
}
```

#### `fe_csv_import_export_import_phase_map`

**Type:** action

**Signature (current implementation):**

```php
do_action( 'fe_csv_import_export_import_phase_map', array $collected_fields, array $headers, array $data ): void
```

**Example:**

```php
add_action( 'fe_csv_import_export_import_phase_map', 'my_swiftcsv_phase_map', 10, 3 );

function my_swiftcsv_phase_map( $collected_fields, $headers, $data ) {
    // Example: observe field mapping.
    if ( isset( $collected_fields['meta_fields'] ) ) {
        error_log( '[FE CSV Import & Export] mapped meta keys: ' . implode( ',', array_keys( (array) $collected_fields['meta_fields'] ) ) );
    }
}
```

#### `fe_csv_import_export_import_phase_map_prepared`

**Type:** action

**Signature (current implementation):**

```php
do_action( 'fe_csv_import_export_import_phase_map_prepared', int $post_id, array $prepared_meta_fields, array $prepare_args ): void
```

#### `fe_csv_import_export_import_phase_post_persist`

**Type:** action

**Signature (current implementation):**

```php
do_action( 'fe_csv_import_export_import_phase_post_persist', int $post_id, array $prepared_meta_fields, array $prepare_args ): void
```

### Row validation and normalization

#### `fe_csv_import_export_import_row_validation`

Row-level validation filter.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_import_row_validation', array $row_validation, array $row_context, array $context ): array
```

**Example:** (require `post_title` for creates)

```php
add_filter( 'fe_csv_import_export_import_row_validation', 'my_swiftcsv_validate_row', 10, 3 );

function my_swiftcsv_validate_row( $row_validation, $row_context, $context ) {
    $post_fields = (array) ( $row_context['post_fields_from_csv'] ?? [] );
    $is_update = ! empty( $row_context['is_update'] );

    if ( ! $is_update && empty( $post_fields['post_title'] ) ) {
        $row_validation['valid'] = false;
        $row_validation['errors'][] = 'post_title is required for new posts.';
    }

    return $row_validation;
}
```

#### `fe_csv_import_export_import_data_filter`

Normalize/filter raw parsed row data.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_import_data_filter', array $filtered_data, array $original_data, array $context ): array
```

**Example:** (trim fields)

```php
add_filter( 'fe_csv_import_export_import_data_filter', 'my_swiftcsv_import_data_filter', 10, 3 );

function my_swiftcsv_import_data_filter( $filtered_data, $original_data, $context ) {
    $data = (array) ( $filtered_data['data'] ?? [] );
    foreach ( $data as $i => $value ) {
        $data[ $i ] = is_string( $value ) ? trim( $value ) : $value;
    }

    $filtered_data['data'] = $data;
    return $filtered_data;
}
```

### Field mapping and post-persist

#### `fe_csv_import_export_import_field_mapping`

Filter collected meta/taxonomy fields derived from headers and row values.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_import_field_mapping', array $collected_fields, array $headers, array $data, array $allowed_post_fields ): array
```

**Example:** (rename a meta key)

```php
add_filter( 'fe_csv_import_export_import_field_mapping', 'my_swiftcsv_import_field_mapping', 10, 4 );

function my_swiftcsv_import_field_mapping( $collected_fields, $headers, $data, $allowed_post_fields ) {
    if ( isset( $collected_fields['meta_fields']['old_key'] ) ) {
        $collected_fields['meta_fields']['new_key'] = $collected_fields['meta_fields']['old_key'];
        unset( $collected_fields['meta_fields']['old_key'] );
    }

    return $collected_fields;
}
```

#### `fe_csv_import_export_prepare_import_fields`

Prepare meta fields right before the post-persist phase.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_prepare_import_fields', array $meta_fields, int $post_id, array $args ): array
```

**Example:** (sanitize a field)

```php
add_filter( 'fe_csv_import_export_prepare_import_fields', 'my_swiftcsv_prepare_import_fields', 10, 3 );

function my_swiftcsv_prepare_import_fields( $meta_fields, $post_id, $args ) {
    if ( isset( $meta_fields['price'] ) ) {
        $meta_fields['price'] = (string) (float) str_replace( ',', '', (string) $meta_fields['price'] );
    }
    return $meta_fields;
}
```

#### `fe_csv_import_export_process_custom_fields`

Legacy-compatible action called after field preparation and post-persist.

**Type:** action

**Signature:**

```php
do_action( 'fe_csv_import_export_process_custom_fields', int $post_id, array $prepared_meta_fields ): void
```

**Example:** (ACF update)

```php
add_action( 'fe_csv_import_export_process_custom_fields', 'my_swiftcsv_process_custom_fields', 10, 2 );

function my_swiftcsv_process_custom_fields( $post_id, $prepared_meta_fields ) {
    // Example: integrate with ACF safely.
    if ( function_exists( 'update_field' ) && isset( $prepared_meta_fields['my_acf_field'] ) ) {
        update_field( 'my_acf_field', $prepared_meta_fields['my_acf_field'], $post_id );
    }
}
```

### Batch / performance

#### `fe_csv_import_export_import_batch_size`

Filter import batch size.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_import_batch_size', int $batch_size, int $total_rows, array $config ): int
```

**Example:**

```php
add_filter( 'fe_csv_import_export_import_batch_size', 'my_swiftcsv_import_batch_size', 10, 3 );

function my_swiftcsv_import_batch_size( $batch_size, $total_rows, $config ) {
    // Example: very small batches for complex imports.
    if ( isset( $config['post_type'] ) && 'product' === $config['post_type'] ) {
        return max( 1, min( (int) $batch_size, 10 ) );
    }
    return (int) $batch_size;
}
```

---

## Admin / UI Hooks

#### `fe_csv_import_export_settings_tabs`

Action fired when rendering settings tabs.

**Type:** action

**Signature:**

```php
do_action( 'fe_csv_import_export_settings_tabs', string $tab ): void
```

#### `fe_csv_import_export_settings_tabs_content`

Action fired when rendering tab content.

**Type:** action

**Signature:**

```php
do_action( 'fe_csv_import_export_settings_tabs_content', string $tab, array $import_results ): void
```

#### `fe_csv_import_export_after_import_settings_fields` / `fe_csv_import_export_after_export_settings_fields` / `fe_csv_import_export_after_advanced_settings_fields` / `fe_csv_import_export_after_license_settings_fields`

Actions fired inside each settings panel, letting extensions append their own fields.

**Type:** action

**Signatures:**

```php
do_action( 'fe_csv_import_export_after_import_settings_fields', object $admin ): void
do_action( 'fe_csv_import_export_after_export_settings_fields', object $admin ): void
do_action( 'fe_csv_import_export_after_advanced_settings_fields', object $admin ): void
do_action( 'fe_csv_import_export_after_license_settings_fields', object $admin ): void
```

**Parameters:**

- `$admin` (`object`) The admin instance (`FE_CSV_Import_Export_Admin`).

#### `fe_csv_import_export_after_export_button`

Action fired right after the export submit button, inside the export settings panel.

**Type:** action

**Signature:**

```php
do_action( 'fe_csv_import_export_after_export_button' ): void
```

#### `fe_csv_import_export_after_import_log_panels`

Action fired after the import log panels, where extra result panels can be rendered.

**Type:** action — **since 1.1.0**

**Signature:**

```php
do_action( 'fe_csv_import_export_after_import_log_panels' ): void
```

It fires immediately after the built-in **Diff** panel, so extensions can append additional result panels alongside it.

#### `fe_csv_import_export_export_form_action`

Filter the form action URL for the export form.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_export_form_action', string $action_url ): string
```

**Parameters:**

- `$action_url` (`string`) Empty by default (uses current page).

**Example:** (redirect to custom handler)

```php
add_filter( 'fe_csv_import_export_export_form_action', 'my_swiftcsv_custom_export_handler', 10, 1 );

function my_swiftcsv_custom_export_handler( $action_url ) {
    return 'https://my-api.com/handle-fe-csv-import-export-export';
}
```

**⚠️ Security Note:** Use with caution. Redirecting to external endpoints can expose sensitive export data. Always ensure proper authentication, HTTPS, and access controls when implementing custom handlers.

**Secure Example:**

```php
add_filter('fe_csv_import_export_export_form_action', 'secure_export_handler', 10, 1);

function secure_export_handler($action_url) {
    // Only allow users with management capabilities
    if (!current_user_can('manage_options')) {
        return $action_url; // Return original URL if insufficient permissions
    }

    // Generate nonce for security verification
    $nonce = wp_create_nonce('secure_export_' . get_current_user_id());

    return home_url("/secure-export?nonce={$nonce}&user_id=" . get_current_user_id());
}
```

#### `fe_csv_import_export_tools_page_capability`

Filter the capability required to access the FE CSV Import & Export admin page.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_tools_page_capability', string $capability ): string
```

**Parameters:**

- `$capability` (`string`) Default `manage_options`.

**Example:** (allow editors to access)

```php
add_filter( 'fe_csv_import_export_tools_page_capability', 'my_swiftcsv_tools_capability', 10, 1 );

function my_swiftcsv_tools_capability( $capability ) {
    return 'edit_posts'; // Allow any user who can edit posts
}
```

---

## Feature Flags / Diagnostics

#### `fe_csv_import_export_enable_direct_sql_import`

Feature flag to enable Direct SQL import.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_enable_direct_sql_import', bool $enabled ): bool
```

#### `fe_csv_import_export_max_log_entries`

Controls how many log entries are stored/displayed.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_max_log_entries', int $max_entries ): int
```

#### `fe_csv_import_export_user_can_export`

Filter user permission to perform exports.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_user_can_export', bool $can_export ): bool
```

**Parameters:**

- `$can_export` (`bool`) Default permission based on `current_user_can('export')`.

**Example:** (require custom capability)

```php
add_filter( 'fe_csv_import_export_user_can_export', 'my_swiftcsv_export_permission', 10, 1 );

function my_swiftcsv_export_permission( $can_export ) {
    return current_user_can( 'manage_options' ) || current_user_can( 'export_csv' );
}
```

#### `fe_csv_import_export_pre_ajax_export`

Filter pre-export validation result.

**Type:** filter

**Signature:**

```php
apply_filters( 'fe_csv_import_export_pre_ajax_export', bool|WP_Error $result, array $post_data ): bool|WP_Error
```

**Parameters:**

- `$result` (`bool|WP_Error`) Validation result.
- `$post_data` (`array`) POST data from export request.

**Example:** (validate export limits)

```php
add_filter( 'fe_csv_import_export_pre_ajax_export', 'my_swiftcsv_export_limits', 10, 2 );

function my_swiftcsv_export_limits( $result, $post_data ) {
    $user_id = get_current_user_id();
    $today_exports = get_transient( 'fe_csv_import_export_exports_' . $user_id ) ?: 0;

    if ( $today_exports >= 10 ) {
        return new WP_Error( 'daily_limit', 'Daily export limit reached (10 exports per day)' );
    }

    return $result;
}
```

---

## Best Practices

1. Validate the `$context` or `$config` before applying changes.
2. Keep hook logic fast; heavy processing should be deferred.
3. Sanitize output and inputs (`sanitize_text_field`, `absint`, etc.).
4. For integrations (ACF/WooCommerce), guard with `function_exists()`.
5. Prefer idempotent logic (a filter might run multiple times per request).
