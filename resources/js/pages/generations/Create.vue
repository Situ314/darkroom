<script setup lang="ts">
import { Head, Link, useForm, usePoll } from '@inertiajs/vue3';
import { Plus, X } from '@lucide/vue';
import { computed, watch } from 'vue';
import CostReceipt from '@/components/CostReceipt.vue';
import GenerationCard from '@/components/GenerationCard.vue';
import InputError from '@/components/InputError.vue';
import { Spinner } from '@/components/ui/spinner';
import { estimate } from '@/lib/estimate';
import { formatUsd, formatUsdExact } from '@/lib/money';
import { cn } from '@/lib/utils';
import { index as creditsIndex } from '@/routes/credits';
import { create, index as historyIndex, store } from '@/routes/generations';
import type { Generation, PricingTable } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Develop', href: create() }],
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
    { value: '1024x1024', label: 'Square', w: 26, h: 26 },
    { value: '1536x1024', label: 'Landscape', w: 36, h: 24 },
    { value: '1024x1536', label: 'Portrait', w: 24, h: 36 },
];

const qualities = [
    { value: 'low', label: 'Low', hint: 'proof' },
    { value: 'medium', label: 'Medium', hint: 'standard' },
    { value: 'high', label: 'High', hint: 'fine art' },
];

const draft = useForm({
    prompt: '',
    size: '1024x1024',
    quality: 'medium',
    reference_images: [] as string[],
});

const promptLength = computed(() => Array.from(draft.prompt).length);

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
    if (draft.processing || !draft.prompt.trim() || !canAfford.value) {
        return;
    }

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

// Hung prints tilt a little, like real ones pegged on a line.
const tilt = (i: number) => ['-0.6deg', '0.4deg', '-0.2deg', '0.7deg'][i % 4];
</script>

