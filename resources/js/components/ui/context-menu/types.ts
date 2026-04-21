import type { Component } from 'vue';

export type ContextMenuAction = () => void;

export type ContextMenuItem = {
    key: string;
    label?: string;
    icon?: Component;
    loading?: boolean;
    disabled?: boolean;
    destructive?: boolean;
    separator?: boolean;
    onSelect?: ContextMenuAction;
    children?: ContextMenuItem[];
};
