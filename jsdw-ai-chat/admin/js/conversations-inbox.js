(function () {
	'use strict';

	var cfg = typeof window.JSDW_AI_CHAT_CONV === 'object' && window.JSDW_AI_CHAT_CONV ? window.JSDW_AI_CHAT_CONV : {};
	var root = document.querySelector('.jsdw-conv-layout');
	if (!root) {
		return;
	}

	var thread = document.getElementById('jsdw-conv-thread');
	var composer = document.getElementById('jsdw-conv-composer');
	var input = document.getElementById('jsdw-conv-input');
	var sendBtn = document.getElementById('jsdw-conv-send');
	var joinBtn = document.getElementById('jsdw-conv-join');
	var releaseBtn = document.getElementById('jsdw-conv-release');
	var composerHint = document.getElementById('jsdw-conv-composer-hint');
	var pillViewing = document.getElementById('jsdw-conv-pill-viewing');
	var pillAgent = document.getElementById('jsdw-conv-pill-agent');
	var debugToggle = document.getElementById('jsdw-conv-debug-toggle');
	var convId = parseInt(root.getAttribute('data-conversation-id') || '0', 10) || 0;
	var storageOn = root.getAttribute('data-storage-on') === '1';
	var agentConnected = root.getAttribute('data-agent-connected') === '1';
	var renderedIds = {};
	var lastMessageId = parseInt(root.getAttribute('data-last-message-id') || '0', 10) || 0;
	if (thread) {
		thread.querySelectorAll('[data-message-id]').forEach(function (el) {
			var id = el.getAttribute('data-message-id');
			if (id) {
				renderedIds[id] = true;
			}
		});
	}

	function bumpLastMessageId(n) {
		var v = typeof n === 'number' ? n : parseInt(n, 10) || 0;
		if (v > lastMessageId) {
			lastMessageId = v;
			root.setAttribute('data-last-message-id', String(lastMessageId));
		}
	}

	function esc(s) {
		var t = document.createElement('div');
		t.textContent = s == null ? '' : String(s);
		return t.innerHTML;
	}

	function isDebugOn() {
		if (debugToggle) {
			return debugToggle.checked;
		}
		return false;
	}

	function applyDebugClass() {
		if (thread) {
			thread.classList.toggle('jsdw-conv-thread--debug', isDebugOn());
		}
	}

	if (debugToggle) {
		try {
			debugToggle.checked = window.localStorage.getItem('jsdw_conv_debug') === '1';
		} catch (e) {
			debugToggle.checked = false;
		}
		debugToggle.addEventListener('change', function () {
			try {
				window.localStorage.setItem('jsdw_conv_debug', debugToggle.checked ? '1' : '0');
			} catch (e) {}
			applyDebugClass();
		});
		applyDebugClass();
	}

	function setElHidden(el, on) {
		if (!el) {
			return;
		}
		if (on) {
			el.setAttribute('hidden', 'hidden');
		} else {
			el.removeAttribute('hidden');
		}
	}

	function setComposerConnected(on) {
		if (!storageOn || !input || !sendBtn) {
			return;
		}
		input.disabled = !on;
		sendBtn.disabled = !on;
		if (composerHint) {
			setElHidden(composerHint, on);
		}
	}

	function setAgentUi(connected) {
		agentConnected = !!connected;
		root.setAttribute('data-agent-connected', connected ? '1' : '0');
		if (pillViewing) {
			setElHidden(pillViewing, connected);
		}
		if (pillAgent) {
			setElHidden(pillAgent, !connected);
		}
		if (joinBtn) {
			setElHidden(joinBtn, !storageOn || connected);
		}
		if (releaseBtn) {
			releaseBtn.disabled = !connected;
		}
		setComposerConnected(connected);
	}

	if (convId > 0 && storageOn) {
		setAgentUi(agentConnected);
	}

	function appendAgentBubble(text, timeMysql, messageId) {
		if (!thread) {
			return;
		}
		var wrap = document.createElement('div');
		wrap.className = 'jsdw-conv-bubble jsdw-conv-bubble--agent';
		var mid = messageId != null ? parseInt(messageId, 10) || 0 : 0;
		if (mid > 0) {
			wrap.setAttribute('data-message-id', String(mid));
			renderedIds[String(mid)] = true;
		}
		wrap.innerHTML =
			'<div class="jsdw-conv-bubble__label">' +
			esc((cfg.strings && cfg.strings.agent) || 'Agent') +
			'</div><div>' +
			esc(text) +
			'</div><div class="jsdw-conv-bubble__time">' +
			esc(timeMysql || '') +
			'</div>';
		thread.appendChild(wrap);
		thread.scrollTop = thread.scrollHeight;
	}

	function appendThreadMessage(m) {
		if (!thread || !m || typeof m !== 'object') {
			return;
		}
		var mid = m.id != null ? parseInt(m.id, 10) || 0 : 0;
		if (mid <= 0 || renderedIds[String(mid)]) {
			return;
		}
		renderedIds[String(mid)] = true;
		bumpLastMessageId(mid);
		var role = m.role != null ? String(m.role) : '';
		var t = m.created_at != null ? String(m.created_at) : '';
		var lab = cfg.strings || {};
		if (role === 'user') {
			var ub = document.createElement('div');
			ub.className = 'jsdw-conv-bubble jsdw-conv-bubble--user';
			ub.setAttribute('data-message-id', String(mid));
			ub.innerHTML =
				'<div class="jsdw-conv-bubble__label">' +
				esc(lab.visitor || 'Visitor') +
				'</div><div>' +
				esc(m.message_text != null ? String(m.message_text) : '') +
				'</div><div class="jsdw-conv-bubble__time">' +
				esc(t) +
				'</div>';
			thread.appendChild(ub);
		} else if (role === 'agent') {
			appendAgentBubble(m.message_text != null ? String(m.message_text) : '', t, mid);
			return;
		} else if (role === 'assistant') {
			var body = m.answer_text != null ? String(m.answer_text) : '';
			if (!body && m.message_text != null) {
				body = String(m.message_text);
			}
			var ast = m.answer_status != null ? String(m.answer_status) : '';
			var cf = m.confidence_score != null && String(m.confidence_score) !== '' ? String(m.confidence_score) : '';
			var asb = document.createElement('div');
			asb.className = 'jsdw-conv-bubble jsdw-conv-bubble--assistant';
			asb.setAttribute('data-message-id', String(mid));
			var inner =
				'<div class="jsdw-conv-bubble__label">' +
				esc(lab.assistant || 'Assistant') +
				'</div><div>' +
				esc(body) +
				'</div><div class="jsdw-conv-bubble__time">' +
				esc(t) +
				'</div>';
			if (ast || cf) {
				inner += '<div class="jsdw-conv-bubble__debug">';
				if (ast) {
					inner +=
						'<div>' +
						esc(lab.answerStatus || 'Answer status:') +
						' ' +
						esc(ast) +
						'</div>';
				}
				if (cf) {
					inner +=
						'<div>' +
						esc(lab.confidence || 'Confidence:') +
						' ' +
						esc(cf) +
						'</div>';
				}
				inner += '</div>';
			}
			asb.innerHTML = inner;
			thread.appendChild(asb);
			if (debugToggle) {
				applyDebugClass();
			}
		} else {
			var fb = m.message_text != null ? String(m.message_text) : '';
			if (!fb && m.answer_text != null) {
				fb = String(m.answer_text);
			}
			var ob = document.createElement('div');
			ob.className = 'jsdw-conv-bubble jsdw-conv-bubble--assistant';
			ob.setAttribute('data-message-id', String(mid));
			ob.innerHTML =
				'<div class="jsdw-conv-bubble__label">' +
				esc(role || lab.message || 'Message') +
				'</div><div>' +
				esc(fb) +
				'</div><div class="jsdw-conv-bubble__time">' +
				esc(t) +
				'</div>';
			thread.appendChild(ob);
		}
		thread.scrollTop = thread.scrollHeight;
	}

	function postJson(url, body) {
		return fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce || ''
			},
			body: JSON.stringify(body)
		}).then(function (res) {
			return res.json().then(function (json) {
				return { ok: res.ok, status: res.status, json: json };
			});
		});
	}

	function lastAgentCreatedAt(messages) {
		if (!Array.isArray(messages)) {
			return '';
		}
		for (var i = messages.length - 1; i >= 0; i--) {
			if (messages[i] && messages[i].role === 'agent') {
				return messages[i].created_at != null ? String(messages[i].created_at) : '';
			}
		}
		return '';
	}

	if (joinBtn && convId > 0 && storageOn) {
		joinBtn.addEventListener('click', function () {
			joinBtn.disabled = true;
			postJson(cfg.restAgentJoin || '', { conversation_id: convId })
				.then(function (pack) {
					if (!pack.ok || !pack.json || pack.json.ok !== true) {
						var msg =
							(pack.json && pack.json.message && String(pack.json.message)) ||
							(cfg.strings && cfg.strings.joinError) ||
							'Error';
						window.alert(msg);
						return;
					}
					setAgentUi(true);
				})
				.catch(function () {
					window.alert((cfg.strings && cfg.strings.joinError) || 'Error');
				})
				.finally(function () {
					joinBtn.disabled = false;
				});
		});
	}

	if (sendBtn && input && composer && convId > 0 && storageOn) {
		sendBtn.addEventListener('click', function () {
			if (!agentConnected) {
				return;
			}
			var msg = (input.value || '').trim();
			if (!msg) {
				return;
			}
			sendBtn.disabled = true;
			input.disabled = true;
			postJson(cfg.restAgentReply || '', { conversation_id: convId, message: msg })
				.then(function (pack) {
					if (pack.status === 409) {
						var m409 =
							(pack.json && pack.json.message && String(pack.json.message)) ||
							(cfg.strings && cfg.strings.joinError) ||
							'';
						window.alert(m409);
						setAgentUi(false);
						return;
					}
					if (!pack.ok || !pack.json || pack.json.ok !== true) {
						window.alert((cfg.strings && cfg.strings.sendError) || 'Error');
						return;
					}
					input.value = '';
					var dpost = pack.json.data && typeof pack.json.data === 'object' ? pack.json.data : {};
					var at = lastAgentCreatedAt(dpost.messages);
					var fallback = new Date().toISOString().slice(0, 19).replace('T', ' ');
					var newMid = dpost.message_id != null ? parseInt(dpost.message_id, 10) || 0 : 0;
					if (Array.isArray(dpost.messages)) {
						dpost.messages.forEach(function (row) {
							var rid = row.id != null ? parseInt(row.id, 10) || 0 : 0;
							bumpLastMessageId(rid);
						});
					}
					appendAgentBubble(msg, at || fallback, newMid);
					bumpLastMessageId(newMid);
				})
				.catch(function () {
					window.alert((cfg.strings && cfg.strings.sendError) || 'Error');
				})
				.finally(function () {
					sendBtn.disabled = false;
					input.disabled = !agentConnected;
					if (agentConnected) {
						input.focus();
					}
				});
		});
	}

	if (releaseBtn && convId > 0) {
		releaseBtn.addEventListener('click', function () {
			if (!agentConnected) {
				return;
			}
			if (
				!window.confirm(
					'End live-agent mode for this visitor? Automated replies will resume for this conversation.'
				)
			) {
				return;
			}
			releaseBtn.disabled = true;
			postJson(cfg.restAgentRelease || '', { conversation_id: convId })
				.then(function (pack) {
					if (!pack.ok || !pack.json || pack.json.ok !== true) {
						window.alert((cfg.strings && cfg.strings.releaseError) || 'Error');
						return;
					}
					setAgentUi(false);
				})
				.catch(function () {
					window.alert((cfg.strings && cfg.strings.releaseError) || 'Error');
				})
				.finally(function () {
					if (!agentConnected) {
						releaseBtn.disabled = true;
					} else {
						releaseBtn.disabled = false;
					}
				});
		});
	}

	/* --- Inbox summary polling (Phase 8.3.2) --- */
	var summaryUrl = cfg.restInboxSummary || '';
	var pollMs = typeof cfg.pollIntervalMs === 'number' ? cfg.pollIntervalMs : 25000;
	var baseDocTitle = String(document.title || '').replace(/^\(\d+\)\s+/, '');
	var pollTimer = null;

	function setUnreadChrome(total) {
		var n = typeof total === 'number' ? total : parseInt(total, 10) || 0;
		var pageEl = document.getElementById('jsdw-conv-page-unread');
		var badgeEl = document.getElementById('jsdw-conv-inbox-badge');
		if (n > 0) {
			var show = String(Math.min(99, n));
			document.title = '(' + show + ') ' + baseDocTitle;
			if (pageEl) {
				pageEl.textContent = '(' + show + ')';
				pageEl.removeAttribute('hidden');
			}
			if (badgeEl) {
				badgeEl.textContent = show;
				badgeEl.removeAttribute('hidden');
			}
		} else {
			document.title = baseDocTitle;
			if (pageEl) {
				pageEl.setAttribute('hidden', 'hidden');
			}
			if (badgeEl) {
				badgeEl.setAttribute('hidden', 'hidden');
			}
		}
	}

	function applySummaryRow(item) {
		if (!item || item.id == null) {
			return;
		}
		var id = String(item.id);
		var link = document.querySelector('.jsdw-conv-list__link[data-conversation-id="' + id + '"]');
		if (!link) {
			return;
		}
		var uc = item.admin_unread_user_count != null ? parseInt(item.admin_unread_user_count, 10) || 0 : 0;
		var unread = uc > 0;
		var live = !!item.agent_connected;
		link.setAttribute('data-unread-count', String(uc));
		link.setAttribute('data-live', live ? '1' : '0');
		link.classList.toggle('jsdw-conv-list__link--unread', unread);
		link.classList.toggle('jsdw-conv-list__link--live', live);
		var lbl = item.attention_label != null ? String(item.attention_label) : '';
		var hint = link.querySelector('.jsdw-conv-list__attention');
		if (lbl) {
			if (!hint) {
				hint = document.createElement('div');
				hint.className = 'jsdw-conv-list__attention';
				link.appendChild(hint);
			}
			hint.textContent = lbl;
		} else if (hint) {
			hint.remove();
		}
	}

	function pollInboxSummary() {
		if (!summaryUrl || document.visibilityState === 'hidden') {
			return;
		}
		var sep = summaryUrl.indexOf('?') >= 0 ? '&' : '?';
		fetch(summaryUrl + sep + '_=' + String(Date.now()), {
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
				if (!pack.ok || !pack.json || pack.json.ok !== true || !pack.json.data) {
					return;
				}
				var d = pack.json.data;
				var total = d.unread_total != null ? parseInt(d.unread_total, 10) || 0 : 0;
				setUnreadChrome(total);
				var arr = d.conversations;
				if (!Array.isArray(arr)) {
					return;
				}
				arr.forEach(applySummaryRow);
			})
			.catch(function () {});
	}

	if (summaryUrl) {
		pollTimer = window.setInterval(pollInboxSummary, pollMs);
		document.addEventListener('visibilitychange', function () {
			if (document.visibilityState === 'visible') {
				pollInboxSummary();
			}
		});
	}

	/* --- Thread message polling (visitor + assistant lines) --- */
	var threadTpl = cfg.restConversationMessagesTpl || '';
	var threadPollMs = typeof cfg.threadPollIntervalMs === 'number' ? cfg.threadPollIntervalMs : 3500;
	var threadPollTimer = null;

	function threadMessagesUrl() {
		if (!threadTpl || convId <= 0) {
			return '';
		}
		return threadTpl.replace(/CONV_ID/g, String(convId));
	}

	function pollThreadMessages() {
		var url = threadMessagesUrl();
		if (!url || !thread || convId <= 0 || document.visibilityState === 'hidden') {
			return;
		}
		var sep = url.indexOf('?') >= 0 ? '&' : '?';
		fetch(
			url + sep + 'since_id=' + encodeURIComponent(String(lastMessageId)) + '&_=' + String(Date.now()),
			{
				credentials: 'same-origin',
				headers: {
					'X-WP-Nonce': cfg.nonce || ''
				}
			}
		)
			.then(function (r) {
				return r.json().then(function (j) {
					return { ok: r.ok, json: j };
				});
			})
			.then(function (pack) {
				if (!pack.ok || !pack.json || pack.json.ok !== true || !pack.json.data) {
					return;
				}
				var rows = pack.json.data;
				if (!Array.isArray(rows)) {
					return;
				}
				rows.forEach(appendThreadMessage);
			})
			.catch(function () {});
	}

	function stopThreadPoll() {
		if (threadPollTimer) {
			clearInterval(threadPollTimer);
			threadPollTimer = null;
		}
	}

	function startThreadPoll() {
		stopThreadPoll();
		if (!threadMessagesUrl() || !thread || convId <= 0) {
			return;
		}
		pollThreadMessages();
		threadPollTimer = window.setInterval(pollThreadMessages, threadPollMs);
	}

	if (threadMessagesUrl() && convId > 0 && thread) {
		startThreadPoll();
		document.addEventListener('visibilitychange', function () {
			if (document.visibilityState === 'visible') {
				startThreadPoll();
			} else {
				stopThreadPoll();
			}
		});
	}
})();
