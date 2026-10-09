import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const script = await readFile(new URL('../server/assets/ui-refresh.js', import.meta.url), 'utf8');

function makeOption(value) {
    return { value, cloneNode: () => makeOption(value) };
}

function makeSelect(values, selected = '') {
    const listeners = new Map();
    return {
        tagName: 'SELECT',
        options: values.map(makeOption),
        value: selected,
        disabled: false,
        addEventListener(name, callback) {
            listeners.set(name, callback);
        },
        dispatchChange() {
            listeners.get('change')?.();
        },
        replaceChildren(...options) {
            this.options = options;
            this.value = '';
        },
    };
}

function makeCheckbox(checked = false) {
    const listeners = new Map();
    return {
        checked,
        disabled: false,
        addEventListener(name, callback) {
            listeners.set(name, callback);
        },
        dispatchChange() {
            listeners.get('change')?.();
        },
    };
}

function makeSnapshot({ ref = 'same-playthrough', a = ['101', '202'], b = ['101', '202'], disabled = false, rosterReady = true, solo = false, csrf = 'fresh-token', label = 'Current', group = null } = {}) {
    const values = new Map([
        ...(group === null ? [] : [
            ['#actor-c', makeSelect(['', ...group])],
            ['#actor-d', makeSelect(['', ...group])],
            ['#opener', makeSelect(['auto', ...group])],
        ]),
        ['#actor-a', makeSelect(['', ...a])],
        ['#actor-b', makeSelect(['', ...b])],
        ['#solo-mode', makeCheckbox(solo)],
        ['#actor-a-label', { textContent: solo ? 'Reflecting NPC' : 'NPC A' }],
        ['#actor-b-label', { textContent: solo ? 'Second NPC (pair mode only)' : 'NPC B' }],
        ['#arm-heading', { textContent: solo ? 'Solo reflection' : 'Choose the pair' }],
        ['#page-intro', { textContent: solo ? 'Choose one NPC to reflect aloud.' : 'Choose two voices for a conversation.' }],
        ['#mode-guidance', { hidden: !solo, textContent: 'Solo reflection asks the NPC to think aloud. Opinion changes require compatible Mind Poisoning support.' }],
        ['.status-badge', { className: 'status-active', textContent: 'Active' }],
        ['.status-panel', { innerHTML: `<p>${label} status</p>` }],
        ['#picker-status', { textContent: `${label} eligibility` }],
        ['#page-notice-container', { innerHTML: `<p>${label} notice</p>` }],
        ['#arm-button', { disabled, dataset: { rosterReady: rosterReady ? '1' : '0' } }],
    ]);
    const csrfInputs = [{ value: csrf }, { value: csrf }];
    return {
        body: { dataset: { playthroughRef: ref } },
        querySelector: (selector) => values.get(selector) ?? null,
        querySelectorAll: (selector) => selector === 'input[name="csrf"]' ? csrfInputs : [],
    };
}

function responseFor(fixtures, id, snapshot) {
    fixtures.set(id, snapshot);
    return { ok: true, text: async () => id };
}

