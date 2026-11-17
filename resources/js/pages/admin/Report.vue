<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck } from '@lucide/vue';
import { formatUsd, formatUsdExact } from '@/lib/money';
import { cn } from '@/lib/utils';
import { report } from '@/routes/admin';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Ledger', href: report() }],
    },
});

type DayRow = {
    date: string;
    generations: number;
    charged_nanos: number;
    provider_nanos: number;
    profit_nanos: number;
    pixelforge_nanos: number | null;
    pixelforge_generations: number | null;
};

type UserRow = {
    id: number;
    name: string;
    email: string;
    balance_nanos: number;
    generations: number;
    charged_nanos: number;
    provider_nanos: number;
    profit_nanos: number;
};

const props = defineProps<{
    days: number;
    range: { start: string; end: string };
    daily: DayRow[];
    perUser: UserRow[];
    totals: {
        generations: number;
        charged_nanos: number;
        provider_nanos: number;
        profit_nanos: number;
        pixelforge_nanos: number | null;
    };
    pixelforgeError: string | null;
}>();

const ranges = [7, 30, 92];

function setRange(days: number) {
    router.get(report().url, { days }, { preserveScroll: true });
}

const matches = (row: {
    provider_nanos: number;
    pixelforge_nanos: number | null;
}) =>
    row.pixelforge_nanos === null ||
    row.pixelforge_nanos === row.provider_nanos;

const allMatch = () =>
    props.totals.pixelforge_nanos !== null &&
    props.totals.pixelforge_nanos === props.totals.provider_nanos;
</script>

