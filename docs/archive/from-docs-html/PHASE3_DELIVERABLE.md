# Phase 3 deliverable: Normalized content and change detection

## Schema (1.2.0)

- `JSDW_AI_CHAT_DB_SCHEMA_VERSION` set to **1.2.0** in `jsdw-ai-chat.php` (mirrored under `jsdw-ai-chat/`).
- `sources` table extended via `dbDelta` with snapshots (`raw_snapshot_text`, `normalized_snapshot_text`), SHA-256 hash columns, content timestamps, `normalized_length`, `extraction_method`, `content_processing_status`, `content_processing_reason`, `material_content_change`, and indexes on `content_processing_status` and `last_content_check_gmt`.
- Content-processing statuses and content-level reason codes live on `JSDW_AI_Chat_DB` (separate from discovery `change_reason`).
- Option `JSDW_AI_CHAT_OPTION_LAST_CONTENT_VERIFICATION` added; uninstall cleanup wired when `cleanup_on_uninstall` is used.

## Pipeline classes

| Class | File | Role |
| --- | --- | --- |
| `JSDW_AI_Chat_Source_Content_Builder` | `includes/class-source-content-builder.php` | Loads content for post/page/cpt, taxonomy, menu, manual, and rendered URL sources; returns `ok`, `unsupported`, or `unavailable` with reason codes. |
| `JSDW_AI_Chat_Content_Normalizer` | `includes/class-content-normalizer.php` | Deterministic text normalization, script/style stripping from HTML before strip_tags, heading extraction for structure hash. |
| `JSDW_AI_Chat_Content_Fingerprint` | `includes/class-content-fingerprint.php` | SHA-256 hashes for title, body, structure (headings), metadata. |
| `JSDW_AI_Chat_Content_State_Comparator` | `includes/class-content-state-comparator.php` | Compares previous vs new hashes; baseline (no prior hashes), `no_change`, or `changed` with primary reason and material reindex flag. |
| `JSDW_AI_Chat_Source_Content_Processor` | `includes/class-source-content-processor.php` | Eligibility (active + rules), orchestration, capped snapshots, atomic `Source_Repository::update_content_state`, `needs_reindex` only on material change, `content_version` bump only when a tracked hash change occurs after a prior baseline (not on first fingerprint). |

## Repository

- `update_content_state()`, `fetch_sources_pending_content_processing()`, `fetch_stale_content_sources()`, `get_content_processing_status_counts()`, `get_material_content_change_counts()`, `get_manual_source_by_id()`.
- `upsert_source()` now returns `id` and `material_discovery_change` for post-discovery queueing.

## Jobs, queue, cron

- Job types: `source_content_process`, `source_content_process_batch`, `source_content_verify`, `source_content_refresh`.
- `JSDW_AI_Chat_Queue::get_content_queue_counts()` for health.
- `handle_queue_runner` dispatches discovery jobs as before and content jobs via `Source_Content_Processor`.
- Scheduled hooks: `jsdw_ai_chat_content_verification` (daily, queues verify job), `jsdw_ai_chat_content_refresh` (weekly, queues refresh job); included in `get_status` / `clear_events` / `schedule_events`.
- After discovery (`run_full_scan` / `run_single_post_scan`), `source_content_process` is queued when the source is allowed, active, and `material_discovery_change` is true.

## REST, admin, health, logging

- REST (admin): `GET /sources/{id}/content-state`, `POST /sources/process-content` (body: `source_ids`), `POST /sources/process-content-single` (body: `source_id`) — all queue jobs by default.
- Health report includes `content_state` (status counts, material counts, last verification option, content job queue counts).
- Sources admin page: content processing summary blocks and table columns for content status, content reason, and last content check.
- Logger event types used include: `content_pipeline_started`, `content_pipeline_completed`, `content_no_change`, `content_material_change`, `normalization_failed`, `content_unsupported`, `content_unavailable`, `content_queued`, `content_batch_completed`, `content_rules_blocked`, `content_skipped_inactive`, `content_verify_scheduled`, `content_refresh_scheduled`, `content_pipeline_missing_source`.

## Mirror and validation

- `jsdw-ai-chat/` tree synced with the same `includes/`, `admin/`, `jsdw-ai-chat.php`, and `uninstall.php`.
- `php -l` run on touched PHP files: no syntax errors.

## Verification checklist (manual)

1. Install or migrate to 1.2.0: new columns present; `db_schema_version` option is `1.2.0`.
2. Run content process on a post source: hashes filled, status `ok`, `needs_reindex` set only when title/content/structure change (not metadata-only).
3. Second run unchanged: `content_no_change`, no `content_version` bump.
4. Manual and unsupported/unavailable paths: structured outcome and logs, no silent success.