function createHarness(fetchQueue, { reportQueue = [], group = false, free = false } = {}) {
    const fixtures = new Map();
    const calls = [];
    const reports = [];
    const timeouts = [];
    let intervalCallback;
    const listeners = new Map();
    const actorA = makeSelect(['', '101', '202'], '101');
    const actorB = makeSelect(['', '101', '202'], '202');
    const soloMode = makeCheckbox(false);
    const csrfInputs = [{ value: 'old-token' }, { value: 'old-token' }];
    const nodes = new Map([
        ['#actor-a', actorA],
        ['#actor-b', actorB],
        ['#solo-mode', soloMode],
        ['#actor-a-label', { textContent: 'NPC A' }],
        ['#actor-b-label', { textContent: 'NPC B' }],
        ['#arm-heading', { textContent: 'Choose the pair' }],
        ['#page-intro', { textContent: 'Choose two voices for a conversation.' }],
        ['#mode-guidance', { hidden: true, textContent: 'Solo reflection asks the NPC to think aloud. Opinion changes require compatible Mind Poisoning support.' }],
        ['.status-badge', { className: 'status-off', textContent: 'Off' }],
        ['.status-panel', { innerHTML: '<p>Old status</p>' }],
        ['#picker-status', { textContent: 'Old eligibility' }],
        ['#page-notice-container', { innerHTML: '' }],
        ['#arm-button', { disabled: true, dataset: { rosterReady: '1' }, textContent: 'Arm pair on next input' }],
        ['#bystander-mode', { value: 'silent' }],
        ['input[name="exclude_player"]', { checked: true }],
        ...(group ? [
            ['#actor-c', makeSelect(['', '101', '202', '303'], '303')],
            ['#actor-d', makeSelect(['', '101', '202', '303'], '')],
            ['#opener', makeSelect(['auto', '101', '202', '303'], 'auto')],
        ] : []),
        ...(free ? [['#free-mode', makeCheckbox(false)], ['#free-cap', makeSelect(['2', '3', '4', '5', '6'], '6')], ['#pace', makeSelect(['short', 'normal', 'long'], 'normal')], ['#actions-physical', makeCheckbox(true)]] : []),
    ]);
    const documentRef = {
        body: { dataset: { playthroughRef: 'same-playthrough', refreshUrl: '?refresh=1', logsUrl: '?view=logs' } },
        visibilityState: 'visible',
        querySelector: (selector) => nodes.get(selector) ?? null,
        querySelectorAll: (selector) => selector === 'input[name="csrf"]' ? csrfInputs : [],
        addEventListener: (name, callback) => listeners.set(name, callback),
    };
    const sandbox = {
        document: documentRef,
        fetch(url, options) {
            if (url === '?view=logs') {
                reports.push({ url, options });
                const next = reportQueue.shift();
                return typeof next === 'function' ? next(options) : Promise.resolve({ ok: true });
            }
            calls.push({ url, options });
            const next = fetchQueue.shift();
            if (!next) {
                throw new Error('Unexpected refresh request.');
            }
            if (typeof next === 'function') {
                return next(options, fixtures);
            }
            return Promise.resolve(responseFor(fixtures, `snapshot-${calls.length}`, next.snapshot));
        },
        DOMParser: class {
            parseFromString(text) {
                const snapshot = fixtures.get(text);
                if (!snapshot) {
                    throw new Error('Unexpected refresh fixture.');
                }
                return snapshot;
            }
        },
        AbortController,
        URLSearchParams,
        setTimeout(callback, delay) {
            const timer = { callback, delay, cleared: false };
            timeouts.push(timer);
            return timer;
        },
        clearTimeout(timer) {
            timer.cleared = true;
        },
        setInterval(callback, delay) {
            intervalCallback = callback;
            return { delay };
        },
    };
    vm.runInNewContext(script, sandbox, { filename: 'ui-refresh.js' });
    return {
        document: documentRef,
        nodes,
        calls,
        reports,
        timeouts,
        csrfInputs,
        poll: () => intervalCallback(),
        visibilityChanged: () => listeners.get('visibilitychange')(),
        response: (id, snapshot) => responseFor(fixtures, id, snapshot),
    };
}

async function flushPromises() {
    for (let i = 0; i < 6; i += 1) {
        await Promise.resolve();
    }
    await new Promise((resolve) => setImmediate(resolve));
}

