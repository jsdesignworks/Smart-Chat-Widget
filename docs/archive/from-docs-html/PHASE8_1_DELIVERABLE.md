# Phase 8.1 — Preview polish (deliverable)

## Constraints honored

- **previewState** shape and role unchanged (same `design`, `widgetUi`, `session` usage in [`jsdw-ai-chat/admin/js/design-studio.js`](jsdw-ai-chat/admin/js/design-studio.js)).
- **No DOM reads** from sidebar controls inside `syncPreviewContent` / `syncSimulationChrome` / `renderPreview`; updates remain **state → DOM** from `previewState` only.
- **No new sync entry points** or feature flags; changes are copy/class/attribute presentation inside the existing pipeline.

## Files modified

| File | Change |
|------|--------|
| [`jsdw-ai-chat/admin/css/design-studio.css`](jsdw-ai-chat/admin/css/design-studio.css) | Phase 8.1 polish: viewport accents, hidden-viewport chip, sim notes pill, behave-hint strip, launcher/panel/close motion, sound/badge/typing/chip/message spacing, blocked overlay pill, midnight `[data-preview-theme]` tuning, fake-page gradient, preview kicker styles |
| [`jsdw-ai-chat/admin/js/design-studio.js`](jsdw-ai-chat/admin/js/design-studio.js) | `data-preview-theme` from `d.theme`; `jsdw-pr-panel--open`; shorter simulation copy; sound/badge/typing classes; inline open-trigger labels for behave hint |
| [`jsdw-ai-chat/admin/views/page-design-studio.php`](jsdw-ai-chat/admin/views/page-design-studio.php) | “Configurator preview” kicker; shortened footnote copy |
| Root [`admin/css/design-studio.css`](admin/css/design-studio.css), [`admin/js/design-studio.js`](admin/js/design-studio.js), [`admin/views/page-design-studio.php`](admin/views/page-design-studio.php) | Mirrored from `jsdw-ai-chat/admin/` |
| [`PREVIEW_PHASE_8_1_LIVE_PREVIEW.html`](PREVIEW_PHASE_8_1_LIVE_PREVIEW.html) | Standalone visual review (embedded CSS, minimal JS, no libs) |

## What was visually polished

- Fake browser: transitions, viewport modes (accent borders / outline / chip for “no match”), logged-in URL suffix.
- Preview widget: launcher hover/active/focus, panel open shadow + position-aware close animation (translate + scale), close control hover.
- Messages, quick chips, input row, brand row: spacing and rhythm; timestamps as small pills; typing row subtle ring when on.
- Simulation: notes and behave-hint read as quiet product chips, not debug strings; blocked overlay uses pill label.
- Midnight preset: stronger panel shadow and behave-hint contrast via `[data-preview-theme="midnight"]` on the preview widget.

## Simulated states made more readable

- Mobile-only / desktop-only / both / hidden viewports (browser chrome cues + bottom chip when hidden).
- Signed-in-only (top sim notes pill + URL hint).
- Hide-on-pages overlay (pill message + scrim).
- Widget hidden when no viewport match (grayscale + dim).
- Sound on vs muted, badge emphasized when on, typing indicator ring, quick replies and branding visibility.

## Interaction improvements

- CSS-only transitions on panel open/close and launcher; animation duration still follows `--chat-anim-dur` from design.
- Close button hover/active feedback.
- Quick-reply chips slight hover lift.

## Helper / overlay messaging

- Shorter strings with a consistent `· preview` suffix where appropriate.
- Logged-in: `Signed-in visitors only · preview`.
- Blocked: `Hidden on selected pages · preview`.
- URL bar: ` · signed in`.
- Behave strip: `Opens: … · preview`, `Delay Ns · preview`.
- Preview column footnote: `Local preview only — no chat requests…`

## Controls that remain simulation-only

- Viewport width / “no match” / hide-on-pages / signed-in URL and notes are **preview simulators**; production behavior remains site-dependent (as before).
- Open trigger / delay hints describe configured intent, not live automation in the admin preview.

## Validation checklist

- [ ] Open/close: panel eases without harsh scale-only pop; launcher feels clickable.
- [ ] State visibility: toggling badge, sound, typing, timestamps, branding, quick replies produces obvious preview changes.
- [ ] Simulation messaging: chips read as product copy, not devtools.
- [ ] No layout jumps: browser width transitions smoothly; preview root not remounted.
- [ ] No dead-feeling controls: each sidebar toggle in Design Studio maps to a visible preview effect (retested in wp-admin).
- [ ] Standalone file [`PREVIEW_PHASE_8_1_LIVE_PREVIEW.html`](PREVIEW_PHASE_8_1_LIVE_PREVIEW.html) opens offline and matches the polish direction.

## Package

After deploy, build with `bash scripts/package-plugin.sh` from the repo root; confirm `dist/jsdw-ai-chat-*.zip`.
