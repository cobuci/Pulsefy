export type ContextMenuAction = () => void;

export type ContextMenuItem = {
    key: string;
    label?: string;
    icon?: string;
    disabled?: boolean;
    destructive?: boolean;
    separator?: boolean;
    onSelect?: ContextMenuAction;
    children?: ContextMenuItem[];
};