test('refresh updates status and CSRF while preserving eligible drafts and clearing stale or cross-playthrough pairs', async () => {
    const harness = createHarness([
        { snapshot: makeSnapshot({ a: ['101', '202', '303'], b: ['101', '202', '303'], csrf: 'csrf-1' }) },
        { snapshot: makeSnapshot({ a: ['101'], b: ['101'], disabled: true, csrf: 'csrf-2', label: 'Reduced' }) },
        { snapshot: makeSnapshot({ ref: 'other-playthrough', a: ['101', '202'], b: ['101', '202'], csrf: 'csrf-3', label: 'Switched' }) },
    ]);

    await flushPromises();
    assert.equal(harness.calls.length, 1);
    assert.equal(harness.calls[0].url, '?refresh=1');
    assert.equal(harness.calls[0].options.credentials, 'same-origin');
    assert.equal(harness.calls[0].options.cache, 'no-store');
    assert.equal(harness.nodes.get('#actor-a').value, '101');
    assert.equal(harness.nodes.get('#actor-b').value, '202');
    assert.equal(harness.nodes.get('#arm-button').disabled, false);
    assert.equal(harness.nodes.get('.status-panel').innerHTML, '<p>Current status</p>');
    assert.equal(harness.nodes.get('#bystander-mode').value, 'silent');
    assert.equal(harness.nodes.get('input[name="exclude_player"]').checked, true);
    assert.deepEqual(harness.csrfInputs.map((input) => input.value), ['csrf-1', 'csrf-1']);

    harness.poll();
    await flushPromises();
    assert.equal(harness.nodes.get('#actor-a').value, '101');
    assert.equal(harness.nodes.get('#actor-b').value, '');
    assert.equal(harness.nodes.get('#arm-button').disabled, true);
    assert.equal(harness.nodes.get('#picker-status').textContent, 'Reduced eligibility');

    harness.poll();
    await flushPromises();
    assert.equal(harness.nodes.get('#actor-a').value, '');
    assert.equal(harness.nodes.get('#actor-b').value, '');
    assert.equal(harness.nodes.get('#solo-mode').checked, false);
    assert.equal(harness.nodes.get('#arm-button').disabled, true);
    assert.equal(harness.document.body.dataset.playthroughRef, 'other-playthrough');
    assert.equal(harness.nodes.get('#bystander-mode').value, 'silent');
    assert.equal(harness.nodes.get('input[name="exclude_player"]').checked, true);
    assert.deepEqual(harness.csrfInputs.map((input) => input.value), ['csrf-3', 'csrf-3']);

    harness.nodes.get('#actor-a').value = '101';
    harness.nodes.get('#actor-a').dispatchChange();
    assert.equal(harness.nodes.get('#arm-button').disabled, true);
    harness.nodes.get('#actor-b').value = '202';
    harness.nodes.get('#actor-b').dispatchChange();
    assert.equal(harness.nodes.get('#arm-button').disabled, false);
    harness.nodes.get('#actor-b').value = '101';
    harness.nodes.get('#actor-b').dispatchChange();
    assert.equal(harness.nodes.get('#arm-button').disabled, true);
});

test('group controls refresh with the roster, block duplicate members, and stay off in solo mode', async () => {
    const harness = createHarness([
        { snapshot: makeSnapshot({ a: ['101', '202', '303'], b: ['101', '202', '303'], group: ['101', '202', '303'] }) },
        { snapshot: makeSnapshot({ a: ['101', '202'], b: ['101', '202'], group: ['101', '202'], label: 'Smaller' }) },
    ], { group: true });
    await flushPromises();
    const actorC = harness.nodes.get('#actor-c');
    const actorD = harness.nodes.get('#actor-d');
    const opener = harness.nodes.get('#opener');
    assert.equal(actorC.value, '303', 'An eligible C draft survives a refresh.');
    assert.equal(opener.value, 'auto');
    assert.equal(harness.nodes.get('#arm-button').disabled, false, 'A, B and C distinct: ARM enabled.');
    actorD.value = '101';
    actorD.dispatchChange();
    assert.equal(harness.nodes.get('#arm-button').disabled, true, 'D duplicating A disables ARM.');
    actorD.value = '';
    actorD.dispatchChange();
    assert.equal(harness.nodes.get('#arm-button').disabled, false);

    harness.poll();
    await flushPromises();
    assert.equal(actorC.value, '', 'A C draft that left the roster is cleared.');
    assert.equal(harness.nodes.get('#arm-button').disabled, false, 'A and B still form a valid group of two.');

    harness.nodes.get('#solo-mode').checked = true;
    harness.nodes.get('#solo-mode').dispatchChange();
    assert.equal(actorC.disabled, true);
    assert.equal(actorD.disabled, true);
    assert.equal(opener.disabled, true);
    harness.nodes.get('#solo-mode').checked = false;
    harness.nodes.get('#solo-mode').dispatchChange();
    assert.equal(actorC.disabled, false);
});

