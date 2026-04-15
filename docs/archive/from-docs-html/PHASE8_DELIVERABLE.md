# Phase 8: Live Preview Behavior Hardening — Deliverable

## 1. Files modified

| File | Change |
|------|--------|
| [`jsdw-ai-chat/admin/js/design-studio.js`](jsdw-ai-chat/admin/js/design-studio.js) | Single `previewState` model; `initPreviewDOM` once; `syncPreviewContent` reads only `previewState`; launcher/close handlers; `collectSettings` via `collectWidgetDesignPayload` + `widget_ui`; simulation chrome |
| [`admin/js/design-studio.js`](admin/js/design-studio.js) | Mirror |
| [`jsdw-ai-chat/admin/css/design-studio.css`](jsdw-ai-chat/admin/css/design-studio.css) | Viewport / blocked / logged-in / notes / behave-hint / panel transition |
| [`admin/css/design-studio.css`](admin/css/design-studio.css) | Mirror |
| [`jsdw-ai-chat/admin/views/page-design-studio.php`](jsdw-ai-chat/admin/views/page-design-studio.php) | `id="jsdw-ds-browser"`; launcher label field; visibility hints |
| [`admin/views/page-design-studio.php`](admin/views/page-design-studio.php) | Mirror |
| [`jsdw-ai-chat/includes/class-rest.php`](jsdw-ai-chat/includes/class-rest.php) | `save_widget_design` merges optional `widget_ui`; response includes `widget_ui` |
| [`includes/class-rest.php`](includes/class-rest.php) | Mirror |

## 2. Preview state model

```text
previewState = {
  design: { ...widget_design keys, normalized },
  widgetUi: { launcher_label: string },
  session: { panelOpen: boolean }
}
```

- `design` is initialized from `JSDW_AI_CHAT_DESIGN.settings` and updated by all sidebar handlers.
- `widgetUi.launcher_label` from localized `widgetUi` and the Content tab input.
- `session.panelOpen` controls open/closed preview; reset from `defaultState` + `openTrigger` via `resetPanelFromDesignRules()` when those rules change; launcher click sets `true`, close sets `false`.

## 3. Control → preview effect

| Control | Effect |
|---------|--------|
| Theme cards / colors / emoji / ranges / font / open trigger / pills / position / toggles | Updates `previewState.design` → `syncPreviewContent` |
| Launcher label (Content) | Updates `previewState.widgetUi.launcher_label` → launcher label in preview |
| Visibility toggles | Narrow/wide/hidden browser chrome; overlay when hide-on-pages + IDs; logged-in badge text on mock chrome URL; grayscale when both mobile+desktop off |
| Launcher (preview) | Sets `session.panelOpen = true` |
| Close (preview) | Sets `session.panelOpen = false` |
| Behavior hint strip | Shows non–page-load trigger + auto-open delay (simulated copy) |

## 4. Previously dead switches — root cause

- **Launcher / close**: No click handlers; static `previewPanelOpenInitially` only.
- **Launcher label**: Read only from PHP `widgetUi`, not editable in Design Studio.
- **Visibility toggles**: Updated `S` but preview DOM unchanged.
- **collectSettings**: Read sidebar DOM (`getElementById().value`, `getActivePill`) → diverged from preview.

## 5. Scroll / remount prevention

- `#jsdw-preview-root` `innerHTML` only in `initPreviewDOM()` (once).
- `syncPreviewContent` restores `#jsdw-pr-messages` `scrollTop` after updates.
- Toggle `change` still restores `window.scrollY` after sync.

## 6. Display-only / simulated (explicit)

- Open trigger (non–page-load), auto-open delay, hide-on-pages with IDs, logged-in-only, mobile/desktop viewport: labeled “simulated” in UI copy or overlay text; **not** live site behavior.

## 7. Validation checklist

- [ ] Change colors / text / toggles → preview updates without reload.
- [ ] Launcher opens panel; close closes; changing default state / open trigger resets panel per rules.
- [ ] Save persists; reload Design Studio → values match.
- [ ] `grep` confirms no sidebar `.value` reads inside `syncPreviewContent` (only `previewState` + preview-node writes).
- [ ] No chat/query calls from preview (Save uses `settings/widget-design` only; hide-pages search uses `wp/v2/pages` for picker).

## 8. REST payload

`POST` body:

```json
{
  "widget_design": { ... },
  "widget_ui": { "launcher_label": "..." }
}
```
