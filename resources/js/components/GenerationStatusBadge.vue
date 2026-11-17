<script setup lang="ts">
import { computed } from 'vue';
import type { GenerationStatus } from '@/types';

const props = defineProps<{ status: GenerationStatus }>();

const stamp = computed(() => {
    switch (props.status) {
        case 'pending':
        case 'queued':
            return {
                label: 'Queued',
                class: 'border-muted-foreground/50 text-muted-foreground',
                dot: 'bg-muted-foreground',
                pulse: false,
            };
        case 'processing':
            return {
                label: 'Processing',
                class: 'border-primary/60 text-primary',
                dot: 'bg-primary',
                pulse: true,
            };
        case 'succeeded':
            return {
                label: 'Succeeded',
                class: 'border-ok/60 text-ok',
                dot: 'bg-ok',
                pulse: false,
            };
        default:
            return {
                label: 'Failed',
                class: 'border-destructive/60 text-destructive',
                dot: 'bg-destructive',
                pulse: false,
            };
    }
});

const realStatus = computed(() =>
    props.status === 'pending' ? 'queued' : props.status,
);
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-[2px] border bg-card/90 px-1.5 py-0.5 font-mono text-[0.625rem] tracking-[0.14em] uppercase backdrop-blur"
        :class="stamp.class"
        :title="`Status: ${realStatus}`"
    >
        <span class="relative flex size-1.5">
            <span
                v-if="stamp.pulse"
                class="absolute inline-flex size-full animate-ping rounded-full opacity-75"
                :class="stamp.dot"
            />
            <span
                class="relative inline-flex size-1.5 rounded-full"
                :class="stamp.dot"
            />
        </span>
        {{ stamp.label }}
    </span>
</template>
