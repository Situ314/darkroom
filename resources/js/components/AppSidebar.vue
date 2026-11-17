<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BookText, GalleryHorizontalEnd, Lightbulb, Wallet } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { formatUsd } from '@/lib/money';
import { report } from '@/routes/admin';
import { index as credits } from '@/routes/credits';
import { create, index as history } from '@/routes/generations';
import type { NavItem } from '@/types';

const page = usePage();

const mainNavItems = computed<NavItem[]>(() => [
    { title: 'Develop', href: create(), icon: Lightbulb },
    { title: 'Prints', href: history(), icon: GalleryHorizontalEnd },
    { title: 'Credits', href: credits(), icon: Wallet },
    ...(page.props.auth.isAdmin
        ? [{ title: 'Ledger', href: report(), icon: BookText }]
        : []),
]);

const balance = computed(() => page.props.balanceNanos as number | null);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="create()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton as-child tooltip="Balance">
                        <Link :href="credits()">
                            <Wallet />
                            <span>Balance</span>
                            <span class="ml-auto font-mono text-xs">
                                {{ formatUsd(balance, 2) }}
                            </span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
