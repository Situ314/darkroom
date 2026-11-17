<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- A frame of film with a safelight dot in the middle. -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 40 40"
        :class="className"
        v-bind="$attrs"
    >
        <path
            fill="currentColor"
            fill-rule="evenodd"
            d="M4 6a2 2 0 0 1 2-2h28a2 2 0 0 1 2 2v28a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6Zm4 1h3v3H8V7Zm7 0h3v3h-3V7Zm7 0h3v3h-3V7Zm7 0h3v3h-3V7ZM8 30h3v3H8v-3Zm7 0h3v3h-3v-3Zm7 0h3v3h-3v-3Zm7 0h3v3h-3v-3ZM8 13v14h24V13H8Z"
        />
        <circle cx="20" cy="20" r="4" class="fill-primary" />
    </svg>
</template>
