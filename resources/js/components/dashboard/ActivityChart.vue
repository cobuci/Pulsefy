<script setup lang="ts">
import { use } from 'echarts/core';
import { LineChart } from 'echarts/charts';
import { GridComponent, TooltipComponent } from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';
import { computed } from 'vue';
import VChart from 'vue-echarts';

use([CanvasRenderer, GridComponent, TooltipComponent, LineChart]);

const props = defineProps<{
    points?: Array<{ label: string; value: number }>;
    trendLabel?: string;
}>();

const hasData = computed(() => (props.points?.length ?? 0) > 0);

const option = computed(() => {
    const labels = (props.points ?? []).map((point) => point.label);
    const values = (props.points ?? []).map((point) => point.value);

    return {
        grid: {
            left: 12,
            right: 12,
            top: 12,
            bottom: 16,
            containLabel: true,
        },
        tooltip: {
            trigger: 'axis',
            backgroundColor: 'rgba(24, 24, 27, 0.95)',
            borderColor: 'rgba(82, 82, 91, 0.6)',
            textStyle: {
                color: '#f4f4f5',
            },
            axisPointer: {
                type: 'line',
                lineStyle: {
                    color: 'rgba(94, 234, 212, 0.5)',
                    width: 1,
                },
            },
        },
        xAxis: {
            type: 'category',
            boundaryGap: false,
            data: labels,
            axisLine: {
                lineStyle: {
                    color: 'rgba(82, 82, 91, 0.35)',
                },
            },
            axisTick: { show: false },
            axisLabel: {
                color: '#a1a1aa',
                fontSize: 11,
            },
        },
        yAxis: {
            type: 'value',
            minInterval: 1,
            splitLine: {
                lineStyle: {
                    color: 'rgba(82, 82, 91, 0.18)',
                },
            },
            axisLabel: {
                color: '#a1a1aa',
                fontSize: 11,
            },
        },
        series: [
            {
                type: 'line',
                smooth: 0.35,
                data: values,
                symbol: 'circle',
                symbolSize: 8,
                lineStyle: {
                    width: 3,
                    color: '#67e8f9',
                },
                itemStyle: {
                    color: '#67e8f9',
                    borderColor: '#18181b',
                    borderWidth: 2,
                },
                areaStyle: {
                    color: {
                        type: 'linear',
                        x: 0,
                        y: 0,
                        x2: 0,
                        y2: 1,
                        colorStops: [
                            {
                                offset: 0,
                                color: 'rgba(103, 232, 249, 0.35)',
                            },
                            {
                                offset: 1,
                                color: 'rgba(45, 212, 191, 0.03)',
                            },
                        ],
                    },
                },
            },
        ],
    };
});
</script>

<template>
    <div
        class="h-full rounded-2xl border border-border bg-card p-5 shadow-card"
    >
        <div class="mb-4 flex items-baseline justify-between">
            <div>
                <p
                    class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                >
                    Listening Activity
                </p>
                <p class="mt-1 font-display text-xl font-bold">This week</p>
            </div>
            <p class="text-xs font-medium text-accent">
                {{ trendLabel ?? 'Real-time summary' }}
            </p>
        </div>

        <VChart
            v-if="hasData"
            :option="option"
            autoresize
            class="h-48 w-full"
        />

        <div
            v-else
            class="grid h-48 place-items-center rounded-lg border border-dashed border-border text-sm text-muted-foreground"
        >
            Not enough listening data yet.
        </div>
    </div>
</template>
