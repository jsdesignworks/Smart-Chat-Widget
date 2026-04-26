/**
 * JSDW AI Chat widget — consumes JSDW_AI_CHAT_WIDGET from wp_localize_script.
 * Renders from formatter fields only; no network analytics.
 */
(function () {
	'use strict';

	var cfg = typeof window.JSDW_AI_CHAT_WIDGET === 'object' && window.JSDW_AI_CHAT_WIDGET ? window.JSDW_AI_CHAT_WIDGET : {};
	var mode = cfg.runtimeMode || 'off';
	if (mode === 'off') {
		return;
	}

	var d = cfg.design || cfg.widgetDesign || {};
	var SK = 'jsdw_ai_chat_w_sk';
	var CID = 'jsdw_ai_chat_w_cid';

	function dbg() {
		if (cfg.debug && typeof console !== 'undefined' && console.debug) {
			console.debug.apply(console, arguments);
		}
	}

	function esc(s) {
		var t = document.createElement('div');
		t.textContent = s == null ? '' : String(s);
		return t.innerHTML;
	}

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

	function applyVars(root, design) {
		var x = design;
		root.style.setProperty('--chat-primary', x.primaryColor || '#6c63ff');
		root.style.setProperty('--chat-bg', x.chatBg || '#ffffff');
		root.style.setProperty('--chat-bot-bubble', x.botBubbleColor || '#f0f0f5');
		root.style.setProperty('--chat-font-family', fontStack(x.fontFamily || 'Instrument Sans'));
		root.style.setProperty('--chat-font-size', (parseInt(x.fontSize, 10) || 13) + 'px');
		root.style.setProperty('--chat-radius', (parseInt(x.borderRadius, 10) || 18) + 'px');
		root.style.setProperty('--chat-width', (parseInt(x.chatWidth, 10) || 360) + 'px');
		root.style.setProperty('--chat-height', (parseInt(x.chatHeight, 10) || 520) + 'px');
		root.style.setProperty('--chat-widget-size', (parseInt(x.widgetSize, 10) || 56) + 'px');
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

	function effectivePosition() {
		var p = cfg.widgetPosition || d.position || 'bottom-right';
		return p;
	}

	function shouldShowWidget(design) {
		var isMobile = window.matchMedia('(max-width: 768px)').matches;
		if (isMobile && !design.showOnMobile) {
			return false;
		}
		if (!isMobile && !design.showOnDesktop) {
			return false;
		}
		if (design.loggedInOnly && !cfg.isLoggedIn) {
			return false;
		}
		return true;
	}

	function normalizeOpenTrigger(t) {
		if (t === 'scroll-half') {
			return 'scroll-50';
		}
		return t || 'page-load';
	}

	function getWrapper() {
		return document.getElementById('jsdw-ai-chat-widget');
	}

	function loadSession() {
		try {
			return {
				sessionKey: localStorage.getItem(SK) || '',
				conversationId: parseInt(localStorage.getItem(CID) || '0', 10) || 0
			};
		} catch (e) {
			return { sessionKey: '', conversationId: 0 };
		}
	}

	function saveSession(sk, cid) {
		try {
			if (sk) {
				localStorage.setItem(SK, sk);
			}
			if (cid > 0) {
				localStorage.setItem(CID, String(cid));
			}
		} catch (e) {
			dbg('session persist failed', e);
		}
	}

	function clearSession() {
		try {
			localStorage.removeItem(SK);
			localStorage.removeItem(CID);
		} catch (e) {
			dbg('session clear failed', e);
		}
	}

	function primaryAssistantText(result) {
		if (!result || typeof result !== 'object') {
			return '';
		}
		var status = String(result.answer_status || '');
		if (status === 'requires_clarification' && result.clarification_question) {
			return String(result.clarification_question);
		}
		return String(result.answer_text || '');
	}

	function buildSourcesHtml(result) {
		if (!cfg.showSources || !result || !Array.isArray(result.sources) || !result.sources.length) {
			return '';
		}
		var items = result.sources
			.map(function (s) {
				var title = s && s.title != null ? String(s.title) : '';
				var url = s && s.url != null ? String(s.url) : '';
				if (url) {
					return '<li><a href="' + esc(url) + '" target="_blank" rel="noopener noreferrer">' + esc(title || url) + '</a></li>';
				}
				return '<li>' + esc(title) + '</li>';
			})
			.join('');
		return '<ul class="jsdw-w-sources" role="list">' + items + '</ul>';
	}

	function updateDebugPanel(panel, result) {
		if (!panel) {
			return;
		}
		panel.innerHTML = '';
		if (!result || !cfg.adminDebugUi || !cfg.useDebugEndpoint) {
			panel.hidden = true;
			return;
		}
		panel.hidden = false;
		var dl = document.createElement('dl');
		function row(k, v) {
			var dt = document.createElement('dt');
			dt.textContent = k;
			var dd = document.createElement('dd');
			dd.textContent = v == null ? '' : String(v);
			dl.appendChild(dt);
			dl.appendChild(dd);
		}
		row('answer_status', result.answer_status);
		row('answer_type', result.answer_type);
		row('confidence', result.confidence);
		if (result.retrieval_stats && typeof result.retrieval_stats === 'object') {
			var rs = result.retrieval_stats;
			if (rs.hit_count != null) {
				row('hit_count', rs.hit_count);
			}
			if (rs.best_score != null) {
				row('best_score', rs.best_score);
			}
		}
		panel.appendChild(dl);
	}

	function openWidget() {
		var w = getWrapper();
		if (!w) {
			return;
		}
		var speed = typeof d.animationSpeed === 'number' ? d.animationSpeed : parseFloat(String(d.animationSpeed || '0.3')) || 0.3;
		w.style.setProperty('--jsdw-anim-speed', speed + 's');
		w.classList.remove('jsdw-anim-slide', 'jsdw-anim-fade', 'jsdw-anim-pop');
		var anim = d.animation || 'slide';
		if (anim !== 'fade' && anim !== 'pop') {
			anim = 'slide';
		}
		w.classList.add('jsdw-anim-' + anim);
		w.classList.add('is-open');
		var input = w.querySelector('.jsdw-w-input');
		if (input && !input.disabled && !input.readOnly) {
			input.focus();
		}
	}

	function closeWidget() {
		var w = getWrapper();
		if (!w) {
			return;
		}
		w.classList.remove('is-open');
		var launcher = w.querySelector('.jsdw-w-launcher');
		if (launcher) {
			launcher.focus();
		}
	}

	function bindOpenTriggers() {
		var ot = normalizeOpenTrigger(d.openTrigger);
		switch (ot) {
			case 'page-load':
				if (d.defaultState === 'open') {
					openWidget();
				}
				break;
			case 'time-delay':
				if (parseInt(d.autoOpenDelay, 10) > 0) {
					window.setTimeout(openWidget, parseInt(d.autoOpenDelay, 10) * 1000);
				}
				break;
			case 'scroll-50': {
				var onScroll = function () {
					var docH = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);
					var winH = window.innerHeight;
					var maxScroll = docH - winH;
					var scrolled = maxScroll <= 0 ? 100 : (window.scrollY / maxScroll) * 100;
					if (scrolled >= 50) {
						openWidget();
						window.removeEventListener('scroll', onScroll);
					}
				};
				onScroll();
				window.addEventListener('scroll', onScroll, { passive: true });
				break;
			}
			case 'exit-intent': {
				var onLeave = function (e) {
					if (e.clientY <= 0) {
						openWidget();
						document.documentElement.removeEventListener('mouseleave', onLeave);
					}
				};
				document.documentElement.addEventListener('mouseleave', onLeave);
				break;
			}
			case 'button-only':
			default:
				break;
		}
	}

	function build() {
		var currentId = parseInt(cfg.currentPostId, 10) || 0;
		var hideIds = Array.isArray(d.hideOnPageIds) ? d.hideOnPageIds.map(function (n) { return parseInt(n, 10); }) : [];
		if (d.hideOnPages && hideIds.indexOf(currentId) !== -1) {
			return;
		}
		if (!shouldShowWidget(d)) {
			return;
		}

		var mount = document.getElementById('jsdw-ai-chat-widget-mount');
		var parent = mount || document.body;

		var root = document.createElement('div');
		root.id = 'jsdw-ai-chat-widget';
		root.className = 'jsdw-widget ' + posClass(effectivePosition());
		applyVars(root, d);
		root.style.setProperty('--jsdw-anim-speed', (typeof d.animationSpeed === 'number' ? d.animationSpeed : parseFloat(String(d.animationSpeed || '0.3')) || 0.3) + 's');

		var launcher = document.createElement('button');
		launcher.type = 'button';
		launcher.className = 'jsdw-w-launcher';
		var openLabel = (cfg.strings && cfg.strings.open) || 'Open chat';
		launcher.setAttribute('aria-label', openLabel);
		var launcherInner = '<span class="jsdw-w-launcher-emoji">' + esc(d.widgetIcon || '💬') + '</span>';
		if (cfg.launcherLabel) {
			launcher.classList.add('jsdw-has-label');
			launcherInner += '<span class="jsdw-w-launcher-text">' + esc(cfg.launcherLabel) + '</span>';
		}
		launcher.innerHTML = launcherInner;
		if (d.showBadge) {
			var badge = document.createElement('span');
			badge.className = 'jsdw-w-launcher-badge';
			badge.textContent = '1';
			launcher.appendChild(badge);
		}

		var panel = document.createElement('div');
		panel.className = 'jsdw-w-panel jsdw-chat-window';
		panel.setAttribute('role', 'dialog');
		panel.setAttribute('aria-modal', 'true');
		panel.setAttribute('aria-label', d.botName || 'Chat');

		var header = document.createElement('div');
		header.className = 'jsdw-w-header';
		var closeLabel = (cfg.strings && cfg.strings.close) || 'Close';
		header.innerHTML =
			'<div class="jsdw-w-avatar">' +
			esc(d.botAvatar || '🤖') +
			'</div>' +
			'<div class="jsdw-w-header-text">' +
			'<div class="jsdw-w-title">' +
			esc(d.botName || 'Assistant') +
			'</div>' +
			'<div class="jsdw-w-status"><span class="jsdw-w-status-dot"></span>' +
			esc(d.statusText || '') +
			'</div></div>';

		var headerActions = document.createElement('div');
		headerActions.className = 'jsdw-w-header-actions';

		var resetBtn = null;
		if (cfg.allowReset && mode === 'live') {
			resetBtn = document.createElement('button');
			resetBtn.type = 'button';
			resetBtn.className = 'jsdw-w-reset';
			resetBtn.setAttribute('aria-label', (cfg.strings && cfg.strings.reset) || 'Reset conversation');
			resetBtn.textContent = '↺';
			headerActions.appendChild(resetBtn);
		}

		var closeBtn = document.createElement('button');
		closeBtn.type = 'button';
		closeBtn.className = 'jsdw-w-close';
		closeBtn.setAttribute('aria-label', closeLabel);
		closeBtn.innerHTML = '&times;';
		headerActions.appendChild(closeBtn);

		header.appendChild(headerActions);

		var messages = document.createElement('div');
		messages.className = 'jsdw-w-messages';
		messages.setAttribute('role', 'log');
		messages.setAttribute('aria-live', 'polite');
		messages.setAttribute('aria-relevant', 'additions');

		var welcomeText = cfg.welcomeMessage != null && cfg.welcomeMessage !== '' ? cfg.welcomeMessage : d.welcomeMessage || '';
		var welcome = document.createElement('div');
		welcome.className = 'jsdw-w-msg jsdw-w-msg-bot jsdw-w-msg-welcome';
		welcome.innerHTML = esc(welcomeText) + (d.showTimestamps ? '<div class="jsdw-w-msg-time">now</div>' : '');

		var typing = document.createElement('div');
		typing.className = 'jsdw-w-typing jsdw-w-typing-hidden';
		typing.setAttribute('aria-hidden', 'true');
		typing.innerHTML = '<span></span><span></span><span></span>';

		var debugPanel = null;
		if (cfg.adminDebugUi && cfg.useDebugEndpoint) {
			debugPanel = document.createElement('div');
			debugPanel.className = 'jsdw-w-debug-panel';
			debugPanel.hidden = true;
		}

		var liveAgentBanner = null;
		if (mode === 'admin_disabled') {
			var dis = document.createElement('div');
			dis.className = 'jsdw-w-msg jsdw-w-msg-bot jsdw-w-msg-system';
			dis.textContent = (cfg.strings && cfg.strings.adminDisabled) || '';
			messages.appendChild(dis);
		} else {
			messages.appendChild(welcome);
		}

		if (mode === 'live') {
			liveAgentBanner = document.createElement('div');
			liveAgentBanner.className = 'jsdw-w-live-agent jsdw-w-live-agent--hidden';
			liveAgentBanner.setAttribute('role', 'status');
			messages.appendChild(liveAgentBanner);
		}

		messages.appendChild(typing);
		if (debugPanel) {
			messages.appendChild(debugPanel);
		}

		launcher.addEventListener('click', function () {
			var w = getWrapper();
			if (w && w.classList.contains('is-open')) {
				closeWidget();
			} else {
				openWidget();
			}
		});
		root.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				closeWidget();
			}
		});
		closeBtn.addEventListener('click', function () {
			closeWidget();
		});

		panel.appendChild(header);
		panel.appendChild(messages);

		var quick = null;
		if (mode === 'live' && d.showQuickReplies && Array.isArray(d.quickReplies)) {
			quick = document.createElement('div');
			quick.className = 'jsdw-w-quick';
			d.quickReplies.forEach(function (text) {
				if (!text) {
					return;
				}
				var b = document.createElement('button');
				b.type = 'button';
				b.className = 'jsdw-w-chip';
				b.textContent = text;
				quick.appendChild(b);
			});
			if (quick.children.length) {
				panel.appendChild(quick);
			}
		}

		var visitorBar = null;
		var vNameInp = null;
		var vEmailInp = null;
		var vSaveBtn = null;
		if (mode === 'live' && cfg.requireVisitorIdentity) {
			visitorBar = document.createElement('div');
			visitorBar.className = 'jsdw-w-visitor-id';
			visitorBar.setAttribute('hidden', 'hidden');
			var vt = (cfg.strings && cfg.strings.visitorIdentityTitle) || '';
			var vnl = (cfg.strings && cfg.strings.visitorNameLabel) || '';
			var vel = (cfg.strings && cfg.strings.visitorEmailLabel) || '';
			var vs = (cfg.strings && cfg.strings.visitorSave) || 'Save';
			visitorBar.innerHTML =
				'<div class="jsdw-w-visitor-id__title">' +
				esc(vt) +
				'</div>' +
				'<label class="jsdw-w-visitor-id__field"><span>' +
				esc(vnl) +
				'</span><input type="text" class="jsdw-w-visitor-id__input" autocomplete="name" /></label>' +
				'<label class="jsdw-w-visitor-id__field"><span>' +
				esc(vel) +
				'</span><input type="email" class="jsdw-w-visitor-id__input" autocomplete="email" /></label>' +
				'<button type="button" class="jsdw-w-visitor-id__save">' +
				esc(vs) +
				'</button>';
			vNameInp = visitorBar.querySelector('input[type="text"]');
			vEmailInp = visitorBar.querySelector('input[type="email"]');
			vSaveBtn = visitorBar.querySelector('.jsdw-w-visitor-id__save');
		}

		var inputRow = document.createElement('div');
		inputRow.className = 'jsdw-w-input-row';
		var inp = document.createElement('input');
		inp.type = 'text';
		inp.className = 'jsdw-w-input';
		inp.placeholder = cfg.placeholderText != null && cfg.placeholderText !== '' ? cfg.placeholderText : d.inputPlaceholder || '';
		inp.autocomplete = 'off';
		inp.readOnly = mode !== 'live';
		inp.disabled = mode !== 'live';
		var send = document.createElement('button');
		send.type = 'button';
		send.className = 'jsdw-w-send';
		send.innerHTML = '&#8593;';
		send.setAttribute('aria-label', (cfg.strings && cfg.strings.send) || 'Send');
		send.disabled = mode !== 'live';
		inputRow.appendChild(inp);
		inputRow.appendChild(send);
		if (visitorBar) {
			panel.appendChild(visitorBar);
		}
		panel.appendChild(inputRow);

		if (d.showBranding) {
			var brand = document.createElement('div');
			brand.className = 'jsdw-w-branding';
			brand.textContent = cfg.branding || 'Powered by JSDW AI Chat';
			panel.appendChild(brand);
		}

		root.appendChild(panel);
		root.appendChild(launcher);
		parent.appendChild(root);

		if (mode !== 'live') {
			if (d.defaultState === 'open' && normalizeOpenTrigger(d.openTrigger) === 'page-load') {
				openWidget();
			} else {
				bindOpenTriggers();
			}
			return;
		}

		var submitting = false;
		var agentPollTimer = null;
		var agentPollTickRef = null;
		var agentPollSince = 0;
		var renderedAgentIds = {};
		var POLL_LS = 'jsdw_ai_chat_w_poll';
		var LIVE_HANDOFF = 'live_agent_handoff';

		function syncVisitorIdentityBar(conv) {
			if (!visitorBar || !cfg.requireVisitorIdentity) {
				return;
			}
			if (!conv || typeof conv !== 'object') {
				visitorBar.setAttribute('hidden', 'hidden');
				return;
			}
			var need =
				!conv.visitor_display_name ||
				!String(conv.visitor_display_name).trim() ||
				!conv.visitor_email ||
				!String(conv.visitor_email).trim();
			if (need) {
				visitorBar.removeAttribute('hidden');
			} else {
				visitorBar.setAttribute('hidden', 'hidden');
			}
		}

		function loadPollState(cid) {
			try {
				var raw = localStorage.getItem(POLL_LS);
				if (!raw) {
					return { since: 0 };
				}
				var o = JSON.parse(raw);
				if (!o || parseInt(o.cid, 10) !== cid) {
					return { since: 0 };
				}
				return { since: parseInt(o.since, 10) || 0 };
			} catch (e) {
				return { since: 0 };
			}
		}

		function savePollState(cid, since) {
			try {
				localStorage.setItem(POLL_LS, JSON.stringify({ cid: cid, since: since }));
			} catch (e) {}
		}

		function clearPollState() {
			try {
				localStorage.removeItem(POLL_LS);
			} catch (e) {}
		}

		function stopAgentPoll() {
			if (agentPollTimer) {
				clearInterval(agentPollTimer);
				agentPollTimer = null;
			}
			agentPollTickRef = null;
		}

		function isAgentConnected(conv) {
			if (!conv || typeof conv !== 'object') {
				return false;
			}
			var v = conv.agent_connected;
			return v === true || v === 1 || v === '1';
		}

		function showLiveAgentBanner() {
			if (!liveAgentBanner) {
				return;
			}
			liveAgentBanner.textContent =
				(cfg.strings && cfg.strings.liveAgentJoined) || 'A team member can now reply here.';
			liveAgentBanner.classList.remove('jsdw-w-live-agent--hidden');
		}

		function hideLiveAgentBanner() {
			if (!liveAgentBanner) {
				return;
			}
			liveAgentBanner.classList.add('jsdw-w-live-agent--hidden');
			liveAgentBanner.textContent = '';
		}

		function appendLiveAgentBubble(text, mid) {
			if (mid != null && mid > 0) {
				if (renderedAgentIds[mid]) {
					return;
				}
				renderedAgentIds[mid] = true;
			}
			var el = document.createElement('div');
			el.className = 'jsdw-w-msg jsdw-w-msg-agent';
			el.textContent = text;
			messages.insertBefore(el, typing);
			messages.scrollTop = messages.scrollHeight;
		}

		function startAgentPoll(convId, sessionKey) {
			stopAgentPoll();
			if (!cfg.restSessionMessages || convId <= 0 || !sessionKey) {
				return;
			}
			var cid = convId;
			var sk = sessionKey;
			function tick() {
				var sep = cfg.restSessionMessages.indexOf('?') >= 0 ? '&' : '?';
				var url =
					cfg.restSessionMessages +
					sep +
					'conversation_id=' +
					encodeURIComponent(String(cid)) +
					'&session_key=' +
					encodeURIComponent(sk) +
					'&since_id=' +
					encodeURIComponent(String(agentPollSince));
				fetch(url, {
					credentials: 'same-origin',
					headers: {
						'X-WP-Nonce': cfg.nonce || ''
					}
				})
					.then(function (r) {
						return r.json().then(function (j) {
							return { ok: r.ok, json: j };
						});
					})
					.then(function (pack) {
						if (!pack.ok || !pack.json || pack.json.ok !== true) {
							return;
						}
						var d = pack.json.data;
						if (!d || typeof d !== 'object') {
							return;
						}
						if (!d.agent_connected) {
							stopAgentPoll();
							hideLiveAgentBanner();
							clearPollState();
							return;
						}
						var arr = d.messages;
						if (!Array.isArray(arr)) {
							return;
						}
						var maxSince = agentPollSince;
						arr.forEach(function (m) {
							if (!m || m.role !== 'agent') {
								return;
							}
							var mid = m.id != null ? parseInt(m.id, 10) : 0;
							var txt = m.message_text != null ? String(m.message_text) : '';
							if (txt && mid > 0) {
								appendLiveAgentBubble(txt, mid);
							}
							if (mid > maxSince) {
								maxSince = mid;
							}
						});
						if (maxSince > agentPollSince) {
							agentPollSince = maxSince;
							savePollState(cid, agentPollSince);
						}
					})
					.catch(function () {});
			}
			agentPollTickRef = tick;
			agentPollTimer = setInterval(tick, 3000);
			tick();
		}

		function hydrateAgentSession() {
			if (!cfg.restSessionMessages || !liveAgentBanner) {
				return;
			}
			var sess = loadSession();
			var cid = sess.conversationId;
			var sk = sess.sessionKey;
			if (cid <= 0 || !sk) {
				return;
			}
			var st = loadPollState(cid);
			agentPollSince = st.since;
			var sep = cfg.restSessionMessages.indexOf('?') >= 0 ? '&' : '?';
			var url =
				cfg.restSessionMessages +
				sep +
				'conversation_id=' +
				encodeURIComponent(String(cid)) +
				'&session_key=' +
				encodeURIComponent(sk) +
				'&since_id=0';
			fetch(url, {
				credentials: 'same-origin',
				headers: {
					'X-WP-Nonce': cfg.nonce || ''
				}
			})
				.then(function (r) {
					return r.json().then(function (j) {
						return { ok: r.ok, json: j };
					});
				})
				.then(function (pack) {
					if (!pack.ok || !pack.json || pack.json.ok !== true) {
						return;
					}
					var data = pack.json.data;
					if (!data || typeof data !== 'object') {
						return;
					}
					if (!data.agent_connected) {
						hideLiveAgentBanner();
						return;
					}
					showLiveAgentBanner();
					var arr = data.messages || [];
					var maxSince = agentPollSince;
					arr.forEach(function (m) {
						if (!m || m.role !== 'agent') {
							return;
						}
						var mid = m.id != null ? parseInt(m.id, 10) : 0;
						var txt = m.message_text != null ? String(m.message_text) : '';
						if (!txt || mid <= 0) {
							return;
						}
						appendLiveAgentBubble(txt, mid);
						if (mid > maxSince) {
							maxSince = mid;
						}
					});
					agentPollSince = maxSince;
					savePollState(cid, agentPollSince);
					startAgentPoll(cid, sk);
				})
				.catch(function () {});
		}

		function setLoading(on) {
			if (on) {
				typing.classList.remove('jsdw-w-typing-hidden');
				inp.setAttribute('aria-busy', 'true');
				inputRow.classList.add('is-loading');
			} else {
				typing.classList.add('jsdw-w-typing-hidden');
				inp.removeAttribute('aria-busy');
				inputRow.classList.remove('is-loading');
			}
		}

		function appendUserBubble(text) {
			var el = document.createElement('div');
			el.className = 'jsdw-w-msg jsdw-w-msg-user';
			el.textContent = text;
			messages.insertBefore(el, typing);
			messages.scrollTop = messages.scrollHeight;
		}

		function appendAssistantBubble(result, errText) {
			var wrap = document.createElement('div');
			wrap.className = 'jsdw-w-msg jsdw-w-msg-bot';
			var status = result && result.answer_status ? String(result.answer_status) : '';
			if (status) {
				wrap.setAttribute('data-answer-status', status);
			}
			if (errText) {
				wrap.classList.add('jsdw-w-msg-error');
				wrap.textContent = errText;
			} else {
				var main = primaryAssistantText(result);
				wrap.innerHTML = esc(main);
				var srcHtml = buildSourcesHtml(result);
				if (srcHtml) {
					wrap.insertAdjacentHTML('beforeend', srcHtml);
				}
			}
			messages.insertBefore(wrap, typing);
			updateDebugPanel(debugPanel, result);
			messages.scrollTop = messages.scrollHeight;
		}

		function handleSend() {
			if (submitting) {
				return;
			}
			var q = (inp.value || '').trim();
			if (!q) {
				return;
			}
			submitting = true;
			setLoading(true);
			inp.disabled = true;
			send.disabled = true;

			appendUserBubble(q);
			inp.value = '';

			var sess = loadSession();
			var url = cfg.useDebugEndpoint && cfg.restUrlDebug ? cfg.restUrlDebug : cfg.restUrl;
			var body = {
				query: q,
				session_key: sess.sessionKey,
				conversation_id: sess.conversationId
			};

			dbg('widget request', url, body);

			fetch(url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': cfg.nonce || ''
				},
				body: JSON.stringify(body)
			})
				.then(function (res) {
					return res
						.json()
						.catch(function () {
							return {};
						})
						.then(function (json) {
							return { ok: res.ok, status: res.status, json: json };
						});
				})
				.then(function (pack) {
					var json = pack.json;
					if (!pack.ok || !json || json.ok !== true) {
						var errMsg = json && json.message ? String(json.message) : '';
						var msg =
							errMsg ||
							(pack.status === 403 ? (cfg.strings && cfg.strings.errorNetwork) : '') ||
							(cfg.strings && cfg.strings.errorResponse) ||
							'Error';
						appendAssistantBubble(null, msg);
						return;
					}
					var data = json.data;
					if (!data || typeof data !== 'object') {
						appendAssistantBubble(null, (cfg.strings && cfg.strings.errorResponse) || 'Error');
						return;
					}
					var conv = data.conversation;
					var result = data.result;
					var latestMid = data.latest_message_id != null ? parseInt(data.latest_message_id, 10) : 0;
					if (isNaN(latestMid)) {
						latestMid = 0;
					}
					var sk = sess.sessionKey;
					var id = sess.conversationId;
					if (conv && typeof conv === 'object') {
						sk = conv.session_key != null ? String(conv.session_key) : sk;
						id = conv.id != null ? parseInt(conv.id, 10) : id;
						saveSession(sk || sess.sessionKey, id > 0 ? id : sess.conversationId);
					}
					if (latestMid > agentPollSince) {
						agentPollSince = latestMid;
					}
					if (id > 0) {
						savePollState(id, agentPollSince);
					}
					syncVisitorIdentityBar(conv);
					stopAgentPoll();
					if (isAgentConnected(conv) && id > 0 && sk) {
						showLiveAgentBanner();
						startAgentPoll(id, sk);
						window.setTimeout(function () {
							if (typeof agentPollTickRef === 'function') {
								agentPollTickRef();
							}
						}, 350);
					} else {
						hideLiveAgentBanner();
					}
					if (result && typeof result === 'object') {
						if (result.answer_type === LIVE_HANDOFF) {
							updateDebugPanel(debugPanel, result);
							dbg('live agent handoff; banner only (no duplicate assistant bubble)');
						} else {
							appendAssistantBubble(result, null);
						}
					} else {
						appendAssistantBubble(null, (cfg.strings && cfg.strings.errorResponse) || 'Error');
					}
				})
				.catch(function () {
					appendAssistantBubble(null, (cfg.strings && cfg.strings.errorNetwork) || 'Network error');
				})
				.finally(function () {
					submitting = false;
					setLoading(false);
					inp.disabled = false;
					send.disabled = false;
					inp.focus();
				});
		}

		if (vSaveBtn && cfg.restVisitorIdentity) {
			vSaveBtn.addEventListener('click', function () {
				var sess = loadSession();
				var cid = sess.conversationId;
				var sk = sess.sessionKey;
				if (!cid || !sk) {
					return;
				}
				var nm = vNameInp ? String(vNameInp.value || '').trim() : '';
				var em = vEmailInp ? String(vEmailInp.value || '').trim() : '';
				var saving = (cfg.strings && cfg.strings.visitorSaving) || '…';
				vSaveBtn.disabled = true;
				var prev = vSaveBtn.textContent;
				vSaveBtn.textContent = saving;
				fetch(cfg.restVisitorIdentity, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': cfg.nonce || ''
					},
					body: JSON.stringify({
						conversation_id: cid,
						session_key: sk,
						visitor_name: nm,
						visitor_email: em
					})
				})
					.then(function (res) {
						return res
							.json()
							.catch(function () {
								return {};
							})
							.then(function (json) {
								return { ok: res.ok, json: json };
							});
					})
					.then(function (pack) {
						if (!pack.ok || !pack.json || pack.json.ok !== true || !pack.json.data || !pack.json.data.conversation) {
							return;
						}
						syncVisitorIdentityBar(pack.json.data.conversation);
					})
					.catch(function () {})
					.finally(function () {
						vSaveBtn.disabled = false;
						vSaveBtn.textContent = prev;
					});
			});
		}

		send.addEventListener('click', handleSend);
		inp.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				handleSend();
			}
		});

		if (quick) {
			quick.addEventListener('click', function (e) {
				var t = e.target;
				if (t && t.classList && t.classList.contains('jsdw-w-chip')) {
					inp.value = t.textContent || '';
					handleSend();
				}
			});
		}

		if (resetBtn) {
			resetBtn.addEventListener('click', function () {
				stopAgentPoll();
				agentPollSince = 0;
				renderedAgentIds = {};
				clearPollState();
				hideLiveAgentBanner();
				clearSession();
				syncVisitorIdentityBar(null);
				var nodes = messages.querySelectorAll(
					'.jsdw-w-msg-user, .jsdw-w-msg-bot:not(.jsdw-w-msg-welcome), .jsdw-w-msg-agent'
				);
				nodes.forEach(function (n) {
					n.remove();
				});
				var sys = messages.querySelectorAll('.jsdw-w-msg-system');
				sys.forEach(function (n) {
					n.remove();
				});
				if (debugPanel) {
					updateDebugPanel(debugPanel, null);
				}
				dbg('conversation reset');
			});
		}

		hydrateAgentSession();

		document.addEventListener('visibilitychange', function () {
			if (document.visibilityState === 'visible' && typeof agentPollTickRef === 'function') {
				agentPollTickRef();
			}
		});

		if (d.defaultState === 'open' && normalizeOpenTrigger(d.openTrigger) === 'page-load') {
			openWidget();
		} else {
			bindOpenTriggers();
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', build);
	} else {
		build();
	}
})();