test('malformed refresh controls preserve both actor drafts and fail closed', async () => {
    const snapshot = makeSnapshot({ ref: 'new-playthrough' });
    const querySelector = snapshot.querySelector;
    snapshot.querySelector = (selector) => selector === '#actor-b' ? {} : querySelector(selector);
    const harness = createHarness([{ snapshot }]);

    await flushPromises();

    assert.equal(harness.nodes.get('#actor-a').value, '101');
    assert.equal(harness.nodes.get('#actor-b').value, '202');
    assert.equal(harness.document.body.dataset.playthroughRef, 'same-playthrough');
    assert.equal(harness.nodes.get('#arm-button').disabled, true);
    assert.deepEqual(harness.reports.map(({ options }) => new URLSearchParams(options.body).get('code')), ['invalid_response']);
});

test('solo mode arms one eligible actor, preserves an eligible B draft across polling, and restores only a distinct B', async () => {
    const harness = createHarness([
        { snapshot: makeSnapshot({ a: ['101', '202', '303'], b: ['101', '202', '303'] }) },
        { snapshot: makeSnapshot({ a: ['101', '202'], b: ['101', '202'], label: 'Polled' }) },
        { snapshot: makeSnapshot({ ref: 'other-playthrough', a: ['101'], b: ['101'], label: 'Solo only' }) },
    ]);
    await flushPromises();
    const actorA = harness.nodes.get('#actor-a');
    const actorB = harness.nodes.get('#actor-b');
    const soloMode = harness.nodes.get('#solo-mode');
    const armButton = harness.nodes.get('#arm-button');
    assert.equal(actorA.value, '101');
    assert.equal(actorB.value, '202');
    const playerCheckbox = harness.nodes.get('input[name="exclude_player"]');
    playerCheckbox.checked = false;

    soloMode.checked = true;
    soloMode.dispatchChange();
    assert.equal(actorB.disabled, true);
    assert.equal(actorB.value, '', 'The pair-only selection is kept in the local draft, not shown as active in solo mode.');
    assert.equal(harness.nodes.get('#actor-a-label').textContent, 'Reflecting NPC');
    assert.equal(harness.nodes.get('#actor-b-label').textContent, 'Second NPC (pair mode only)');
    assert.equal(harness.nodes.get('#arm-heading').textContent, 'Solo reflection');
    assert.equal(harness.nodes.get('#mode-guidance').hidden, false);
    assert.match(harness.nodes.get('#mode-guidance').textContent, /compatible Mind Poisoning support/);
    assert.equal(armButton.disabled, false);
    assert.equal(harness.nodes.get('input[name="exclude_player"]').checked, true);
    assert.equal(harness.nodes.get('input[name="exclude_player"]').disabled, true);
    assert.equal(harness.nodes.get('#bystander-mode').disabled, false);
    assert.equal(harness.calls.length, 1, 'Changing mode must not trigger a fresh game observation.');

    harness.poll();
    await flushPromises();
    assert.equal(soloMode.checked, true);
    assert.equal(actorB.disabled, true);
    assert.equal(actorB.options.some((option) => option.value === '202'), true);
    soloMode.checked = false;
    soloMode.dispatchChange();
    assert.equal(actorB.disabled, false);
    assert.equal(harness.nodes.get('#mode-guidance').hidden, true);
    assert.equal(actorB.value, '202');
    assert.equal(harness.nodes.get('input[name="exclude_player"]').checked, false);
    assert.equal(harness.nodes.get('input[name="exclude_player"]').disabled, false);

    actorA.value = '202';
    actorA.dispatchChange();
    soloMode.checked = true;
    soloMode.dispatchChange();
    assert.equal(armButton.disabled, false);
    soloMode.checked = false;
    soloMode.dispatchChange();
    assert.equal(actorB.value, '', 'A previous B equal to A must not be restored.');
    assert.equal(armButton.disabled, true);

    soloMode.checked = true;
    soloMode.dispatchChange();
    harness.poll();
    await flushPromises();
    assert.equal(actorA.value, '', 'Identity changes clear actor selections.');
    assert.equal(actorB.value, '', 'Identity changes clear the remembered B draft.');
    assert.equal(soloMode.checked, false, 'Identity changes reset a stale solo draft to pair mode.');
    assert.equal(actorB.disabled, true, 'Pair mode keeps B unavailable when only one eligible NPC remains.');
    assert.equal(playerCheckbox.checked, false, 'Identity changes preserve the non-actor checkbox draft.');
});

