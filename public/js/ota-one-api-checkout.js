/**
 * One API checkout extras — catalog load, selection IDs, final price (no client amounts).
 */
(function () {
    const root = document.querySelector('[data-one-api-checkout]');
    if (!root) {
        return;
    }

    const contextId = root.getAttribute('data-workflow-context-id') || '';
    const connectionId = root.getAttribute('data-supplier-connection-id') || '';
    const catalogUrl = root.getAttribute('data-catalog-url') || '';
    const finalPriceUrl = root.getAttribute('data-final-price-url') || '';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const statusEl = root.querySelector('[data-one-api-status]');
    const continueBtn = document.querySelector('[data-one-api-continue]');

    let catalogState = null;
    let submitting = false;
    let finalConfirmed = false;
    let catalogLoading = false;

    function setStatus(message, isError) {
        if (!statusEl) {
            return;
        }
        statusEl.textContent = message;
        statusEl.classList.toggle('text-danger', !!isError);
    }

    function setContinueEnabled(enabled) {
        if (continueBtn) {
            continueBtn.disabled = !enabled;
        }
    }

    function updateContinueGate() {
        setContinueEnabled(finalConfirmed && !submitting && !catalogLoading);
    }

    function groupBy(items, keyFn) {
        const groups = {};
        (items || []).forEach((item) => {
            const key = keyFn(item);
            if (!groups[key]) {
                groups[key] = [];
            }
            groups[key].push(item);
        });
        return groups;
    }

    function renderOptions(host, title, groups, inputType, namePrefix) {
        if (!host) {
            return;
        }
        host.innerHTML = '';
        const heading = document.createElement('h3');
        heading.className = 'h6';
        heading.textContent = title;
        host.appendChild(heading);
        Object.keys(groups).forEach((groupKey) => {
            const section = document.createElement('div');
            section.className = 'mb-2';
            const cap = document.createElement('div');
            cap.className = 'small text-secondary mb-1';
            cap.textContent = groupKey;
            section.appendChild(cap);
            groups[groupKey].forEach((opt) => {
                const label = document.createElement('label');
                label.className = 'd-block mb-1';
                const input = document.createElement('input');
                input.type = inputType;
                input.name = namePrefix;
                input.value = opt.selection_id;
                input.dataset.oneApiSelection = '1';
                if (opt.available === false) {
                    input.disabled = true;
                }
                const price = opt.amount && opt.currency ? ` (${opt.amount} ${opt.currency})` : (opt.included_price ? ' (included)' : '');
                label.appendChild(input);
                label.appendChild(document.createTextNode(` ${opt.label || opt.seat_number || opt.selection_id}${price}`));
                section.appendChild(label);
            });
            host.appendChild(section);
        });
    }

    function renderCatalog(data) {
        catalogState = data;
        const bundleGroups = groupBy(data.bundles || [], (b) => (b.group === 'inbound' ? 'Inbound bundle' : 'Outbound bundle'));
        renderOptions(root.querySelector('[data-one-api-bundles]'), 'Fare bundles', bundleGroups, 'radio', 'one_api_bundle');

        const baggageGroups = groupBy(data.baggage || [], (b) => `Passenger ${b.passenger_ref} · segment ${b.segment_ref}`);
        renderOptions(root.querySelector('[data-one-api-baggage]'), 'Baggage', baggageGroups, 'checkbox', 'one_api_baggage');

        const mealGroups = groupBy(data.meals || [], (m) => `Passenger ${m.passenger_ref} · segment ${m.segment_ref}`);
        renderOptions(root.querySelector('[data-one-api-meals]'), 'Meals', mealGroups, 'checkbox', 'one_api_meal');

        const seatGroups = groupBy(data.seats || [], (s) => `Passenger ${s.passenger_ref} · segment ${s.segment_ref}`);
        renderOptions(root.querySelector('[data-one-api-seats]'), 'Seats', seatGroups, 'radio', `one_api_seat_${Math.random()}`);
        root.querySelectorAll('[data-one-api-seats] input[type=radio]').forEach((input) => {
            input.name = 'one_api_seat';
        });
    }

    function selectedIds(selector) {
        return Array.from(root.querySelectorAll(selector))
            .filter((el) => el.checked && !el.disabled)
            .map((el) => el.value);
    }

    async function loadCatalog() {
        if (!contextId || !connectionId || !catalogUrl) {
            return;
        }
        catalogLoading = true;
        updateContinueGate();
        setStatus('Loading extras…', false);
        try {
            const url = new URL(catalogUrl, window.location.origin);
            url.searchParams.set('workflow_context_id', contextId);
            url.searchParams.set('supplier_connection_id', connectionId);
            const res = await fetch(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = await res.json();
            if (!res.ok) {
                throw new Error(data.message || data.error || 'Unable to load extras.');
            }
            renderCatalog(data);
            setStatus('Select options, then confirm final price.', false);
        } catch (err) {
            setStatus(err.message || 'Failed to load extras.', true);
        } finally {
            catalogLoading = false;
            updateContinueGate();
        }
    }

    async function submitFinalPrice() {
        if (submitting || finalConfirmed) {
            return;
        }
        submitting = true;
        updateContinueGate();
        setStatus('Confirming final price with airline…', false);
        try {
            const bundleIds = selectedIds('input[name="one_api_bundle"]:checked');
            const baggageIds = selectedIds('input[name="one_api_baggage"]:checked');
            const mealIds = selectedIds('input[name="one_api_meal"]:checked');
            const seatIds = selectedIds('input[name="one_api_seat"]:checked');

            const res = await fetch(finalPriceUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    workflow_context_id: contextId,
                    supplier_connection_id: Number(connectionId),
                    bundle_selection_ids: bundleIds,
                    baggage_selection_ids: baggageIds,
                    meal_selection_ids: mealIds,
                    seat_selection_ids: seatIds,
                }),
            });
            const data = await res.json();
            if (!res.ok) {
                throw new Error(data.message || data.error || 'Final price failed.');
            }
            finalConfirmed = !!data.final_price_confirmed;
            if (data.supplier_settlement) {
                setStatus(
                    `Final price confirmed: ${data.supplier_settlement.amount} ${data.supplier_settlement.currency}. You may continue.`,
                    false,
                );
            } else if (finalConfirmed) {
                setStatus('Final price confirmed. You may continue.', false);
            } else {
                throw new Error('Final price not confirmed.');
            }
        } catch (err) {
            setStatus(err.message || 'Final price failed.', true);
            finalConfirmed = false;
        } finally {
            submitting = false;
            updateContinueGate();
        }
    }

    root.addEventListener('click', (event) => {
        const target = event.target;
        if (target && target.matches('[data-one-api-confirm-price]')) {
            event.preventDefault();
            submitFinalPrice();
        }
    });

    root.addEventListener('change', (event) => {
        if (event.target && event.target.matches('[data-one-api-selection]')) {
            finalConfirmed = false;
            updateContinueGate();
        }
    });

    const passengerForm = document.getElementById('ota-checkout-passengers-form');
    if (passengerForm && continueBtn) {
        passengerForm.addEventListener('submit', (event) => {
            if (!finalConfirmed) {
                event.preventDefault();
                setStatus('Confirm final price with the airline before continuing.', true);
            }
        });
    }

    finalConfirmed = false;
    updateContinueGate();
    loadCatalog();
})();
