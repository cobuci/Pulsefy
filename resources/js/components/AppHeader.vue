<script setup lang="ts">
import { Link, useHttp, usePage } from '@inertiajs/vue3';
import { Activity, Disc3, LayoutGrid, ListMusic, Menu, Search, Sparkles } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
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
import { index as discoveryIndex } from '@/routes/discovery';
import { index as libraryIndex } from '@/routes/library';
import { search } from '@/routes';
import type { BreadcrumbItem, NavItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

type SpotlightItem = {
    id: string;
    type: string;
    title: string;
    subtitle: string;
    href: string;
    image?: string | null;
};

type SpotlightSearchResponse = {
    quick_actions: SpotlightItem[];
    artists: SpotlightItem[];
    albums: SpotlightItem[];
    tracks: SpotlightItem[];
};

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const auth = computed(() => page.props.auth);
const { whenCurrentUrl } = useCurrentUrl();
const searchOpen = ref(false);
const searchQuery = ref('');
const searchActiveIndex = ref(0);
const searchInputRef = ref<HTMLInputElement | null>(null);
const isClientMounted = ref(false);
const searchHttp = useHttp<SpotlightSearchResponse>();
let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null;
let lastIssuedSearchQuery = '';

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
    {
        title: 'Library',
        href: libraryIndex(),
        icon: ListMusic,
    },
    {
        title: 'Discovery',
        href: discoveryIndex(),
        icon: Sparkles,
    },
];

const searchGroups = computed(() => {
    const payload = searchHttp.response as SpotlightSearchResponse | null;

    if (!payload) {
        return [];
    }

    const groups: Array<{ label: string; items: SpotlightItem[] }> = [];

    if (searchQuery.value.trim() === '' && payload.quick_actions.length > 0) {
        groups.push({ label: 'Quick actions', items: payload.quick_actions });
    }

    if (payload.artists.length > 0) {
        groups.push({ label: 'Artists', items: payload.artists });
    }

    if (payload.albums.length > 0) {
        groups.push({ label: 'Albums', items: payload.albums });
    }

    if (payload.tracks.length > 0) {
        groups.push({ label: 'Tracks', items: payload.tracks });
    }

    return groups;
});

const flattenedSearchItems = computed(() => {
    return searchGroups.value.flatMap((group) => group.items);
});

function openSearch() {
    searchOpen.value = true;
}

function closeSearch() {
    searchOpen.value = false;
}

function runSearchRequest(query: string) {
    lastIssuedSearchQuery = query;

    void searchHttp.get(
        search.url({
            query: {
                q: query,
            },
        }),
    );
}

watch(searchOpen, (isOpen) => {
    if (!isOpen) {
        searchQuery.value = '';
        searchActiveIndex.value = 0;
        searchHttp.reset();
        lastIssuedSearchQuery = '';

        return;
    }

    runSearchRequest('');
    void nextTick(() => {
        searchInputRef.value?.focus();
    });
});

watch(searchQuery, (value) => {
    searchActiveIndex.value = 0;

    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }

    const normalizedQuery = value.trim();

    searchDebounceTimer = setTimeout(() => {
        if (normalizedQuery === '') {
            if (lastIssuedSearchQuery === '') {
                return;
            }

            runSearchRequest('');

            return;
        }

        if (normalizedQuery.length < 2) {
            return;
        }

        if (normalizedQuery === lastIssuedSearchQuery) {
            return;
        }

        runSearchRequest(normalizedQuery);
    }, 260);
});

function onSearchKeydown(event: KeyboardEvent) {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();

        if (searchOpen.value) {
            closeSearch();

            return;
        }

        openSearch();

        return;
    }

    if (!searchOpen.value) {
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        closeSearch();

        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        searchActiveIndex.value = Math.min(
            searchActiveIndex.value + 1,
            Math.max(0, flattenedSearchItems.value.length - 1),
        );

        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        searchActiveIndex.value = Math.max(searchActiveIndex.value - 1, 0);

        return;
    }

    if (event.key !== 'Enter') {
        return;
    }

    event.preventDefault();

    const activeItem = flattenedSearchItems.value[searchActiveIndex.value] as
        | SpotlightItem
        | undefined;

    if (!activeItem?.href) {
        return;
    }

    goToSearchItem(activeItem);
}