test('one eligible NPC enables ARM only after selecting solo mode', async () => {
    const harness = createHarness([
        { snapshot: makeSnapshot({ a: ['101'], b: ['101'] }) },
    ]);
    await flushPromises();
    assert.equal(harness.nodes.get('#actor-a').value, '101');
    assert.equal(harness.nodes.get('#arm-button').disabled, true);
    harness.nodes.get('#solo-mode').checked = true;
    harness.nodes.get('#solo-mode').dispatchChange();
    assert.equal(harness.nodes.get('#arm-button').disabled, false);
});

test('solo mode discards a remembered B when it leaves the eligible roster during same-identity polling', async () => {
    const harness = createHarness([
        { snapshot: makeSnapshot({ a: ['101', '202'], b: ['101', '202'] }) },
        { snapshot: makeSnapshot({ a: ['101'], b: ['101'], label: 'B left the roster' }) },
    ]);
    await flushPromises();
    const actorB = harness.nodes.get('#actor-b');
    const soloMode = harness.nodes.get('#solo-mode');
    soloMode.checked = true;
    soloMode.dispatchChange();
    assert.equal(actorB.value, '');

    harness.poll();
    await flushPromises();
    assert.equal(soloMode.checked, true);
    assert.equal(actorB.options.some((option) => option.value === '202'), false);
    soloMode.checked = false;
    soloMode.dispatchChange();
    assert.equal(actorB.value, '', 'B must not be restored after losing eligibility.');
    assert.equal(harness.nodes.get('#arm-button').disabled, true);
});

test('polling is single-flight, pauses while hidden, and refreshes immediately on return', async () => {
    let finishFirst;
    const harness = createHarness([
        (options, fixtures) => new Promise((resolve) => {
            finishFirst = () => resolve(responseFor(fixtures, 'first', makeSnapshot()));
            assert.equal(options.signal.aborted, false);
        }),
        { snapshot: makeSnapshot({ label: 'Returned' }) },
    ]);
    assert.equal(harness.timeouts[0].delay, 8_000);

    harness.document.visibilityState = 'hidden';
    harness.poll();
    assert.equal(harness.calls.length, 1);
    harness.document.visibilityState = 'visible';
    harness.visibilityChanged();
    assert.equal(harness.nodes.get('#arm-button').disabled, true);
    assert.equal(harness.calls.length, 1);
    finishFirst();
    await flushPromises();
    assert.equal(harness.calls.length, 2);
    assert.equal(harness.nodes.get('#picker-status').textContent, 'Returned eligibility');
});

test('timeout fails closed and the next poll can recover', async () => {
    const harness = createHarness([
        { snapshot: makeSnapshot() },
        (options) => new Promise((resolve, reject) => {
            options.signal.addEventListener('abort', () => reject(new Error('aborted')), { once: true });
        }),
        { snapshot: makeSnapshot({ csrf: 'recovered-token', label: 'Recovered' }) },
    ]);
    await flushPromises();
    assert.equal(harness.nodes.get('#arm-button').disabled, false);

    harness.document.visibilityState = 'hidden';
    harness.visibilityChanged();
    harness.document.visibilityState = 'visible';
    harness.visibilityChanged();
    assert.equal(harness.nodes.get('#arm-button').disabled, true);
    const timeout = harness.timeouts.filter((timer) => timer.delay === 8_000 && !timer.cleared).at(-1);
    assert.ok(timeout);
    timeout.callback();
    await flushPromises();
    assert.equal(harness.nodes.get('#arm-button').disabled, true);
    assert.match(harness.nodes.get('#picker-status').textContent, /ARM is disabled/);
    assert.equal(new URLSearchParams(harness.reports[0]?.options.body).get('code'), 'timeout');
    harness.nodes.get('#actor-a').value = '202';
    harness.nodes.get('#actor-a').dispatchChange();
    harness.nodes.get('#actor-b').value = '101';
    harness.nodes.get('#actor-b').dispatchChange();
    assert.equal(harness.nodes.get('#arm-button').disabled, true);

    harness.poll();
    await flushPromises();
    assert.equal(harness.nodes.get('#arm-button').disabled, false);
    assert.equal(harness.nodes.get('#picker-status').textContent, 'Recovered eligibility');
    assert.deepEqual(harness.csrfInputs.map((input) => input.value), ['recovered-token', 'recovered-token']);
});

