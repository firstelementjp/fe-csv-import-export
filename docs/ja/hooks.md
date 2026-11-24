# FE CSV Import & Export フック

このドキュメントは、現在のコードベースで実装されている FE CSV Import & Export の利用可能なフックを一覧表示します。

以下に焦点を当てています：

- 正確なフック名とシグネチャ
- 実用的な使用パターン
- 実際の拡張（WooCommerce、カスタムビジネスルールなど）を反映した例

## 目次

- [エクスポートフック](#エクスポートフック)
    - [ヘッダー生成](#ヘッダー生成)
    - [行生成](#行生成)
    - [バッチ/パフォーマンス](#バッチパフォーマンス)
- [インポートフック](#インポートフック)
    - [権限と検証](#権限と検証)
    - [フィールド準備とマッピング](#フィールド準備とマッピング)
    - [バッチ処理](#バッチ処理)
    - [スケジュール実行シーム](#スケジュール実行シーム)
    - [Dry Run プレビューと一時ファイル](#dry-run-プレビューと一時ファイル)
    - [フェーズ化インポートアクション](#フェーズ化インポートアクション)
    - [行の検証と正規化](#行の検証と正規化)
    - [ログと診断](#ログと診断)
- [管理/UI フック](#管理ui-フック)
- [機能フラグ](#機能フラグ)
- [ベストプラクティス](#ベストプラクティス)

---

## エクスポートフック

### ヘッダー生成

#### `fe_csv_import_export_export_filter_taxonomy_objects`

`tax_{taxonomy}` ヘッダーの構築に使用されるタクソノミーオブジェクトをフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_export_filter_taxonomy_objects', array $taxonomies, array $args): array
```

**パラメータ:**

- `$taxonomies` (`array`) `get_object_taxonomies($post_type, 'objects')` によって返されるタクソノミーオブジェクト
- `$args` (`array`) コンテキスト引数
    - `post_type` (`string`)
    - `export_scope` (`string`)
    - `include_private_meta` (`bool`)
    - `context` (`string`) 現在 `taxonomy_objects_filter`

**例:** (内部タクソノミーを除外)

```php
add_filter('fe_csv_import_export_export_filter_taxonomy_objects', 'my_swiftcsv_filter_taxonomies', 10, 2);

function my_swiftcsv_filter_taxonomies($taxonomies, $args) {
    if (!is_array($taxonomies)) {
        return [];
    }

    foreach ($taxonomies as $key => $tax) {
        if (!isset($tax->name)) {
            continue;
        }

        // 例: エクスポートからタクソノミーを非表示
        if ('post_format' === $tax->name) {
            unset($taxonomies[$key]);
        }
    }

    return $taxonomies;
}
```

#### `fe_csv_import_export_export_sample_query_args`

メタキー発見のための「サンプル投稿」を選択するために使用される WP クエリ引数をフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_export_sample_query_args', array $query_args, array $args): array
```

**パラメータ:**

- `$query_args` (`array`) サンプル ID を取得するために使用される WP クエリ引数
- `$args` (`array`) コンテキスト引数
    - `post_type` (`string`)
    - `context` (`string`) 現在 `meta_discovery`

**例:** (メタ付きの最近の投稿を優先)

```php
add_filter('fe_csv_import_export_export_sample_query_args', 'my_swiftcsv_sample_query_args', 10, 2);

function my_swiftcsv_sample_query_args($query_args, $args) {
    // 例: カスタムフィールドを持つ可能性が高い投稿を優先
    $query_args['orderby'] = 'modified';
    $query_args['order'] = 'DESC';
    return $query_args;
}
```

#### `fe_csv_import_export_export_classify_meta_keys`

サンプル投稿から発見されたメタキーを分類します。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_export_classify_meta_keys', array $all_meta_keys, array $args): array
```

**パラメータ:**

- `$all_meta_keys` (`array<string>`) 生の発見されたメタキー
- `$args` (`array`) コンテキスト
    - `post_type` (`string`)
    - `export_scope` (`string`)
    - `include_private_meta` (`bool`)
    - `context` (`string`) 現在 `meta_key_classification`

**期待される戻り値:**

```php
[
  'regular' => array<string>,
  'private' => array<string>,
]
```

**例:** (ノイズの多いキーを除外)

```php
add_filter('fe_csv_import_export_export_classify_meta_keys', 'my_swiftcsv_classify_meta_keys', 10, 2);

function my_swiftcsv_classify_meta_keys($all_meta_keys, $args) {
    $regular = [];
    $private = [];

    foreach ((array) $all_meta_keys as $key) {
        $key = (string) $key;
        if ('' === $key) {
            continue;
        }

        // 例: WordPress 内部キーを削除
        if (in_array($key, ['_edit_lock', '_edit_last'], true)) {
            continue;
        }

        if (0 === strpos($key, '_')) {
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

分類されたメタキーからカスタムフィールド（メタ）ヘッダーを生成します。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_export_generate_custom_field_headers', array $headers, array $classified_meta_keys, array $args): array
```

**パラメータ:**

- `$headers` (`array<string>`) 空の配列から開始
- `$classified_meta_keys` (`array`) `fe_csv_import_export_export_classify_meta_keys` の結果
- `$args` (`array`) コンテキスト
    - `post_type` (`string`)
    - `export_scope` (`string`)
    - `include_private_meta` (`bool`)
    - `context` (`string`) 現在 `custom_field_headers_generation`

**例:** (許可リストのメタキーのみ)

```php
add_filter('fe_csv_import_export_export_generate_custom_field_headers', 'my_swiftcsv_custom_field_headers', 10, 3);

function my_swiftcsv_custom_field_headers($headers, $classified_meta_keys, $args) {
    $allow = ['price', 'color', 'size'];
    $out = [];

    foreach ((array) ($classified_meta_keys['regular'] ?? []) as $meta_key) {
        $meta_key = (string) $meta_key;
        if (in_array($meta_key, $allow, true)) {
            $out[] = 'cf_' . $meta_key;
        }
    }

    return $out;
}
```

#### `fe_csv_import_export_export_headers`

最終的なヘッダーリストをフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_export_headers', array $headers, array $config, string $context): array
```

**パラメータ:**

- `$headers` (`array<string>`) 最終ヘッダー
- `$config` (`array`) エクスポート設定
- `$context` (`string`) 現在 `standard` (WP 互換) または `direct_sql`

**例:** (カスタム計算カラムを追加)

```php
add_filter('fe_csv_import_export_export_headers', 'my_swiftcsv_add_custom_header', 10, 3);

function my_swiftcsv_add_custom_header($headers, $config, $context) {
    // 標準以外のヘッダーを追加。その値は fe_csv_import_export_export_process_custom_header によって提供される
    $headers[] = 'my_permalink';
    return $headers;
}
```

#### `fe_csv_import_export_export_phase_headers`

ヘッダーが確定した後に発火されるアクション。

**タイプ:** action

**シグネチャ:**

```php
do_action('fe_csv_import_export_export_phase_headers', array $headers, array $config, string $context): void
```

**例:** (ヘッダーをログ記録)

```php
add_action('fe_csv_import_export_export_phase_headers', 'my_swiftcsv_log_headers', 10, 3);

function my_swiftcsv_log_headers($headers, $config, $context) {
    error_log('[FE CSV Import & Export] Export headers finalized (' . $context . '): ' . implode(',', (array) $headers));
}
```

### 行生成

#### `fe_csv_import_export_export_row`

エクスポート生成中に各行をフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_export_row', array $row, int $post_id, array $config, string $context): array
```

**パラメータ:**

- `$row` (`array`) 行データ
    - WP 互換エクスポートの場合: 通常、ヘッダーに合わせたインデックス配列
    - Direct SQL エクスポートの場合: CSV に変換する前に連想配列がよく使用される
- `$post_id` (`int`) 投稿 ID
- `$config` (`array`) エクスポート設定
- `$context` (`string`) `wp_compatible`, `direct_sql`、またはスコープ値

**例:** (商品価格をフォーマット)

```php
add_filter('fe_csv_import_export_export_row', 'my_swiftcsv_export_row_format', 10, 4);

function my_swiftcsv_export_row_format($row, $post_id, $config, $context) {
    // WP 互換エクスポートは通常、ヘッダーに合わせたインデックス行を渡す
    // Direct SQL エクスポートは通常、連想行を渡す

    if ('direct_sql' === $context && is_array($row) && isset($row['cf_price'])) {
        $row['cf_price'] = number_format((float) $row['cf_price'], 2, '.', '');
        return $row;
    }

    // 例: wp_compatible の場合、インデックスで更新（形状を維持）
    // ここでヘッダー対応の編集が必要な場合、インデックスを特定するために fe_csv_import_export_export_headers もフックする
    if ('wp_compatible' === $context && is_array($row) && isset($row[0])) {
        // 何もしない例: 行をそのまま返す
        return $row;
    }

    return $row;
}
```

#### `fe_csv_import_export_export_process_custom_header`

標準の `post_*`, `tax_*`, `cf_*`, `ID` ではないカスタムヘッダーの値を提供します。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_export_process_custom_header', string $value, string $header, int $post_id, array $args): string
```

**パラメータ:**

- `$value` (`string`) デフォルトは空
- `$header` (`string`) ヘッダー名
- `$post_id` (`int`) 投稿 ID
- `$args` (`array`) コンテキスト
    - `post_type` (`string`)
    - `context` (`string`) 現在 `export_data_processing`

**例:** (`my_permalink` ヘッダーを実装)

```php
add_filter('fe_csv_import_export_export_process_custom_header', 'my_swiftcsv_custom_header_value', 10, 4);

function my_swiftcsv_custom_header_value($value, $header, $post_id, $args) {
    if ('my_permalink' === $header) {
        return (string) get_permalink($post_id);
    }

    return (string) $value;
}
```

#### `fe_csv_import_export_export_batch_size`

エクスポートバッチサイズをフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_export_batch_size', int $batch_size, int $total_count, string $post_type, array $config): int
```

**例:**

```php
add_filter('fe_csv_import_export_export_batch_size', 'my_swiftcsv_export_batch_size', 10, 4);

function my_swiftcsv_export_batch_size($batch_size, $total_count, $post_type, $config) {
    // 例: 重い投稿タイプのバッチサイズを削減
    if ('product' === $post_type) {
        return max(100, min((int) $batch_size, 500));
    }
    return (int) $batch_size;
}
```

#### `fe_csv_import_export_export_batch_prepare`

WP互換エクスポートの各バッチ処理前に、投稿オブジェクトがロードされた状態で発火されるアクション。

**タイプ:** action

**シグネチャ:**

```php
do_action('fe_csv_import_export_export_batch_prepare', array $post_ids, array $posts, array $headers, array $config, string $context): void
```

**パラメータ:**

- `$post_ids` (`array<int>`) このバッチの投稿 ID
- `$posts` (`array`) バッチ分のロード済み `WP_Post` オブジェクト
- `$headers` (`array<string>`) エクスポートヘッダー
- `$config` (`array`) エクスポート設定
- `$context` (`string`) 現在は `wp_compatible`

**例:** (バッチ単位で外部データを事前ロード)

```php
add_action('fe_csv_import_export_export_batch_prepare', 'my_swiftcsv_export_batch_prepare', 10, 5);

function my_swiftcsv_export_batch_prepare($post_ids, $posts, $headers, $config, $context) {
    // 例: バッチ全体のカスタム参照テーブルを事前準備
    my_prime_external_cache($post_ids);
}
```

---

## インポートフック

### 権限と検証

#### `fe_csv_import_export_user_can_import`

インポートを実行するユーザー権限をフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_user_can_import', bool $can_import): bool
```

**パラメータ:**

- `$can_import` (`bool`) `current_user_can('import')` に基づくデフォルト権限

**例:** (カスタム権限を要求)

```php
add_filter('fe_csv_import_export_user_can_import', 'my_swiftcsv_import_permission', 10, 1);

function my_swiftcsv_import_permission($can_import) {
    return current_user_can('manage_options') || current_user_can('import_csv');
}
```

#### `fe_csv_import_export_pre_ajax_import`

インポート前検証結果をフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_pre_ajax_import', bool|WP_Error $result, array $post_data): bool|WP_Error
```

**パラメータ:**

- `$result` (`bool|WP_Error`) 検証結果
- `$post_data` (`array`) インポートリクエストからの POST データ

**例:** (営業時間を検証)

```php
add_filter('fe_csv_import_export_pre_ajax_import', 'my_swiftcsv_business_hours_check', 10, 2);

function my_swiftcsv_business_hours_check($result, $post_data) {
    $hour = (int) date('H');
    if ($hour < 9 || $hour > 17) {
        return new WP_Error('business_hours', 'Import only allowed during business hours (9AM-5PM)');
    }
    return $result;
}
```

#### `fe_csv_import_export_import_row_validation`

行レベルの検証フィルター。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_import_row_validation', array $row_validation, array $row_context, array $context): array
```

**例:** (新規作成時に `post_title` を必須化)

```php
add_filter('fe_csv_import_export_import_row_validation', 'my_swiftcsv_validate_row', 10, 3);

function my_swiftcsv_validate_row($row_validation, $row_context, $context) {
    $post_fields = (array) ($row_context['post_fields_from_csv'] ?? []);
    $is_update = !empty($row_context['is_update']);

    if (!$is_update && empty($post_fields['post_title'])) {
        $row_validation['valid'] = false;
        $row_validation['errors'][] = 'post_title is required for new posts.';
    }

    return $row_validation;
}
```

### フィールド準備とマッピング

#### `fe_csv_import_export_prepare_import_fields`

インポート前に準備されたメタフィールドをフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_prepare_import_fields', array $meta_fields, int $post_id, array $args): array
```

**パラメータ:**

- `$meta_fields` (`array`) 準備されたメタフィールド
- `$post_id` (`int`) インポート中の投稿 ID
- `$args` (`array`) post_type とコンテキストを含むコンテキスト引数

**例:** (カスタムフィールド形式を処理)

```php
add_filter('fe_csv_import_export_prepare_import_fields', 'my_swiftcsv_process_custom_fields', 10, 3);

function my_swiftcsv_process_custom_fields($meta_fields, $post_id, $args) {
    foreach ($meta_fields as $key => $value) {
        if (strpos($key, 'price_') === 0) {
            $meta_fields[$key] = floatval($value);
        }
    }
    return $meta_fields;
}
```

#### `fe_csv_import_export_filter_sample_posts`

インポートフィールド検出に使われるサンプル投稿 ID をフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_filter_sample_posts', array $post_ids, array $args): array
```

**パラメータ:**

- `$post_ids` (`array<int>`) サンプル投稿 ID（デフォルトは空）
- `$args` (`array`) コンテキスト
    - `post_type` (`string`)
    - `context` (`string`) 現在は `import_field_detection`

#### `fe_csv_import_export_allow_protected_meta_import`

アンダースコアで始まるメタキー（保護メタ）のインポートを許可します。デフォルトでは無効です。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_allow_protected_meta_import', bool $allow): bool
```

**パラメータ:**

- `$allow` (`bool`) デフォルト `false`

**⚠️ セキュリティ注意:** 保護メタにはプラグイン内部値（`_edit_lock`、ACFのフィールドキーなど）が含まれます。信頼できる移行用途に限定してください。

#### `fe_csv_import_export_import_data_filter`

パースされた生の行データを正規化/フィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_import_data_filter', array $filtered_data, array $original_data, array $context): array
```

**例:** (フィールドをトリム)

```php
add_filter('fe_csv_import_export_import_data_filter', 'my_swiftcsv_import_data_filter', 10, 3);

function my_swiftcsv_import_data_filter($filtered_data, $original_data, $context) {
    $data = (array) ($filtered_data['data'] ?? []);
    foreach ($data as $i => $value) {
        $data[$i] = is_string($value) ? trim($value) : $value;
    }

    $filtered_data['data'] = $data;
    return $filtered_data;
}
```

#### `fe_csv_import_export_import_field_mapping`

ヘッダーと行値から収集されたメタ/タクソノミーフィールドをフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_import_field_mapping', array $collected_fields, array $headers, array $data, array $allowed_post_fields): array
```

**例:** (メタキーをリネーム)

```php
add_filter('fe_csv_import_export_import_field_mapping', 'my_swiftcsv_import_field_mapping', 10, 4);

function my_swiftcsv_import_field_mapping($collected_fields, $headers, $data, $allowed_post_fields) {
    if (isset($collected_fields['meta_fields']['old_key'])) {
        $collected_fields['meta_fields']['new_key'] = $collected_fields['meta_fields']['old_key'];
        unset($collected_fields['meta_fields']['old_key']);
    }

    return $collected_fields;
}
```

#### `fe_csv_import_export_process_custom_fields`

フィールド準備と投稿永続化の後に呼び出される、後方互換用のアクション。

**タイプ:** action

**シグネチャ:**

```php
do_action('fe_csv_import_export_process_custom_fields', int $post_id, array $prepared_meta_fields): void
```

**例:** (ACF 更新)

```php
add_action('fe_csv_import_export_process_custom_fields', 'my_swiftcsv_process_custom_fields', 10, 2);

function my_swiftcsv_process_custom_fields($post_id, $prepared_meta_fields) {
    // 例: ACF と安全に連携
    if (function_exists('update_field') && isset($prepared_meta_fields['my_acf_field'])) {
        update_field('my_acf_field', $prepared_meta_fields['my_acf_field'], $post_id);
    }
}
```

### バッチ処理

#### `fe_csv_import_export_import_batch_size`

インポートバッチサイズをフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_import_batch_size', int $batch_size, int $total_rows, array $config): int
```

**パラメータ:**

- `$batch_size` (`int`) 計算されたバッチサイズ
- `$total_rows` (`int`) 処理する総行数
- `$config` (`array`) インポート設定

**例:** (サーバーパフォーマンスを最適化)

```php
add_filter('fe_csv_import_export_import_batch_size', 'my_swiftcsv_optimize_batch_size', 10, 3);

function my_swiftcsv_optimize_batch_size($batch_size, $total_rows, $config) {
    // メモリ制約のあるサーバーのバッチサイズを削減
    $memory_limit = ini_get('memory_limit');
    if ($memory_limit === '128M') {
        return min(5, $batch_size);
    }
    return $batch_size;
}
```

#### `fe_csv_import_export_import_max_batch_size`

インポートバッチサイズに適用されるハード上限をフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_import_max_batch_size', int $max_batch_size, int $total_rows, array $config): int
```

**パラメータ:**

- `$max_batch_size` (`int`) バッチサイズの上限（デフォルト 500）
- `$total_rows` (`int`) 処理する総行数
- `$config` (`array`) インポート設定

#### `fe_csv_import_export_import_batch_completed`

各インポートバッチが永続化された後に発火されるアクション。

**タイプ:** action — **1.1.0 以降**

**シグネチャ:**

```php
do_action('fe_csv_import_export_import_batch_completed', array $post_ids, array $context): void
```

**パラメータ:**

- `$post_ids` (`array<int, int>`) このバッチで永続化された投稿 ID
- `$context` (`array`) バッチコンテキスト
    - `post_type` (`string`)
    - `import_session` (`string`)
    - `start_row` (`int`)
    - `dry_run` (`bool`)
    - `batch_size` (`int`)

インポートは直接クエリで投稿を書き込むため、インポート行では `save_post` が発火しません。投稿変更に反応する連携（検索インデクサーなど）はここにフックしてください。

**例:** (インポート済み投稿を再インデックス)

```php
add_action('fe_csv_import_export_import_batch_completed', 'my_swiftcsv_reindex_batch', 10, 2);

function my_swiftcsv_reindex_batch($post_ids, $context) {
    if (!empty($context['dry_run'])) {
        return;
    }
    my_search_indexer_update($post_ids);
}
```

### スケジュール実行シーム

これらの拡張ポイント（すべて **1.1.0 以降**）は、FE CSV Import & Export Pro が1回限りの予約インポート/エクスポートを実行するためのものです。主に Pro プラグインが消費する用途を想定しています。

#### `fe_csv_import_export_response_handler`

JSON レスポンス送信前に呼び出されるハンドラーをフィルターします。callable を返すと、デフォルトの `wp_send_json()` フローが置き換えられます。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_response_handler', callable|null $handler, array $payload): callable|null
```

**パラメータ:**

- `$handler` (`callable|null`) レスポンスハンドラー callable または `null`
- `$payload` (`array`) 送信予定の JSON ペイロード

Pro のスケジュールジョブランナーは、単一 PHP プロセス内で `wp_send_json()`/`wp_die()` を実行せずにエンジンのレスポンスを（例外スロー経由で）捕捉するためにこの callable を登録します。

#### `fe_csv_import_export_import_file_path`

事前配置済みのインポートファイルパスを注入し、アップロードフローと nonce 検証をスキップするフィルター。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_import_file_path', string $file_path): string
```

**パラメータ:**

- `$file_path` (`string`) 事前配置済みファイルパス（デフォルトは空）。空でない読み取り可能なパスを返すと、nonce 検証とファイルアップロードがバイパスされます

Pro の予約インポートランナーは、ジョブ用にステージングされた CSV パスをここで返します。

**⚠️ セキュリティ注意:** 信頼できるサーバーサイドコードからのみパスを返してください。このフィルターは設計上、アップロード時の nonce 検証をバイパスします。

#### `fe_csv_import_export_import_csv_store_ttl`

インポートバッチ間でパース済み CSV メタデータを保持する transient の TTL をフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_import_csv_store_ttl', int $ttl): int
```

**パラメータ:**

- `$ttl` (`int`) transient の TTL 秒数（デフォルト `3600`）

Pro の予約インポートランナーは、長時間実行されるバックグラウンドジョブがストリームオフセットを失わないようこの値を延長します。

### Dry Run プレビューと一時ファイル

#### `fe_csv_import_export_dry_run_log_limit`

Dry Run 中に保持する行単位ログエントリの最大数をフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_dry_run_log_limit', int $limit): int
```

**パラメータ:**

- `$limit` (`int`) デフォルト `50`

#### `fe_csv_import_export_dry_run_diff_row_limit`

管理画面の Diff プレビューに描画する差分行の最大数をフィルターします。

**タイプ:** filter — **1.1.0 以降**

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_dry_run_diff_row_limit', int $limit): int
```

**パラメータ:**

- `$limit` (`int`) デフォルト `100`。プレビュー表示にのみ影響し、保存される差分データは常に全行をカバーします

#### `fe_csv_import_export_dry_run_diff_value_max_length`

Dry Run 差分に保存される変更前/変更後の値の最大長をフィルターします。

**タイプ:** filter — **1.1.0 以降**

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_dry_run_diff_value_max_length', int $max_chars): int
```

**パラメータ:**

- `$max_chars` (`int`) デフォルト `500` 文字。これを超える値は差分出力で切り詰められます

#### `fe_csv_import_export_dry_run_diff_ttl`

Dry Run 差分 JSONL ファイルの保持期間をフィルターします。

**タイプ:** filter — **1.1.0 以降**

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_dry_run_diff_ttl', int $ttl): int
```

**パラメータ:**

- `$ttl` (`int`) 差分ファイルの保持秒数。デフォルト `7 * DAY_IN_SECONDS`

#### `fe_csv_import_export_import_temp_ttl`

アップロードされたインポート CSV 一時ファイルの保持期間をフィルターします。

**タイプ:** filter — **1.1.0 以降**

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_import_temp_ttl', int $ttl): int
```

**パラメータ:**

- `$ttl` (`int`) アップロードファイルの保持秒数。デフォルト `30 * DAY_IN_SECONDS`。放棄されたインポートセッションの残存ファイルのみがこの期間に達します。完了したインポートは最終バッチでアップロードを削除します

### フェーズ化インポートアクション

インポートは `do_action` によるフェーズモデルを公開しています。可観測性と連携用途を想定しています。

#### `fe_csv_import_export_import_phase_normalize`

**タイプ:** action

**シグネチャ:**

```php
do_action('fe_csv_import_export_import_phase_normalize', array $filtered_data, array $context): void
```

#### `fe_csv_import_export_import_phase_validate`

**タイプ:** action

**シグネチャ:**

```php
do_action('fe_csv_import_export_import_phase_validate', array $row_validation, array $row_context, array $context): void
```

#### `fe_csv_import_export_import_phase_map`

**タイプ:** action

**シグネチャ:**

```php
do_action('fe_csv_import_export_import_phase_map', array $collected_fields, array $headers, array $data): void
```

#### `fe_csv_import_export_import_phase_map_prepared`

フィールドマッピングが準備された後に発火されるアクション。

**タイプ:** action

**シグネチャ:**

```php
do_action('fe_csv_import_export_import_phase_map_prepared', int $post_id, array $prepared_meta_fields, array $prepare_args): void
```

#### `fe_csv_import_export_import_phase_post_persist`

投稿データが永続化された後に発火されるアクション。

**タイプ:** action

**シグネチャ:**

```php
do_action('fe_csv_import_export_import_phase_post_persist', int $post_id, array $prepared_meta_fields, array $prepare_args): void
```

---

## 管理/UI フック

#### `fe_csv_import_export_settings_tabs`

設定タブのレンダリング時に発火されるアクション。

**タイプ:** action

**シグネチャ:**

```php
do_action('fe_csv_import_export_settings_tabs', string $tab): void
```

#### `fe_csv_import_export_settings_tabs_content`

タブコンテンツのレンダリング時に発火されるアクション。

**タイプ:** action

**シグネチャ:**

```php
do_action('fe_csv_import_export_settings_tabs_content', string $tab, array $import_results): void
```

#### `fe_csv_import_export_after_import_settings_fields` / `fe_csv_import_export_after_export_settings_fields` / `fe_csv_import_export_after_advanced_settings_fields` / `fe_csv_import_export_after_license_settings_fields`

各設定パネル内で発火し、拡張機能が独自のフィールドを追加できるアクション。

**タイプ:** action

**シグネチャ:**

```php
do_action('fe_csv_import_export_after_import_settings_fields', object $admin): void
do_action('fe_csv_import_export_after_export_settings_fields', object $admin): void
do_action('fe_csv_import_export_after_advanced_settings_fields', object $admin): void
do_action('fe_csv_import_export_after_license_settings_fields', object $admin): void
```

**パラメータ:**

- `$admin` (`object`) 管理インスタンス（`FE_CSV_Import_Export_Admin`）

#### `fe_csv_import_export_after_export_button`

エクスポート設定パネル内、エクスポート実行ボタンの直後に発火されるアクション。

**タイプ:** action

**シグネチャ:**

```php
do_action('fe_csv_import_export_after_export_button'): void
```

#### `fe_csv_import_export_after_import_log_panels`

インポートログパネルの後に発火し、追加の結果パネルを描画できるアクション。

**タイプ:** action — **1.1.0 以降**

**シグネチャ:**

```php
do_action('fe_csv_import_export_after_import_log_panels'): void
```

組み込みの **Diff** パネルの直後に発火するため、拡張機能はその横に追加の結果パネルを描画できます。

#### `fe_csv_import_export_export_form_action`

エクスポートフォームのアクション URL をフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_export_form_action', string $action_url): string
```

**パラメータ:**

- `$action_url` (`string`) デフォルトは空（現在のページを使用）

**例:** (カスタムハンドラにリダイレクト)

```php
add_filter('fe_csv_import_export_export_form_action', 'my_swiftcsv_custom_export_handler', 10, 1);

function my_swiftcsv_custom_export_handler($action_url) {
    return 'https://my-api.com/handle-fe-csv-import-export-export';
}
```

**⚠️ セキュリティ注意:** ご注意ください。外部エンドポイントへのリダイレクトは機密データを露出する可能性があります。カスタムハンドラを実装する際は、適切な認証、HTTPS、アクセス制御を確保してください。

**安全な実装例:**

```php
add_filter('fe_csv_import_export_export_form_action', 'secure_export_handler', 10, 1);

function secure_export_handler($action_url) {
    // 管理権限を持つユーザーのみ許可
    if (!current_user_can('manage_options')) {
        return $action_url; // 権限が不十分な場合は元の URL を返す
    }

    // セキュリティ検証用の nonce を生成
    $nonce = wp_create_nonce('secure_export_' . get_current_user_id());

    return home_url("/secure-export?nonce={$nonce}&user_id=" . get_current_user_id());
}
```

#### `fe_csv_import_export_tools_page_capability`

FE CSV Import & Export 管理ページにアクセスするために必要な権限をフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_tools_page_capability', string $capability): string
```

**パラメータ:**

- `$capability` (`string`) デフォルト `manage_options`

**例:** (編集者がアクセスできるようにする)

```php
add_filter('fe_csv_import_export_tools_page_capability', 'my_swiftcsv_tools_capability', 10, 1);

function my_swiftcsv_tools_capability($capability) {
    return 'edit_posts'; // 投稿を編集できるすべてのユーザーを許可
}
```

---

## 機能フラグ / 診断

#### `fe_csv_import_export_enable_direct_sql_import`

Direct SQL インポートを有効にする機能フラグ。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_enable_direct_sql_import', bool $enabled): bool
```

#### `fe_csv_import_export_max_log_entries`

保存/表示されるログエントリ数を制御します。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_max_log_entries', int $max_entries): int
```

#### `fe_csv_import_export_user_can_export`

エクスポートを実行するユーザー権限をフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_user_can_export', bool $can_export): bool
```

**パラメータ:**

- `$can_export` (`bool`) `current_user_can('export')` に基づくデフォルト権限

**例:** (カスタム権限を要求)

```php
add_filter('fe_csv_import_export_user_can_export', 'my_swiftcsv_export_permission', 10, 1);

function my_swiftcsv_export_permission($can_export) {
    return current_user_can('manage_options') || current_user_can('export_csv');
}
```

#### `fe_csv_import_export_pre_ajax_export`

エクスポート前検証結果をフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('fe_csv_import_export_pre_ajax_export', bool|WP_Error $result, array $post_data): bool|WP_Error
```

**パラメータ:**

- `$result` (`bool|WP_Error`) 検証結果
- `$post_data` (`array`) エクスポートリクエストからの POST データ

**例:** (エクスポート回数制限を検証)

```php
add_filter('fe_csv_import_export_pre_ajax_export', 'my_swiftcsv_export_limits', 10, 2);

function my_swiftcsv_export_limits($result, $post_data) {
    $user_id = get_current_user_id();
    $today_exports = get_transient('fe_csv_import_export_exports_' . $user_id) ?: 0;

    if ($today_exports >= 10) {
        return new WP_Error('daily_limit', 'Daily export limit reached (10 exports per day)');
    }

    return $result;
}
```

---

## ベストプラクティス

### 開発ガイドライン

1. **英語コメントのみ**: フック実装では英語コメントを使用してください
2. **エラーハンドリング**: 常に適切なエラーハンドリングを実装してください
3. **パフォーマンス**: 大量データ処理ではバッチ処理を考慮してください
4. **セキュリティ**: ユーザー権限とデータ検証を常に確認してください

### 一般的なパターン

- **エクスポート**: ヘッダー生成 → 行処理 → バッチ処理
- **インポート**: 検証 → フィールドマッピング → 投稿永続化 → 後処理
- **UI**: 設定タブ → フォーム処理 → 権限チェック

### デバッグ

開発中は `WP_DEBUG` を有効にして、詳細なログ情報を取得してください。
