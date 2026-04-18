<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Activity, Disc3, LayoutGrid, Menu, Search } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuList,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { getInitials } from '@/composables/useInitials';
import { dashboard, recentlyPlayed } from '@/routes';
import { index as artistsIndex } from '@/routes/artists';
import type { BreadcrumbItem, NavItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const auth = computed(() => page.props.auth);
const { whenCurrentUrl } = useCurrentUrl();

const activeItemStyles = 'text-foreground bg-secondary';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Artists',
        href: artistsIndex(),
        icon: Disc3,
    },
    {
        title: 'Recently Played',
        href: recentlyPlayed(),
        icon: Activity,
    },
];
</script>

<template>
    <header
        class="glass fixed top-0 right-0 left-0 z-50 border-b border-border/60"
    >
        <div class="mx-auto flex h-16 items-center gap-6 px-6 md:max-w-7xl">
            <div class="lg:hidden">
                <Sheet>
                    <SheetTrigger :as-child="true">
                        <Button variant="ghost" size="icon" class="h-9 w-9">
                            <Menu class="h-5 w-5" />
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="left" class="w-[300px] p-6">
                        <SheetTitle class="sr-only">Navigation</SheetTitle>
                        <SheetHeader class="flex justify-start text-left">
                            <div
                                class="bg-gradient-primary shadow-glow relative grid size-8 place-items-center rounded-lg"
                            >
                                <AppLogoIcon
                                    class="size-5 text-primary-foreground"
                                />
                            </div>
                        </SheetHeader>
                        <nav class="-mx-3 mt-6 space-y-1">
                            <Link
                                v-for="item in mainNavItems"
                                :key="item.title"
                                :href="item.href"
                                class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                                :class="
                                    whenCurrentUrl(
                                        item.href,
                                        'bg-secondary font-semibold text-foreground',
                                    )
                                "
                            >
                                <component
                                    v-if="item.icon"
                                    :is="item.icon"
                                    class="h-5 w-5"
                                />
                                {{ item.title }}
                            </Link>
                        </nav>
                    </SheetContent>
                </Sheet>
            </div>

            <Link :href="dashboard()" class="group flex items-center gap-2">
                <div
                    class="bg-gradient-primary shadow-glow relative grid size-8 place-items-center rounded-lg"
                >
                    <AppLogoIcon class="size-5 text-primary-foreground" />
                </div>
                <span class="font-display text-lg font-bold tracking-tight">
                    Pulse<span class="text-gradient">fy</span>
                </span>
            </Link>

            <nav class="ml-4 hidden items-center gap-1 md:flex">
                <NavigationMenu>
                    <NavigationMenuList class="gap-1">
                        <NavigationMenuItem
                            v-for="(item, index) in mainNavItems"
                            :key="index"
                        >
                            <Link
                                :class="[
                                    navigationMenuTriggerStyle(),
                                    'h-9 cursor-pointer rounded-md bg-transparent px-3 text-sm text-muted-foreground transition-colors hover:bg-transparent hover:text-foreground',
                                    whenCurrentUrl(item.href, activeItemStyles),
                                ]"
                                :href="item.href"
                            >
                                {{ item.title }}
                            </Link>
                        </NavigationMenuItem>
                    </NavigationMenuList>
                </NavigationMenu>
            </nav>

            <button
                type="button"
                class="ml-auto hidden h-9 w-full max-w-xs items-center gap-2 rounded-lg border border-border/60 bg-secondary/60 px-3 text-sm text-muted-foreground transition-all hover:bg-secondary lg:flex"
            >
                <Search class="h-4 w-4" />
                <span class="flex-1 text-left">Search...</span>
                <kbd
                    class="hidden items-center gap-0.5 rounded border border-border bg-background/60 px-1.5 py-0.5 font-mono text-[10px] sm:inline-flex"
                >
                    ⌘K
                </kbd>
            </button>

            <div>
                <DropdownMenu>
                    <DropdownMenuTrigger :as-child="true">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="relative h-9 w-9 rounded-full p-0.5 focus-within:ring-2 focus-within:ring-primary"
                        >
                            <Avatar class="size-9 overflow-hidden rounded-full">
                                <AvatarImage
                                    v-if="auth.user.avatar"
                                    :src="auth.user.avatar"
                                    :alt="auth.user.name"
                                />
                                <AvatarFallback
                                    class="rounded-full bg-primary font-semibold text-primary-foreground"
                                >
                                    {{ getInitials(auth.user?.name) }}
                                </AvatarFallback>
                            </Avatar>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <UserMenuContent :user="auth.user" />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <div
            v-if="props.breadcrumbs.length > 1"
            class="border-t border-border/50 bg-background/30"
        >
            <div
                class="mx-auto flex h-10 w-full items-center px-6 md:max-w-7xl"
            >
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
        </div>
    </header>
</template>