function goToSearchItem(item: SpotlightItem) {
    window.location.assign(item.href);
    closeSearch();
}

onMounted(() => {
    isClientMounted.value = true;
    document.addEventListener('keydown', onSearchKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', onSearchKeydown);

    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
});
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
                @click="openSearch"
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
            class="border-t border-border/50 bg-background/50"
        >
            <div
                class="mx-auto flex h-10 w-full items-center px-6 md:max-w-7xl"
            >
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
        </div>

        <Teleport v-if="isClientMounted" to="body">
            <div
                v-if="searchOpen"
                class="fixed inset-0 z-[100] grid place-items-start justify-items-center px-4 pt-[18vh]"
            >
            <button
                type="button"
                class="absolute inset-0 bg-background/60 backdrop-blur-md"
                @click="closeSearch"
            />

            <div
                class="relative w-full max-w-[640px] overflow-hidden rounded-2xl border border-accent/20 bg-card shadow-xl"
            >
                <div class="flex h-14 items-center gap-3 border-b border-border/60 px-5">
                    <Search class="h-5 w-5 text-muted-foreground" />
                    <input
                        ref="searchInputRef"
                        v-model="searchQuery"
                        placeholder="Search artists, albums, tracks..."
                        class="flex-1 bg-transparent text-base outline-none placeholder:text-muted-foreground"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        class="text-xs text-muted-foreground hover:text-foreground"
                        @click="searchQuery = ''"
                    >
                        Clear
                    </button>
                </div>

                <div class="max-h-[50vh] overflow-y-auto py-2">
                    <div
                        v-if="searchHttp.processing"
                        class="px-4 py-8 text-center text-sm text-muted-foreground"
                    >
                        Searching...
                    </div>

                    <div
                        v-else-if="flattenedSearchItems.length === 0"
                        class="px-4 py-8 text-center text-sm text-muted-foreground"
                    >
                        No results for "{{ searchQuery }}"
                    </div>

                    <div
                        v-for="group in searchGroups"
                        :key="group.label"
                        class="px-2 pb-2"
                    >
                        <div class="px-3 py-1.5 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                            {{ group.label }}
                        </div>

                        <button
                            v-for="item in group.items"
                            :key="`${item.type}-${item.id}`"
                            type="button"
                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-foreground/80 transition-colors"
                            :class="
                                flattenedSearchItems[searchActiveIndex] === item
                                    ? 'bg-accent/15 text-foreground'
                                    : ''
                            "
                            @mouseenter="
                                searchActiveIndex = flattenedSearchItems.findIndex((flatItem) => flatItem === item)
                            "
                            @click="goToSearchItem(item)"
                        >
                            <img
                                v-if="item.image"
                                :src="item.image"
                                alt=""
                                class="h-9 w-9 shrink-0 rounded-md object-cover"
                            />
                            <div
                                v-else
                                class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-secondary text-xs text-muted-foreground uppercase"
                            >
                                {{ String(item.type).slice(0, 1) }}
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-medium">
                                    {{ item.title }}
                                </div>
                                <div class="truncate text-xs text-muted-foreground">
                                    {{ item.subtitle }}
                                </div>
                            </div>

                            <span class="text-[10px] uppercase tracking-wider text-muted-foreground/70">
                                {{ item.type }}
                            </span>
                        </button>
                    </div>
                </div>

                <div class="flex h-10 items-center justify-between border-t border-border/60 px-4 text-[11px] text-muted-foreground">
                    <div class="flex items-center gap-3">
                        <span><kbd class="rounded bg-secondary px-1.5 py-0.5">↑↓</kbd> navigate</span>
                        <span><kbd class="rounded bg-secondary px-1.5 py-0.5">↵</kbd> open</span>
                        <span><kbd class="rounded bg-secondary px-1.5 py-0.5">esc</kbd> close</span>
                    </div>
                    <span class="font-medium text-accent">Pulsefy</span>
                </div>
            </div>
            </div>
        </Teleport>
    </header>
</template>
