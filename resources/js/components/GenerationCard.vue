<script setup lang="ts">
import { ImageOff } from '@lucide/vue';
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

// A short, stable "frame number" printed on the edge, like on a negative.
const frame = computed(() =>
    props.generation.id.replace(/-/g, '').slice(0, 6).toUpperCase(),
);

const created = computed(() =>
    new Date(props.generation.created_at).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }),
);
</script>

<template>
    <article class="print group flex flex-col p-2.5 pb-0">
        <!-- The image area -->
        <div
            class="relative flex items-center justify-center overflow-hidden bg-muted"
            :style="{ aspectRatio: aspect }"
        >
            <img
                v-if="generation.image_url && !imageBroken"
                :key="generation.image_url"
                :src="generation.image_url"
                :alt="generation.prompt"
                class="develop-in size-full object-cover"
                loading="lazy"
                @error="imageBroken = true"
            />
            <div
                v-else-if="generation.status === 'processing'"
                class="tray flex size-full items-end p-3"
            >
                <span class="film-label !text-white/80">
                    In the developer…
                </span>
            </div>
            <div
                v-else-if="isWorking"
                class="tray-waiting flex size-full items-end p-3"
            >
                <span class="film-label">Waiting for a tray…</span>
            </div>
            <div
                v-else-if="generation.status === 'failed'"
                class="flex size-full flex-col items-center justify-center gap-2 bg-muted px-6 text-center"
            >
                <span class="display text-2xl text-muted-foreground">
                    Spoiled print
                </span>
                <span class="text-xs text-muted-foreground">
                    {{ generation.error_message }}
                </span>
            </div>
            <div
                v-else
                class="flex size-full flex-col items-center justify-center gap-2 text-muted-foreground"
            >
                <ImageOff class="size-5" />
                <span class="film-label">Link expired</span>
            </div>

            <GenerationStatusBadge
                :status="generation.status"
                class="absolute top-2 left-2"
            />
        </div>

        <!-- The caption strip -->
        <div class="flex flex-1 flex-col gap-2 px-1 pt-3 pb-3">
            <p
                class="display line-clamp-2 text-lg leading-snug"
                :title="generation.prompt"
            >
                {{ generation.prompt }}
            </p>

            <div
                class="mt-auto flex items-baseline justify-between gap-3 border-t border-dashed pt-2 font-mono text-[0.6875rem] text-muted-foreground"
            >
                <span class="truncate uppercase">
                    №{{ frame }} · {{ generation.quality }} ·
                    {{ generation.size.replace('x', '×') }}
                    <template v-if="generation.reference_images.length">
                        · {{ generation.reference_images.length }} ref
                    </template>
                </span>
                <span class="shrink-0">{{ created }}</span>
            </div>

            <dl class="grid grid-cols-2 gap-2 font-mono text-xs">
                <div>
                    <dt class="film-label">Quoted</dt>
                    <dd
                        :title="`${formatUsdExact(generation.estimated_min_nanos)} – ${formatUsdExact(generation.estimated_max_nanos)}`"
                    >
                        {{ formatUsd(generation.estimated_min_nanos) }}–{{
                            formatUsd(generation.estimated_max_nanos).slice(1)
                        }}
                    </dd>
                </div>
                <div class="text-right">
                    <dt class="film-label">Charged</dt>
                    <dd :title="formatUsdExact(generation.charged_nanos)">
                        <span
                            v-if="generation.status === 'succeeded'"
                            class="font-medium text-foreground"
                        >
                            {{ formatUsd(generation.charged_nanos) }}
                        </span>
                        <span
                            v-else-if="generation.status === 'failed'"
                            class="text-muted-foreground"
                        >
                            $0 · free
                        </span>
                        <span v-else class="text-muted-foreground">…</span>
                    </dd>
                </div>
            </dl>
        </div>
    </article>
</template>
