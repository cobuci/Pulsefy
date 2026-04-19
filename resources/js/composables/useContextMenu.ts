import { computed, ref } from 'vue';
import type { ContextMenuItem } from '@/components/ui/context-menu';

type ContextMenuState = {
    open: boolean;
    x: number;
    y: number;
    items: ContextMenuItem[];
};

const sharedState = ref<ContextMenuState>({
    open: false,
    x: 0,
    y: 0,
    items: [],
});

export function useContextMenu() {

    function open(event: MouseEvent, items: ContextMenuItem[]) {
        event.preventDefault();
        event.stopPropagation();

        sharedState.value = {
            open: true,
            x: event.clientX,
            y: event.clientY,
            items,
        };
    }

    function close() {
        if (!sharedState.value.open) {
            return;
        }

        sharedState.value = {
            ...sharedState.value,
            open: false,
        };
    }

    function setItems(items: ContextMenuItem[]) {
        sharedState.value = {
            ...sharedState.value,
            items,
        };
    }

    const hasActions = computed(() => sharedState.value.items.some((item) => !item.separator));

    return {
        state: sharedState,
        open,
        close,
        setItems,
        hasActions,
    };
}
