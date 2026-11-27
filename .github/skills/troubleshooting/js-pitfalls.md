# JavaScript Pitfalls

## #002 Progress Element Not Found (2026-02-06)

**Symptom**: Console error `Progress element not found!` after UI refactor.

**Cause**: JS was looking for old `#fe-csv-import-export-ajax-export-progress` element, but the new UI uses `.fe-csv-import-export-progress`.

**Fix** (`assets/js/fe-csv-import-export-export-unified.js`):

```javascript
// BEFORE
const progressContainer = document.querySelector('#fe-csv-import-export-ajax-export-progress');

// AFTER
const container = document.querySelector('.fe-csv-import-export-progress');
if (!container) return;
const progressFill = container.querySelector('.progress-bar-fill');
```

**Lesson**: After any UI structure change, grep JS files for old selectors.

---

## #003 Export Filename Date Format Bug (2026-02-06)

**Symptom**: Filename shows `202602-06` instead of `2026-02-06` (missing hyphen between year and month).

**Cause**: String concatenation was missing `-` after `getFullYear()`.

**Fix** (`assets/js/fe-csv-import-export-export-unified.js`):

```javascript
// BEFORE
const dateStr = now.getFullYear() +
    String(now.getMonth() + 1).padStart(2, '0') + '-' + ...

// AFTER
const dateStr = now.getFullYear() + '-' +
    String(now.getMonth() + 1).padStart(2, '0') + '-' + ...
```

**Lesson**: Always test date formatting with actual output. Easy to miss in concatenation chains.

---

## #006 Export Cancellation Not Working (2026-02-10)

**Symptom**: Export continues running after clicking cancel, or cancellation flags accumulate across sessions.

**Cause**: Three issues:

1. WordPress option cache prevents immediate flag detection
2. Cancellation flags not isolated per export session
3. Multiple cancel handlers registered simultaneously

**Fix** (PHP — `includes/export/class-fe-csv-import-export-export-cancel-manager.php` and unified export handler flow):

```php
// BEFORE — Option cache issue
$is_cancelled = get_option("fe_csv_import_export_export_cancelled_{$user_id}");

// AFTER — Direct DB read to bypass cache
global $wpdb;
$is_cancelled = $wpdb->get_var($wpdb->prepare(
    "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
    "fe_csv_import_export_export_cancelled_{$user_id}_{$export_session}"
));
```

**Fix** (JS — `assets/js/fe-csv-import-export-export-unified.js`):

```javascript
// BEFORE — Multiple event handlers accumulate
cancelBtn.addEventListener('click', cancelHandler);

// AFTER — Clean up previous handler first
if (exportCancelHandler) {
	cancelBtn.removeEventListener('click', exportCancelHandler);
}
exportCancelHandler = function () {
	/* ... */
};
cancelBtn.addEventListener('click', exportCancelHandler, { once: true });
```

**Lesson**: Use direct DB reads for time-sensitive flags. Always clean up event listeners. Use session IDs to isolate concurrent operations.

---

## #008 UI Disappears After JS Modularization (2026-02-10)

**Symptom**: Log window, progress bar, and download button disappear on page load. DOM shows `.fe-csv-import-export-log` container is empty.

**Cause**: Three compounding issues:

1. `initLoggingSystem()` used `.fe-csv-import-export-log` (parent container) instead of `#fe-csv-import-export-export-log-content` (child), clearing all children including buttons and progress bar
2. `addLogEntry()` also targeted the wrong container
3. Modules tried to initialize before they were loaded

**Fix** (`assets/js/fe-csv-import-export-core.js` — don't clear parent):

```javascript
// BEFORE — Destroys all child elements
function initLoggingSystem() {
	const logContainer = document.querySelector('.fe-csv-import-export-log');
	logContainer.innerHTML = '';
}

// AFTER — Preserve HTML structure
function initLoggingSystem() {
	// Don't clear logs on page load - preserve initial messages
}
```

**Fix** (`assets/js/fe-csv-import-export-main.js` — correct selectors):

```javascript
// BEFORE — Wrong selector
const logContainer = document.querySelector('.fe-csv-import-export-log');
logContainer.innerHTML = '';

// AFTER — Target specific child
const logContent = document.querySelector(`#${context}-log-content`);
if (logContent) logContent.innerHTML = '';
```

**Fix** (`assets/js/fe-csv-import-export-main.js` — wait for modules):

```javascript
const checkModules = () => {
	if (
		window.FeCsvImportExportCore &&
		window.FeCsvImportExportExport &&
		window.FeCsvImportExportImport &&
		window.FeCsvImportExportLicense
	) {
		// Safe to initialize
	} else {
		setTimeout(checkModules, 50);
	}
};
```

**Lesson**: Always use specific child selectors for DOM manipulation. Never `innerHTML = ''` on a parent container that holds multiple UI elements. Wait for all module globals before initialization.

---

## #009 HTML/JavaScript Naming Convention Mismatch (2025-03-25)

**Symptom**: Custom post type (e.g., `cafe-menu`) updates not working despite selecting correct post type in UI. Dry run always shows "Create" instead of "Update". Actual import neither updates nor creates posts.

**Cause**: HTML id attribute and JavaScript selector mismatch:

```html
<!-- HTML -->
<select name="fe_csv_import_export_import_post_type" id="ajax_import_post_type" required></select>
```

```javascript
// JavaScript (WRONG)
const postType = document.querySelector('#import_post_type')?.value || 'post';
```

Selector returns `null`, fallback to `'post'` always sent to server. Update query fails: `ID=123 AND post_type='post'` vs actual `cafe-menu`.

**Fix** (`assets/js/fe-csv-import-export-import.js`):

```javascript
// BEFORE
const postType = document.querySelector('#import_post_type')?.value || 'post';

// AFTER
const postType = document.querySelector('#ajax_import_post_type')?.value || 'post';
```

**Lesson**: Always verify HTML/JavaScript naming convention consistency. Use debug logging to confirm actual sent values. Test with custom post types, not just default 'post'.

---

## #014 "No file selected" After Drag & Drop or Completed Import (2026-11-27)

**Symptom**: Import fails with `noFileSelected` even though the UI shows a selected file. Typically reported as "Dry Run failed, but the real import worked after re-picking the file."

**Cause**: Two separate issues in `assets/js/fe-csv-import-export-import.js`:

1. The drop handler called `handleFileSelect(files[0])` (UI update only) but never assigned `fileInput.files`, so the `<input type="file">` stayed empty.
2. `completeAjaxImport()` cleared `fileInput.value = ''` and hid the file info after every completed import, so a second run (e.g. Dry Run then real import) always failed unless the file was re-picked.

**Fix**:

```javascript
// Drop handler — reflect the file on the input
const files = e.dataTransfer.files;
if (files.length > 0) {
	try {
		fileInput.files = files;
		droppedFile = null;
	} catch (error) {
		droppedFile = files[0]; // Fallback when FileList assignment is not allowed
	}
	handleFileSelect(files[0]);
}

// startAjaxImport — check the file before clearing logs/progress
const file = document.querySelector('#ajax_csv_file')?.files[0] || droppedFile;

// completeAjaxImport — removed the file input reset block entirely
```

**Lesson**: Updating the "file selected" UI without populating `input.files` creates a false selected state. Verify the actual `input.files` contents in every selection path (dialog, drag & drop, remove). Do not clear the file input on import completion — the Dry Run → real import workflow requires the selection to persist.
