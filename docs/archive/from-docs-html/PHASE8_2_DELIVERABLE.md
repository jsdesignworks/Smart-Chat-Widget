# Phase 8.2 — Design Studio persistence and rehydration

## Files modified

- [`jsdw-ai-chat/admin/js/design-studio.js`](jsdw-ai-chat/admin/js/design-studio.js) — `syncFormControlsFromState`, `normalizeDesign` quick-reply padding, `collectWidgetDesignPayload` fixed 3 slots, hardened `saveSettings`, helper syncs, `initFormFromState` wires listeners then `syncFormControlsFromState`
- [`jsdw-ai-chat/includes/class-rest.php`](jsdw-ai-chat/includes/class-rest.php) — `widget_ui` merge when `array_key_exists( 'widget_ui', $params )`
- [`jsdw-ai-chat/admin/class-admin.php`](jsdw-ai-chat/admin/class-admin.php) — `saveDebug` in `JSDW_AI_CHAT_DESIGN` (`WP_DEBUG` + `manage_ai_chat_widget_settings`)
- Mirrored: [`admin/js/design-studio.js`](admin/js/design-studio.js), [`includes/class-rest.php`](includes/class-rest.php), [`admin/class-admin.php`](admin/class-admin.php)

## Root causes fixed

1. **Post-save drift**: Server-sanitized values updated `previewState` but sidebar controls kept stale values; sliders/colors especially diverged after PHP clamping/rounding.
2. **False success**: Success UI could show without validating `body.ok === true` and `body.data`.
3. **Quick replies**: Compact non-empty-only arrays caused inconsistent round-trips vs PHP’s three-slot sanitize path.
4. **`widget_ui` REST merge**: `empty( $incoming_wu )` skipped merge for empty array; explicit key check merges when the client sends `widget_ui` (including cleared launcher label scenarios).

## What `syncFormControlsFromState` updates

Writes from `previewState` only: color pickers/swatches (`syncColorInputs`), all six ranges, font family, open trigger, bot/status/welcome/placeholder text, launcher label, three quick-reply inputs, default state and animation pills, position grid, feature toggles, theme cards active state, widget icon and bot avatar emoji grids, hide-pages hidden field + tags + picker visibility.

## Quick replies payload

`quickReplies` is always exactly **three** trimmed strings in `collectWidgetDesignPayload()`.

## Validation checklist (manual)

- [ ] Move chat width slider to a value PHP rounds → Save → slider readout matches without reload
- [ ] Change launcher label → Save → reload → label persists; preview matches
- [ ] Edit three quick replies (including clearing one) → Save → reload → fields match
- [ ] Change default state / open trigger → Save → preview session matches rules after `resetPanelFromDesignRules`
- [ ] Force invalid REST response → error message, no green success
- [ ] With `WP_DEBUG` true, `saveDebug` logs payload and response in console

## Preview-only behavior

Unchanged: preview still simulates viewport, hide-on-pages overlay, etc.; persistence covers saved `widget_design` / `widget_ui` only.
