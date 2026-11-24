# ⭐ Pro Version Features

This page describes the features provided by **FE CSV Import & Export Pro**, and how to use them.

The Free and Pro plugins work together: install and activate both plugins, then activate your license key to unlock Pro features.

!> Everything on this page requires **FE CSV Import & Export Pro** with an active license.

## Feature Comparison

| Feature | Free | Pro |
| ------- | :--: | :-: |
| CSV import / export | ✓ | ✓ |
| Custom fields (`cf_` prefix) | ✓ | ✓ |
| Taxonomy columns (incl. hierarchy) | ✓ | ✓ |
| Automatic batch processing | ✓ | ✓ |
| Dry Run diff preview | ✓ | ✓ |
| Full diff CSV download | — | ✓ |
| ACF fields (`acf_` prefix) | — | ✓ |
| Direct SQL export | — | ✓ |
| Scheduled jobs | — | ✓ |
| UpdraftPlus database backup | — | ✓ |
| Execution password / re-authentication | — | ✓ |

## License Activation

1. Purchase a license from the [product page](https://www.firstelement.co.jp/en/products/fe-csv-import-export-plugin/)
2. Copy the license key from your account page on the sales site
3. Open **Tools → FE CSV Import & Export → License** and enter the key
4. Activate the license to enable Pro features and automatic updates

Pro features remain hidden until the license is active.

## ACF Field Support

Imports and exports Advanced Custom Fields data while preserving field formatting.

- Use `acf_{field_name}` CSV columns to map values to ACF fields (e.g. `acf_price`)
- Taxonomy field values are exported as readable names; multi-value fields use the standard `|` separator
- Supported field types include Text, Number, Select, Checkbox, Taxonomy, User, File, Relationship, Repeater, and Flexible Content
- Requires the ACF plugin to be installed and active; no additional configuration is needed

## Scheduled Jobs

Schedule one-time imports and exports to run later in the background.

### Creating a job

1. Open the **Export** or **Import** tab and configure the operation as usual
2. Under **Execution timing**, choose **Schedule for later**
3. Pick a **start time** (WordPress site timezone) and optionally enable **Email me on completion or failure**
4. Start the operation — it is registered as a job instead of running immediately

Jobs are listed on the **Schedules** tab with their status: Pending, Running, Completed, Failed, or Cancelled.

### How jobs run

- Jobs execute through WP-Cron. On quiet sites WP-Cron can run late, so each job also has a **secret trigger URL** shown on the Schedules tab — point a real server cron at that URL for precise start times
- Job types: one-time import or one-time export (WP-compatible mode only)
- Scheduled imports run with automatic column mapping; the interactive mapping review step is skipped
- For imports, the CSV is staged in private schedule storage outside the web root when the job is created

### Managing jobs

- **Run**: start a pending job immediately
- **Cancel**: stop a pending or running job
- **Rotate**: regenerate the secret trigger URL
- **Download CSV**: fetch the result of a completed export
- **Delete**: remove a finished job

You can manage only the jobs you created; site administrators can manage every job.

!> Requires FE CSV Import & Export (free) **1.1.0 or later**.

## Dry Run Full Diff CSV Download

The free version's Dry Run preview shows the per-field diff for up to 100 rows on screen. With Pro, a **Download full diff (CSV)** button appears in the diff area after a dry run, letting you download the complete diff for every row — including rows not shown in the preview.

!> Requires FE CSV Import & Export (free) **1.1.0 or later**.

## Direct SQL Export

An **Export (Direct SQL)** button is added next to the standard export button on the Export tab. It bypasses WordPress core functions and queries the database directly for maximum export performance — useful for very large datasets and advanced workflows.

## UpdraftPlus Database Backup

When the [UpdraftPlus](https://updraftplus.com/) backup plugin is installed and active, an option appears in the **Advanced Settings** tab: **Run UpdraftPlus database backup before executing import**. Enabling it takes a database backup automatically before each import, so you can restore immediately if something goes wrong.

## Execution Password and Re-authentication

Adds execution-time access control, configured on the dedicated **Execution Password** tab (administrators only). Import and export are configured independently:

- **Require login password**: the operator must re-enter their own account password before each execution
- **Require execution password**: a shared password set on this tab is required
  - **Ask every time**: prompt on every execution
  - **Allow time window**: re-ask after the configured number of minutes (1–1440)

Changing the execution password requires the administrator's login password.
