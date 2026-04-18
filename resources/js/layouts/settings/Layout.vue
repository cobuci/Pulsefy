<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editReverbTest } from '@/routes/reverb-test';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: editProfile(),
    },
    {
        title: 'Appearance',
        href: editAppearance(),
    },
    {
        title: 'Reverb test',
        href: editReverbTest(),
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="mx-auto w-full max-w-7xl px-6 py-8">
        <Heading
            title="Settings"
            description="Manage your profile and account settings"
        />

        <div class="grid gap-6 lg:grid-cols-[220px_1fr]">
            <aside class="w-full">
                <nav
                    class="glass rounded-2xl border border-border/60 p-2"
                    aria-label="Settings"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'w-full justify-start rounded-xl px-3',
                            {
                                'bg-secondary text-foreground':
                                    isCurrentOrParentUrl(item.href),
                            },
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="min-w-0">
                <section
                    class="glass-strong max-w-3xl rounded-2xl border border-border/60 p-6 shadow-card"
                >
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
