/**
 * JetPK supplier connection form — provider-scoped field visibility.
 * No dependencies; works in JetPK admin shell without Bootstrap.
 */
(function (global) {
    'use strict';

    var SCOPED_PROVIDERS = ['sabre', 'iati', 'pia_ndc', 'airblue'];

    function panelKeyForProvider(provider) {
        if (SCOPED_PROVIDERS.indexOf(provider) !== -1) {
            return provider;
        }
        return provider ? 'generic' : '';
    }

    function isSensitiveKey(key, meta) {
        if (meta && meta.type === 'password') return true;
        return ['access_token', 'client_secret', 'password', 'token', 'api_key', 'secret', 'agent_password'].indexOf(key) !== -1;
    }

    function isSemiSensitiveKey(key) {
        return ['username', 'agency_id', 'mco_invoice_number', 'sign_in', 'client_id', 'client_key', 'auth_code', 'organization_id', 'agent_id', 'agent_type', 'agency_name'].indexOf(key) !== -1;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function init(boot) {
        var select = document.querySelector('[data-provider-select]');
        if (!select) return;

        var container = document.querySelector('[data-credentials-container]');
        var duffelHelp = document.querySelector('[data-duffel-help]');
        var providerConfigSection = document.querySelector('[data-supplier-provider-config]');
        var credentialsSection = document.querySelector('[data-supplier-credentials-section]');
        var advancedWrap = document.querySelector('[data-supplier-advanced-wrap]');
        var sabreMasked = document.querySelector('[data-sabre-masked-summary]');

        var providerConfig = boot.providerCredentialConfig || {};
        var credentialFieldStatesByProvider = boot.credentialFieldStatesByProvider || {};
        var oldCredentials = boot.oldCredentials || {};
        var isEdit = !!boot.isEdit;

        function editOptionalField(provider, key, state) {
            if (!isEdit) return false;
            if (provider === 'duffel' && key === 'access_token') return true;
            if (provider === 'sabre' && (key === 'sign_in' || key === 'password')) return true;
            if (provider === 'iati' && key === 'secret') return true;
            if (provider === 'pia_ndc' && ['username', 'password', 'agency_id', 'agency_name', 'owner_code', 'mco_invoice_number', 'payment_type'].indexOf(key) !== -1) return true;
            if (provider === 'airblue' && ['username', 'password', 'agency_id', 'agency_name', 'owner_code', 'agent_password', 'client_id', 'client_key', 'agent_type', 'agent_id'].indexOf(key) !== -1) return true;
            return !!(state && state.has_saved);
        }

        function setPanelEnabled(panel, enabled) {
            if (!panel) return;
            panel.querySelectorAll('input, select, textarea').forEach(function (el) {
                if (el.type === 'hidden' && el.hasAttribute('data-sabre-gds-enabled-input')) return;
                el.disabled = !enabled;
            });
        }

        function togglePanelVisibility(provider) {
            var key = panelKeyForProvider(provider);
            var hasProvider = key !== '';

            [providerConfigSection, credentialsSection, advancedWrap].forEach(function (el) {
                if (el) el.classList.toggle('jp-is-hidden', !hasProvider);
            });

            document.querySelectorAll('[data-provider-panel]').forEach(function (panel) {
                var panelKey = panel.getAttribute('data-provider-panel');
                var advancedOnly = panel.getAttribute('data-advanced-only');
                if (advancedOnly) {
                    panel.classList.toggle('jp-is-hidden', provider !== advancedOnly);
                    setPanelEnabled(panel, provider === advancedOnly);
                    return;
                }
                var active = panelKey === key;
                panel.classList.toggle('jp-is-hidden', !active);
                setPanelEnabled(panel, active);
            });

            if (sabreMasked) {
                sabreMasked.classList.toggle('jp-is-hidden', provider !== 'sabre');
            }
        }

        function sabreBaseUrlForEnv(env) {
            return env === 'live' ? boot.sabreLiveUrl : boot.sabreCertUrl;
        }

        function iatiBaseUrlForEnv(env) {
            return env === 'live' ? boot.iatiLiveUrl : boot.iatiCertUrl;
        }

        function syncIatiBaseUrl() {
            var envSelect = document.querySelector('[data-iati-environment]');
            var preview = document.querySelector('[data-iati-base-url-preview]');
            var hiddenInput = document.querySelector('[data-iati-base-url-input]');
            if (!envSelect || !preview) return;
            var url = iatiBaseUrlForEnv(envSelect.value);
            preview.textContent = url;
            if (hiddenInput) hiddenInput.value = url;
        }

        function syncSabreBaseUrl() {
            var envSelect = document.querySelector('[data-sabre-environment]');
            var preview = document.querySelector('[data-sabre-base-url-preview]');
            var hiddenInput = document.querySelector('[data-sabre-base-url-input]');
            var override = document.querySelector('[data-sabre-base-url-override]');
            if (!envSelect || !preview) return;
            var url = sabreBaseUrlForEnv(envSelect.value);
            preview.textContent = url;
            if (hiddenInput && !(override && override.checked)) {
                hiddenInput.value = url;
            }
        }

        function syncSabreChannelToggles() {
            var gdsSwitch = document.querySelector('[data-sabre-gds-enabled-switch]');
            var ndcSwitch = document.querySelector('[data-sabre-ndc-enabled-switch]');
            var gdsInput = document.querySelector('[data-sabre-gds-enabled-input]');
            var ndcInput = document.querySelector('[data-sabre-ndc-enabled-input]');
            var warning = document.querySelector('[data-sabre-channels-off-warning]');
            if (gdsInput && gdsSwitch) gdsInput.value = gdsSwitch.checked ? '1' : '0';
            if (ndcInput && ndcSwitch) ndcInput.value = ndcSwitch.checked ? '1' : '0';
            if (warning && gdsSwitch && ndcSwitch) {
                warning.classList.toggle('jp-is-hidden', gdsSwitch.checked || ndcSwitch.checked);
            }
        }

        function toggleSabreBaseUrlOverride() {
            var override = document.querySelector('[data-sabre-base-url-override]');
            var wrap = document.querySelector('[data-sabre-base-url-override-wrap]');
            var overrideInput = document.querySelector('[data-sabre-base-url-override-input]');
            var hiddenInput = document.querySelector('[data-sabre-base-url-input]');
            if (!override || !wrap || !overrideInput) return;
            var enabled = override.checked;
            wrap.classList.toggle('jp-is-hidden', !enabled);
            overrideInput.disabled = !enabled;
            if (hiddenInput) hiddenInput.disabled = enabled;
            if (!enabled) syncSabreBaseUrl();
        }

        function syncAirblueBaseUrl() {
            var envSelect = document.querySelector('[data-airblue-environment]');
            var channelSelect = document.querySelector('[data-airblue-channel]');
            var baseInput = document.querySelector('[data-airblue-base-url]');
            var wsdlHint = document.querySelector('[data-airblue-wsdl-hint]');
            if (!envSelect || !baseInput) return;
            var channel = channelSelect ? channelSelect.value : 'crane_ndc';
            var isLive = envSelect.value === 'live';
            var url = channel === 'zapways_ota'
                ? (isLive ? boot.airblueOtaLiveUrl : boot.airblueOtaCertUrl)
                : boot.airblueNdcUrl;
            if (!isEdit || baseInput.value.trim() === '') {
                baseInput.value = url;
            }
            if (wsdlHint) {
                wsdlHint.classList.toggle('jp-is-hidden', channel !== 'crane_ndc');
            }
        }

        function renderCredentials(provider) {
            if (!container) return;
            var fields = (providerConfig[provider] && providerConfig[provider].fields) ? providerConfig[provider].fields : {};
            var providerStates = credentialFieldStatesByProvider[provider] || {};
            var html = '';
            Object.keys(fields).forEach(function (key) {
                var meta = fields[key] || {};
                if (provider === 'airblue' && key === 'api_channel') return;
                if (provider === 'airblue' && meta.channel) {
                    var activeChannel = (document.querySelector('[data-airblue-channel]') || {}).value || 'crane_ndc';
                    if (meta.channel !== activeChannel) return;
                }
                var state = providerStates[key] || {};
                var label = meta.label || key.replace(/_/g, ' ').replace(/\b\w/g, function (ch) { return ch.toUpperCase(); });
                var type = meta.type || 'text';
                var placeholder = meta.placeholder || '';
                var value = oldCredentials[key] || state.prefill_value || '';
                if (state.has_saved && (isSensitiveKey(key, meta) || isSemiSensitiveKey(key))) {
                    placeholder = state.placeholder || 'Saved — leave blank to keep existing value.';
                    if (!oldCredentials[key]) value = '';
                }
                var required = (meta.required && !editOptionalField(provider, key, state)) ? 'required' : '';
                var help = meta.help || '';
                if (state.preserve_hint) {
                    help = 'Saved value exists. Leave blank to keep it, or enter a new value.';
                } else if (!help && provider === 'duffel' && key === 'access_token') {
                    help = 'Leave blank to keep existing token.';
                }
                var badge = '';
                if (state.has_saved && state.masked_label) {
                    badge = ' <span class="badge bg-secondary-lt">Saved: ' + escapeHtml(state.masked_label) + '</span>';
                } else if (state.has_saved) {
                    badge = ' <span class="badge bg-secondary-lt">Saved</span>';
                }
                var fieldClass = 'jp-field' + (type === 'password' || isSensitiveKey(key, meta) ? ' jp-secret-field' : '');
                if (key === 'pcc') fieldClass += ' jp-field--credential-row2';
                html += '<div class="' + fieldClass + '" data-credential-field="' + escapeHtml(key) + '">';
                html += '<label class="form-label jp-label">' + escapeHtml(label) + badge + '</label>';
                html += '<input type="' + escapeHtml(type) + '" name="credentials[' + escapeHtml(key) + ']" class="form-control jp-control" value="' + escapeHtml(value) + '" placeholder="' + escapeHtml(placeholder) + '" ' + required + ' autocomplete="off">';
                if (help) html += '<p class="form-hint">' + escapeHtml(help) + '</p>';
                html += '</div>';
            });
            container.innerHTML = html;
            if (duffelHelp) duffelHelp.classList.toggle('jp-is-hidden', provider !== 'duffel');
        }

        function applyProvider(provider) {
            togglePanelVisibility(provider);
            if (provider === 'sabre') {
                syncSabreBaseUrl();
                syncSabreChannelToggles();
            }
            if (provider === 'iati') {
                syncIatiBaseUrl();
                if (!isEdit) {
                    var nameInput = document.querySelector('[data-connection-name]');
                    if (nameInput && nameInput.value.trim() === '') nameInput.value = boot.defaultIatiName || '';
                }
            }
            if (provider === 'pia_ndc' && !isEdit) {
                var piaName = document.querySelector('[data-connection-name]');
                if (piaName && piaName.value.trim() === '') piaName.value = boot.defaultPiaNdcName || '';
            }
            if (provider === 'airblue') {
                syncAirblueBaseUrl();
                if (!isEdit) {
                    var airName = document.querySelector('[data-connection-name]');
                    if (airName && airName.value.trim() === '') airName.value = boot.defaultAirBlueName || '';
                }
            }
            renderCredentials(provider);
        }

        select.addEventListener('change', function () {
            applyProvider(select.value);
        });

        var sabreEnv = document.querySelector('[data-sabre-environment]');
        if (sabreEnv) sabreEnv.addEventListener('change', syncSabreBaseUrl);
        var iatiEnv = document.querySelector('[data-iati-environment]');
        if (iatiEnv) iatiEnv.addEventListener('change', syncIatiBaseUrl);
        var airblueEnv = document.querySelector('[data-airblue-environment]');
        if (airblueEnv) {
            airblueEnv.addEventListener('change', function () {
                syncAirblueBaseUrl();
                if (select.value === 'airblue') applyProvider('airblue');
            });
        }
        var airblueChannel = document.querySelector('[data-airblue-channel]');
        if (airblueChannel) {
            airblueChannel.addEventListener('change', function () {
                syncAirblueBaseUrl();
                if (select.value === 'airblue') applyProvider('airblue');
            });
        }
        var sabreGdsSwitch = document.querySelector('[data-sabre-gds-enabled-switch]');
        if (sabreGdsSwitch) sabreGdsSwitch.addEventListener('change', syncSabreChannelToggles);
        var sabreNdcSwitch = document.querySelector('[data-sabre-ndc-enabled-switch]');
        if (sabreNdcSwitch) sabreNdcSwitch.addEventListener('change', syncSabreChannelToggles);
        var sabreOverride = document.querySelector('[data-sabre-base-url-override]');
        if (sabreOverride) sabreOverride.addEventListener('change', toggleSabreBaseUrlOverride);

        applyProvider(select.value);
        toggleSabreBaseUrlOverride();
        bindDeleteModal();
    }

    function bindDeleteModal() {
        var modal = document.getElementById('ota-delete-connection-modal');
        if (!modal) return;
        var openBtn = document.querySelector('[data-open-delete-confirm]');
        var closeButtons = modal.querySelectorAll('[data-close-delete-confirm]');
        var lastFocused = null;
        function openModal() {
            lastFocused = document.activeElement;
            modal.hidden = false;
            document.body.classList.add('overflow-hidden');
        }
        function closeModal() {
            modal.hidden = true;
            document.body.classList.remove('overflow-hidden');
            if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
        }
        if (openBtn) openBtn.addEventListener('click', openModal);
        closeButtons.forEach(function (btn) { btn.addEventListener('click', closeModal); });
        document.addEventListener('keydown', function (event) {
            if (modal.hidden || event.key !== 'Escape') return;
            event.preventDefault();
            closeModal();
        });
    }

    global.JetPkSupplierForm = { init: init };
})(window);
