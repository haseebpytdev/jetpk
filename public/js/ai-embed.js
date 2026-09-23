(function () {
    "use strict";

    var cfg = window.JP_AI_EMBED || {};
    var root = document.getElementById("jp-ai-embed");
    var statusEl = document.getElementById("jp-ai-status");
    var messagesEl = document.getElementById("jp-ai-messages");
    var formEl = document.getElementById("jp-ai-form");
    var inputEl = document.getElementById("jp-ai-input");
    var clearBtn = document.getElementById("jp-ai-clear");
    var handoffBtn = document.getElementById("jp-ai-handoff");

    var sessionToken = null;
    var parentOrigin = null;
    var conversationId = null;
    var lastMessageId = 0;
    var pollTimer = null;
    var readySent = false;

    function setStatus(text) {
        if (statusEl) {
            statusEl.textContent = text || "";
        }
    }

    function detectParentOrigin() {
        if (window.location.ancestorOrigins && window.location.ancestorOrigins.length > 0) {
            return window.location.ancestorOrigins[0];
        }
        if (document.referrer) {
            try {
                return new URL(document.referrer).origin;
            } catch (e) {
                return null;
            }
        }
        return null;
    }

    function headers(includeSession) {
        var h = {
            Accept: "application/json",
            "Content-Type": "application/json",
        };
        if (parentOrigin) {
            h[cfg.parentOriginHeader || "X-JP-AI-Embed-Parent-Origin"] = parentOrigin;
        }
        if (includeSession && sessionToken) {
            h[cfg.sessionHeader || "X-JP-AI-Embed-Session"] = sessionToken;
        }
        return h;
    }

    function apiFetch(url, options) {
        options = options || {};
        options.headers = Object.assign({}, headers(options.includeSession !== false), options.headers || {});
        options.credentials = "omit";
        return fetch(url, options).then(function (response) {
            return response.json().catch(function () {
                return { ok: false, status: "invalid", message: "Unexpected response." };
            }).then(function (payload) {
                return { response: response, payload: payload };
            });
        });
    }

    function postParent(type, detail) {
        if (!parentOrigin || !window.parent || window.parent === window) {
            return;
        }
        window.parent.postMessage(
            Object.assign({ type: type, tenant: cfg.tenant || "jetpakistan" }, detail || {}),
            parentOrigin
        );
    }

    function notifyResize() {
        if (!root) {
            return;
        }
        postParent("JP_AI_RESIZE", { height: root.scrollHeight });
    }

    function saveSession(token) {
        sessionToken = token;
        try {
            sessionStorage.setItem(cfg.storageKey || "jp_ai_embed_session", token);
        } catch (e) {
            /* ignore */
        }
    }

    function loadStoredSession() {
        try {
            return sessionStorage.getItem(cfg.storageKey || "jp_ai_embed_session");
        } catch (e) {
            return null;
        }
    }

    function saveConversation(id) {
        conversationId = id;
        try {
            if (id) {
                sessionStorage.setItem(cfg.conversationKey || "jp_ai_embed_conversation_id", id);
            } else {
                sessionStorage.removeItem(cfg.conversationKey || "jp_ai_embed_conversation_id");
            }
        } catch (e) {
            /* ignore */
        }
    }

    function loadConversation() {
        try {
            return sessionStorage.getItem(cfg.conversationKey || "jp_ai_embed_conversation_id");
        } catch (e) {
            return null;
        }
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function absoluteHref(href) {
        if (!href) {
            return "#";
        }
        if (/^https?:\/\//i.test(href)) {
            return href;
        }
        return "https://jetpakistan.pk" + (href.charAt(0) === "/" ? href : "/" + href);
    }

    function renderMessage(message) {
        var role = message.role || "assistant";
        var bubble = document.createElement("div");
        bubble.className = "jp-ai-embed__bubble jp-ai-embed__bubble--" + role;
        bubble.innerHTML = escapeHtml(message.body || "");

        if (Array.isArray(message.recommendations) && message.recommendations.length > 0) {
            var cards = document.createElement("div");
            cards.className = "jp-ai-embed__cards";
            message.recommendations.forEach(function (rec) {
                var card = document.createElement("div");
                card.className = "jp-ai-embed__card";
                var title = rec.title || rec.subtitle || "Recommendation";
                card.innerHTML =
                    '<p class="jp-ai-embed__card-title">' + escapeHtml(title) + "</p>" +
                    (rec.subtitle ? '<p class="jp-ai-embed__card-subtitle">' + escapeHtml(rec.subtitle) + "</p>" : "");
                var url = rec.view_and_book_url || rec.results_url || rec.package_url;
                if (url) {
                    var link = document.createElement("a");
                    link.className = "jp-ai-embed__btn--link";
                    link.href = absoluteHref(url);
                    link.target = "_blank";
                    link.rel = "noopener noreferrer";
                    link.textContent = "View details";
                    card.appendChild(link);
                }
                cards.appendChild(card);
            });
            bubble.appendChild(cards);
        }

        if (Array.isArray(message.actions) && message.actions.length > 0) {
            var actions = document.createElement("div");
            actions.className = "jp-ai-embed__actions";
            message.actions.forEach(function (action) {
                if (action.href) {
                    var actionLink = document.createElement("a");
                    actionLink.className = "jp-ai-embed__btn--link";
                    actionLink.href = absoluteHref(action.href);
                    actionLink.target = "_blank";
                    actionLink.rel = "noopener noreferrer";
                    actionLink.textContent = action.label || "Open";
                    actions.appendChild(actionLink);
                }
            });
            bubble.appendChild(actions);
        }

        messagesEl.appendChild(bubble);
        messagesEl.scrollTop = messagesEl.scrollHeight;
        notifyResize();
    }

    function appendAssistantFromPayload(payload) {
        renderMessage({
            role: "assistant",
            body: payload.message || "",
            recommendations: payload.recommendations || [],
            actions: payload.actions || [],
        });
    }

    function ensureSession() {
        parentOrigin = detectParentOrigin();
        if (!parentOrigin) {
            setStatus("Open this assistant inside an allowed client website iframe.");
            return Promise.reject(new Error("missing parent origin"));
        }

        var stored = loadStoredSession();
        if (stored) {
            sessionToken = stored;
            conversationId = loadConversation();
            return Promise.resolve();
        }

        return apiFetch(cfg.sessionEndpoint, {
            method: "POST",
            body: JSON.stringify({ tenant: cfg.tenant }),
            includeSession: false,
        }).then(function (result) {
            if (!result.response.ok || !result.payload.ok) {
                throw new Error(result.payload.message || "Unable to start embed session.");
            }
            saveSession(result.payload.token);
            conversationId = loadConversation();
        });
    }

    function setBusy(isBusy) {
        if (root) {
            root.classList.toggle("is-busy", !!isBusy);
        }
    }

    function handleChatResponse(payload) {
        if (payload.conversation_id) {
            saveConversation(payload.conversation_id);
        }
        if (payload.message) {
            appendAssistantFromPayload(payload);
        }
        if (payload.state === "WAITING_FOR_HUMAN") {
            startPolling();
        }
        if (clearBtn) {
            clearBtn.hidden = false;
        }
        if (handoffBtn) {
            handoffBtn.hidden = payload.state === "WAITING_FOR_HUMAN";
        }
    }

    function sendMessage(message) {
        setBusy(true);
        renderMessage({ role: "user", body: message });
        return apiFetch(cfg.chatEndpoint, {
            method: "POST",
            body: JSON.stringify({
                message: message,
                conversation_id: conversationId,
            }),
        }).then(function (result) {
            if (!result.response.ok && result.payload.status !== "refused") {
                throw new Error(result.payload.message || "Chat request failed.");
            }
            handleChatResponse(result.payload);
        }).finally(function () {
            setBusy(false);
            setStatus("");
        });
    }

    function pollMessages() {
        if (!conversationId) {
            return;
        }
        var url = cfg.messagesEndpoint +
            "?conversation_id=" + encodeURIComponent(conversationId) +
            (lastMessageId ? "&since_id=" + encodeURIComponent(String(lastMessageId)) : "");
        apiFetch(url, { method: "GET" }).then(function (result) {
            if (!result.response.ok) {
                return;
            }
            var messages = result.payload.messages || [];
            messages.forEach(function (msg) {
                if (msg.id) {
                    lastMessageId = Math.max(lastMessageId, Number(msg.id));
                }
                if (msg.role === "staff" || msg.role === "assistant") {
                    renderMessage({
                        role: msg.role,
                        body: msg.body,
                        recommendations: (msg.meta && msg.meta.recommendations) || [],
                        actions: (msg.meta && msg.meta.actions) || [],
                    });
                }
            });
        });
    }

    function startPolling() {
        if (pollTimer) {
            return;
        }
        pollTimer = window.setInterval(pollMessages, 4000);
    }

    function clearConversation() {
        setBusy(true);
        return apiFetch(cfg.clearEndpoint, {
            method: "POST",
            body: JSON.stringify({ conversation_id: conversationId }),
        }).then(function (result) {
            if (!result.response.ok) {
                throw new Error(result.payload.message || "Unable to clear conversation.");
            }
            messagesEl.innerHTML = "";
            lastMessageId = 0;
            if (pollTimer) {
                window.clearInterval(pollTimer);
                pollTimer = null;
            }
            saveConversation(result.payload.conversation_id || null);
            appendAssistantFromPayload({
                message: "Started a new conversation. How can I help?",
            });
        }).finally(function () {
            setBusy(false);
        });
    }

    function requestHandoff() {
        if (!conversationId) {
            return Promise.resolve();
        }
        setBusy(true);
        return apiFetch(cfg.handoffEndpoint, {
            method: "POST",
            body: JSON.stringify({ conversation_id: conversationId }),
        }).then(function (result) {
            if (!result.response.ok) {
                throw new Error(result.payload.message || "Handoff failed.");
            }
            handleChatResponse(result.payload);
        }).finally(function () {
            setBusy(false);
        });
    }

    function bindEvents() {
        if (formEl) {
            formEl.addEventListener("submit", function (event) {
                event.preventDefault();
                var message = (inputEl && inputEl.value || "").trim();
                if (!message) {
                    return;
                }
                if (inputEl) {
                    inputEl.value = "";
                }
                sendMessage(message).catch(function (error) {
                    setStatus(error.message || "Something went wrong.");
                });
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener("click", function () {
                clearConversation().catch(function (error) {
                    setStatus(error.message || "Unable to start a new chat.");
                });
            });
        }

        if (handoffBtn) {
            handoffBtn.addEventListener("click", function () {
                requestHandoff().catch(function (error) {
                    setStatus(error.message || "Unable to connect to support.");
                });
            });
        }

        window.addEventListener("message", function (event) {
            if (!parentOrigin || event.origin !== parentOrigin) {
                return;
            }
            var data = event.data || {};
            if (!data || typeof data.type !== "string") {
                return;
            }
            if (data.type === "JP_AI_OPEN") {
                postParent("JP_AI_OPEN", { open: true });
            }
            if (data.type === "JP_AI_CLOSE") {
                postParent("JP_AI_CLOSE", { open: false });
            }
        });
    }

    function boot() {
        bindEvents();
        setStatus("Connecting...");
        ensureSession()
            .then(function () {
                setStatus("");
                if (!readySent) {
                    postParent("JP_AI_READY", { ready: true });
                    readySent = true;
                }
                if (clearBtn) {
                    clearBtn.hidden = false;
                }
                if (handoffBtn) {
                    handoffBtn.hidden = false;
                }
                if (!conversationId) {
                    appendAssistantFromPayload({
                        message: "Hi, I am Ask JetPakistan. Ask about flights, bookings, or support.",
                    });
                }
                notifyResize();
            })
            .catch(function (error) {
                setStatus(error.message || "Embed assistant unavailable.");
            });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
