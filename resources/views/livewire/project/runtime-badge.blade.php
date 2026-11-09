<template x-if="runtimeStatuses[project.uuid]">
    <span class="inline-flex items-center gap-1 text-[11px] font-medium"
        :title="runtimeStatuses[project.uuid].running + ' of ' + runtimeStatuses[project.uuid].total + ' resources running'">
        <span aria-hidden="true" class="size-2 shrink-0 rounded-full"
            :style="{ backgroundColor: ({ Running: '#22c55e', Partial: '#f59e0b', Degraded: '#ef4444', Starting: '#38bdf8', Stopped: '#9ca3af', Unknown: '#9ca3af', Empty: '#9ca3af' })[runtimeStatuses[project.uuid].state] }"></span>
        <span class="tabular-nums" x-show="runtimeStatuses[project.uuid].total"
            x-text="runtimeStatuses[project.uuid].running + '/' + runtimeStatuses[project.uuid].total"></span>
        <span x-text="runtimeStatuses[project.uuid].total ? runtimeStatuses[project.uuid].state : 'No resources'"></span>
    </span>
</template>
