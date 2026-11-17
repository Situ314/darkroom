<script setup lang="ts">
import { CircleCheck, CircleX, Clock, LoaderCircle } from '@lucide/vue';
import { computed } from 'vue';
import type { GenerationStatus } from '@/types';

const props = defineProps<{ status: GenerationStatus }>();

const config = computed(() => {
    switch (props.status) {
        case 'pending':
        case 'queued':
            // "pending" = waiting on our side for a free slot at Pixelforge. To the user it's just queued.
            return {
                label: 'Queued',
                icon: Clock,
                class: 'bg-muted text-muted-foreground',
                spin: false,
            };
        case 'processing':
            return {
                label: 'Processing',
                icon: LoaderCircle,
                class: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
                spin: true,
            };
        case 'succeeded':
            return {
                label: 'Succeeded',
                icon: CircleCheck,
                class: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
                spin: false,
            };
        default:
            return {
                label: 'Failed',
                icon: CircleX,
                class: 'bg-red-500/10 text-red-600 dark:text-red-400',
                spin: false,
            };
    }
});
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
        :class="config.class"
    >
        <component
            :is="config.icon"
            class="size-3.5"
            :class="{ 'animate-spin': config.spin }"
        />
        {{ config.label }}
    </span>
</template>