<template>
    <Head title="Develop" />

    <div
        class="grid gap-10 px-4 py-6 lg:grid-cols-[minmax(0,30rem)_1fr] lg:px-8"
    >
        <!-- Left: the enlarger -->
        <form class="flex flex-col gap-7" @submit.prevent="submit">
            <header>
                <p class="film-label">Studio · new exposure</p>
                <h1 class="display mt-2 text-5xl sm:text-6xl">
                    What should we
                    <span class="text-primary">develop</span>?
                </h1>
            </header>

            <!-- Prompt -->
            <div class="grid gap-2">
                <div class="flex items-baseline justify-between">
                    <label for="prompt" class="film-label">The negative</label>
                    <span
                        class="font-mono text-[0.6875rem] text-muted-foreground"
                        :class="{
                            'text-destructive': promptLength > MAX_PROMPT,
                        }"
                    >
                        {{ promptLength }}/{{ MAX_PROMPT }}
                    </span>
                </div>
                <textarea
                    id="prompt"
                    v-model="draft.prompt"
                    v-focus
                    rows="4"
                    required
                    :maxlength="MAX_PROMPT"
                    placeholder="A lighthouse keeper's cat watching ships at dawn, grainy 35mm…"
                    class="w-full resize-y border-0 border-b-2 border-foreground/80 bg-transparent px-0 pb-3 text-xl leading-relaxed outline-none placeholder:text-muted-foreground/60 focus:border-primary"
                    @keydown.meta.enter.prevent="submit"
                    @keydown.ctrl.enter.prevent="submit"
                />
                <InputError :message="draft.errors.prompt" />
            </div>

            <!-- Format -->
            <fieldset class="grid gap-2">
                <legend class="film-label mb-2">Paper format</legend>
                <div class="grid grid-cols-3 gap-2">
                    <button
                        v-for="s in sizes"
                        :key="s.value"
                        type="button"
                        :class="
                            cn(
                                'group relative flex flex-col items-center gap-2 border bg-card px-2 pt-4 pb-3 transition-colors hover:border-foreground/40',
                                draft.size === s.value &&
                                    'border-primary ring-1 ring-primary',
                            )
                        "
                        :aria-pressed="draft.size === s.value"
                        @click="draft.size = s.value"
                    >
                        <span class="flex h-10 items-center">
                            <span
                                class="block border-[1.5px] transition-colors"
                                :class="
                                    draft.size === s.value
                                        ? 'border-primary bg-primary/10'
                                        : 'border-foreground/50'
                                "
                                :style="{
                                    width: `${s.w}px`,
                                    height: `${s.h}px`,
                                }"
                            />
                        </span>
                        <span class="text-sm font-medium">{{ s.label }}</span>
                        <span
                            class="font-mono text-[0.625rem] text-muted-foreground"
                        >
                            {{ s.value.replace('x', '×') }}
                        </span>
                    </button>
                </div>
                <InputError :message="draft.errors.size" />
            </fieldset>

            <!-- Quality -->
            <fieldset class="grid gap-2">
                <legend class="film-label mb-2">Print quality</legend>
                <div class="grid grid-cols-3 border bg-card">
                    <button
                        v-for="(q, i) in qualities"
                        :key="q.value"
                        type="button"
                        :class="
                            cn(
                                'flex flex-col items-center py-2.5 transition-colors',
                                i > 0 && 'border-l',
                                draft.quality === q.value
                                    ? 'bg-foreground text-background'
                                    : 'hover:bg-accent',
                            )
                        "
                        :aria-pressed="draft.quality === q.value"
                        @click="draft.quality = q.value"
                    >
                        <span class="text-sm font-medium">{{ q.label }}</span>
                        <span class="font-mono text-[0.625rem] opacity-60">
                            {{ q.hint }}
                        </span>
                    </button>
                </div>
                <InputError :message="draft.errors.quality" />
            </fieldset>

            <!-- References -->
            <fieldset class="grid gap-2">
                <div class="flex items-center justify-between">
                    <legend class="film-label">
                        Reference images · optional
                    </legend>
                    <button
                        v-if="draft.reference_images.length < MAX_REFERENCES"
                        type="button"
                        class="flex items-center gap-1 font-mono text-[0.6875rem] tracking-wider text-primary uppercase hover:underline"
                        @click="addReference"
                    >
                        <Plus class="size-3" /> Add URL
                    </button>
                </div>
                <p
                    v-if="!draft.reference_images.length"
                    class="text-sm text-muted-foreground"
                >
                    Up to {{ MAX_REFERENCES }} image links to steer the style.
                    {{ pricing.tokens.per_reference_image }} tokens each.
                </p>
                <div
                    v-for="(url, index) in draft.reference_images"
                    :key="index"
                    class="grid gap-1"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="w-5 font-mono text-xs text-muted-foreground"
                        >
                            {{ index + 1 }}.
                        </span>
                        <input
                            :value="url"
                            type="url"
                            placeholder="https://example.com/inspiration.jpg"
                            class="h-9 w-full min-w-0 border-0 border-b bg-transparent px-0 font-mono text-sm outline-none placeholder:text-muted-foreground/60 focus:border-primary"
                            @input="
                                setReference(
                                    index,
                                    ($event.target as HTMLInputElement).value,
                                )
                            "
                        />
                        <button
                            type="button"
                            class="text-muted-foreground hover:text-foreground"
                            aria-label="Remove reference image"
                            @click="removeReference(index)"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                    <InputError :message="referenceError(index)" />
                </div>
                <InputError :message="draft.errors.reference_images" />
            </fieldset>

            <!-- Quote + action -->
            <CostReceipt :cost="cost" :markup="markup" :pricing="pricing" />

            <div class="flex flex-col gap-3">
                <button
                    type="submit"
                    class="group flex h-14 items-center justify-center gap-3 bg-primary text-lg font-medium text-primary-foreground transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="
                        draft.processing || !draft.prompt.trim() || !canAfford
                    "
                >
                    <Spinner v-if="draft.processing" />
                    <span v-else class="size-2.5 rounded-full bg-current" />
                    Develop print
                    <kbd
                        class="ml-1 hidden font-mono text-xs opacity-60 sm:inline"
                    >
                        ⌘↵
                    </kbd>
                </button>
                <div
                    class="flex items-center justify-between font-mono text-[0.6875rem] text-muted-foreground"
                >
                    <span>
                        AVAILABLE
                        <span
                            class="text-foreground"
                            :title="formatUsdExact(availableNanos)"
                        >
                            {{ formatUsd(availableNanos, 2) }}
                        </span>
                    </span>
                    <Link
                        :href="creditsIndex()"
                        class="uppercase hover:text-foreground"
                        :class="{ '!text-destructive': !canAfford }"
                    >
                        {{
                            canAfford
                                ? 'Top up →'
                                : 'Not enough credit · top up →'
                        }}
                    </Link>
                </div>
            </div>
        </form>

        <!-- Right: the drying line -->
        <section class="flex min-w-0 flex-col gap-6">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="film-label">Drying line</p>
                    <h2 class="display mt-1 text-3xl">Latest prints</h2>
                </div>
                <Link
                    v-if="recent.length"
                    :href="historyIndex()"
                    class="font-mono text-[0.6875rem] tracking-wider text-muted-foreground uppercase hover:text-foreground"
                >
                    All prints →
                </Link>
            </div>

            <div
                v-if="!recent.length"
                class="flex min-h-80 flex-1 flex-col items-center justify-center gap-3 border border-dashed text-center"
            >
                <span class="display text-3xl text-muted-foreground">
                    Nothing on the line yet
                </span>
                <span class="text-sm text-muted-foreground">
                    Describe something on the left and hit
                    <span class="text-primary">Develop</span>.
                </span>
            </div>

            <div v-else class="relative pt-3">
                <!-- the line itself -->
                <div
                    class="absolute inset-x-0 top-0 h-px bg-foreground/25"
                    aria-hidden="true"
                />
                <div
                    class="grid gap-x-5 gap-y-8 sm:grid-cols-2 2xl:grid-cols-3"
                >
                    <div
                        v-for="(generation, i) in recent"
                        :key="generation.id"
                        class="relative"
                        :style="{ rotate: tilt(i) }"
                    >
                        <!-- peg -->
                        <span
                            class="absolute -top-4 left-1/2 z-10 h-5 w-2.5 -translate-x-1/2 rounded-[1px] bg-[oklch(0.72_0.06_70)] shadow-sm dark:bg-[oklch(0.55_0.05_60)]"
                            aria-hidden="true"
                        />
                        <GenerationCard :generation="generation" />
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
