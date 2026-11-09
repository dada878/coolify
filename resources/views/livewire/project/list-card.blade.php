<article
    class="group relative flex min-h-28 min-w-0 flex-col rounded-xl border border-neutral-200 bg-white p-3 shadow-sm transition-all hover:-translate-y-px hover:border-neutral-300 hover:shadow-md dark:border-white/[0.08] dark:bg-white/[0.05] dark:hover:border-white/[0.14]">
    <a :href="project.href" {{ wireNavigate() }} class="absolute inset-0 rounded-xl"
        :aria-label="`Open ${project.name}`"></a>
    <div class="flex items-start gap-3">
        <div
            class="resource-glyph flex size-8 shrink-0 items-center justify-center rounded-lg border border-neutral-200 bg-neutral-50 text-neutral-500">
            <template x-if="project.iconUrl">
                <img :src="project.iconUrl" :alt="`${project.name} icon`" class="h-full w-full rounded-lg object-cover">
            </template>
            <template x-if="!project.iconUrl">
                <x-reicon name="projects" class="size-4" />
            </template>
        </div>
        <div class="min-w-0 flex-1 self-center">
            <h2
                class="truncate text-[13px]! leading-4! font-semibold! text-black dark:text-fg"
                x-text="project.name"></h2>
            <p class="mt-0.5 truncate text-[11px] text-neutral-500 dark:text-fg-faint"
                x-show="project.description?.trim()"
                x-text="project.description"></p>
        </div>
    </div>

    <div class="my-2">
        @include('livewire.project.runtime-badge')
    </div>
    <div class="mt-auto flex items-center justify-between gap-3 border-t border-neutral-100 pt-2.5 dark:border-white/[0.06]">
        <p class="project-card-stats relative z-10 min-w-0">
            <b x-text="project.environmentCount"></b>
            <span x-text="Number(project.environmentCount) === 1 ? 'environment' : 'environments'"></span>
            <span class="project-card-stats-dot" aria-hidden="true">&middot;</span>
            <b x-text="project.resourceCount"></b>
            <span x-text="Number(project.resourceCount) === 1 ? 'resource' : 'resources'"></span>
        </p>

        <div class="relative z-10 flex shrink-0 items-center gap-0.5">
            @include('livewire.project.pin-button')
            <a x-show="project.addResourceHref" :href="project.addResourceHref"
                {{ wireNavigate() }}
                class="card-icon-button flex size-7.5 items-center justify-center rounded-md text-neutral-400 transition-colors hover:bg-neutral-100 hover:text-black"
                title="Add resource" :aria-label="`Add resource to ${project.name}`">
                <x-reicon name="plus" class="size-3" />
            </a>
            <a x-show="project.settingsHref" :href="project.settingsHref" {{ wireNavigate() }}
                class="card-icon-button flex size-7.5 items-center justify-center rounded-md text-neutral-400 transition-colors hover:bg-neutral-100 hover:text-black"
                title="Project settings" :aria-label="`Open settings for ${project.name}`">
                <x-reicon name="settings" class="size-3" />
            </a>
        </div>
    </div>
</article>
