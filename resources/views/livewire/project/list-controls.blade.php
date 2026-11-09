<script>
    function projectListControls(projects, savedSort, pendingSort, allowedSorts, saveMethod) {
        // Livewire 3 resolves action promises on success. Its commit hook reports transport failures.
        async function save(wire, method, ...params) {
            let unsubscribe;
            try {
                const failure = new Promise((_, reject) => {
                    unsubscribe = Livewire.hook('commit', ({ component, commit, fail }) => {
                        if (component.id === wire.$id && commit.calls.some(call =>
                            call.method === method && call.params[0] === params[0])) {
                            fail(() => reject(new Error('Project preference could not be saved')));
                        }
                    });
                });
                await Promise.race([wire.$call(method, ...params), failure]);
            } finally {
                unsubscribe?.();
            }
        }

        return {
            projects,
            sortBy: savedSort,
            confirmedSort: savedSort,
            pendingSort,
            savingSort: false,
            init() {
                if (allowedSorts.includes(this.pendingSort)) {
                    this.sortBy = this.pendingSort;
                    this.flushSort();
                } else {
                    this.pendingSort = null;
                }
                this.$watch('sortBy', value => {
                    if (value === this.confirmedSort && !this.savingSort) return;
                    this.pendingSort = value;
                    this.flushSort();
                });
            },
            async flushSort() {
                if (this.savingSort) return;
                this.savingSort = true;
                try {
                    while (this.pendingSort !== null) {
                        const value = this.pendingSort;
                        try {
                            await save(this.$wire, saveMethod, value);
                            this.confirmedSort = value;
                        } catch {
                            if (this.pendingSort === value) this.sortBy = this.confirmedSort;
                        }
                        if (this.pendingSort === value) this.pendingSort = null;
                    }
                } finally {
                    this.savingSort = false;
                }
            },
            sortProjects(projects) {
                const [column, direction] = this.sortBy.split('-');
                const sortValues = {
                    running: (project) => this.runtimeStatuses[project.uuid]?.running ?? 0,
                    name: (project) => project.name,
                    resources: (project) => Number(project.resourceCount),
                    environments: (project) => Number(project.environmentCount),
                    created: (project) => Date.parse(project.createdAt) || 0,
                    description: (project) => project.description || '',
                };
                const sortValue = sortValues[column] || sortValues.name;
                const order = direction === 'desc' ? -1 : 1;
                const compare = (first, second) => typeof first === 'number' ?
                    first - second :
                    first.localeCompare(second);

                return [...projects].sort((first, second) =>
                    Number(second.pinned) - Number(first.pinned) ||
                    order * compare(sortValue(first), sortValue(second)) ||
                    first.name.localeCompare(second.name));
            },
            async toggleProjectPin(project) {
                if (project.savingPin) return;
                const previousPin = project.pinned;
                project.savingPin = true;
                project.pinned = !previousPin;
                if (project.pinned && this.page > 1) this.page = 1;
                try {
                    await save(this.$wire, 'setProjectPinned', project.uuid, project.pinned);
                } catch {
                    project.pinned = previousPin;
                } finally {
                    project.savingPin = false;
                }
            },
        };
    }
</script>