test('refresh failure telemetry uses fixed codes, current CSRF, and never retries its own failure', async () => {
    const cases = [
        [() => Promise.reject(Object.assign(new TypeError('private network detail'), { name: 'TypeError' })), 'network'],
        [() => Promise.reject(Object.assign(new Error('private abort detail'), { name: 'AbortError' })), 'timeout'],
        [() => Promise.resolve({ ok: false }), 'http'],
        [() => Promise.resolve({ ok: true, text: async () => 'invalid response detail' }), 'invalid_response'],
        [() => Promise.reject(Object.assign(new Error('private unknown detail'), { name: 'UnexpectedError' })), 'unknown'],
    ];

    for (const [refresh, expectedCode] of cases) {
        const harness = createHarness([refresh]);
        await flushPromises();
        assert.equal(harness.reports.length, 1, `${expectedCode} should be reported once`);
        const [{ url, options }] = harness.reports;
        assert.equal(url, '?view=logs');
        assert.equal(options.method, 'POST');
        assert.equal(options.credentials, 'same-origin');
        const body = new URLSearchParams(options.body);
        assert.deepEqual([...body.entries()].sort(), [
            ['action', 'client_report'],
            ['code', expectedCode],
            ['csrf', 'old-token'],
        ].sort());
        assert.doesNotMatch(options.body, /private|invalid response detail/);
    }

    const harness = createHarness([
        () => Promise.reject(Object.assign(new TypeError('private network detail'), { name: 'TypeError' })),
        () => Promise.reject(Object.assign(new TypeError('private network detail'), { name: 'TypeError' })),
    ], { reportQueue: [() => Promise.reject(new Error('report endpoint unavailable'))] });
    await flushPromises();
    harness.poll();
    await flushPromises();
    assert.equal(harness.calls.length, 2, 'a failed telemetry request must not restart scene polling');
    assert.equal(harness.reports.length, 1, 'the same browser failure code is reported once per page');
    assert.equal(harness.nodes.get('#arm-button').disabled, true, 'refresh failure must keep ARM disabled');

    const synchronousFailure = createHarness([
        () => Promise.reject(Object.assign(new TypeError('private network detail'), { name: 'TypeError' })),
    ], { reportQueue: [() => { throw new Error('synchronous telemetry transport failure'); }] });
    await flushPromises();
    assert.equal(synchronousFailure.calls.length, 1, 'a synchronous telemetry failure must not restart scene polling');
    assert.equal(synchronousFailure.reports.length, 1, 'a synchronous telemetry failure must not be retried');
    assert.equal(synchronousFailure.nodes.get('#arm-button').disabled, true, 'telemetry failure must preserve fail-closed ARM state');
});

test('a successful refresh re-arms telemetry for a new failure episode', async () => {
    const harness = createHarness([
        () => Promise.reject(Object.assign(new TypeError('first private failure'), { name: 'TypeError' })),
        { snapshot: makeSnapshot({ label: 'Recovered' }) },
        () => Promise.reject(Object.assign(new TypeError('second private failure'), { name: 'TypeError' })),
        () => Promise.reject(Object.assign(new TypeError('same episode failure'), { name: 'TypeError' })),
    ]);
    await flushPromises();
    harness.poll();
    await flushPromises();
    harness.poll();
    await flushPromises();
    harness.poll();
    await flushPromises();

    assert.deepEqual(harness.reports.map(({ options }) => new URLSearchParams(options.body).get('code')), ['network', 'network']);
    assert.equal(harness.calls.length, 4);
    assert.equal(harness.nodes.get('#arm-button').disabled, true, 'the later refresh failure must still disable ARM');
});

