/**
 * Design Studio: previewState is the single source of truth for preview + save payload.
 * renderPreview / syncPreviewContent read only previewState (not sidebar DOM values).
 */
(function () {
	'use strict';

	var cfg = typeof window.JSDW_AI_CHAT_DESIGN === 'object' && window.JSDW_AI_CHAT_DESIGN ? window.JSDW_AI_CHAT_DESIGN : {};

	var DEFAULT_WIDGET = {
		theme: 'violet',
		primaryColor: '#6c63ff',
		chatBg: '#ffffff',
		botBubbleColor: '#f0f0f5',
		fontFamily: 'Instrument Sans',
		fontSize: 13,
		borderRadius: 18,
		chatWidth: 360,
		chatHeight: 520,
		widgetSize: 56,
		widgetIcon: '💬',
		position: 'bottom-right',
		defaultState: 'open',
		autoOpenDelay: 5,
		openTrigger: 'page-load',
		animation: 'slide',
		animationSpeed: 0.3,
		showOnMobile: true,
		showOnDesktop: true,
		hideOnPages: false,
		hideOnPageIds: [],
		loggedInOnly: false,
		showBadge: true,
		showQuickReplies: true,
		showTypingIndicator: true,
		soundEnabled: false,
		showTimestamps: true,
		showBranding: true,
		botName: 'Aria',
		statusText: 'Online · Typically replies instantly',
		botAvatar: '🤖',
		welcomeMessage: "Hi there 👋 I'm Aria! How can I help you today?",
		quickReplies: ['📦 Track my order', '💬 Talk to support', '📋 View FAQ'],
		inputPlaceholder: 'Type a message...'
	};

	var THEME_PRESETS = {
		violet: { theme: 'violet', primaryColor: '#6c63ff', chatBg: '#ffffff', botBubbleColor: '#f0f0f5' },
		midnight: { theme: 'midnight', primaryColor: '#e94560', chatBg: '#1a1a2e', botBubbleColor: '#16213e' },
		forest: { theme: 'forest', primaryColor: '#276749', chatBg: '#f0fff4', botBubbleColor: '#c6f6d5' },
		coral: { theme: 'coral', primaryColor: '#ff6b6b', chatBg: '#fffaf5', botBubbleColor: '#ffe4e6' },
		ocean: { theme: 'ocean', primaryColor: '#0077b6', chatBg: '#caf0f8', botBubbleColor: '#e0f4ff' },
		slate: { theme: 'slate', primaryColor: '#495057', chatBg: '#f8f9fa', botBubbleColor: '#e9ecef' }
	};

	var WIDGET_ICONS = ['💬', '🤖', '✨', '🧠', '💡', '🎯', '🔮', '⚡', '🌟', '💎'];
	var BOT_AVATARS = ['🤖', '💬', '✨', '🧠', '🌟'];
	var POS_CLASS_LIST = ['jsdw-pos-bottom-right', 'jsdw-pos-bottom-left', 'jsdw-pos-top-right', 'jsdw-pos-top-left'];
	var ANIM_CLASS_LIST = ['jsdw-anim-slide', 'jsdw-anim-fade', 'jsdw-anim-pop'];

	var hideSearchTimer = null;
	var selectedPageTitles = {};

	function normalizeDesign(raw) {
		var d = Object.assign({}, DEFAULT_WIDGET, raw || {});
		if (d.openTrigger === 'scroll-half') {
			d.openTrigger = 'scroll-50';
		}
		if (!Array.isArray(d.hideOnPageIds)) {
			d.hideOnPageIds = [];
		}
		if (!Array.isArray(d.quickReplies)) {
			d.quickReplies = ['', '', ''];
		} else {
			while (d.quickReplies.length < 3) {
				d.quickReplies.push('');
			}
			d.quickReplies = d.quickReplies.slice(0, 3);
		}
		return d;
	}

	function createPreviewState() {
		var design = normalizeDesign(cfg.settings || {});
		var wu = cfg.widgetUi && typeof cfg.widgetUi === 'object' ? cfg.widgetUi : {};
		return {
			design: design,
			widgetUi: {
				launcher_label: String(wu.launcher_label || '').trim()
			},
			session: {
				panelOpen: previewPanelOpenInitially(design)
			}
		};
	}

	var previewState = createPreviewState();

	function fontStack(name) {
		var m = {
			'Instrument Sans': '"Instrument Sans", system-ui, sans-serif',
			Inter: '"Inter", system-ui, sans-serif',
			'DM Sans': '"DM Sans", system-ui, sans-serif',
			Georgia: 'Georgia, "Times New Roman", serif',
			'System UI': 'system-ui, -apple-system, BlinkMacSystemFont, sans-serif'
		};
		return m[name] || m['Instrument Sans'];
	}

	function applyVars(el, d) {
		if (!el) {
			return;
		}
		el.style.setProperty('--chat-primary', d.primaryColor || '#6c63ff');
		el.style.setProperty('--chat-bg', d.chatBg || '#ffffff');
		el.style.setProperty('--chat-bot-bubble', d.botBubbleColor || '#f0f0f5');
		el.style.setProperty('--chat-font-family', fontStack(d.fontFamily || 'Instrument Sans'));
		var fs = parseInt(d.fontSize, 10) || 13;
		el.style.setProperty('--chat-font-size', fs + 'px');
		el.style.setProperty('--chat-font-size-lg', Math.round(fs * 1.15) + 'px');
		el.style.setProperty('--chat-font-size-sm', Math.max(10, Math.round(fs * 0.92)) + 'px');
		el.style.setProperty('--chat-radius', (parseInt(d.borderRadius, 10) || 18) + 'px');
		el.style.setProperty('--chat-width', (parseInt(d.chatWidth, 10) || 360) + 'px');
		el.style.setProperty('--chat-height', (parseInt(d.chatHeight, 10) || 520) + 'px');
		el.style.setProperty('--chat-widget-size', (parseInt(d.widgetSize, 10) || 56) + 'px');
		var spd = parseFloat(d.animationSpeed);
		if (isNaN(spd)) {
			spd = 0.3;
		}
		el.style.setProperty('--chat-anim-dur', spd + 's');
		el.style.setProperty('--jsdw-anim-speed', spd + 's');
	}

	function syncPreviewWidgetShell(wrap, d, sess) {
		if (!wrap) {
			return;
		}
		wrap.classList.add('jsdw-pr-widget');
		POS_CLASS_LIST.forEach(function (c) {
			wrap.classList.remove(c);
		});
		wrap.classList.add(posClass(d.position));
		ANIM_CLASS_LIST.forEach(function (c) {
			wrap.classList.remove(c);
		});
		var anim = String(d.animation || 'slide').toLowerCase();
		if (anim !== 'slide' && anim !== 'fade' && anim !== 'pop') {
			anim = 'slide';
		}
		wrap.classList.add('jsdw-anim-' + anim);
		wrap.classList.toggle('is-open', !!sess.panelOpen);
	}

	function updateSimStateBar() {
		var d = previewState.design;
		var sess = previewState.session;
		var el = document.getElementById('jsdw-pr-sim-state');
		if (!el) {
			return;
		}
		el.textContent = '';
		var chips = [
			['Panel', !!sess.panelOpen],
			['Badge', !!d.showBadge],
			['Quick', !!d.showQuickReplies],
			['Typing', !!d.showTypingIndicator],
			['Time', !!d.showTimestamps],
			['Brand', !!d.showBranding],
			['Sound', !!d.soundEnabled]
		];
		chips.forEach(function (pair, i) {
			if (i > 0) {
				el.appendChild(document.createTextNode(' '));
			}
			var s = document.createElement('span');
			s.className = 'jsdw-pr-sim-chip' + (pair[1] ? ' is-on' : '');
			s.textContent = pair[0];
			el.appendChild(s);
		});
	}

	function posClass(p) {
		var map = {
			'bottom-right': 'jsdw-pos-bottom-right',
			'bottom-left': 'jsdw-pos-bottom-left',
			'top-right': 'jsdw-pos-top-right',
			'top-left': 'jsdw-pos-top-left'
		};
		return map[p] || map['bottom-right'];
	}

	function normalizeOpenTrigger(t) {
		if (t === 'scroll-half') {
			return 'scroll-50';
		}
		return t || 'page-load';
	}

	function previewPanelOpenInitially(d) {
		if (!d || d.defaultState !== 'open') {
			return false;
		}
		return normalizeOpenTrigger(d.openTrigger) === 'page-load';
	}

	function resetPanelFromDesignRules() {
		previewState.session.panelOpen = previewPanelOpenInitially(previewState.design);
	}

	function ensurePreviewMount() {
		var studio = document.getElementById('jsdw-ai-chat-design-studio');
		if (!studio) {
			return;
		}
		var fake = studio.querySelector('.jsdw-ds-fake-page');
		var root = document.getElementById('jsdw-preview-root');
		if (!fake || !root) {
			return;
		}
		if (root.parentNode !== fake) {
			fake.appendChild(root);
		}
	}

	function initPreviewDOM() {
		ensurePreviewMount();
		var root = document.getElementById('jsdw-preview-root');
		if (!root) {
			return;
		}
		root.innerHTML =
			'<div class="jsdw-pr-sim-notes" id="jsdw-pr-sim-notes" aria-hidden="true"></div>' +
			'<div class="jsdw-pr-sim-state" id="jsdw-pr-sim-state" aria-live="polite"></div>' +
			'<div class="jsdw-pr-widget" id="jsdw-pr-widget">' +
			'<div class="jsdw-pr-panel" id="jsdw-pr-panel">' +
			'<div class="jsdw-pr-header">' +
			'<div class="jsdw-pr-avatar" id="jsdw-pr-avatar"></div>' +
			'<div class="jsdw-pr-head-text">' +
			'<div class="jsdw-pr-title" id="jsdw-pr-title"></div>' +
			'<div class="jsdw-pr-status-row">' +
			'<span id="jsdw-pr-status"></span>' +
			'<span class="jsdw-pr-sound" id="jsdw-pr-sound" aria-hidden="true"></span>' +
			'</div></div>' +
			'<button type="button" class="jsdw-pr-close" id="jsdw-pr-close" aria-label="Close">&times;</button></div>' +
			'<div class="jsdw-pr-behave-hint" id="jsdw-pr-behave-hint"></div>' +
			'<div class="jsdw-pr-messages" id="jsdw-pr-messages">' +
			'<div class="jsdw-pr-msg jsdw-pr-msg-bot" id="jsdw-pr-welcome"></div>' +
			'<div class="jsdw-pr-msg jsdw-pr-msg-user" id="jsdw-pr-user-sample"></div>' +
			'<div class="jsdw-pr-typing" id="jsdw-pr-typing" style="display:none"><span></span><span></span><span></span></div>' +
			'</div>' +
			'<div class="jsdw-pr-quick" id="jsdw-pr-quick"></div>' +
			'<div class="jsdw-pr-input-row"><input type="text" class="jsdw-pr-input" id="jsdw-pr-input" readonly />' +
			'<button type="button" class="jsdw-pr-send" aria-hidden="true">↑</button></div>' +
			'<div class="jsdw-pr-brand" id="jsdw-pr-brand"></div>' +
			'</div>' +
			'<button type="button" class="jsdw-pr-launcher" id="jsdw-pr-launcher">' +
			'<span class="jsdw-pr-launcher-inner" id="jsdw-pr-launcher-inner">' +
			'<span id="jsdw-pr-launcher-ico"></span>' +
			'<span class="jsdw-pr-launcher-label" id="jsdw-pr-launcher-label"></span></span>' +
			'<span class="jsdw-pr-badge" id="jsdw-pr-badge" style="display:none">1</span>' +
			'</button>' +
			'</div>';
	}

	function bindPreviewInteractions() {
		var launcher = document.getElementById('jsdw-pr-launcher');
		var closeBtn = document.getElementById('jsdw-pr-close');
		if (launcher) {
			launcher.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				previewState.session.panelOpen = !previewState.session.panelOpen;
				syncPreviewContent();
			});
		}
		if (closeBtn) {
			closeBtn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				previewState.session.panelOpen = false;
				syncPreviewContent();
			});
		}
	}

	function syncSimulationChrome() {
		var d = previewState.design;
		var browser = document.getElementById('jsdw-ds-browser');
		if (browser) {
			browser.classList.remove(
				'jsdw-ds-sim--viewport-narrow',
				'jsdw-ds-sim--viewport-wide',
				'jsdw-ds-sim--viewport-both',
				'jsdw-ds-sim--viewport-hidden',
				'jsdw-ds-sim--loggedin'
			);
			if (!d.showOnMobile && !d.showOnDesktop) {
				browser.classList.add('jsdw-ds-sim--viewport-hidden');
			} else if (d.showOnMobile && d.showOnDesktop) {
				browser.classList.add('jsdw-ds-sim--viewport-both');
			} else if (d.showOnMobile) {
				browser.classList.add('jsdw-ds-sim--viewport-narrow');
			} else {
				browser.classList.add('jsdw-ds-sim--viewport-wide');
			}
			if (d.loggedInOnly) {
				browser.classList.add('jsdw-ds-sim--loggedin');
			}
		}

		var notes = document.getElementById('jsdw-pr-sim-notes');
		if (notes) {
			notes.textContent = '';
			if (d.loggedInOnly) {
				notes.textContent = 'Signed-in visitors only · preview';
			}
		}

		var wrap = document.getElementById('jsdw-pr-widget');
		if (wrap) {
			wrap.classList.toggle('jsdw-pr-sim-blocked', !!(d.hideOnPages && d.hideOnPageIds && d.hideOnPageIds.length));
			wrap.classList.toggle('jsdw-pr-sim-hidden-viewport', !d.showOnMobile && !d.showOnDesktop);
		}
	}

	function syncPreviewContent() {
		var d = previewState.design;
		var wu = previewState.widgetUi;
		var sess = previewState.session;

		var wrap = document.getElementById('jsdw-pr-widget');
		var panel = document.getElementById('jsdw-pr-panel');
		if (!wrap || !panel) {
			return;
		}

		var msgs = document.getElementById('jsdw-pr-messages');
		var prevScroll = msgs ? msgs.scrollTop : 0;

		applyVars(wrap, d);
		wrap.setAttribute('data-preview-theme', d.theme || '');
		syncPreviewWidgetShell(wrap, d, sess);

		var av = document.getElementById('jsdw-pr-avatar');
		if (av) {
			av.textContent = d.botAvatar || '🤖';
		}
		var title = document.getElementById('jsdw-pr-title');
		if (title) {
			title.textContent = d.botName || '';
		}
		var st = document.getElementById('jsdw-pr-status');
		if (st) {
			st.textContent = d.statusText || '';
		}
		var soundEl = document.getElementById('jsdw-pr-sound');
		if (soundEl) {
			soundEl.textContent = d.soundEnabled ? ' · Sound on' : ' · Muted';
			soundEl.classList.toggle('jsdw-pr-sound--on', !!d.soundEnabled);
			soundEl.classList.toggle('jsdw-pr-sound--off', !d.soundEnabled);
		}

		var behave = document.getElementById('jsdw-pr-behave-hint');
		if (behave) {
			var ot = normalizeOpenTrigger(d.openTrigger);
			var delay = parseInt(d.autoOpenDelay, 10);
			if (isNaN(delay)) {
				delay = 0;
			}
			var parts = [];
			if (ot !== 'page-load') {
				var openLbl = ot.replace(/-/g, ' ');
				if (ot === 'scroll-50') {
					openLbl = 'Scroll 50%';
				} else if (ot === 'exit-intent') {
					openLbl = 'Exit intent';
				} else if (ot === 'button-only') {
					openLbl = 'Launcher only';
				} else if (ot === 'time-delay') {
					openLbl = 'Time delay';
				}
				parts.push('Opens: ' + openLbl + ' · preview');
			}
			if (delay > 0) {
				parts.push('Delay ' + delay + 's · preview');
			}
			behave.textContent = parts.join(' · ');
			behave.style.display = parts.length ? '' : 'none';
		}

		var welcome = document.getElementById('jsdw-pr-welcome');
		if (welcome) {
			welcome.querySelectorAll('.jsdw-pr-msg-time').forEach(function (n) {
				n.remove();
			});
			var wm = String(d.welcomeMessage || '').trim();
			if (!wm) {
				wm =
					"Hi there 👋 I'm your assistant. Adjust font size and toggles in the sidebar — typography and chips should read clearly here.";
			}
			welcome.textContent = wm;
			if (d.showTimestamps) {
				var time = document.createElement('div');
				time.className = 'jsdw-pr-msg-time';
				time.textContent = 'now';
				welcome.appendChild(time);
			}
		}
		var userEl = document.getElementById('jsdw-pr-user-sample');
		if (userEl) {
			userEl.querySelectorAll('.jsdw-pr-msg-time').forEach(function (n) {
				n.remove();
			});
			userEl.textContent =
				'Sample visitor reply — notice how body text scales with the font size control.';
			if (d.showTimestamps) {
				var ut = document.createElement('div');
				ut.className = 'jsdw-pr-msg-time';
				ut.textContent = 'now';
				userEl.appendChild(ut);
			}
		}
		var typingEl = document.getElementById('jsdw-pr-typing');
		if (typingEl) {
			typingEl.style.display = d.showTypingIndicator ? 'flex' : 'none';
			typingEl.classList.toggle('jsdw-pr-typing--visible', !!d.showTypingIndicator);
		}

		var quick = document.getElementById('jsdw-pr-quick');
		if (quick) {
			quick.innerHTML = '';
			if (d.showQuickReplies && Array.isArray(d.quickReplies)) {
				d.quickReplies.forEach(function (t) {
					var s = String(t || '').trim();
					if (!s) {
						return;
					}
					var b = document.createElement('span');
					b.className = 'jsdw-pr-chip';
					b.textContent = s;
					quick.appendChild(b);
				});
			}
		}

		var inpEl = document.getElementById('jsdw-pr-input');
		if (inpEl) {
			inpEl.placeholder = d.inputPlaceholder || '';
		}
		var brand = document.getElementById('jsdw-pr-brand');
		if (brand) {
			if (d.showBranding) {
				brand.style.display = '';
				brand.style.minHeight = '';
				brand.textContent = 'Powered by JSDW AI Chat';
			} else {
				brand.style.display = 'none';
				brand.style.minHeight = '0';
			}
		}

		var launcherIco = document.getElementById('jsdw-pr-launcher-ico');
		if (launcherIco) {
			launcherIco.textContent = d.widgetIcon || '💬';
		}
		var badge = document.getElementById('jsdw-pr-badge');
		if (badge) {
			badge.style.display = d.showBadge ? '' : 'none';
			badge.classList.toggle('jsdw-pr-badge--active', !!d.showBadge);
			badge.classList.toggle('jsdw-pr-badge--pulse', !!d.showBadge);
		}

		var lab = String(wu.launcher_label || '').trim();
		var launcher = document.getElementById('jsdw-pr-launcher');
		var launcherLabel = document.getElementById('jsdw-pr-launcher-label');
		if (launcherLabel) {
			launcherLabel.textContent = lab;
		}
		if (launcher) {
			launcher.classList.toggle('jsdw-has-label', !!lab);
			launcher.setAttribute('aria-expanded', sess.panelOpen ? 'true' : 'false');
			launcher.setAttribute('aria-controls', 'jsdw-pr-panel');
		}
		if (panel) {
			panel.setAttribute('aria-hidden', sess.panelOpen ? 'false' : 'true');
		}

		updateSimStateBar();
		syncSimulationChrome();

		if (msgs) {
			msgs.scrollTop = prevScroll;
		}
	}

	function renderPreview() {
		syncPreviewContent();
	}

	function buildThemeCards() {
		var grid = document.getElementById('jsdw-ds-themes');
		if (!grid) {
			return;
		}
		grid.innerHTML = '';
		var d = previewState.design;
		Object.keys(THEME_PRESETS).forEach(function (key) {
			var t = THEME_PRESETS[key];
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'jsdw-ds-theme-card';
			btn.dataset.themeKey = key;
			if (d.theme === key) {
				btn.classList.add('is-active');
			}
			btn.innerHTML =
				'<div class="jsdw-ds-theme-preview" style="background:' +
				t.chatBg +
				'">' +
				'<div class="jsdw-ds-theme-preview-bar" style="background:' +
				t.primaryColor +
				'"></div>' +
				'<div class="jsdw-ds-theme-preview-body" style="background:' +
				t.chatBg +
				'">' +
				'<span class="jsdw-ds-theme-preview-bubble" style="background:' +
				t.botBubbleColor +
				'"></span>' +
				'<span class="jsdw-ds-theme-preview-bubble" style="background:' +
				t.primaryColor +
				';opacity:0.9"></span>' +
				'</div></div>' +
				'<span style="font-size:12px;font-weight:600">' +
				key.charAt(0).toUpperCase() +
				key.slice(1) +
				'</span>';
			btn.addEventListener('click', function () {
				Object.assign(previewState.design, THEME_PRESETS[key]);
				grid.querySelectorAll('.jsdw-ds-theme-card').forEach(function (c) {
					c.classList.toggle('is-active', c.dataset.themeKey === key);
				});
				syncColorInputs();
				syncPreviewContent();
			});
			grid.appendChild(btn);
		});
	}

	function buildEmojiGrid(containerId, emojis, field) {
		var el = document.getElementById(containerId);
		if (!el) {
			return;
		}
		el.innerHTML = '';
		emojis.forEach(function (em) {
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'jsdw-ds-emoji-btn';
			b.textContent = em;
			b.dataset.value = em;
			if (previewState.design[field] === em) {
				b.classList.add('is-active');
			}
			b.addEventListener('click', function () {
				previewState.design[field] = em;
				el.querySelectorAll('.jsdw-ds-emoji-btn').forEach(function (x) {
					x.classList.toggle('is-active', x.dataset.value === em);
				});
				syncPreviewContent();
			});
			el.appendChild(b);
		});
	}

	function applyRangeFromState(id, key, valEl, formatter) {
		var el = document.getElementById(id);
		var v = document.getElementById(valEl);
		var d = previewState.design;
		if (!el) {
			return;
		}
		el.value = d[key];
		if (v) {
			v.textContent = formatter ? formatter(d[key]) : d[key];
		}
	}

	function syncPillsFromState(field) {
		var wrap = document.querySelector('.jsdw-ds-pills[data-field="' + field + '"]');
		if (!wrap) {
			return;
		}
		var d = previewState.design;
		wrap.querySelectorAll('.jsdw-ds-pill').forEach(function (p) {
			p.classList.toggle('is-active', p.dataset.value === d[field]);
		});
	}

	function syncPositionFromState() {
		document.querySelectorAll('#jsdw-position-grid .jsdw-ds-pos-cell[data-pos]').forEach(function (cell) {
			cell.classList.toggle('is-active', cell.dataset.pos === previewState.design.position);
		});
	}

	function syncTogglesFromState() {
		document.querySelectorAll('.jsdw-ds-toggle').forEach(function (inp) {
			var f = inp.dataset.field;
			inp.checked = !!previewState.design[f];
		});
	}

	function syncThemeCardsFromState() {
		var grid = document.getElementById('jsdw-ds-themes');
		if (!grid) {
			return;
		}
		var theme = previewState.design.theme;
		grid.querySelectorAll('.jsdw-ds-theme-card').forEach(function (btn) {
			btn.classList.toggle('is-active', btn.dataset.themeKey === theme);
		});
	}

	function syncEmojiGridFromState(containerId, field) {
		var el = document.getElementById(containerId);
		if (!el) {
			return;
		}
		var val = previewState.design[field];
		el.querySelectorAll('.jsdw-ds-emoji-btn').forEach(function (b) {
			b.classList.toggle('is-active', b.dataset.value === val);
		});
	}

	/**
	 * Write sidebar control values from previewState only (no DOM reads). Safe after save/rehydration.
	 */
	function syncFormControlsFromState() {
		var d = previewState.design;
		syncColorInputs();
		applyRangeFromState('jsdw-fontSize', 'fontSize', 'jsdw-fontSize-val', null);
		applyRangeFromState('jsdw-borderRadius', 'borderRadius', 'jsdw-borderRadius-val', null);
		applyRangeFromState('jsdw-chatWidth', 'chatWidth', 'jsdw-chatWidth-val', null);
		applyRangeFromState('jsdw-chatHeight', 'chatHeight', 'jsdw-chatHeight-val', null);
		applyRangeFromState('jsdw-widgetSize', 'widgetSize', 'jsdw-widgetSize-val', null);
		applyRangeFromState('jsdw-autoOpenDelay', 'autoOpenDelay', 'jsdw-autoOpenDelay-val', null);
		applyRangeFromState('jsdw-animationSpeed', 'animationSpeed', 'jsdw-animationSpeed-val', function (n) {
			return String(n);
		});

		var ff = document.getElementById('jsdw-fontFamily');
		if (ff) {
			ff.value = d.fontFamily || 'Instrument Sans';
		}
		var ot = document.getElementById('jsdw-openTrigger');
		if (ot) {
			ot.value = d.openTrigger || 'page-load';
		}

		['botName', 'statusText', 'welcomeMessage', 'inputPlaceholder'].forEach(function (fid) {
			var el = document.getElementById('jsdw-' + fid);
			if (el) {
				el.value = d[fid] || '';
			}
		});

		var ll = document.getElementById('jsdw-launcherLabel');
		if (ll) {
			ll.value = previewState.widgetUi.launcher_label || '';
		}

		[0, 1, 2].forEach(function (i) {
			var el = document.getElementById('jsdw-qr' + i);
			if (el) {
				el.value = (d.quickReplies && d.quickReplies[i]) || '';
			}
		});

		syncPillsFromState('defaultState');
		syncPillsFromState('animation');
		syncPositionFromState();
		syncTogglesFromState();
		syncThemeCardsFromState();
		syncEmojiGridFromState('jsdw-widget-icons', 'widgetIcon');
		syncEmojiGridFromState('jsdw-bot-avatars', 'botAvatar');

		var hid = document.getElementById('jsdw-hide-pages-ids');
		if (hid) {
			hid.value = (d.hideOnPageIds || []).join(',');
		}
		renderHideTags(d.hideOnPageIds || []);
		toggleHidePagesPicker();
	}

	function syncColorInputs() {
		var d = previewState.design;
		document.querySelectorAll('.jsdw-ds-color-field').forEach(function (field) {
			var key = field.dataset.colorField;
			var hex = d[key] || '#000000';
			var picker = field.querySelector('.jsdw-ds-color-picker');
			var text = field.querySelector('.jsdw-ds-hex-input');
			if (picker) {
				picker.value = hex;
			}
			if (text) {
				text.value = hex;
			}
			field.querySelectorAll('.jsdw-ds-swatch').forEach(function (sw) {
				sw.classList.toggle('is-active', sw.dataset.hex.toLowerCase() === String(hex).toLowerCase());
			});
		});
	}

	function bindColorFields() {
		document.querySelectorAll('.jsdw-ds-color-field').forEach(function (field) {
			var key = field.dataset.colorField;
			var picker = field.querySelector('.jsdw-ds-color-picker');
			var text = field.querySelector('.jsdw-ds-hex-input');
			field.querySelectorAll('.jsdw-ds-swatch').forEach(function (sw) {
				sw.addEventListener('click', function () {
					var h = sw.dataset.hex;
					previewState.design[key] = h;
					if (picker) {
						picker.value = h;
					}
					if (text) {
						text.value = h;
					}
					previewState.design.theme = 'custom';
					field.querySelectorAll('.jsdw-ds-swatch').forEach(function (s) {
						s.classList.toggle('is-active', s.dataset.hex === h);
					});
					var tg = document.getElementById('jsdw-ds-themes');
					if (tg) {
						tg.querySelectorAll('.jsdw-ds-theme-card').forEach(function (c) {
							c.classList.remove('is-active');
						});
					}
					syncPreviewContent();
				});
			});
			if (picker) {
				picker.addEventListener('input', function () {
					previewState.design[key] = picker.value;
					previewState.design.theme = 'custom';
					if (text) {
						text.value = picker.value;
					}
					syncColorInputs();
					syncPreviewContent();
				});
			}
			if (text) {
				text.addEventListener('input', function () {
					var v = text.value.trim();
					if (/^#[0-9A-Fa-f]{6}$/.test(v)) {
						previewState.design[key] = v;
						if (picker) {
							picker.value = v;
						}
						previewState.design.theme = 'custom';
						syncColorInputs();
						syncPreviewContent();
					}
				});
			}
		});
	}

	function setRange(id, key, valEl, formatter) {
		var el = document.getElementById(id);
		var v = document.getElementById(valEl);
		if (!el) {
			return;
		}
		applyRangeFromState(id, key, valEl, formatter);
		el.addEventListener('input', function () {
			var n = el.type === 'range' && el.step === '0.05' ? parseFloat(el.value) : parseInt(el.value, 10);
			previewState.design[key] = n;
			if (v) {
				v.textContent = formatter ? formatter(n) : n;
			}
			syncPreviewContent();
		});
	}

	function bindTabs() {
		document.querySelectorAll('.jsdw-ds-tab').forEach(function (tab) {
			tab.addEventListener('click', function () {
				var name = tab.dataset.tab;
				document.querySelectorAll('.jsdw-ds-tab').forEach(function (t) {
					t.classList.toggle('is-active', t === tab);
					t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
				});
				document.querySelectorAll('.jsdw-ds-panel').forEach(function (p) {
					var on = p.dataset.panel === name;
					p.hidden = !on;
					p.classList.toggle('is-active', on);
				});
			});
		});
	}

	function bindPills(field) {
		var wrap = document.querySelector('.jsdw-ds-pills[data-field="' + field + '"]');
		if (!wrap) {
			return;
		}
		wrap.querySelectorAll('.jsdw-ds-pill').forEach(function (p) {
			p.addEventListener('click', function () {
				previewState.design[field] = p.dataset.value;
				syncPillsFromState(field);
				if (field === 'defaultState') {
					resetPanelFromDesignRules();
				}
				syncPreviewContent();
			});
		});
		syncPillsFromState(field);
	}

	function bindPosition() {
		document.querySelectorAll('#jsdw-position-grid .jsdw-ds-pos-cell[data-pos]').forEach(function (cell) {
			cell.addEventListener('click', function () {
				previewState.design.position = cell.dataset.pos;
				syncPositionFromState();
				syncPreviewContent();
			});
		});
		syncPositionFromState();
	}

	function bindToggles() {
		document.querySelectorAll('.jsdw-ds-toggle').forEach(function (inp) {
			var f = inp.dataset.field;
			inp.addEventListener('change', function () {
				var scrollY = window.scrollY;
				previewState.design[f] = inp.checked;
				inp.blur();
				if (f === 'hideOnPages') {
					toggleHidePagesPicker();
				}
				syncPreviewContent();
				requestAnimationFrame(function () {
					window.scrollTo(0, scrollY);
				});
			});
		});
		syncTogglesFromState();
	}

	function toggleHidePagesPicker() {
		var box = document.getElementById('jsdw-hide-pages-picker');
		if (!box) {
			return;
		}
		box.style.display = previewState.design.hideOnPages ? 'block' : 'none';
	}

	function syncHiddenIdsFromArray(ids) {
		previewState.design.hideOnPageIds = ids.slice();
		var hid = document.getElementById('jsdw-hide-pages-ids');
		if (hid) {
			hid.value = ids.join(',');
		}
		syncPreviewContent();
	}

	function renderHideTags(ids) {
		var wrap = document.getElementById('jsdw-hide-pages-tags');
		if (!wrap) {
			return;
		}
		wrap.innerHTML = '';
		ids.forEach(function (id) {
			var tag = document.createElement('span');
			tag.className = 'jsdw-hide-page-tag';
			tag.style.cssText =
				'display:inline-flex;align-items:center;gap:4px;padding:4px 8px;background:#f0f0f1;border-radius:4px;font-size:12px;';
			var label = selectedPageTitles[id] || 'Page #' + id;
			tag.innerHTML =
				'<span>' +
				escHtml(label) +
				'</span><button type="button" class="jsdw-hide-tag-x" data-id="' +
				id +
				'" aria-label="Remove" style="border:none;background:transparent;cursor:pointer;font-size:14px;line-height:1;">×</button>';
			tag.querySelector('.jsdw-hide-tag-x').addEventListener('click', function () {
				var next = previewState.design.hideOnPageIds.filter(function (x) {
					return x !== id;
				});
				delete selectedPageTitles[id];
				syncHiddenIdsFromArray(next);
				renderHideTags(next);
			});
			wrap.appendChild(tag);
		});
	}

	function escHtml(s) {
		var d = document.createElement('div');
		d.textContent = s;
		return d.innerHTML;
	}

	function bindHidePagesSearch() {
		var inp = document.getElementById('jsdw-hide-pages-search');
		var results = document.getElementById('jsdw-hide-pages-results');
		if (!inp || !results || !cfg.wpPagesUrl) {
			return;
		}
		inp.addEventListener('input', function () {
			var q = inp.value.trim();
			window.clearTimeout(hideSearchTimer);
			if (q.length < 2) {
				results.innerHTML = '';
				return;
			}
			hideSearchTimer = window.setTimeout(function () {
				fetch(cfg.wpPagesUrl + '?search=' + encodeURIComponent(q) + '&per_page=10', {
					credentials: 'same-origin',
					headers: { Accept: 'application/json' }
				})
					.then(function (r) {
						return r.json();
					})
					.then(function (pages) {
						if (!Array.isArray(pages)) {
							return;
						}
						results.innerHTML = '';
						var current = previewState.design.hideOnPageIds.slice();
						pages.forEach(function (p) {
							if (current.indexOf(p.id) !== -1) {
								return;
							}
							var b = document.createElement('button');
							b.type = 'button';
							b.className = 'button button-small';
							b.style.margin = '2px 4px 2px 0';
							b.textContent = p.title && p.title.rendered ? stripTags(p.title.rendered) : '—';
							b.addEventListener('click', function () {
								selectedPageTitles[p.id] = b.textContent;
								var next = previewState.design.hideOnPageIds.concat([p.id]);
								syncHiddenIdsFromArray(next);
								renderHideTags(next);
								results.innerHTML = '';
								inp.value = '';
							});
							results.appendChild(b);
						});
					})
					.catch(function () {});
			}, 300);
		});
	}

	function stripTags(html) {
		var d = document.createElement('div');
		d.innerHTML = html;
		return d.textContent || d.innerText || '';
	}

	function prefetchHidePageTitles(ids) {
		if (!cfg.wpPagesUrl || !ids.length) {
			return;
		}
		ids.forEach(function (id) {
			fetch(cfg.wpPagesUrl + '/' + id, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
				.then(function (r) {
					return r.json();
				})
				.then(function (p) {
					if (p && p.title && p.title.rendered) {
						selectedPageTitles[id] = stripTags(p.title.rendered);
						renderHideTags(previewState.design.hideOnPageIds.slice());
					}
				})
				.catch(function () {});
		});
	}

	function initFormFromState() {
		var d = previewState.design;
		var ff = document.getElementById('jsdw-fontFamily');
		if (ff) {
			ff.addEventListener('change', function () {
				previewState.design.fontFamily = ff.value;
				syncPreviewContent();
			});
		}
		setRange('jsdw-fontSize', 'fontSize', 'jsdw-fontSize-val', null);
		setRange('jsdw-borderRadius', 'borderRadius', 'jsdw-borderRadius-val', null);
		setRange('jsdw-chatWidth', 'chatWidth', 'jsdw-chatWidth-val', null);
		setRange('jsdw-chatHeight', 'chatHeight', 'jsdw-chatHeight-val', null);
		setRange('jsdw-widgetSize', 'widgetSize', 'jsdw-widgetSize-val', null);
		setRange('jsdw-autoOpenDelay', 'autoOpenDelay', 'jsdw-autoOpenDelay-val', null);
		setRange('jsdw-animationSpeed', 'animationSpeed', 'jsdw-animationSpeed-val', function (n) {
			return String(n);
		});

		var ot = document.getElementById('jsdw-openTrigger');
		if (ot) {
			ot.addEventListener('change', function () {
				previewState.design.openTrigger = ot.value;
				resetPanelFromDesignRules();
				syncPreviewContent();
			});
		}

		['botName', 'statusText', 'welcomeMessage', 'inputPlaceholder'].forEach(function (fid) {
			var el = document.getElementById('jsdw-' + fid);
			if (!el) {
				return;
			}
			el.addEventListener('input', function () {
				previewState.design[fid] = el.value;
				syncPreviewContent();
			});
		});

		var ll = document.getElementById('jsdw-launcherLabel');
		if (ll) {
			ll.addEventListener('input', function () {
				previewState.widgetUi.launcher_label = ll.value;
				syncPreviewContent();
			});
		}

		[0, 1, 2].forEach(function (i) {
			var el = document.getElementById('jsdw-qr' + i);
			if (!el) {
				return;
			}
			el.addEventListener('input', function () {
				if (!Array.isArray(previewState.design.quickReplies)) {
					previewState.design.quickReplies = ['', '', ''];
				}
				previewState.design.quickReplies[i] = el.value;
				syncPreviewContent();
			});
		});

		syncFormControlsFromState();
		prefetchHidePageTitles(d.hideOnPageIds || []);
	}

	function collectWidgetDesignPayload() {
		var d = previewState.design;
		var qr = ['', '', ''];
		if (Array.isArray(d.quickReplies)) {
			for (var i = 0; i < 3; i++) {
				qr[i] = String(d.quickReplies[i] != null ? d.quickReplies[i] : '').trim();
			}
		}
		return {
			theme: d.theme,
			primaryColor: d.primaryColor,
			chatBg: d.chatBg,
			botBubbleColor: d.botBubbleColor,
			fontFamily: d.fontFamily,
			fontSize: parseInt(d.fontSize, 10) || 13,
			borderRadius: parseInt(d.borderRadius, 10) || 18,
			chatWidth: parseInt(d.chatWidth, 10) || 360,
			chatHeight: parseInt(d.chatHeight, 10) || 520,
			widgetSize: parseInt(d.widgetSize, 10) || 56,
			widgetIcon: d.widgetIcon,
			position: d.position,
			defaultState: d.defaultState,
			autoOpenDelay: parseInt(d.autoOpenDelay, 10) || 0,
			openTrigger: d.openTrigger,
			animation: d.animation,
			animationSpeed: parseFloat(d.animationSpeed) || 0.3,
			showOnMobile: !!d.showOnMobile,
			showOnDesktop: !!d.showOnDesktop,
			hideOnPages: !!d.hideOnPages,
			hideOnPageIds: Array.isArray(d.hideOnPageIds) ? d.hideOnPageIds.slice() : [],
			loggedInOnly: !!d.loggedInOnly,
			showBadge: !!d.showBadge,
			showQuickReplies: !!d.showQuickReplies,
			showTypingIndicator: !!d.showTypingIndicator,
			soundEnabled: !!d.soundEnabled,
			showTimestamps: !!d.showTimestamps,
			showBranding: !!d.showBranding,
			botName: d.botName || '',
			statusText: d.statusText || '',
			botAvatar: d.botAvatar,
			welcomeMessage: d.welcomeMessage || '',
			quickReplies: qr,
			inputPlaceholder: d.inputPlaceholder || ''
		};
	}

	function collectSettings() {
		return {
			widget_design: collectWidgetDesignPayload(),
			widget_ui: {
				launcher_label: previewState.widgetUi.launcher_label || ''
			}
		};
	}

	function saveSettings() {
		var btn = document.getElementById('jsdw-ds-save');
		var msg = document.getElementById('jsdw-ds-save-msg');
		if (!btn) {
			return;
		}
		var origText = btn.textContent;
		btn.textContent = 'Saving…';
		btn.disabled = true;
		if (msg) {
			msg.textContent = '';
		}

		var payload = collectSettings();
		if (cfg.saveDebug) {
			console.info('[JSDW Design Studio] save payload', JSON.parse(JSON.stringify(payload)));
		}

		fetch(cfg.restUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce || ''
			},
			body: JSON.stringify(payload)
		})
			.then(function (res) {
				return res.json().then(function (body) {
					return { res: res, body: body };
				});
			})
			.then(function (result) {
				var res = result.res;
				var body = result.body;
				if (cfg.saveDebug) {
					console.info('[JSDW Design Studio] save response', res.status, body);
				}
				if (!res.ok) {
					var httpMsg = body && body.message ? body.message : 'HTTP ' + res.status;
					throw new Error(httpMsg);
				}
				if (body && body.code && typeof body.code === 'string' && body.code.indexOf('rest_') === 0) {
					throw new Error(body.message || body.code);
				}
				if (!body || body.ok !== true || !body.data) {
					throw new Error((cfg.i18n && cfg.i18n.error) || 'Invalid save response');
				}
				if (body.data.widget_design) {
					Object.assign(previewState.design, normalizeDesign(body.data.widget_design));
				}
				if (body.data.widget_ui && typeof body.data.widget_ui === 'object') {
					previewState.widgetUi.launcher_label = String(body.data.widget_ui.launcher_label || '');
				}
				resetPanelFromDesignRules();
				syncFormControlsFromState();
				renderPreview();
				btn.textContent = '✓ Saved';
				btn.style.background = '#22c55e';
				if (msg) {
					msg.textContent = (cfg.i18n && cfg.i18n.saved) || '';
				}
				window.setTimeout(function () {
					btn.textContent = origText;
					btn.style.background = '';
					btn.disabled = false;
					if (msg) {
						msg.textContent = '';
					}
				}, 2500);
			})
			.catch(function (err) {
				btn.textContent = '✗ Error — Retry';
				btn.style.background = '#ef4444';
				btn.disabled = false;
				if (msg) {
					msg.textContent = (cfg.i18n && cfg.i18n.error) || String(err.message || err);
				}
				console.error('[JSDW Design Studio]', err);
			});
	}

	document.addEventListener('DOMContentLoaded', function () {
		buildThemeCards();
		buildEmojiGrid('jsdw-widget-icons', WIDGET_ICONS, 'widgetIcon');
		buildEmojiGrid('jsdw-bot-avatars', BOT_AVATARS, 'botAvatar');
		initPreviewDOM();
		bindPreviewInteractions();
		bindTabs();
		bindColorFields();
		initFormFromState();
		bindPills('defaultState');
		bindPills('animation');
		bindPosition();
		bindToggles();
		bindHidePagesSearch();

		var saveBtn = document.getElementById('jsdw-ds-save');
		if (saveBtn) {
			saveBtn.addEventListener('click', saveSettings);
		}

		renderPreview();
	});
})();
