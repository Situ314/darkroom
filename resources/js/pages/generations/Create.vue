<script setup lang="ts">
import { Head, Link, useForm, usePoll } from '@inertiajs/vue3';
import { ImagePlus, Plus, Sparkles, Wallet, X } from '@lucide/vue';
import { computed, watch } from 'vue';
import GenerationCard from '@/components/GenerationCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { estimate } from '@/lib/estimate';
import { formatUsd, formatUsdExact } from '@/lib/money';
import { cn } from '@/lib/utils';
import { index as creditsIndex } from '@/routes/credits';
import { create, index as historyIndex, store } from '@/routes/generations';
import type { Generation, PricingTable } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Generate', href: create() }],
    },
});

const props = defineProps<{
    pricing: PricingTable;
    markup: string;
    availableNanos: number;
    recent: Generation[];
}>();

const MAX_PROMPT = 4000;
const MAX_REFERENCES = 4;

const sizes = [
    { value: '1024x1024', label: 'Square', hint: '1024 × 1024', w: 22, h: 22 },
    {
        value: '1536x1024',
        label: 'Landscape',
        hint: '1536 × 1024',
        w: 30,
        h: 20,
    },
    {
        value: '1024x1536',
        label: 'Portrait',
        hint: '1024 × 1536',
        w: 20,
        h: 30,
    },
];

const qualities = [
    { value: 'low', label: 'Low', hint: 'Fast drafts' },
    { value: 'medium', label: 'Medium', hint: 'Balanced' },
    { value: 'high', label: 'High', hint: 'Most detail' },
];

const draft = useForm({
    prompt: '',
    size: '1024x1024',
    quality: 'medium',
    reference_images: [] as string[],
});

const referenceCount = computed(
    () => draft.reference_images.filter((url) => url.trim() !== '').length,
);

const cost = computed(() =>
    estimate(
        props.pricing,
        props.markup,
        draft.prompt,
        draft.size,
        draft.quality,
        referenceCount.value,
    ),
);

const canAfford = computed(() => props.availableNanos >= cost.value.maxNanos);

function addReference() {
    if (draft.reference_images.length < MAX_REFERENCES) {
        draft.reference_images.push('');
    }
}

function setReference(i: number, value: string) {
    draft.reference_images[i] = value;
}

function removeReference(i: number) {
    draft.reference_images.splice(i, 1);
}

function referenceError(i: number): string | undefined {
    return (draft.errors as Record<string, string | undefined>)[
        `reference_images.${i}`
    ];
}

function submit() {
    draft.post(store().url, {
        preserveScroll: true,
        onSuccess: () => draft.reset('prompt'),
    });
}

// Live status: poll only while something is still running.
const hasActive = computed(() =>
    props.recent.some((g) =>
        ['pending', 'queued', 'processing'].includes(g.status),
    ),
);

const { start, stop } = usePoll(
    2500,
    { only: ['recent', 'availableNanos', 'balanceNanos'] },
    { autoStart: false },
);

watch(hasActive, (active) => (active ? start() : stop()), { immediate: true });

const tokenRows = computed(() => [
    {
        label: 'Prompt text',
        value: `${cost.value.min.input_text_tokens}–${cost.value.max.input_text_tokens}`,
        note: '±15%, exact after',
    },
    {
        label: 'Reference images',
        value: `${cost.value.max.input_image_tokens}`,
        note: `${props.pricing.tokens.per_reference_image} each`,
    },
    {
        label: 'Output image',
        value: `${cost.value.max.output_tokens}`,
        note: 'exact',
    },
    {
        label: 'Reasoning',
        value: `${cost.value.min.reasoning_tokens}–${cost.value.max.reasoning_tokens}`,
        note: 'known after',
    },
]);
</script>

