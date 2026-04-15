# Phase 7.1 — Design Token System and CSS Foundation

## 1. Files created or modified

| File | Role |
|------|------|
| [admin/css/tokens-admin.css](admin/css/tokens-admin.css) | **New.** Full `--jsdw-*` palette for surfaces, text, actions, status, preview chrome; two modes on `body` |
| [admin/css/foundation-admin.css](admin/css/foundation-admin.css) | **New.** Typography/spacing/radius/motion roles + scoped base styles + overflow utilities |
| [admin/class-admin.php](admin/class-admin.php) | Enqueue chain, unified `admin_plugin_body_class`, `get_admin_ui_mode_slug()` |
| [admin/css/design-studio.css](admin/css/design-studio.css) | `--ds-*` one-way aliases from `--jsdw-*`; hex replaced with tokens where mapped |
| [public/css/tokens-widget.css](public/css/tokens-widget.css) | **New.** Widget-root `--jsdw-*` + `--chat-*` aliases + box model + utilities |
| [public/css/widget.css](public/css/widget.css) | Consumes tokens; removed duplicate root variable block |
| [public/class-widget-renderer.php](public/class-widget-renderer.php) | Enqueues `tokens-widget` before `widget` |
| [jsdw-ai-chat.php](jsdw-ai-chat.php) | `JSDW_AI_CHAT_USER_META_ADMIN_UI_MODE` constant |
| [jsdw-ai-chat/](jsdw-ai-chat/) | Mirrored copies of the above |

## 2. Token system structure

- **Single semantic namespace:** `--jsdw-color-*`, `--jsdw-type-*` (composite font stacks in foundation), `--jsdw-space-*`, `--jsdw-radius-*`, `--jsdw-motion-*`, `--jsdw-easing-standard`.
- **Authoritative hex** lives only in [admin/css/tokens-admin.css](admin/css/tokens-admin.css) (admin) and [public/css/tokens-widget.css](public/css/tokens-widget.css) (widget shell defaults).
- **Widget:** `--chat-*` are **aliases** to `--jsdw-*` so runtime/JS inline design overrides keep working.

## 3. Dual mode (admin)

- **Storage:** `user_meta` key `jsdw_ai_chat_admin_ui_mode` (`JSDW_AI_CHAT_USER_META_ADMIN_UI_MODE`) — values `warm-clay` or default **`dark-violet`**.
- **Application:** PHP adds `jsdw-ai-chat-admin` and `jsdw-ai-chat-admin--{mode}` on plugin admin screens only (`admin_plugin_body_class`). No `localStorage`. No AJAX toggle in this phase (change mode via `update_user_meta` or future UI).

## 4. CSS variables introduced (summary)

- **Admin modes:** Surfaces (app, sidebar, content, card, input, muted, active, slider), borders (shell, card, row), full text role set, actions (primary, secondary, save, toggles), code/log/status colors, preview mock (browser chrome, skeleton, traffic dots).
- **Foundation:** `--jsdw-type-page-title`, `--jsdw-space-content-padding-x`, `--jsdw-radius-card`, `--jsdw-motion-page-enter`, etc. (see [foundation-admin.css](admin/css/foundation-admin.css)).
- **Widget:** Semantic `--jsdw-color-*` for panel, bubbles, text, focus ring, errors, debug, typing dots; `--chat-*` aliases listed in [tokens-widget.css](public/css/tokens-widget.css).

## 5. Overflow utilities

- **Admin:** `body.jsdw-ai-chat-admin #wpbody-content .jsdw-u-ellipsis` / `.jsdw-u-break-word` in [foundation-admin.css](admin/css/foundation-admin.css).
- **Widget:** `#jsdw-ai-chat-widget .jsdw-u-ellipsis` / `.jsdw-u-break-word` in [tokens-widget.css](public/css/tokens-widget.css).
- **`table-layout: fixed`:** Not applied globally; deferred per plan.

## 6. CSS isolation

- Plugin admin styling requires **`body.jsdw-ai-chat-admin`** in selectors affecting `#wpbody-content`.
- Widget rules remain **`#jsdw-ai-chat-widget`**-scoped.
- Design Studio remains **`#jsdw-ai-chat-design-studio`**-scoped with `--ds-*` mapping into shared `--jsdw-*`.

## 7. Areas using token-based styling

- **All plugin admin screens:** background, postboxes, tables, buttons, notices, code (foundation).
- **Design Studio:** inherits body tokens; `--ds-*` aliases only.
- **Front-end widget:** root tokens + aliases; [widget.css](public/css/widget.css) uses `var(--jsdw-…)` for former hardcoded colors.

## 8. Deferred to later phases

- Polished admin mode switcher UI and AJAX save (optional).
- Full layout / component redesign, sidebar slider, page transitions.
- `table-layout: fixed` on specific wide tables once columns are defined.
- Public widget live theme switching (`data-jsdw-widget-theme`) — tokens are ready to extend.

## 9. Validation checklist

- [ ] Load any `jsdw-ai-chat-*` admin page: body has `jsdw-ai-chat-admin` and `jsdw-ai-chat-admin--dark-violet` (or warm if meta set).
- [ ] Non-plugin admin screens: no `jsdw-ai-chat-admin` class; no plugin token styling.
- [ ] Set `jsdw_ai_chat_admin_ui_mode` to `warm-clay` for your user: warm token set applies.
- [ ] Design Studio: layout unchanged; colors track mode.
- [ ] Front-end widget: unchanged behavior; chips, header, errors still render.
- [ ] No PHP notices; `php -l` clean on touched PHP files.
