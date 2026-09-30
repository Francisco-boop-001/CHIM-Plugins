(() => {
    'use strict';

    const POLL_INTERVAL_MS = 15_000;
    const REQUEST_TIMEOUT_MS = 8_000;
    const ERROR_MESSAGE = 'Automatic refresh failed. ARM is disabled until a current eligibility check succeeds.';
    const documentRef = globalThis.document;
    if (!documentRef || !documentRef.body || !documentRef.body.dataset.refreshUrl) {
        return;
    }

    let inFlight = false;
    let refreshAgain = false;
    let serverAllowsArm = false;
    let rememberedActorB = '';
    let rememberedPlayerExclusion = null;
    let lastSoloMode = false;

    function available(select, value) {
        return value !== '' && Array.from(select.options).some((option) => option.value === value);
    }

    function updateArmButton() {
        const armButton = documentRef.querySelector('#arm-button');
        const actorA = documentRef.querySelector('#actor-a');
        const actorB = documentRef.querySelector('#actor-b');
        const soloMode = documentRef.querySelector('#solo-mode');
        if (!armButton || !actorA || !actorB || !soloMode) {
            return;
        }
        const solo = soloMode.checked;
        const hasA = available(actorA, actorA.value);
        const hasPair = available(actorB, actorB.value) && actorA.value !== actorB.value;
        armButton.disabled = !serverAllowsArm || !hasA || (!solo && !hasPair);
    }

    function updateModePresentation() {
        const soloMode = documentRef.querySelector('#solo-mode');
        const actorA = documentRef.querySelector('#actor-a');
        const actorB = documentRef.querySelector('#actor-b');
        const actorALabel = documentRef.querySelector('#actor-a-label');
        const actorBLabel = documentRef.querySelector('#actor-b-label');
        const armHeading = documentRef.querySelector('#arm-heading');
        const pageIntro = documentRef.querySelector('#page-intro');
        const modeGuidance = documentRef.querySelector('#mode-guidance');
        const armButton = documentRef.querySelector('#arm-button');
        const bystanderMode = documentRef.querySelector('#bystander-mode');
        const playerCheckbox = documentRef.querySelector('input[name="exclude_player"]');
        if (!soloMode || !actorA || !actorB) {
            return;
        }

        const solo = soloMode.checked;
        const rosterReady = serverAllowsArm;
        const hasTwo = Array.from(actorA.options).filter((option) => option.value !== '').length >= 2;
        actorA.disabled = !rosterReady;
        actorB.disabled = !rosterReady || !hasTwo || solo;
        soloMode.disabled = !rosterReady;
        if (bystanderMode) {
            bystanderMode.disabled = false;
        }
        if (playerCheckbox) {
            if (solo) {
                playerCheckbox.checked = true;
                playerCheckbox.disabled = true;
            } else {
                playerCheckbox.disabled = false;
            }
        }
        if (actorALabel) {
            actorALabel.textContent = solo ? 'Reflecting NPC' : 'NPC A';
        }
        if (actorBLabel) {
            actorBLabel.textContent = solo ? 'Second NPC (pair mode only)' : 'NPC B';
        }
        if (armHeading) {
            armHeading.textContent = solo ? 'Solo reflection' : 'Choose the pair';
        }
        if (pageIntro) {
            pageIntro.textContent = solo
                ? 'Choose one NPC to think aloud. CHIM carries that scene direction into the next eligible ordinary Standard-mode input.'
                : 'Choose two voices for a conversation, or one NPC to think aloud. CHIM carries that scene direction into the next eligible ordinary Standard-mode input.';
        }
        if (modeGuidance) {
            modeGuidance.hidden = !solo;
        }
        if (armButton) {
            armButton.textContent = solo ? 'Arm reflection on next input' : 'Arm or update on next input';
        }
        updateArmButton();
    }

    function handleModeChange() {
        const soloMode = documentRef.querySelector('#solo-mode');
        const actorA = documentRef.querySelector('#actor-a');
        const actorB = documentRef.querySelector('#actor-b');
        const playerCheckbox = documentRef.querySelector('input[name="exclude_player"]');
        if (!soloMode || !actorA || !actorB || !playerCheckbox) {
            return;
        }

        const solo = soloMode.checked;
        if (solo && !lastSoloMode) {
            rememberedActorB = available(actorB, actorB.value) && actorB.value !== actorA.value
                ? actorB.value : '';
            rememberedPlayerExclusion = playerCheckbox.checked;
            actorB.value = '';
        } else if (!solo && lastSoloMode) {
            actorB.value = available(actorB, rememberedActorB) && rememberedActorB !== actorA.value
                ? rememberedActorB : '';
            if (rememberedPlayerExclusion !== null) {
                playerCheckbox.checked = rememberedPlayerExclusion;
            }
            rememberedActorB = '';
            rememberedPlayerExclusion = null;
        }
        lastSoloMode = solo;
        updateModePresentation();
    }

    function setRefreshError() {
        const note = documentRef.querySelector('#picker-status');
        serverAllowsArm = false;
        updateModePresentation();
        if (note) {
            note.textContent = ERROR_MESSAGE;
        }
    }

    function applySnapshot(snapshot) {
        const currentA = documentRef.querySelector('#actor-a');
        const currentB = documentRef.querySelector('#actor-b');
        const currentSoloMode = documentRef.querySelector('#solo-mode');
        const nextA = snapshot.querySelector('#actor-a');
        const nextB = snapshot.querySelector('#actor-b');
        const nextBadge = snapshot.querySelector('.status-badge');
        const nextStatus = snapshot.querySelector('.status-panel');
        const nextNote = snapshot.querySelector('#picker-status');
        const nextNotice = snapshot.querySelector('#page-notice-container');
        const currentBadge = documentRef.querySelector('.status-badge');
        const currentStatus = documentRef.querySelector('.status-panel');
        const currentNote = documentRef.querySelector('#picker-status');
        const currentNotice = documentRef.querySelector('#page-notice-container');
        const currentArm = documentRef.querySelector('#arm-button');
        const nextArm = snapshot.querySelector('#arm-button');
        const nextCsrfInputs = snapshot.querySelectorAll('input[name="csrf"]');
        const currentCsrfInputs = documentRef.querySelectorAll('input[name="csrf"]');

        if (!currentA || !currentB || !currentSoloMode || !nextA || !nextB || !nextBadge || !nextStatus
            || !nextNote || !nextNotice || !currentBadge || !currentStatus || !currentNote
            || !currentNotice || !currentArm || !nextArm || nextCsrfInputs.length === 0
            || currentCsrfInputs.length === 0 || typeof snapshot.body.dataset.playthroughRef !== 'string') {
            throw new Error('Incomplete refresh response.');
        }

        const identityChanged = documentRef.body.dataset.playthroughRef !== snapshot.body.dataset.playthroughRef;
        if (identityChanged) {
            if (currentSoloMode.checked) {
                const playerCheckbox = documentRef.querySelector('input[name="exclude_player"]');
                if (playerCheckbox && rememberedPlayerExclusion !== null) {
                    playerCheckbox.checked = rememberedPlayerExclusion;
                }
            }
            currentSoloMode.checked = false;
            rememberedActorB = '';
            rememberedPlayerExclusion = null;
            lastSoloMode = false;
        }
        const selectedA = identityChanged ? '' : currentA.value;
        const selectedB = identityChanged || currentSoloMode.checked ? '' : currentB.value;
        currentA.replaceChildren(...Array.from(nextA.options, (option) => option.cloneNode(true)));
        currentB.replaceChildren(...Array.from(nextB.options, (option) => option.cloneNode(true)));
        currentA.value = available(currentA, selectedA) ? selectedA : '';
        currentB.value = available(currentB, selectedB) && selectedB !== currentA.value ? selectedB : '';

        if (currentSoloMode.checked) {
            if (!available(currentB, rememberedActorB) || rememberedActorB === currentA.value) {
                rememberedActorB = '';
            }
        } else if (currentB.value === '') {
            rememberedActorB = '';
        }

        currentBadge.className = nextBadge.className;
        currentBadge.textContent = nextBadge.textContent;
        currentStatus.innerHTML = nextStatus.innerHTML;
        currentNote.textContent = nextNote.textContent;
        currentNotice.innerHTML = nextNotice.innerHTML;
        for (const input of currentCsrfInputs) {
            input.value = nextCsrfInputs[0].value;
        }
        documentRef.body.dataset.playthroughRef = snapshot.body.dataset.playthroughRef;
        serverAllowsArm = nextArm.dataset.rosterReady === '1';
        updateModePresentation();
    }

    async function refresh() {
        if (documentRef.visibilityState !== 'visible') {
            return;
        }
        if (inFlight) {
            refreshAgain = true;
            return;
        }

        inFlight = true;
        const controller = new AbortController();
        const timeout = globalThis.setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);
        try {
            const response = await globalThis.fetch(documentRef.body.dataset.refreshUrl, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'text/html' },
                signal: controller.signal,
            });
            if (!response.ok) {
                throw new Error('Refresh request failed.');
            }
            const html = await response.text();
            const snapshot = new DOMParser().parseFromString(html, 'text/html');
            applySnapshot(snapshot);
        } catch {
            if (documentRef.visibilityState === 'visible') {
                setRefreshError();
            }
        } finally {
            globalThis.clearTimeout(timeout);
            inFlight = false;
            if (refreshAgain && documentRef.visibilityState === 'visible') {
                refreshAgain = false;
                void refresh();
            }
        }
    }

    const initialArm = documentRef.querySelector('#arm-button');
    serverAllowsArm = Boolean(initialArm && initialArm.dataset.rosterReady === '1');
    lastSoloMode = Boolean(documentRef.querySelector('#solo-mode')?.checked);
    updateModePresentation();

    globalThis.setInterval(() => {
        void refresh();
    }, POLL_INTERVAL_MS);
    documentRef.addEventListener('visibilitychange', () => {
        if (documentRef.visibilityState === 'visible') {
            serverAllowsArm = false;
            updateModePresentation();
            const note = documentRef.querySelector('#picker-status');
            if (note) {
                note.textContent = 'Checking the latest eligibility report; ARM is temporarily disabled.';
            }
            void refresh();
        }
    });
    documentRef.querySelector('#actor-a')?.addEventListener('change', updateArmButton);
    documentRef.querySelector('#actor-b')?.addEventListener('change', updateArmButton);
    documentRef.querySelector('#solo-mode')?.addEventListener('change', handleModeChange);
    void refresh();
})();
