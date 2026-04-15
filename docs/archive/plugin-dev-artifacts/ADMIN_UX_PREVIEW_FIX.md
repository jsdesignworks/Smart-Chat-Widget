# ADMIN_UX_PREVIEW_FIX

Deliverable for **Admin UX and Design Studio preview correction** (presentation-only; no retrieval/answer/knowledge logic changes).

## Files changed

| Area | Files |
|------|--------|
| Design Studio preview | `admin/js/design-studio.js`, `admin/css/design-studio.css`, `admin/views/page-design-studio.php` |
| Settings save + UI | `admin/class-admin.php`, `admin/views/page-settings.php` |
| Admin lists / health | `admin/views/page-system-info.php`, `admin/views/page-sources.php`, `admin/views/page-jobs.php`, `admin/views/page-conversations.php` |
| Mirror | Same paths under `jsdw-ai-chat/` |

## Scroll jump: root cause and fix order

1. **Cause:** The preview rebuilt the entire message column on each change (`innerHTML` / full remount), causing layout reflow and perceived viewport jumps. Toggle controls used hidden checkboxes inside labels, so focus could trigger scroll-into-view behavior.
2. **Fix order (as implemented):**
   - **First:** Incremental DOM updates — stable nodes for welcome, sample user line, typing row; update visibility/text/classes only.
   - **Second:** On toggle `change`, sync state to `S`, `blur()` the input, run preview sync, then `requestAnimationFrame` + `window.scrollTo` with the previous `scrollY` as a guard.
   - **Third:** Scroll restoration only as a last-resort fallback (same rAF path).

## Preview wiring (single source of truth)

- Global **`S`** holds widget design toggles and strings used by the simulated preview.
- Control handlers update **`S`** then call preview render; **`collectSettings()`** for REST save builds from **`S`** (and color/range inputs where those values live), not divergent DOM reads.
- **Launcher label:** Read-only in Design Studio; value comes from localized **`widget_ui.launcher_label`** (same option as production). No duplicate field under `widget_design`.
- **Sound:** Indicator only from **`S.soundEnabled`** (no audio playback).

## Admin pages

- **Settings:** Grouped postboxes for existing keys; POST merges `$_POST['jsdw']` via `merge_settings_from_request()` → `JSDW_AI_Chat_Settings::sanitize_settings()` → `update_option( JSDW_AI_CHAT_OPTION_SETTINGS, … )`.
- **System info:** Health report rendered as sections (tables / key–value), no raw JSON blobs.
- **Sources / Jobs:** Count summaries as tables; jobs page includes queue summary + discovery queue counts table.
- **Conversations:** `mysql2date`-style display for datetimes; **status** on the list when the row includes `status` (`c.*`); message columns for **answer_status** / **confidence_score** only when those keys exist on loaded message rows (empty shown as **—**, not fabricated values).

## Validation checklist

- [ ] Design Studio toggles: no viewport jump; preview reflects **`S`** only.
- [ ] Launcher label in preview matches **Settings → Widget UI → Launcher label** (`widget_ui.launcher_label`).
- [ ] Settings save persists and respects **`sanitize_settings()`**.
- [ ] Admin pages: no raw `wp_json_encode` dumps in `<pre>` for these screens; values match underlying arrays/DB.
- [ ] Conversations: no inferred columns; timestamps readable.

## Mirror

The `jsdw-ai-chat/` directory is kept in sync with the above admin assets for packaging or distribution.
