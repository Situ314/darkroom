<script setup lang="ts">
import { ImageOff, Sparkles } from '@lucide/vue';
import { computed, ref } from 'vue';
import GenerationStatusBadge from '@/components/GenerationStatusBadge.vue';
import { formatUsd, formatUsdExact } from '@/lib/money';
import type { Generation } from '@/types';

const props = defineProps<{ generation: Generation }>();

const imageBroken = ref(false);

const aspect = computed(() => {
    const [w, h] = props.generation.size.split('x').map(Number);

    return `${w} / ${h}`;
});

const isWorking = computed(() =>
    ['pending', 'queued', 'processing'].includes(props.generation.status),
);

const created = computed(() =>
    new Date(props.generation.created_at).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }),
);
</script>

<template>
    <article
        class="flex flex-col overflow-hidden rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
    >
        <div
            class="relative flex items-center justify-center overflow-hidden bg-muted"
            :style="{ aspectRatio: aspect }"
        >
            <img
                v-if="generation.image_url && !imageBroken"
                :src="generation.image_url"
                :alt="generation.prompt"
                class="size-full object-cover"
                loading="lazy"
                @error="imageBroken = true"
            />
            <div
                v-else-if="isWorking"
                class="flex flex-col items-center gap-2 text-muted-foreground"
            >
                <Sparkles class="size-6 animate-pulse" />
                <span class="text-xs">
                    {{
                        generation.status === 'processing'
                            ? 'Painting pixels…'
                            : 'Waiting in line…'
                    }}
                </span>
            </div>
            <div
                v-else-if="generation.status === 'failed'"
                class="flex flex-col items-center gap-2 px-4 text-center text-muted-foreground"
            >
                <ImageOff class="size-6" />
                <span class="text-xs">{{ generation.error_message }}</span>
            </div>
            <div
                v-else
                class="flex flex-col items-center gap-2 text-muted-foreground"
            >
                <ImageOff class="size-6" />
                <span class="text-xs">Image link expired</span>
            </div>

            <GenerationStatusBadge
                :status="generation.status"
                class="absolute top-2 left-2 shadow-sm backdrop-blur"
            />
        </div>

        <div class="flex flex-1 flex-col gap-3 p-3">
            <p class="line-clamp-2 text-sm" :title="generation.prompt">
                {{ generation.prompt }}
            </p>

            <div
                class="mt-auto flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground"
            >
                <span class="capitalize">{{ generation.quality }}</span>
                <span>·</span>
                <span>{{ generation.size }}</span>
                <span v-if="generation.reference_images.length">·</span>
                <span v-if="generation.reference_images.length">
                    {{ generation.reference_images.length }} ref
                </span>
                <span class="ml-auto">{{ created }}</span>
            </div>

            <dl class="grid grid-cols-2 gap-2 border-t pt-3 text-xs">
                <div>
                    <dt class="text-muted-foreground">Estimated</dt>
                    <dd
                        class="font-medium tabular-nums"
                        :title="`${formatUsdExact(generation.estimated_min_nanos)} – ${formatUsdExact(generation.estimated_max_nanos)}`"
                    >
                        {{ formatUsd(generation.estimated_min_nanos) }}–{{
                            formatUsd(generation.estimated_max_nanos)
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Actual</dt>
                    <dd
                        class="font-medium tabular-nums"
                        :title="formatUsdExact(generation.charged_nanos)"
                    >
                        <template v-if="generation.status === 'succeeded'">
                            {{ formatUsd(generation.charged_nanos) }}
                        </template>
                        <template v-else-if="generation.status === 'failed'">
                            $0.0000
                            <span class="font-normal text-muted-foreground">
                                (not charged)
                            </span>
                        </template>
                        <span v-else class="text-muted-foreground">
                            pending
                        </span>
                    </dd>
                </div>
            </dl>
        </div>
    </article>
</template>
