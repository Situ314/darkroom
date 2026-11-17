<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { History } from '@lucide/vue';
import { computed, watch } from 'vue';
import GenerationCard from '@/components/GenerationCard.vue';
import { Button } from '@/components/ui/button';
import { formatUsd, formatUsdExact } from '@/lib/money';
import { create, index } from '@/routes/generations';
import type { Generation, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'History', href: index() }],
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
    <Head title="History" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-lg font-semibold tracking-tight">History</h1>
                <p class="text-sm text-muted-foreground">
                    {{ totals.count }} generation{{
                        totals.count === 1 ? '' : 's'
                    }}
                    · spent
                    <span
                        class="font-medium text-foreground tabular-nums"
                        :title="formatUsdExact(totals.spent_nanos)"
                    >
                        {{ formatUsd(totals.spent_nanos) }}
                    </span>
                </p>
            </div>
            <Button as-child>
                <Link :href="create()">New image</Link>
            </Button>
        </div>

        <div
            v-if="!generations.data.length"
            class="flex min-h-64 flex-col items-center justify-center gap-2 rounded-xl border border-dashed text-sm text-muted-foreground"
        >
            <History class="size-6" />
            Nothing here yet.
            <Link :href="create()" class="underline underline-offset-4">
                Generate your first image
            </Link>
        </div>

        <div
            v-else
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4"
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
