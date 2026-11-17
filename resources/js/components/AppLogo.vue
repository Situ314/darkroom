<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div class="flex aspect-square size-8 items-center justify-center">
        <AppLogoIcon class="size-7 text-foreground" />
    </div>
    <div class="ml-1 grid flex-1 text-left leading-none">
        <span class="display truncate text-xl">Darkroom</span>
        <span class="film-label mt-0.5 !text-[0.6rem]"> words → prints </span>
    </div>
</template>
