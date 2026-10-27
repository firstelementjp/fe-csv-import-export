#!/usr/bin/env bash
#
# Creates draft release-announcement posts on the company WordPress site.
#
# Creates the Japanese draft first (when a JA changelog exists), then the
# English draft linked via the Polylang `translations` field. With Polylang
# Pro the two posts are linked automatically; with the free Polylang plugin
# the fields are ignored and the posts must be linked manually.
#
# Required env: WP_BASE_URL, WP_USERNAME, WP_APP_PASSWORD, PLUGIN_NAME,
#               GITHUB_REF_NAME, GITHUB_REPOSITORY
# Optional env: WP_CATEGORY_ID (fallback for both languages),
#               WP_JA_CATEGORY_ID, WP_EN_CATEGORY_ID (per-language term IDs)
#
# Post titles are "<fixed prefix> <tag> <suffix>" with an optional
# release highlight appended after a pipe. Highlights are read from
# single-line files created during release prep:
#   .github/announce/<tag>.ja.txt
#   .github/announce/<tag>.en.txt
# The pipe and highlight are omitted when the file is absent.
set -euo pipefail

VERSION="${GITHUB_REF_NAME#v}"
RELEASE_URL="https://github.com/${GITHUB_REPOSITORY}/releases/tag/${GITHUB_REF_NAME}"
API_URL="${WP_BASE_URL%/}/wp-json/wp/v2/posts"
ANNOUNCE_DIR=".github/announce"

# Prints the changelog body for VERSION from the given changes file.
# Supports both "## X.Y.Z" and "## [X.Y.Z]" heading styles.
extract_section() {
    awk -v ver="${VERSION}" '
        index($0, "## " ver " ") == 1 || index($0, "## [" ver "]") == 1 { flag = 1; next }
        /^## / && flag { exit }
        flag { print }
    ' "$1"
}

# Converts Markdown on stdin-argument to HTML using marked.
md_to_html() {
    local tmp
    tmp="$(mktemp)"
    printf '%s\n' "$1" >"${tmp}"
    npx --yes marked@15 "${tmp}"
    rm -f "${tmp}"
}

# Creates a draft post and prints the API response JSON.
# Args: title content lang [translations-json] [category-term-id]
create_draft() {
    local title="$1" content="$2" lang="$3" translations="${4:-null}"
    local category="${5:-${WP_CATEGORY_ID:-null}}"
    local payload
    payload="$(jq -n \
        --arg title "${title}" \
        --arg content "${content}" \
        --arg lang "${lang}" \
        --argjson category "${category}" \
        --argjson translations "${translations}" \
        '{
            title: $title,
            content: $content,
            status: "draft",
            lang: $lang
        }
        + (if $category then { categories: [ $category ] } else {} end)
        + (if $translations then { translations: $translations } else {} end)')"

    curl -sfS -u "${WP_USERNAME}:${WP_APP_PASSWORD}" \
        -X POST "${API_URL}" \
        -H "Content-Type: application/json" \
        -d "${payload}"
}

JA_POST_ID=""
if [ -f "docs/ja/changes.md" ]; then
    JA_NOTES_MD="$(extract_section docs/ja/changes.md)"
    if [ -n "${JA_NOTES_MD}" ]; then
        JA_NOTES_HTML="$(md_to_html "${JA_NOTES_MD}")"
        JA_TITLE="WordPress向けCSVインポート・エクスポート「${PLUGIN_NAME}」${GITHUB_REF_NAME} 提供開始"
        if [ -f "${ANNOUNCE_DIR}/${GITHUB_REF_NAME}.ja.txt" ]; then
            JA_TITLE="${JA_TITLE}｜$(head -n 1 "${ANNOUNCE_DIR}/${GITHUB_REF_NAME}.ja.txt")"
        fi
        JA_CONTENT="<p>${PLUGIN_NAME} ${VERSION} をリリースしました。主な変更点は以下の通りです。</p>${JA_NOTES_HTML}<p>ダウンロード: <a href=\"${RELEASE_URL}\">GitHub Releases</a></p>"
        JA_POST_ID="$(create_draft "${JA_TITLE}" "${JA_CONTENT}" "ja" "null" "${WP_JA_CATEGORY_ID:-}" | jq -r '.id // empty')"
        echo "Japanese draft created: id=${JA_POST_ID:-unknown}"
    else
        echo "::warning::No changelog section found in docs/ja/changes.md for ${VERSION}"
    fi
else
    echo "No docs/ja/changes.md; skipping Japanese draft."
fi

EN_NOTES_MD="$(extract_section docs/changes.md)"
if [ -z "${EN_NOTES_MD}" ]; then
    echo "::warning::No changelog section found in docs/changes.md for ${VERSION}; skipping English draft."
    exit 0
fi
EN_NOTES_HTML="$(md_to_html "${EN_NOTES_MD}")"
EN_TITLE="${PLUGIN_NAME} ${GITHUB_REF_NAME} Released"
EN_TITLE_FILE="${ANNOUNCE_DIR}/${GITHUB_REF_NAME}.en.txt"
if [ -f "${EN_TITLE_FILE}" ]; then
    EN_TITLE="${EN_TITLE} | $(head -n 1 "${EN_TITLE_FILE}")"
fi
EN_CONTENT="<p>${PLUGIN_NAME} ${VERSION} is now available. Highlights:</p>${EN_NOTES_HTML}<p>Download: <a href=\"${RELEASE_URL}\">GitHub Releases</a></p>"

TRANSLATIONS="null"
if [ -n "${JA_POST_ID}" ]; then
    TRANSLATIONS="{\"ja\": ${JA_POST_ID}}"
fi
EN_POST_ID="$(create_draft "${EN_TITLE}" "${EN_CONTENT}" "en" "${TRANSLATIONS}" "${WP_EN_CATEGORY_ID:-}" | jq -r '.id // empty')"
echo "English draft created: id=${EN_POST_ID:-unknown}"

if [ -n "${JA_POST_ID}" ]; then
    echo "JA/EN posts were linked via the translations field (Polylang Pro required; link manually otherwise)."
fi
