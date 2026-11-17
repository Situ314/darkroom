<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import GenerationCard from '@/components/GenerationCard.vue';
import { Button } from '@/components/ui/button';
import { formatUsd, formatUsdExact } from '@/lib/money';
import { create, index } from '@/routes/generations';
import type { Generation, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Prints', href: index() }],
    },
});

const props = defineProps<{
    generations: Paginated<Generation>;
    totals: { count: number; spent_nanos: number };
}>();

const hasActive = computed(() =>
    props.generations.data.some((g) =>
        ['pending', 'queued', 'processing'].includes(g.status),
    ),
);

const { start, stop } = usePoll(
    3000,
    { only: ['generations', 'totals', 'balanceNanos'] },
    { autoStart: false },
);

watch(hasActive, (active) => (active ? start() : stop()), { immediate: true });
</script>

<template>
    <Head title="Prints" />

    <div class="flex flex-col gap-8 px-4 py-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="film-label">Contact sheet</p>
                <h1 class="display mt-2 text-5xl">Every print</h1>
                <p class="mt-3 font-mono text-xs text-muted-foreground">
                    {{ totals.count }} EXPOSURE{{
                        totals.count === 1 ? '' : 'S'
                    }}
                    · SPENT
                    <span
                        class="text-foreground"
                        :title="formatUsdExact(totals.spent_nanos)"
                    >
                        {{ formatUsd(totals.spent_nanos) }}
                    </span>
                </p>
            </div>
            <Link
                :href="create()"
                class="flex h-11 items-center gap-2 bg-primary px-5 font-medium text-primary-foreground hover:brightness-110"
            >
                <span class="size-2 rounded-full bg-current" /> New exposure
            </Link>
        </div>

        <div
            v-if="!generations.data.length"
            class="flex min-h-80 flex-col items-center justify-center gap-3 border border-dashed text-sm text-muted-foreground"
        >
            <span class="display text-3xl">The sheet is empty</span>
            <Link :href="create()" class="text-primary hover:underline">
                Develop your first print →
            </Link>
        </div>

        <div
            v-else
            class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4"
        >
            <GenerationCard
                v-for="generation in generations.data"
                :key="generation.id"
                :generation="generation"
            />
        </div>

        <nav
            v-if="generations.last_page > 1"
            class="flex items-center justify-center gap-3 text-sm"
        >
            <Button
                variant="outline"
                size="sm"
                :disabled="!generations.prev_page_url"
                as-child
            >
                <Link :href="generations.prev_page_url ?? '#'" preserve-scroll>
                    Previous
                </Link>
            </Button>
            <span class="text-muted-foreground">
                Page {{ generations.current_page }} of
                {{ generations.last_page }}
            </span>
            <Button
                variant="outline"
                size="sm"
                :disabled="!generations.next_page_url"
                as-child
            >
                <Link :href="generations.next_page_url ?? '#'" preserve-scroll>
                    Next
                </Link>
            </Button>
        </nav>
    </div>
</template>