<template>
    <Head title="Ledger" />

    <div class="flex flex-col gap-6 px-4 py-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="film-label">Operators only</p>
                <h1 class="display mt-2 text-5xl">The ledger</h1>
                <p class="text-sm text-muted-foreground">
                    {{ range.start }} → {{ range.end }} (UTC, by completion day
                    — same as Pixelforge's <code>/v1/usage</code>)
                </p>
            </div>
            <div class="flex gap-1 rounded-lg bg-muted p-1 text-sm">
                <button
                    v-for="d in ranges"
                    :key="d"
                    :class="
                        cn(
                            'rounded-md px-3 py-1',
                            d === days
                                ? 'bg-background font-medium shadow-sm'
                                : 'text-muted-foreground',
                        )
                    "
                    @click="setRange(d)"
                >
                    {{ d }}d
                </button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="card in [
                    {
                        label: 'Credit spend (charged to users)',
                        value: totals.charged_nanos,
                    },
                    {
                        label: 'Provider cost (our records)',
                        value: totals.provider_nanos,
                    },
                    { label: 'Profit', value: totals.profit_nanos },
                    {
                        label: 'Pixelforge /v1/usage',
                        value: totals.pixelforge_nanos,
                    },
                ]"
                :key="card.label"
                class="print p-4"
            >
                <div class="film-label">
                    {{ card.label }}
                </div>
                <div
                    class="mt-1 font-mono text-xl font-medium"
                    :title="formatUsdExact(card.value)"
                >
                    {{ formatUsd(card.value) }}
                </div>
            </div>
        </div>

        <div
            v-if="pixelforgeError"
            class="flex items-center gap-2 rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm"
        >
            <CircleAlert class="size-4" />
            Couldn't load Pixelforge usage: {{ pixelforgeError }}
        </div>
        <div
            v-else
            class="flex items-center gap-2 text-sm"
            :class="
                allMatch()
                    ? 'text-emerald-600 dark:text-emerald-400'
                    : 'text-amber-600'
            "
        >
            <component
                :is="allMatch() ? CircleCheck : CircleAlert"
                class="size-4"
            />
            {{
                allMatch()
                    ? 'Provider cost matches Pixelforge to the nano-dollar.'
                    : 'Some days differ from Pixelforge — see highlighted rows.'
            }}
        </div>

        <section class="print overflow-hidden">
            <h2 class="film-label border-b px-4 py-3">By day (UTC)</h2>
            <div class="overflow-x-auto">
                <table class="w-full font-mono text-xs whitespace-nowrap">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr class="border-b">
                            <th class="px-4 py-2 font-normal">Date</th>
                            <th class="px-4 py-2 text-right font-normal">
                                Images
                            </th>
                            <th class="px-4 py-2 text-right font-normal">
                                Credit spend
                            </th>
                            <th class="px-4 py-2 text-right font-normal">
                                Provider cost
                            </th>
                            <th class="px-4 py-2 text-right font-normal">
                                Pixelforge says
                            </th>
                            <th class="px-4 py-2 text-right font-normal">
                                Profit
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in daily"
                            :key="row.date"
                            :class="
                                cn(
                                    'border-b last:border-0',
                                    !matches(row) && 'bg-amber-500/10',
                                    !row.generations &&
                                        !row.pixelforge_nanos &&
                                        'text-muted-foreground',
                                )
                            "
                        >
                            <td class="px-4 py-2">{{ row.date }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">
                                {{ row.generations }}
                            </td>
                            <td
                                class="px-4 py-2 text-right tabular-nums"
                                :title="formatUsdExact(row.charged_nanos)"
                            >
                                {{ formatUsd(row.charged_nanos, 6) }}
                            </td>
                            <td
                                class="px-4 py-2 text-right tabular-nums"
                                :title="formatUsdExact(row.provider_nanos)"
                            >
                                {{ formatUsd(row.provider_nanos, 6) }}
                            </td>
                            <td
                                class="px-4 py-2 text-right tabular-nums"
                                :title="formatUsdExact(row.pixelforge_nanos)"
                            >
                                {{ formatUsd(row.pixelforge_nanos, 6) }}
                            </td>
                            <td
                                class="px-4 py-2 text-right tabular-nums"
                                :title="formatUsdExact(row.profit_nanos)"
                            >
                                {{ formatUsd(row.profit_nanos, 6) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="print overflow-hidden">
            <h2 class="film-label border-b px-4 py-3">By user</h2>
            <div class="overflow-x-auto">
                <table class="w-full font-mono text-xs whitespace-nowrap">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr class="border-b">
                            <th class="px-4 py-2 font-normal">User</th>
                            <th class="px-4 py-2 text-right font-normal">
                                Images
                            </th>
                            <th class="px-4 py-2 text-right font-normal">
                                Credit spend
                            </th>
                            <th class="px-4 py-2 text-right font-normal">
                                Provider cost
                            </th>
                            <th class="px-4 py-2 text-right font-normal">
                                Profit
                            </th>
                            <th class="px-4 py-2 text-right font-normal">
                                Balance
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="u in perUser"
                            :key="u.id"
                            class="border-b last:border-0"
                        >
                            <td class="px-4 py-2">
                                <div>{{ u.name }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ u.email }}
                                </div>
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums">
                                {{ u.generations }}
                            </td>
                            <td
                                class="px-4 py-2 text-right tabular-nums"
                                :title="formatUsdExact(u.charged_nanos)"
                            >
                                {{ formatUsd(u.charged_nanos, 6) }}
                            </td>
                            <td
                                class="px-4 py-2 text-right tabular-nums"
                                :title="formatUsdExact(u.provider_nanos)"
                            >
                                {{ formatUsd(u.provider_nanos, 6) }}
                            </td>
                            <td
                                class="px-4 py-2 text-right tabular-nums"
                                :title="formatUsdExact(u.profit_nanos)"
                            >
                                {{ formatUsd(u.profit_nanos, 6) }}
                            </td>
                            <td
                                class="px-4 py-2 text-right text-muted-foreground tabular-nums"
                            >
                                {{ formatUsd(u.balance_nanos, 2) }}
                            </td>
                        </tr>
                        <tr v-if="!perUser.length">
                            <td
                                colspan="6"
                                class="px-4 py-6 text-center text-muted-foreground"
                            >
                                No completed generations in this range.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
