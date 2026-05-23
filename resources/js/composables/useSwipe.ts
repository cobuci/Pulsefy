import { onUnmounted, ref } from 'vue';
import type { Ref } from 'vue';

interface SwipeOptions {
    threshold?: number;
    onSwipeLeft?: () => void;
    onSwipeRight?: () => void;
    onSwiping?: (deltaX: number) => void;
    onSwipeEnd?: () => void;
}

export function useSwipe(
    elementRef: Ref<HTMLElement | null>,
    options: SwipeOptions,
) {
    const isDragging = ref(false);
    const deltaX = ref(0);

    let startX = 0;

    function onPointerDown(e: PointerEvent) {
        startX = e.clientX;
        isDragging.value = true;
        (e.target as HTMLElement).setPointerCapture(e.pointerId);
    }

    function onPointerMove(e: PointerEvent) {
        if (!isDragging.value) {
            return;
        }

        deltaX.value = e.clientX - startX;
        options.onSwiping?.(deltaX.value);
    }

    function onPointerUp() {
        if (!isDragging.value) {
            return;
        }

        isDragging.value = false;
        const threshold = options.threshold ?? 50;

        if (deltaX.value < -threshold) {
            options.onSwipeLeft?.();
        } else if (deltaX.value > threshold) {
            options.onSwipeRight?.();
        } else {
            options.onSwipeEnd?.();
        }

        deltaX.value = 0;
    }

    // Attach listeners when the element becomes available via watch
    let attached = false;

    function attach() {
        const el = elementRef.value;

        if (!el || attached) {
            return;
        }

        el.addEventListener('pointerdown', onPointerDown);
        el.addEventListener('pointermove', onPointerMove);
        el.addEventListener('pointerup', onPointerUp);
        el.addEventListener('pointercancel', onPointerUp);
        attached = true;
    }

    function detach() {
        const el = elementRef.value;

        if (!el) {
            return;
        }

        el.removeEventListener('pointerdown', onPointerDown);
        el.removeEventListener('pointermove', onPointerMove);
        el.removeEventListener('pointerup', onPointerUp);
        el.removeEventListener('pointercancel', onPointerUp);
        attached = false;
    }

    onUnmounted(detach);

    return { isDragging, deltaX, attach, detach };
}
