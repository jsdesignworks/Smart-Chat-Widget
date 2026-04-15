# Phase 7.1 — Validation and hardening report

Audit-only (no Phase 7.2). No layout redesign; one enqueue-order hardening applied.

---

## 1. Admin mode class application

**Verified in code** ([admin/class-admin.php](admin/class-admin.php)):

- `admin_plugin_body_class()` runs on all admin requests but **returns unchanged `$classes`** when `is_plugin_admin_screen()` is false.
- `is_plugin_admin_screen()` requires `get_current_screen()` and `strpos( $screen->id, 'jsdw-ai-chat' ) !== false`. Typical WP screen IDs include `toplevel_page_jsdw-ai-chat-dashboard`, `jsdw-ai-chat-dashboard_page_jsdw-ai-chat-settings`, etc., all containing `jsdw-ai-chat`.
- On plugin screens, the filter appends exactly:
  - `jsdw-ai-chat-admin`
  - **one** of `jsdw-ai-chat-admin--dark-violet` or `jsdw-ai-chat-admin--warm-clay` (from `get_admin_ui_mode_slug()` → user_meta `jsdw_ai_chat_admin_ui_mode`, default `dark-violet`).
- Design Studio additionally gets `jsdw-ai-chat-design-studio-admin` when the screen id contains `jsdw-ai-chat-design-studio`.

**Non-plugin screens:** No plugin classes are added when the screen id does not contain `jsdw-ai-chat` (e.g. core Dashboard, other plugins), so **no plugin mode classes** leak globally.

---

## 2. Asset dependency and load order

**Admin (priority 5 then 10):**

1. `enqueue_admin_foundation_assets` (priority **5**): registers `jsdw-ai-chat-admin-tokens` (no deps) → `jsdw-ai-chat-admin-foundation` (depends on tokens).
2. `enqueue_design_studio_assets` (priority **10**): registers fonts → `jsdw-ai-chat-design-studio` (depends on **foundation + fonts**).

**Hardening applied:** Google Fonts are now registered **before** `design-studio.css` and listed as a **dependency** of `jsdw-ai-chat-design-studio`, so font CSS is guaranteed to load before Design Studio rules (previously fonts were enqueued after without a dependency link).

**Widget** ([public/class-widget-renderer.php](public/class-widget-renderer.php)):

- `jsdw-ai-chat-widget-tokens` → `jsdw-ai-chat-widget` with `array( 'jsdw-ai-chat-widget-tokens' )` dependency. **Tokens load before widget.css.**

---

## 3. CSS isolation

**Admin:** [admin/css/foundation-admin.css](admin/css/foundation-admin.css) rules use **`body.jsdw-ai-chat-admin`** as the first selector segment (plus `#wpbody-content` where needed). Token values live in [admin/css/tokens-admin.css](admin/css/tokens-admin.css) under **`body.jsdw-ai-chat-admin.jsdw-ai-chat-admin--{mode}`**. No plugin admin CSS targets bare `#wpbody-content` without the body guard.

**Widget:** [public/css/tokens-widget.css](public/css/tokens-widget.css) and [public/css/widget.css](public/css/widget.css) selectors are prefixed with **`#jsdw-ai-chat-widget`** (or descendants under that root).

---

## 4. Widget compatibility (`--chat-*`)

**Verified:** [public/css/tokens-widget.css](public/css/tokens-widget.css) defines `--chat-primary`, `--chat-bg`, `--chat-bot-bubble`, dimensions, and `--jsdw-anim-speed` as **aliases** to `--jsdw-*`. Runtime JS that sets inline `--chat-*` on the widget root continues to override appearance as before; unset properties still resolve through the alias chain.

**Residual non-token colors in widget.css:** Only **rgba()** used for shadows, glass (header avatar, reset), and debug panel tint — acceptable as **effects**, not palette tokens for Phase 7.1.

---

## 5. Design Studio compatibility (`--ds-*`)

**Verified:** [admin/css/design-studio.css](admin/css/design-studio.css) opens with `--ds-*: var(--jsdw-*)` mappings on `#jsdw-ai-chat-design-studio`. Preview and layout use `var(--ds-*)` or shared `--jsdw-*` / `--chat-*` for the embedded preview widget. Tokens resolve from **`body.jsdw-ai-chat-admin`** + mode, so preview tracks admin mode.

---

## 6. Hardcoded color audit (outside token files)

| Location | Finding |
|----------|---------|
| [admin/css/tokens-admin.css](admin/css/tokens-admin.css) | **Canonical hex** — expected. |
| [public/css/tokens-widget.css](public/css/tokens-widget.css) | **Canonical hex/rgba** for widget defaults — expected. |
| [public/css/widget.css](public/css/widget.css) | **rgba only** for shadows, white overlays, subtle backgrounds — **intentional** (elevation/glass); not migrated to named tokens in 7.1. |
| [admin/css/design-studio.css](admin/css/design-studio.css) | **No standalone `#RRGGBB`** in property values** — colors go through `var(--ds-*)` / `var(--jsdw-*)` / `rgba(...)` for grid/gradients. |
| [admin/css/foundation-admin.css](admin/css/foundation-admin.css) | **No hex** — uses `var(--jsdw-*)` only. |

**Accidental leftovers:** None requiring immediate token migration without expanding scope; effect-level rgba in widget.css is documented as intentional.

---

## Validation checklist (Phase 7.1 hardening)

- [x] Plugin admin screens: `jsdw-ai-chat-admin` + exactly one of `--dark-violet` / `--warm-clay`; Design Studio adds `jsdw-ai-chat-design-studio-admin` when applicable.
- [x] Other admin screens: no plugin body classes without `jsdw-ai-chat` in screen id.
- [x] Admin: tokens → foundation → (fonts →) design-studio CSS order.
- [x] Widget: tokens → widget.css.
- [x] Isolation: admin/widget selectors scoped as designed.
- [x] Design Studio font enqueue order hardened (dependency chain).
