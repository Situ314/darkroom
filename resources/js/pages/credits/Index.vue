<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Wallet } from '@lucide/vue';
import CreditController from '@/actions/App/Http/Controllers/CreditController';
import { Button } from '@/components/ui/button';
import { formatUsd, formatUsdExact } from '@/lib/money';
import { index } from '@/routes/credits';
import type { Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Credits', href: index() }],
    },
});

type Transaction = {
    id: number;
    type: 'purchase' | 'welcome' | 'charge';
    description: string;
    amount_nanos: number;
    balance_after_nanos: number;
    created_at: string;
};

defineProps<{
    balanceNanos: number;
    availableNanos: number;
    packs: { usd: string; nanos: number }[];
    transactions: Paginated<Transaction>;
}>();

const date = (iso: string) =>
    new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
</script>

<template>
    <Head title="Credits" />

    <div class="flex max-w-3xl flex-col gap-6 p-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div
                class="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border"
            >
                <div
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <Wallet class="size-4" /> Balance
                </div>
                <div
                    class="mt-2 text-3xl font-semibold tabular-nums"
                    :title="formatUsdExact(balanceNanos)"
                >
                    {{ formatUsd(balanceNanos, 2) }}
                </div>
                <p
                    v-if="availableNanos !== balanceNanos"
                    class="mt-1 text-xs text-muted-foreground"
                >
                    {{ formatUsd(availableNanos, 4) }} available — the rest is
                    held for images still generating.
                </p>
            </div>

            <div
                class="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border"
            >
                <div class="text-sm text-muted-foreground">Buy credits</div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <Form
                        v-for="pack in packs"
                        :key="pack.usd"
                        v-bind="CreditController.store.form()"
                        :transform="() => ({ usd: pack.usd })"
                        :options="{ preserveScroll: true }"
                        v-slot="{ processing }"
                    >
                        <Button
                            type="submit"
                            variant="outline"
                            :disabled="processing"
                        >
                            + ${{ pack.usd.replace(/\.00$/, '') }}
                        </Button>
                    </Form>
                </div>
                <p class="mt-3 text-xs text-muted-foreground">
                    Demo only — no payment is taken.
                </p>
            </div>
        </div>

        <section
            class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <h2 class="border-b px-4 py-3 text-sm font-medium">Activity</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr class="border-b">
                            <th class="px-4 py-2 font-normal">Date</th>
                            <th class="px-4 py-2 font-normal">Description</th>
                            <th class="px-4 py-2 text-right font-normal">
                                Amount
                            </th>
                            <th class="px-4 py-2 text-right font-normal">
                                Balance
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="t in transactions.data"
                            :key="t.id"
                            class="border-b last:border-0"
                        >
                            <td
                                class="px-4 py-2 whitespace-nowrap text-muted-foreground"
                            >
                                {{ date(t.created_at) }}
                            </td>
                            <td class="px-4 py-2">{{ t.description }}</td>
                            <td
                                class="px-4 py-2 text-right tabular-nums"
                                :class="
                                    t.amount_nanos > 0
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : ''
                                "
                                :title="formatUsdExact(t.amount_nanos)"
                            >
                                {{ t.amount_nanos > 0 ? '+' : ''
                                }}{{ formatUsd(t.amount_nanos) }}
                            </td>
                            <td
                                class="px-4 py-2 text-right text-muted-foreground tabular-nums"
                                :title="formatUsdExact(t.balance_after_nanos)"
                            >
                                {{ formatUsd(t.balance_after_nanos) }}
                            </td>
                        </tr>
                        <tr v-if="!transactions.data.length">
                            <td
                                colspan="4"
                                class="px-4 py-6 text-center text-muted-foreground"
                            >
                                No activity yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
