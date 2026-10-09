(() => {
    'use strict';

    const POLL_INTERVAL_MS = 15_000;
    const REQUEST_TIMEOUT_MS = 8_000;
    const ERROR_MESSAGE = 'Automatic refresh failed. ARM is disabled until a current eligibility check succeeds.';
    const FAILURE_CODES = ['timeout', 'network', 'http', 'invalid_response', 'unknown'];
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
    let lastFreeMode = false;
    let rememberedFreePlayerExclusion = null;
    const reportedFailures = new Set();

    function reportRefreshFailure(code) {
        const logsUrl = documentRef.body.dataset.logsUrl;
        const csrf = documentRef.querySelectorAll('input[name="csrf"]')[0]?.value;
        if (!FAILURE_CODES.includes(code) || !logsUrl || typeof csrf !== 'string' || csrf === '' || reportedFailures.has(code)) {
            return;
        }
        reportedFailures.add(code);
        const body = new globalThis.URLSearchParams({ action: 'client_report', code, csrf });
        try {
            void globalThis.fetch(logsUrl, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'text/plain', 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body: body.toString(),
                keepalive: true,
            }).catch(() => {});
        } catch {
        }
    }

    function available(select, value) {
        return value !== '' && Array.from(select.options).some((option) => option.value === value);
    }

    function isSelectElement(element) {
        const options = element?.options;
        return element?.tagName === 'SELECT' && options !== undefined && options !== null
            && Number.isInteger(options.length) && typeof element.value === 'string'
            && typeof element.replaceChildren === 'function';
    }

    // Optional group members (0.1.11). Absent controls are simply skipped.
    const EXTRA_MEMBERS = ['#actor-c', '#actor-d'];

    function extraMembersValid(actorA, actorB) {
        const chosen = [actorA.value, actorB.value];
        for (const selector of EXTRA_MEMBERS) {
            const select = documentRef.querySelector(selector);
            if (!select || select.value === '') {
                continue;
            }
            if (!available(select, select.value) || chosen.includes(select.value)) {
                return false;
            }
            chosen.push(select.value);
        }
        return true;
    }

    function freeChecked() {
        return Boolean(documentRef.querySelector('#free-mode')?.checked);
    }

    function eligibleOptionCount(select) {
        return Array.from(select.options).filter((option) => option.value !== '').length;
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
        if (freeChecked()) {
            // Free scene (0.1.12): members are chosen at activation; ARM needs two eligible NPCs.
            armButton.disabled = !serverAllowsArm || eligibleOptionCount(actorA) < 2;
            return;
        }
        const hasA = available(actorA, actorA.value);
        const hasPair = available(actorB, actorB.value) && actorA.value !== actorB.value && extraMembersValid(actorA, actorB);
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
        const free = !solo && freeChecked();
        const rosterReady = serverAllowsArm;
        const hasTwo = eligibleOptionCount(actorA) >= 2;
        actorA.disabled = !rosterReady || free;
        actorB.disabled = !rosterReady || !hasTwo || solo || free;
        for (const selector of [...EXTRA_MEMBERS, '#opener']) {
            const control = documentRef.querySelector(selector);
            if (control) {
                control.disabled = !rosterReady || !hasTwo || solo || free;
            }
        }
        soloMode.disabled = !rosterReady;
        const freeMode = documentRef.querySelector('#free-mode');
        if (freeMode) {
            freeMode.disabled = !rosterReady;
        }
        // 0.1.14: free size only for free scenes; turn length for everything except solo.
        const freeCap = documentRef.querySelector('#free-cap');
        if (freeCap) {
            freeCap.disabled = !free;
        }
        const pace = documentRef.querySelector('#pace');
        if (pace) {
            pace.disabled = solo;
        }
        // 0.1.16: solo has no one to brawl with.
        const physicalActions = documentRef.querySelector('#actions-physical');
        if (physicalActions) {
            physicalActions.disabled = solo;
        }
        if (bystanderMode) {
            bystanderMode.disabled = false;
        }
        if (playerCheckbox) {
            if (solo || free) {
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
            armHeading.textContent = solo ? 'Solo reflection' : (free ? 'Free scene' : 'Choose the pair');
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
        const freeMode = documentRef.querySelector('#free-mode');
        if (solo && freeMode?.checked) {
            // Solo and free are exclusive: leaving free restores the player draft first.
            freeMode.checked = false;
            leaveFree(playerCheckbox);
        }
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

    function leaveFree(playerCheckbox) {
        if (lastFreeMode && rememberedFreePlayerExclusion !== null) {
            playerCheckbox.checked = rememberedFreePlayerExclusion;
        }
        rememberedFreePlayerExclusion = null;
        lastFreeMode = false;
    }

    function handleFreeChange() {
        const freeMode = documentRef.querySelector('#free-mode');
        const soloMode = documentRef.querySelector('#solo-mode');
        const playerCheckbox = documentRef.querySelector('input[name="exclude_player"]');
        if (!freeMode || !playerCheckbox) {
            return;
        }
        if (freeMode.checked && soloMode?.checked) {
            soloMode.checked = false;
            handleModeChange();
        }
        if (freeMode.checked && !lastFreeMode) {
            rememberedFreePlayerExclusion = playerCheckbox.checked;
            lastFreeMode = true;
        } else if (!freeMode.checked) {
            leaveFree(playerCheckbox);
        }
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

        if (!isSelectElement(currentA) || !isSelectElement(currentB) || !currentSoloMode || !isSelectElement(nextA)
            || !isSelectElement(nextB) || !nextBadge || !nextStatus
            || !nextNote || !nextNotice || !currentBadge || !currentStatus || !currentNote
            || !currentNotice || !currentArm || !nextArm || nextCsrfInputs.length === 0
            || currentCsrfInputs.length === 0 || typeof snapshot.body.dataset.playthroughRef !== 'string') {
            throw new Error('Incomplete refresh response.');
        }

        const actorAOptions = Array.from(nextA.options, (option) => option.cloneNode(true));
        const actorBOptions = Array.from(nextB.options, (option) => option.cloneNode(true));
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
        currentA.replaceChildren(...actorAOptions);
        currentB.replaceChildren(...actorBOptions);
        currentA.value = available(currentA, selectedA) ? selectedA : '';
        currentB.value = available(currentB, selectedB) && selectedB !== currentA.value ? selectedB : '';

        if (currentSoloMode.checked) {
            if (!available(currentB, rememberedActorB) || rememberedActorB === currentA.value) {
                rememberedActorB = '';
            }
        } else if (currentB.value === '') {
            rememberedActorB = '';
        }

        // Optional group controls: refresh options and keep a draft only while it is eligible and distinct.
        const taken = [currentA.value, currentB.value];
        for (const selector of [...EXTRA_MEMBERS, '#opener']) {
            const current = documentRef.querySelector(selector);
            const next = snapshot.querySelector(selector);
            if (!isSelectElement(current) || !isSelectElement(next)) {
                continue;
            }
            const draft = identityChanged ? '' : current.value;
            current.replaceChildren(...Array.from(next.options, (option) => option.cloneNode(true)));
            if (selector === '#opener') {
                current.value = available(current, draft) ? draft : 'auto';
                continue;
            }
            current.value = available(current, draft) && !taken.includes(draft) ? draft : '';
            if (current.value !== '') {
                taken.push(current.value);
            }
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
        let phase = 'network';
        try {
            const response = await globalThis.fetch(documentRef.body.dataset.refreshUrl, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'text/html' },
                signal: controller.signal,
            });
            phase = 'response';
            if (!response.ok) {
                phase = 'http';
                throw new Error('Refresh request failed.');
            }
            phase = 'invalid_response';
            const html = await response.text();
            const snapshot = new DOMParser().parseFromString(html, 'text/html');
            applySnapshot(snapshot);
            reportedFailures.clear();
        } catch (error) {
            if (documentRef.visibilityState === 'visible') {
                setRefreshError();
                const code = controller.signal.aborted || error?.name === 'AbortError'
                    ? 'timeout'
                    : phase === 'http' ? 'http'
                        : phase === 'network' ? (error?.name === 'TypeError' ? 'network' : 'unknown')
                            : 'invalid_response';
                reportRefreshFailure(code);
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
    lastFreeMode = freeChecked();
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
    for (const selector of EXTRA_MEMBERS) {
        documentRef.querySelector(selector)?.addEventListener('change', updateArmButton);
    }
    documentRef.querySelector('#solo-mode')?.addEventListener('change', handleModeChange);
    documentRef.querySelector('#free-mode')?.addEventListener('change', handleFreeChange);
    void refresh();
})();
