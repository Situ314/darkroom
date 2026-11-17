<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { login, register } from '@/routes';
import { create } from '@/routes/generations';

// Decorative prints for the hero. Pure CSS, no image requests.
const samples = [
    {
        caption: 'a tram at midnight in the rain',
        meta: '№A41F · HIGH · 1536×1024',
        tilt: '-2deg',
        bg: 'radial-gradient(circle at 30% 30%, oklch(0.75 0.15 60), transparent 45%), radial-gradient(circle at 70% 70%, oklch(0.45 0.18 30), transparent 50%), oklch(0.25 0.03 260)',
    },
    {
        caption: 'grandmother’s kitchen, 1974',
        meta: '№0C9E · MEDIUM · 1024×1024',
        tilt: '1.5deg',
        bg: 'radial-gradient(circle at 60% 40%, oklch(0.85 0.08 90), transparent 50%), linear-gradient(160deg, oklch(0.6 0.09 60), oklch(0.35 0.05 40))',
    },
    {
        caption: 'a lighthouse keeper’s cat',
        meta: '№7B22 · LOW · 1024×1536',
        tilt: '-0.8deg',
        bg: 'radial-gradient(circle at 50% 25%, oklch(0.9 0.05 80), transparent 35%), linear-gradient(180deg, oklch(0.55 0.08 230), oklch(0.3 0.05 250))',
    },
];

const promises = [
    {
        n: '01',
        title: 'A quote before every shot',
        body: 'Token by token, as you type. Output and reference images are exact; the rest is a tight range.',
    },
    {
        n: '02',
        title: 'Charged the exact cost',
        body: 'Pixelforge’s real usage plus a flat 25%, down to the billionth of a dollar. Never above the quote.',
    },
    {
        n: '03',
        title: 'Spoiled prints are free',
        body: 'If an image fails, you pay nothing. The hold on your credit is released straight away.',
    },
];
</script>

<template>
    <Head title="Develop images from words" />

    <div class="min-h-screen overflow-hidden bg-background text-foreground">
        <!-- Safelight glow -->
        <div
            class="pointer-events-none fixed -top-40 right-[-10%] size-[42rem] rounded-full bg-primary/20 blur-3xl"
            aria-hidden="true"
        />

        <header
            class="relative mx-auto flex max-w-6xl items-center justify-between px-6 py-6"
        >
            <div class="flex items-center gap-2">
                <AppLogoIcon class="size-8 text-foreground" />
                <span class="display text-2xl">Darkroom</span>
            </div>
            <nav class="flex items-center gap-2 text-sm">
                <Link
                    v-if="$page.props.auth.user"
                    :href="create()"
                    class="bg-foreground px-4 py-2 text-background hover:opacity-90"
                >
                    Open the studio →
                </Link>
                <template v-else>
                    <Link :href="login()" class="px-4 py-2 hover:text-primary">
                        Log in
                    </Link>
                    <Link
                        :href="register()"
                        class="bg-foreground px-4 py-2 text-background hover:opacity-90"
                    >
                        Get $1 free
                    </Link>
                </template>
            </nav>
        </header>

        <main class="relative mx-auto max-w-6xl px-6">
            <section
                class="grid items-center gap-16 py-12 lg:grid-cols-[1.1fr_1fr] lg:py-20"
            >
                <div>
                    <p class="film-label">
                        A small image lab · powered by Pixelforge
                    </p>
                    <h1 class="display mt-5 text-6xl sm:text-7xl lg:text-8xl">
                        Develop images
                        <br />
                        from
                        <span class="text-primary">words.</span>
                    </h1>
                    <p
                        class="mt-6 max-w-md text-lg leading-relaxed text-muted-foreground"
                    >
                        Write a prompt, see the price before you press the
                        button, and watch your print come up in the tray. You
                        only ever pay for what develops.
                    </p>
                    <div class="mt-8 flex flex-wrap items-center gap-4">
                        <Link
                            :href="
                                $page.props.auth.user ? create() : register()
                            "
                            class="flex h-14 items-center gap-3 bg-primary px-7 text-lg font-medium text-primary-foreground hover:brightness-110"
                        >
                            <span class="size-2.5 rounded-full bg-current" />
                            Start developing
                        </Link>
                        <span class="font-mono text-xs text-muted-foreground">
                            $1.00 OF CREDIT ON SIGN-UP
                        </span>
                    </div>
                </div>

                <!-- The drying line -->
                <div class="relative hidden h-[30rem] sm:block">
                    <div
                        class="absolute inset-x-0 top-6 h-px bg-foreground/30"
                        aria-hidden="true"
                    />
                    <figure
                        v-for="(s, i) in samples"
                        :key="s.meta"
                        class="print absolute top-6 w-44 p-2.5 pb-3"
                        :style="{
                            left: `${i * 31}%`,
                            rotate: s.tilt,
                            marginTop: `${[0, 3.5, 1.2][i]}rem`,
                        }"
                    >
                        <span
                            class="absolute -top-3 left-1/2 h-5 w-2.5 -translate-x-1/2 bg-[oklch(0.72_0.06_70)]"
                            aria-hidden="true"
                        />
                        <div
                            class="aspect-[4/5]"
                            :style="{ background: s.bg }"
                        />
                        <figcaption class="mt-2 px-0.5">
                            <p class="display text-base leading-tight">
                                {{ s.caption }}
                            </p>
                            <p
                                class="mt-1 font-mono text-[0.6rem] text-muted-foreground"
                            >
                                {{ s.meta }}
                            </p>
                        </figcaption>
                    </figure>
                </div>
            </section>

            <section class="grid gap-px border bg-border sm:grid-cols-3">
                <div v-for="p in promises" :key="p.n" class="bg-background p-6">
                    <span class="font-mono text-xs text-primary">{{
                        p.n
                    }}</span>
                    <h2 class="display mt-3 text-2xl">{{ p.title }}</h2>
                    <p
                        class="mt-2 text-sm leading-relaxed text-muted-foreground"
                    >
                        {{ p.body }}
                    </p>
                </div>
            </section>

            <footer
                class="flex flex-wrap justify-between gap-2 py-10 font-mono text-[0.6875rem] text-muted-foreground"
            >
                <span>DARKROOM · LARAVEL + VUE + INERTIA</span>
                <span>IMAGES BY PIXELFORGE · BILLED TO THE NANO-DOLLAR</span>
            </footer>
        </main>
    </div>
</template>