test('free scene disables every picker, forces the player out, and arms with two eligible NPCs', async () => {
    const harness = createHarness([
        { snapshot: makeSnapshot({ a: ['101', '202', '303'], b: ['101', '202', '303'], group: ['101', '202', '303'] }) },
        { snapshot: makeSnapshot({ a: ['101'], b: ['101'], group: ['101'], label: 'Lonely' }) },
    ], { group: true, free: true });
    await flushPromises();
    const freeMode = harness.nodes.get('#free-mode');
    const soloMode = harness.nodes.get('#solo-mode');
    const player = harness.nodes.get('input[name="exclude_player"]');
    player.checked = false;
    harness.nodes.get('#actor-a').value = '';
    harness.nodes.get('#actor-b').value = '';
    freeMode.checked = true;
    freeMode.dispatchChange();
    for (const selector of ['#actor-a', '#actor-b', '#actor-c', '#actor-d', '#opener']) {
        assert.equal(harness.nodes.get(selector).disabled, true, `${selector} is disabled in a free scene.`);
    }
    assert.equal(player.checked, true, 'A free scene always excludes the player.');
    assert.equal(player.disabled, true);
    assert.equal(harness.nodes.get('#arm-button').disabled, false, 'Free arms without selections when two are eligible.');

    soloMode.checked = true;
    soloMode.dispatchChange();
    assert.equal(freeMode.checked, false, 'Solo and free are exclusive.');
    soloMode.checked = false;
    soloMode.dispatchChange();
    freeMode.checked = true;
    freeMode.dispatchChange();
    assert.equal(soloMode.checked, false);

    harness.poll();
    await flushPromises();
    assert.equal(harness.nodes.get('#arm-button').disabled, true, 'Free needs at least two eligible NPCs.');

    freeMode.checked = false;
    freeMode.dispatchChange();
    assert.equal(player.checked, false, 'Leaving free restores the player draft.');
    assert.equal(player.disabled, false);
    assert.equal(harness.nodes.get('#actor-a').disabled, false);
});

test('0.1.14: free size follows the Free scene box and turn length is off for solo', async () => {
    const harness = createHarness([
        { snapshot: makeSnapshot({ a: ['101', '202', '303'], b: ['101', '202', '303'], group: ['101', '202', '303'] }) },
    ], { group: true, free: true });
    await flushPromises();
    const freeMode = harness.nodes.get('#free-mode');
    const soloMode = harness.nodes.get('#solo-mode');
    const cap = harness.nodes.get('#free-cap');
    const pace = harness.nodes.get('#pace');
    assert.equal(cap.disabled, true, 'Free size is off until Free scene is ticked.');
    assert.equal(pace.disabled, false, 'Turn length applies to pairs and groups.');
    freeMode.checked = true;
    freeMode.dispatchChange();
    assert.equal(cap.disabled, false, 'Free size is on for a free scene.');
    assert.equal(pace.disabled, false, 'Turn length applies to free scenes.');
    soloMode.checked = true;
    soloMode.dispatchChange();
    assert.equal(cap.disabled, true, 'Free size is off again when solo replaces free.');
    assert.equal(pace.disabled, true, 'Turn length is off for solo.');
});

test('0.1.16: the Physical scene-action box is off for solo', async () => {
    const harness = createHarness([
        { snapshot: makeSnapshot({ a: ['101', '202', '303'], b: ['101', '202', '303'], group: ['101', '202', '303'] }) },
    ], { group: true, free: true });
    await flushPromises();
    const soloMode = harness.nodes.get('#solo-mode');
    const physical = harness.nodes.get('#actions-physical');
    assert.equal(physical.disabled, false, 'Physical applies to pairs and groups.');
    soloMode.checked = true;
    soloMode.dispatchChange();
    assert.equal(physical.disabled, true, 'Solo has no one to brawl with.');
    soloMode.checked = false;
    soloMode.dispatchChange();
    assert.equal(physical.disabled, false, 'Leaving solo restores Physical.');
});