<script setup lang="ts">
import { computed } from 'vue';
import type { Estimate } from '@/lib/estimate';
import { formatUsd, formatUsdExact } from '@/lib/money';
import type { PricingTable } from '@/types';

const props = defineProps<{
    cost: Estimate;
    markup: string;
    pricing: PricingTable;
}>();

const markupPct = computed(() => Math.round((Number(props.markup) - 1) * 100));

const range = (min: number, max: number) =>
    min === max ? `${min}` : `${min}–${max}`;

const lines = computed(() => [
    {
        label: 'Prompt text',
        tokens: range(
            props.cost.min.input_text_tokens,
            props.cost.max.input_text_tokens,
        ),
        note: '±15%',
    },
    {
        label: 'Reference images',
        tokens: range(
            props.cost.min.input_image_tokens,
            props.cost.max.input_image_tokens,
        ),
        note: 'exact',
    },
    {
        label: 'Output image',
        tokens: range(
            props.cost.min.output_tokens,
            props.cost.max.output_tokens,
        ),
        note: 'exact',
    },
    {
        label: 'Reasoning',
        tokens: range(
            props.cost.min.reasoning_tokens,
            props.cost.max.reasoning_tokens,
        ),
        note: 'after',
    },
]);

const fee = computed(() => ({
    min: props.cost.minNanos - props.cost.providerMinNanos,
    max: props.cost.maxNanos - props.cost.providerMaxNanos,
}));
</script>

<template>
    <div class="receipt border border-b-0 px-5 pt-4 text-xs">
        <div class="flex items-baseline justify-between">
            <span class="tracking-[0.2em] uppercase">Quote</span>
            <span class="text-muted-foreground">tokens</span>
        </div>

        <ul class="mt-3 space-y-1.5">
            <li v-for="line in lines" :key="line.label" class="flex">
                <span>{{ line.label }}</span>
                <span class="leader" />
                <span class="tabular-nums">{{ line.tokens }}</span>
                <span class="ml-2 w-10 text-right text-muted-foreground">
                    {{ line.note }}
                </span>
            </li>
        </ul>

        <div class="my-3 border-t border-dashed" />

        <ul class="space-y-1.5">
            <li class="flex">
                <span>Pixelforge cost</span>
                <span class="leader" />
                <span
                    class="tabular-nums"
                    :title="`${formatUsdExact(cost.providerMinNanos)} – ${formatUsdExact(cost.providerMaxNanos)}`"
                >
                    {{ formatUsd(cost.providerMinNanos) }}–{{
                        formatUsd(cost.providerMaxNanos).slice(1)
                    }}
                </span>
            </li>
            <li class="flex">
                <span>Studio fee {{ markupPct }}%</span>
                <span class="leader" />
                <span class="tabular-nums">
                    {{ formatUsd(fee.min) }}–{{ formatUsd(fee.max).slice(1) }}
                </span>
            </li>
        </ul>

        <div class="my-3 border-t-2 border-double border-foreground/60" />

        <div class="flex items-baseline">
            <span class="text-sm font-medium tracking-[0.15em] uppercase">
                Estimate
            </span>
            <span class="leader" />
            <span
                class="text-base font-medium tabular-nums"
                :title="`${formatUsdExact(cost.minNanos)} – ${formatUsdExact(cost.maxNanos)}`"
            >
                {{ formatUsd(cost.minNanos) }} –
                {{ formatUsd(cost.maxNanos) }}
            </span>
        </div>

        <p class="mt-3 leading-relaxed text-muted-foreground">
            You pay the exact cost once the print is done, never more than the
            top of this range. The top is held from your balance meanwhile.
            Failed prints are free.
        </p>
    </div>
</template>
