import { Link, usePage } from '@inertiajs/react';
import { ChevronRight, Plus, type LucideIcon } from 'lucide-react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <SidebarGroup>
            <SidebarGroupLabel>Platform</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => {
                    const isChildActive = item.items?.some((subItem) => subItem.isActive ?? isCurrentUrl(subItem.href));
                    const isOpen = item.isActive || isChildActive;

                    return (
                        <Collapsible
                            key={item.title}
                            asChild
                            defaultOpen={isOpen}
                            className="group/collapsible"
                        >
                            <SidebarMenuItem>
                                <CollapsibleTrigger asChild>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={item.isActive ?? isCurrentUrl(item.href)}
                                    >
                                        <button type="button" className="w-full">
                                            {item.icon && <item.icon />}
                                            <span className="flex-1 text-left">{item.title}</span>
                                            {item.items && (
                                                <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                                            )}
                                        </button>
                                    </SidebarMenuButton>
                                </CollapsibleTrigger>
                                {item.items?.length ? (
                                    <CollapsibleContent>
                                        <SidebarMenuSub>
                                            {item.items.map((subItem) => (
                                                <SidebarMenuSubItem key={subItem.title}>
                                                    <div className="flex items-center w-full group/item">
                                                        <SidebarMenuSubButton
                                                            asChild
                                                            isActive={subItem.isActive ?? isCurrentUrl(subItem.href)}
                                                            className="flex-1"
                                                        >
                                                            <Link href={subItem.href} prefetch>
                                                                <span>{subItem.title}</span>
                                                            </Link>
                                                        </SidebarMenuSubButton>
                                                        {subItem.createUrl && (
                                                            <Link
                                                                href={subItem.createUrl}
                                                                className="ml-2 p-1 text-sidebar-foreground/70 hover:text-sidebar-foreground hover:bg-sidebar-accent rounded-sm opacity-0 group-hover/item:opacity-100 transition-opacity"
                                                                title={`Create ${subItem.title}`}
                                                            >
                                                                <Plus className="h-4 w-4" />
                                                            </Link>
                                                        )}
                                                    </div>
                                                </SidebarMenuSubItem>
                                            ))}
                                        </SidebarMenuSub>
                                    </CollapsibleContent>
                                ) : (
                                    !item.items && (
                                        <Link href={item.href} prefetch className="absolute inset-0" />
                                    )
                                )}
                            </SidebarMenuItem>
                        </Collapsible>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}

