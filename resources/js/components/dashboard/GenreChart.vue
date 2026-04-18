<script setup lang="ts">
import { PieChart } from 'echarts/charts';
import { TooltipComponent } from 'echarts/components';
import { use } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { computed, nextTick, onMounted, ref } from 'vue';
import VChart from 'vue-echarts';

use([CanvasRenderer, TooltipComponent, PieChart]);

const props = defineProps<{
    genres?: Array<{ label: string; value: number; color: string }>;
}>();

const canRenderChart = ref(false);

onMounted(async () => {
    await nextTick();
    canRenderChart.value = true;
});

const hasData = computed(() => (props.genres?.length ?? 0) > 0);

function formatGenreLabel(label: string): string {
    if (!label) {
        return label;
    }

    return label.charAt(0).toUpperCase() + label.slice(1);
}

const option = computed(() => {
    const chartData = (props.genres ?? []).map((genre) => ({
        name: formatGenreLabel(genre.label),
        value: genre.value,
        itemStyle: {
            color: genre.color,
        },
    }));

    return {
        tooltip: {
            trigger: 'item',
            backgroundColor: 'rgba(24, 24, 27, 0.95)',
            borderColor: 'rgba(82, 82, 91, 0.6)',
            textStyle: {
                color: '#f4f4f5',
            },
            formatter: '{b}: {c}%',
        },
        series: [
            {
                type: 'pie',
                radius: ['58%', '88%'],
                avoidLabelOverlap: true,
                label: { show: false },
                labelLine: { show: false },
                itemStyle: {
                    borderColor: '#18181b',
                    borderWidth: 2,
                },
                emphasis: {
                    scale: false,
                    itemStyle: {
                        borderColor: '#27272a',
                        borderWidth: 2,
                    },
                },
                data: chartData,
            },
        ],
    };
});
</script>

<template>
    <div
        class="h-full rounded-2xl border border-border bg-card p-5 shadow-card"
    >
        <p
            class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
        >
            Genre Mix
        </p>
        <p class="mt-1 mb-3 font-display text-xl font-bold">Top genres</p>

        <div v-if="hasData" class="flex items-center gap-4">
            <div class="h-36 w-36 shrink-0">
                <VChart
                    v-if="canRenderChart"
                    :option="option"
                    autoresize
                    class="h-full w-full"
                />
            </div>

            <div class="flex-1 space-y-1.5">
                <div
                    v-for="genre in props.genres"
                    :key="genre.label"
                    class="flex items-center gap-2 text-sm"
                >
                    <span
                        class="size-2.5 rounded-full"
                        :style="{ background: genre.color }"
                    />
                    <span class="flex-1 truncate">{{ formatGenreLabel(genre.label) }}</span>
                    <span class="text-muted-foreground tabular-nums"
                        >{{ genre.value }}%</span
                    >
                </div>
            </div>
        </div>

        <div
            v-else
            class="grid h-36 place-items-center rounded-lg border border-dashed border-border text-sm text-muted-foreground"
        >
            Not enough genre data yet.
        </div>
    </div>
</template>
