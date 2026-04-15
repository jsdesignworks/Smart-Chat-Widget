# Phase 4 Deliverable: Local Knowledge Preparation and Retrieval Foundation

This document summarizes what was implemented for schema **1.3.0**, deterministic chunking and fact extraction, keyword retrieval, admin/REST/cron integration, and explicit non-goals (no widget UI, embeddings, or LLM answers).

## Files touched (primary plugin root)

- `jsdw-ai-chat.php` — DB schema version `1.3.0`, option `JSDW_AI_CHAT_OPTION_LAST_KNOWLEDGE_VERIFICATION`, require order for Phase 4 classes.
- `includes/class-db.php` — Extended `sources`, `chunks`, `facts` tables; indexes for knowledge queries.
- `includes/class-migrations.php` — Existing `maybe_migrate()` calls `install_tables()` / dbDelta when stored schema is below target (adds new columns on upgrade).
- `includes/class-knowledge-constants.php` — Knowledge processing, chunk/fact lifecycle, retrieval hit kinds, confidence enums.
- `includes/class-chunk-repository.php`, `includes/class-fact-repository.php` — Persistence, retire/supersede, search helpers, counts.
- `includes/class-content-chunker.php`, `includes/class-fact-extractor.php` — Deterministic chunking; conservative regex-only facts.
- `includes/class-source-knowledge-processor.php` — Orchestrates build → normalize → chunk → persist chunks → facts → `update_knowledge_state()`.
- `includes/class-query-normalizer.php`, `includes/class-knowledge-retriever.php`, `includes/class-answer-context-builder.php`, `includes/class-confidence-policy.php` — Retrieval stack.
- `includes/class-source-repository.php` — Knowledge state updates and aggregations (`fetch_stale_knowledge_sources`, status counts, etc.).
- `includes/class-source-content-processor.php` — After OK content update, queues `source_knowledge_process` when appropriate (skips when `content_no_change` and chunks already exist for version).
- `includes/class-job-repository.php` — Knowledge-related job type constants.
- `includes/class-queue.php` — `get_knowledge_queue_counts()`.
- `includes/class-cron.php` — Knowledge hooks, queue runner dispatch, verification/refresh handlers.
- `includes/class-plugin.php` — Wires dependencies (admin gets chunk/fact repos).
- `includes/class-rest.php` — Knowledge and retrieval REST routes (admin capability).
- `includes/class-health.php` — `knowledge_state` in health report.
- `includes/class-uninstaller.php` — Deletes `JSDW_AI_CHAT_OPTION_LAST_KNOWLEDGE_VERIFICATION` on cleanup uninstall.
- `admin/class-admin.php`, `admin/views/page-sources.php` — Knowledge summary and per-source columns.

A mirrored copy of the plugin lives under `jsdw-ai-chat/` and should match the root plugin files for deployment or packaging.

## Schema diff (1.3.0)

**`sources`**

- `knowledge_processing_status`, `knowledge_processing_reason`, `last_knowledge_processing_gmt`, `knowledge_headings_json` (cached headings from last successful knowledge run).

**`chunks`**

- `source_content_version`, `chunk_hash`, `chunk_status`, `chunk_reason`, `token_estimate`, `position_start`, `position_end`, `superseded_at`, `superseded_by_chunk_id` (plus indexes on version/status/active).

**`facts`**

- `source_content_version`, `fact_status`, `fact_reason`, `superseded_at` (plus indexes on source/version/status).

**Options**

- `jsdw_ai_chat_last_knowledge_verification` — last batch verification timestamp (cron).

## Job types (`JSDW_AI_Chat_Job_Repository`)

- `source_knowledge_process`, `source_knowledge_process_batch`
- `source_fact_extract`, `source_fact_refresh` (reserved; fact work runs inside knowledge processor unless split later)
- `source_knowledge_verify`, `source_knowledge_refresh`

## Cron hooks (`JSDW_AI_Chat_Cron`)

- `HOOK_KNOWLEDGE_VERIFY` — `jsdw_ai_chat_knowledge_verification` (daily): queues batch knowledge verification.
- `HOOK_KNOWLEDGE_REFRESH` — `jsdw_ai_chat_knowledge_refresh` (weekly): stale knowledge refresh.
- `HOOK_QUEUE_RUN` processes pending jobs including knowledge types when dispatched from `handle_queue_runner`.

## REST routes (`ai-chat-widget/v1`, admin permission)

| Method | Route | Purpose |
|--------|--------|---------|
| GET | `/sources/{id}/chunks` | Active chunks for source |
| GET | `/sources/{id}/facts` | Active facts for source |
| POST | `/sources/process-knowledge` | Body: `{ "source_ids": [1,2] }` — queue knowledge jobs |
| POST | `/sources/process-knowledge-single` | Body: `{ "source_id": 1 }` — queue single knowledge job |
| POST | `/retrieval/test` | Body: `{ "query": "..." }` — returns hits, context summary, confidence (no LLM text) |

## Chunking strategy (summary)

- Input: normalized package with `title`, `body`, `headings`, `source_id`, `content_version`.
- Boundaries: paragraph groups (`\n\n`), min/max size and optional overlap per `class-content-chunker.php` header.
- Output: DTOs with stable `chunk_hash`, section/heading when mappable, `token_estimate`, positions when defined.

## Fact extraction scope and limitations

- Conservative: titles, headings, URLs/emails/phones via regex, FAQ `Q:`/`A:` blocks, hours with strict patterns.
- No inference or semantic extraction.

## Ranking rules (`class-knowledge-retriever.php`)

- Keyword search over active chunks and facts; deterministic sort by score.
- Weights: source title match, then heading, then section, then body; fact exact key/value boost; slight preference for higher `source_content_version`; inactive sources excluded at SQL level where applicable.

## Confidence policy (`class-confidence-policy.php`)

- Inputs: `hit_count`, `best_score`, `ambiguous`, `has_title_hit` (reserved for future tuning).
- Outcomes map to `JSDW_AI_Chat_Knowledge_Constants::CONF_*`: e.g. no match, clarification, answerable locally, low confidence, requires future AI assist.

## Lifecycle rules

- **Chunks**: new version supersedes prior active rows (retired/superseded status, `is_active` cleared).
- **Facts**: retired when replaced by a new content version for the same source.

## Logger event types (non-exhaustive)

Use `JSDW_AI_Chat_Logger` with event strings such as: `knowledge_processing_started`, `knowledge_processing_finished`, `chunk_generation_failed`, `fact_extraction_failed`, `retrieval_test_executed` (see implementations for exact strings).

## Verification checklist

- [ ] Migrate existing site: confirm `wp_options` schema version becomes `1.3.0` and new columns exist.
- [ ] Process content for a source, then run knowledge (manual job or REST); confirm chunks/facts rows and `knowledge_processing_status`.
- [ ] `GET` chunks/facts REST returns JSON for that source.
- [ ] `POST /retrieval/test` returns hits + context + confidence without generating answers.
- [ ] Health report includes `knowledge_state`.
- [ ] Sources admin page shows knowledge columns and counts.
- [ ] Uninstall with cleanup removes knowledge verification option.

## Non-goals (confirmed)

- No changes to `public/widget.js` / `public/widget.css`.
- No embeddings, vector tables, or semantic search.
- No LLM calls or public answer endpoint in this phase.
