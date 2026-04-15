# JSDW AI Chat — systems check reference

Operational notes for this repo: what WordPress actually loads, which settings gate behavior, and where to look when something seems broken.

## 1. Deploy path and source tree

- **WordPress must load the plugin from** `jsdw-ai-chat/` (entrypoint: `jsdw-ai-chat/jsdw-ai-chat.php`). That directory is the **only** plugin source in this repo; do not point `wp-content/plugins` at the repository root.
- Historical **mirror** copies at the repo root (`admin/`, `includes/`, `public/`) were removed to avoid drift. All edits belong under `jsdw-ai-chat/`.

## 2. Settings and feature gates (`jsdw_ai_chat_settings`)

| Symptom | Check in Settings / code path |
|--------|----------------------------------|
| Widget shows “public endpoint disabled” | `chat.allow_public_query_endpoint` must be enabled. See `JSDW_AI_Chat_Widget_Renderer::resolve_runtime_mode()` and REST `chat_query_permission`. |
| Conversations / agent join / reply errors | Both `privacy.store_conversations` and `features.enable_chat_storage` must be true. `JSDW_AI_Chat_Chat_Service::chat_storage_enabled()` gates agent REST routes. |
| Phrase assist never runs | `chat.allow_ai_phrase_assist` plus answer mode that allows phrase assist; provider must be **OpenAI** with a valid key for current implementation. Non-OpenAI providers are not implemented for phrase assist (Settings shows warnings). |
| “No match” / generic fallbacks | Normal if the knowledge index is empty or query does not match chunks; see section 4. Canned copy is resolved via `JSDW_AI_Chat_Fallback_Responses` + `JSDW_AI_Chat_Canned_Responses` (`chat.answer_style`, `chat.canned_responses`). |

**Defaults (from `class-settings.php`):** `allow_public_query_endpoint` and `allow_ai_phrase_assist` default to **false**; `store_conversations` / `enable_chat_storage` default to **true**.

## 3. Capabilities (permissions)

Custom capabilities (added to the **Administrator** role on plugin activation):

- `manage_ai_chat_widget` — Dashboard, System Info, general access.
- `manage_ai_chat_widget_settings` — Settings, Design Studio.
- `manage_ai_chat_widget_index` — Sources.
- `manage_ai_chat_widget_logs` — Jobs & Logs.
- `manage_ai_chat_widget_conversations` — Conversations inbox.

Non-admin users need these caps assigned explicitly (`class-capabilities.php`).

## 4. Indexing and “wrong / empty” answers

Trace in order:

1. **Sources** (`admin` → Sources): source status (disabled / missing / content not OK blocks knowledge).
2. **Jobs & Logs**: queue jobs, failures, cron (`class-cron.php`, `class-queue.php`).
3. **Retrieval**: chunks and facts in DB; `JSDW_AI_Chat_Knowledge_Retriever` + answer pipeline.

Poor answers are often **data / indexing state**, not a single broken toggle.

## 5. Design Studio preview vs production

- The right-hand **preview is simulated** for viewport width, hide-on-page overlays, logged-in badge on a mock URL, open-trigger timing hints, etc. See `PHASE8_DELIVERABLE.md` / `PHASE8_1_DELIVERABLE.md`.
- **Always validate** appearance and behavior on a **real front-end page** after Save.

## 6. Agent handoff (Phase 8.3.1)

- Opening a conversation does **not** connect the agent; use **Join conversation** before **Send**.
- REST returns **409** if `agent-reply` is called while `agent_connected` is false.

## 7. Documentation QA status

Phase deliverable markdown files contain **unchecked** manual validation boxes (`[ ]`); that means checklists were not formally completed in-repo, not that features are missing.