<template>
    <Head title="Generate" />

    <div class="grid gap-6 p-4 lg:grid-cols-[minmax(0,26rem)_1fr]">
        <!-- Form -->
        <form
            class="flex flex-col gap-6 self-start rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border"
            @submit.prevent="submit"
        >
            <div>
                <h1 class="text-lg font-semibold tracking-tight">
                    Create an image
                </h1>
                <p class="text-sm text-muted-foreground">
                    Describe it, pick a shape and quality. You only pay for
                    images that succeed.
                </p>
            </div>

            <div class="grid gap-2">
                <div class="flex items-baseline justify-between">
                    <Label for="prompt">Prompt</Label>
                    <span
                        class="text-xs text-muted-foreground tabular-nums"
                        :class="{
                            'text-destructive':
                                [...draft.prompt].length > MAX_PROMPT,
                        }"
                    >
                        {{ [...draft.prompt].length }} / {{ MAX_PROMPT }}
                    </span>
                </div>
                <textarea
                    id="prompt"
                    v-model="draft.prompt"
                    v-focus
                    rows="5"
                    required
                    :maxlength="MAX_PROMPT"
                    placeholder="A lighthouse on a cliff at dusk, painted in thick oil strokes"
                    class="w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                    @keydown.meta.enter="submit"
                    @keydown.ctrl.enter="submit"
                />
                <InputError :message="draft.errors.prompt" />
            </div>

            <div class="grid gap-2">
                <Label>Size</Label>
                <div class="grid grid-cols-3 gap-2">
                    <button
                        v-for="s in sizes"
                        :key="s.value"
                        type="button"
                        :class="
                            cn(
                                'flex flex-col items-center gap-2 rounded-lg border p-3 text-xs transition-colors hover:bg-accent',
                                draft.size === s.value &&
                                    'border-primary bg-accent ring-1 ring-primary',
                            )
                        "
                        :aria-pressed="draft.size === s.value"
                        @click="draft.size = s.value"
                    >
                        <span class="flex h-8 items-center">
                            <span
                                class="block rounded-sm border-2 border-current opacity-70"
                                :style="{
                                    width: `${s.w}px`,
                                    height: `${s.h}px`,
                                }"
                            />
                        </span>
                        <span class="font-medium">{{ s.label }}</span>
                        <span class="text-muted-foreground">{{ s.hint }}</span>
                    </button>
                </div>
                <InputError :message="draft.errors.size" />
            </div>

            <div class="grid gap-2">
                <Label>Quality</Label>
                <div class="grid grid-cols-3 gap-1 rounded-lg bg-muted p-1">
                    <button
                        v-for="q in qualities"
                        :key="q.value"
                        type="button"
                        :class="
                            cn(
                                'rounded-md px-2 py-1.5 text-sm transition-colors',
                                draft.quality === q.value
                                    ? 'bg-background font-medium shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground',
                            )
                        "
                        :aria-pressed="draft.quality === q.value"
                        :title="q.hint"
                        @click="draft.quality = q.value"
                    >
                        {{ q.label }}
                    </button>
                </div>
                <InputError :message="draft.errors.quality" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label>
                        Reference images
                        <span class="font-normal text-muted-foreground">
                            (optional)
                        </span>
                    </Label>
                    <Button
                        v-if="draft.reference_images.length < MAX_REFERENCES"
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="addReference"
                    >
                        <Plus /> Add URL
                    </Button>
                </div>
                <p
                    v-if="!draft.reference_images.length"
                    class="flex items-center gap-2 text-xs text-muted-foreground"
                >
                    <ImagePlus class="size-4" /> Up to {{ MAX_REFERENCES }}
                    image URLs to guide the style.
                </p>
                <div
                    v-for="(url, index) in draft.reference_images"
                    :key="index"
                    class="grid gap-1"
                >
                    <div class="flex gap-2">
                        <input
                            :value="url"
                            type="url"
                            placeholder="https://example.com/inspiration.png"
                            class="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                            @input="
                                setReference(
                                    index,
                                    ($event.target as HTMLInputElement).value,
                                )
                            "
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label="Remove reference image"
                            @click="removeReference(index)"
                        >
                            <X />
                        </Button>
                    </div>
                    <InputError :message="referenceError(index)" />
                </div>
                <InputError :message="draft.errors.reference_images" />
            </div>

            <!-- Estimate -->
            <div class="rounded-lg border bg-muted/40 p-4">
                <div class="flex items-baseline justify-between gap-4">
                    <span class="text-sm text-muted-foreground">
                        Estimated cost
                    </span>
                    <span
                        class="text-lg font-semibold tabular-nums"
                        :title="`${formatUsdExact(cost.minNanos)} – ${formatUsdExact(cost.maxNanos)}`"
                    >
                        {{ formatUsd(cost.minNanos) }} –
                        {{ formatUsd(cost.maxNanos) }}
                    </span>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    The exact price is known when the image is done. You're
                    charged the real amount, never more than the top of this
                    range. Failed images are free.
                </p>
                <details class="mt-3 text-xs">
                    <summary
                        class="cursor-pointer text-muted-foreground select-none hover:text-foreground"
                    >
                        Token breakdown
                    </summary>
                    <table class="mt-2 w-full">
                        <tbody>
                            <tr v-for="row in tokenRows" :key="row.label">
                                <td class="py-0.5">{{ row.label }}</td>
                                <td class="py-0.5 text-right tabular-nums">
                                    {{ row.value }}
                                </td>
                                <td
                                    class="py-0.5 pl-3 text-right text-muted-foreground"
                                >
                                    {{ row.note }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </details>
            </div>

            <div class="flex flex-col gap-3">
                <Button
                    type="submit"
                    size="lg"
                    :disabled="
                        draft.processing || !draft.prompt.trim() || !canAfford
                    "
                >
                    <Spinner v-if="draft.processing" />
                    <Sparkles v-else />
                    Generate
                </Button>
                <div
                    class="flex items-center justify-between text-xs text-muted-foreground"
                >
                    <span class="flex items-center gap-1.5">
                        <Wallet class="size-3.5" />
                        Available:
                        <span
                            class="font-medium text-foreground tabular-nums"
                            :title="formatUsdExact(availableNanos)"
                        >
                            {{ formatUsd(availableNanos, 2) }}
                        </span>
                    </span>
                    <Link
                        :href="creditsIndex()"
                        class="underline-offset-4 hover:underline"
                        :class="{ 'font-medium text-destructive': !canAfford }"
                    >
                        {{
                            canAfford
                                ? 'Buy credits'
                                : 'Not enough credit — buy more'
                        }}
                    </Link>
                </div>
            </div>
        </form>

        <!-- Recent results -->
        <section class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <h2 class="font-medium">Recent</h2>
                <Link
                    v-if="recent.length"
                    :href="historyIndex()"
                    class="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                >
                    View all history
                </Link>
            </div>

            <div
                v-if="!recent.length"
                class="flex min-h-64 flex-1 flex-col items-center justify-center gap-2 rounded-xl border border-dashed text-center text-sm text-muted-foreground"
            >
                <Sparkles class="size-6" />
                Your images will show up here.
            </div>

            <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <GenerationCard
                    v-for="generation in recent"
                    :key="generation.id"
                    :generation="generation"
                />
            </div>
        </section>
    </div>
</template>
