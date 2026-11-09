import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const script = readFileSync(new URL('../views/livewire/project/list-controls.blade.php', import.meta.url), 'utf8')
    .replace('<script>', '').replace('</script>', '');

function list() {
    const hooks = new Set();
    const context = vm.createContext({ Livewire: {
        hook(event, fn) {
            assert.equal(event, 'commit');
            hooks.add(fn);
            return () => hooks.delete(fn);
        },
    } });
    vm.runInContext(script, context);
    const requests = [];
    const wire = {
        $id: 'projects',
        $call(method, ...params) {
            return new Promise(resolve => {
                const failures = [];
                for (const hook of hooks) hook({
                    component: { id: this.$id }, commit: { calls: [{ method, params }] },
                    fail: callback => failures.push(callback),
                });
                requests.push({ params, resolve, fail: () => failures.forEach(callback => callback()) });
            });
        },
    };
    const projects = Array.from({ length: 12 }, (_, i) => ({
        uuid: String(i), name: 'Project ' + String(i).padStart(2, '0'), pinned: false,
    }));
    const model = context.projectListControls(projects, 'name-asc', null, ['name-asc', 'name-desc', 'running-desc'], 'setProjectSort');
    model.$wire = wire;
    model.runtimeStatuses = Object.fromEntries(projects.map((p, i) => [p.uuid, { running: i }]));
    return { model, projects, requests };
}

test('pins stay inside the ranked rows before pagination and after filtering', () => {
    const { model, projects } = list();
    projects[2].pinned = true;
    projects[10].pinned = true;
    for (const [sort, first, second] of [['name-asc', '2', '10'], ['name-desc', '10', '2'], ['running-desc', '10', '2']]) {
        model.sortBy = sort;
        const ranked = Array.from(model.sortProjects(projects), p => p.uuid);
        assert.deepEqual(ranked.slice(0, 2), [first, second]);
        assert.equal(new Set(ranked).size, projects.length);
        assert.ok(ranked.slice(0, 8).includes('10'));
    }
    assert.equal(model.sortProjects(projects.filter(p => p.uuid !== '10'))[0].uuid, '2');
});

test('pin moves before the request resolves and unwinds on failure', async () => {
    const { model, projects, requests } = list();
    const pin = model.toggleProjectPin(projects[11]);
    assert.equal(model.sortProjects(projects)[0].uuid, '11');
    requests[0].fail();
    await pin;
    assert.equal(model.sortProjects(projects)[0].uuid, '0');
    assert.equal(projects[11].savingPin, false);
});

test('newest sort stays visible while earlier writes finish and a failed save restores the last confirmed order', async () => {
    const { model, projects, requests } = list();
    model.pendingSort = model.sortBy = 'name-desc';
    const saving = model.flushSort();
    model.pendingSort = model.sortBy = 'running-desc';
    await model.flushSort();
    assert.equal(model.sortProjects(projects)[0].uuid, '11');
    assert.equal(requests.length, 1);
    requests[0].resolve();
    await new Promise(resolve => setImmediate(resolve));
    assert.deepEqual(requests.map(r => r.params[0]), ['name-desc', 'running-desc']);
    assert.equal(model.sortBy, 'running-desc');
    requests[1].fail();
    await saving;
    assert.equal(model.sortBy, 'name-desc');
    assert.equal(model.pendingSort, null);
});
