<script setup lang="ts">
import { Ban, ChevronRight } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { cn } from '@/lib/utils';
import type { ContextMenuItem } from './types';

const props = defineProps<{
    open: boolean;
    x: number;
    y: number;
    items: ContextMenuItem[];
}>();

const emit = defineEmits<{
    close: [];
}>();

const menuRef = ref<HTMLDivElement | null>(null);
const contentRef = ref<HTMLDivElement | null>(null);
const submenuRefs = ref<Record<string, HTMLDivElement | null>>({});
const openSubmenuKey = ref<string | null>(null);
const position = ref({ x: props.x, y: props.y });

const rootItems = computed(() => props.items);
const hasActionItems = computed(() => rootItems.value.some((item) => !item.separator));

watch(
    () => [props.open, props.x, props.y],
    () => {
        if (!props.open) {
            return;
        }

        openSubmenuKey.value = null;
        position.value = { x: props.x, y: props.y };
        requestAnimationFrame(adjustPosition);
    },
);

function setSubmenuRef(key: string, element: Element | null): void {
    submenuRefs.value[key] = element instanceof HTMLDivElement ? element : null;
}

function closeMenu(): void {
    emit('close');
}

function onGlobalPointerDown(event: PointerEvent): void {
    if (!props.open || !menuRef.value) {
        return;
    }

    const target = event.target;

    if (!(target instanceof Node)) {
        return;
    }

    if (!menuRef.value.contains(target)) {
        closeMenu();
    }
}

function onGlobalContextMenu(event: MouseEvent): void {
    if (!props.open || !menuRef.value) {
        return;
    }

    const target = event.target;

    if (!(target instanceof Node)) {
        return;
    }

    if (!menuRef.value.contains(target)) {
        event.preventDefault();
        closeMenu();
    }
}

function onGlobalKeyDown(event: KeyboardEvent): void {
    if (!props.open) {
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        closeMenu();
    }
}

function adjustPosition(): void {
    if (!contentRef.value) {
        return;
    }

    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const menuRect = contentRef.value.getBoundingClientRect();
    const margin = 12;

    let nextX = props.x;
    let nextY = props.y;

    if (nextX + menuRect.width > viewportWidth - margin) {
        nextX = Math.max(margin, viewportWidth - menuRect.width - margin);
    }

    if (nextY + menuRect.height > viewportHeight - margin) {
        nextY = Math.max(margin, viewportHeight - menuRect.height - margin);
    }

    position.value = {
        x: nextX,
        y: nextY,
    };
}

function openSubmenu(item: ContextMenuItem): void {
    if (!item.children || item.children.length === 0 || item.disabled) {
        openSubmenuKey.value = null;
        return;
    }

    openSubmenuKey.value = item.key;
    requestAnimationFrame(() => adjustSubmenuPosition(item.key));
}

function adjustSubmenuPosition(itemKey: string): void {
    const submenu = submenuRefs.value[itemKey];

    if (!submenu) {
        return;
    }

    submenu.style.left = 'calc(100% + 0.25rem)';
    submenu.style.right = 'auto';
    submenu.style.top = '0';

    const rect = submenu.getBoundingClientRect();
    const margin = 12;

    if (rect.right > window.innerWidth - margin) {
        submenu.style.left = 'auto';
        submenu.style.right = 'calc(100% + 0.25rem)';
    }

    if (rect.bottom > window.innerHeight - margin) {
        const overflow = rect.bottom - (window.innerHeight - margin);
        submenu.style.top = `${Math.max(-rect.top + margin, -overflow)}px`;
    }
}

function selectItem(item: ContextMenuItem): void {
    if (item.disabled || item.separator || item.children?.length) {
        return;
    }

    item.onSelect?.();
    closeMenu();
}

function clearSubmenu(): void {
    openSubmenuKey.value = null;
}

onMounted(() => {
    window.addEventListener('pointerdown', onGlobalPointerDown);
    window.addEventListener('contextmenu', onGlobalContextMenu);
    window.addEventListener('keydown', onGlobalKeyDown);
    window.addEventListener('resize', adjustPosition);
});

onUnmounted(() => {
    window.removeEventListener('pointerdown', onGlobalPointerDown);
    window.removeEventListener('contextmenu', onGlobalContextMenu);
    window.removeEventListener('keydown', onGlobalKeyDown);
    window.removeEventListener('resize', adjustPosition);
});
</script>

<template>
    <teleport to="body">
        <div v-if="open" ref="menuRef" class="fixed inset-0 z-50">
            <div
                ref="contentRef"
                class="fixed min-w-52 rounded-xl border border-border/80 bg-popover p-1 shadow-xl backdrop-blur-sm"
                :style="{
                    left: `${position.x}px`,
                    top: `${position.y}px`,
                }"
                role="menu"
                @mouseleave="clearSubmenu"
            >
                <template v-if="hasActionItems">
                    <template v-for="item in rootItems" :key="item.key">
                        <div v-if="item.separator" class="my-1 h-px bg-border" />

                        <button
                            v-else
                            type="button"
                            class="relative flex w-full items-center justify-between rounded-md px-2.5 py-2 text-left text-sm transition-colors"
                            :class="
                                cn(
                                    'text-popover-foreground hover:bg-accent/60 hover:text-accent-foreground',
                                    item.destructive
                                        ? 'text-destructive hover:bg-destructive/10 hover:text-destructive'
                                        : '',
                                    item.disabled ? 'pointer-events-none opacity-40' : '',
                                )
                            "
                            @mouseenter="openSubmenu(item)"
                            @focus="openSubmenu(item)"
                            @click="selectItem(item)"
                        >
                            <span>{{ item.label }}</span>
                            <ChevronRight v-if="item.children?.length" class="size-4 text-muted-foreground" />

                            <div
                                v-if="item.children?.length && openSubmenuKey === item.key"
                                :ref="(element) => setSubmenuRef(item.key, element)"
                                class="absolute top-0 left-[calc(100%+0.25rem)] min-w-48 rounded-xl border border-border/80 bg-popover p-1 shadow-xl"
                            >
                                <template v-for="child in item.children" :key="child.key">
                                    <div v-if="child.separator" class="my-1 h-px bg-border" />

                                    <button
                                        v-else
                                        type="button"
                                        class="flex w-full items-center rounded-md px-2.5 py-2 text-left text-sm transition-colors"
                                        :class="
                                            cn(
                                                'text-popover-foreground hover:bg-accent/60 hover:text-accent-foreground',
                                                child.destructive
                                                    ? 'text-destructive hover:bg-destructive/10 hover:text-destructive'
                                                    : '',
                                                child.disabled ? 'pointer-events-none opacity-40' : '',
                                            )
                                        "
                                        @click="selectItem(child)"
                                    >
                                        {{ child.label }}
                                    </button>
                                </template>
                            </div>
                        </button>
                    </template>
                </template>

                <div v-else class="flex items-center gap-2 rounded-md px-2.5 py-2 text-sm text-muted-foreground">
                    <Ban class="size-4" />
                    No actions available
                </div>
            </div>
        </div>
    </teleport>
</template>
