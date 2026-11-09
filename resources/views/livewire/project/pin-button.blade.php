<button type="button"
    class="card-icon-button flex size-7 items-center justify-center rounded-md text-neutral-400 transition-colors hover:bg-neutral-100 hover:text-black dark:hover:bg-white/[0.06] dark:hover:text-fg"
    :aria-label="(project.pinned ? 'Unpin ' : 'Pin ') + project.name"
    :title="project.pinned ? 'Unpin project' : 'Pin project to top'"
    :aria-pressed="project.pinned" :disabled="project.savingPin === true"
    x-on:click="toggleProjectPin(project)">
    <svg class="size-3.5" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"
        stroke-linejoin="round" :fill="project.pinned ? 'currentColor' : 'none'"
        :class="project.pinned && 'text-coollabs dark:text-brand-light'" aria-hidden="true">
        <path d="m12 3 2.8 5.7 6.3.9-4.6 4.5 1.1 6.3-5.6-3-5.6 3 1.1-6.3L3 9.6l6.2-.9z" />
    </svg>
</button>
