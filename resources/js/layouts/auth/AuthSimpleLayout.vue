<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative flex min-h-svh flex-col items-center justify-center gap-6 overflow-hidden bg-background p-6 md:p-10"
    >
        <!-- Safelight glow -->
        <div
            class="pointer-events-none absolute -top-48 left-1/2 size-[36rem] -translate-x-1/2 rounded-full bg-primary/15 blur-3xl"
            aria-hidden="true"
        />

        <div class="relative w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-5">
                    <Link
                        :href="home()"
                        class="flex items-center gap-2 font-medium"
                    >
                        <AppLogoIcon class="size-8 text-foreground" />
                        <span class="display text-2xl">Darkroom</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="display text-4xl">{{ title }}</h1>
                        <p class="text-center text-sm text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <div class="print p-6">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
