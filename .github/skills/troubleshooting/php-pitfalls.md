# PHP Pitfalls

## #001 IDE Static Analysis Warnings (2026-03-19)

**Symptom**: IDE shows static analysis warnings for PHPDoc @param/@return types with specific class names.

**Cause**: IDE cannot resolve class names in PHPDoc while method signatures use strict types.

**Fix** (All import classes):

```php
// BEFORE — Specific class names in PHPDoc
/**
 * @param FE_CSV_Import_Export_Import_Persister $persister Persister utility.
 * @return FE_CSV_Import_Export_Import_Row_Context
 */

// AFTER — Use object type in PHPDoc
/**
 * @param object $persister Persister utility.
 * @return object
 */
public function method( FE_CSV_Import_Export_Import_Persister $persister ): FE_CSV_Import_Export_Import_Row_Context {
    // Implementation
}
```

**Lesson**: Use `object` in PHPDoc for IDE compatibility while maintaining strict type hints in method signatures.

---

## #005 WordPress AJAX Success Flag Missing (2026-02-08)

**Symptom**: AJAX request succeeds but JavaScript throws error. Console shows "Import failed" despite server processing completing successfully.

**Cause**: WordPress AJAX responses must include `success: true` flag. JavaScript checks `if (!data.success)` and throws error when flag is missing.

**Fix** (AJAX handlers):

```php
// BEFORE — Missing success flag
wp_send_json([
    'processed' => $next_row,
    'total' => $total_rows,
]);

// AFTER — Include success flag
wp_send_json([
    'success' => true,
    'processed' => $next_row,
    'total' => $total_rows,
]);

// Or use WordPress standard functions
wp_send_json_success($data);
wp_send_json_error($message);
```

**Lesson**: Always include `success` flag in WordPress AJAX JSON responses. Prefer `wp_send_json_success()` / `wp_send_json_error()`.

---

## #006 Import File Cleanup Issues (2026-03-19)

**Symptom**: Temporary import files persist after errors or completion, causing disk space issues.

**Cause**: Missing cleanup on error paths or exception handling.

**Fix** (Import classes):

```php
// BEFORE — No cleanup on error
try {
    $result = $this->process_import( $file_path );
} catch ( Exception $e ) {
    wp_send_json_error( $e->getMessage() );
    // File not cleaned up!
}

// AFTER — Cleanup on all paths
try {
    $result = $this->process_import( $file_path );
} catch ( Exception $e ) {
    if ( $file_path && file_exists( $file_path ) ) {
        wp_delete_file( $file_path );
    }
    wp_send_json_error( $e->getMessage() );
}

// Always cleanup on completion
if ( $file_path && file_exists( $file_path ) ) {
    wp_delete_file( $file_path );
}
```

**Lesson**: Always include cleanup on ALL execution paths, not just success paths.

---

## #015 TypeError on Import When CSV ID Column Is Empty (2026-11-27)

**Symptom**: Both Dry Run and real import fail with
`TypeError: resolve_post_id_and_update_flag(): Argument #4 ($post_id_from_csv) must be of type int, string given`
whenever the CSV `ID` column is empty (new-post imports).

**Cause**: `get_post_id_from_csv_row()` returns `string` (e.g. `''` for new rows), but
`resolve_post_id_and_update_flag()` declared the parameter as `int`. PHP throws a
TypeError for `''` even in coercive mode, so every create-mode row crashed.
Numeric ID strings only appeared to work because PHP coerces them silently.

**Fix** (`includes/import/class-fe-csv-import-export-import-row-context.php`):

```php
// BEFORE — crashes on '' (empty ID column)
public function resolve_post_id_and_update_flag(
    wpdb $wpdb,
    string $update_existing,
    string $post_type,
    int $post_id_from_csv
): array

// AFTER — matches get_post_id_from_csv_row() return type
public function resolve_post_id_and_update_flag(
    wpdb $wpdb,
    string $update_existing,
    string $post_type,
    string $post_id_from_csv
): array
```

**Lesson**: CSV-derived values are always strings, including "empty" cells (`''`).
Type `int` hints on values read straight from CSV rows will TypeError on empty
cells — keep them `string` at the boundary and cast only after emptiness checks.
This also existed since the file was introduced, so test imports with an empty
ID column (create mode), not only numeric-ID update rows.
