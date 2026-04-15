# Phase 5 Deliverable: Grounded Answer Engine and Controlled Response Pipeline

## What was implemented

- Added a local-first answer pipeline with strict state semantics:
  - `confidence` = evidence assessment (`CONF_*`).
  - `answer_status` = final outcome.
  - `answer_type` = response form.
  - `answer_strategy` = production method.
- Added a single stored evidence payload in `messages.source_snapshot_json`.
- Added lightweight query guard (empty/min/max + transient throttle).
- Added new chat routes with `/chat/query` disabled publicly by default behind `chat.allow_public_query_endpoint`.
- Added admin-only `/chat/query-debug`.
- Added read-only admin Conversations UI.
- Added answer state metrics in health report.

## Schema

- `JSDW_AI_CHAT_DB_SCHEMA_VERSION` bumped to `1.4.0`.
- `messages` table additions:
  - `answer_status` (`varchar(50)`, nullable)
  - `answer_type` (`varchar(50)`, nullable)
- `source_snapshot_json` remains the canonical stored evidence/trace field.

## Snapshot JSON contract

Stored in `messages.source_snapshot_json`:

```json
{
  "query": "...",
  "normalized_query": "...",
  "retrieval_stats": {},
  "confidence": "...",
  "answer_status": "...",
  "sources": [],
  "chunks": [],
  "facts": [],
  "trace": {},
  "meta": {
    "engine_version": "1.4.0",
    "timestamp": "..."
  }
}
```

## Core pipeline classes

- `includes/class-answer-constants.php`
- `includes/class-answer-status-mapper.php`
- `includes/class-answer-policy.php`
- `includes/class-fallback-responses.php`
- `includes/class-local-answer-builder.php`
- `includes/class-ai-phrase-assist.php` (stub only)
- `includes/class-answer-trace.php`
- `includes/class-answer-formatter.php`
- `includes/class-query-guard.php`
- `includes/class-answer-engine.php`
- `includes/class-conversation-service.php`
- `includes/class-chat-service.php`

## Centralized mapping

`JSDW_AI_Chat_Answer_Status_Mapper::map_confidence_to_answer_status()` is the single mapping point from confidence and guard outcomes into `answer_status`.

Default mapping:

- `CONF_ANSWERABLE_LOCALLY` -> `answered_locally`
- `CONF_LOW_CONFIDENCE` -> `low_confidence`
- `CONF_NO_MATCH` -> `no_match`
- `CONF_REQUIRES_CLARIFICATION` -> `requires_clarification`
- guard rejected -> `guard_rejected` (precedence)

## REST routes

Under `ai-chat-widget/v1`:

- `POST /chat/query` (feature-gated public access)
- `POST /chat/query-debug` (admin only)
- `GET /chat/conversations` (admin conversation capability)
- `GET /chat/conversations/{id}` (admin conversation capability)
- `GET /chat/conversations/{id}/messages` (admin conversation capability)

`JSDW_AI_Chat_REST` delegates chat responses through `JSDW_AI_Chat_Chat_Service` + formatter; no retrieval-debug duplication inside route callbacks.

## Conversation identity model

- Anonymous users always resolve to a namespaced session key (`a:...`).
- Logged-in users use namespaced user/session keys (`u:{user_id}:...`).
- Keys are normalized to avoid collisions and cross-user mixing.

## AI phrase assist behavior

- Kept as scaffold/stub and disabled by default.
- Never adds new facts.
- Only eligible when confidence is `CONF_ANSWERABLE_LOCALLY`.

## Health and logging

- Added `answer_state` in health report:
  - `conversations`
  - `messages`
  - `allow_public_query_endpoint`
  - `allow_ai_phrase_assist`
  - `answer_mode`
  - `last_answer_request`
- Added chat/answer lifecycle logging (`chat_query_received`, `query_guard_rejected`, `answer_engine_completed`).

## Admin UI

- Added read-only Conversations page:
  - conversation list
  - per-conversation message inspection
- No reply/edit/moderation actions in Phase 5.

## Mirroring

All touched files were mirrored under `jsdw-ai-chat/` to match repo conventions.
