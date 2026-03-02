import * as React from 'react';
import { useState, useEffect, Fragment } from 'react';
import { Combobox, Transition } from '@headlessui/react';
import { Check, ChevronsUpDown, Loader2 } from 'lucide-react';
import { useDebounce } from '@/hooks/use-debounce';
import axios from 'axios';
import { cn } from '@/lib/utils';

export interface AsyncComboboxProps<T> {
    value?: T;
    onChange: (value: T | null) => void;
    endpoint: string;
    placeholder?: string;
    renderItem?: (item: T) => React.ReactNode;
    itemValue?: (item: T) => string | number;
    itemLabel?: (item: T) => string;
    queryParam?: string;
    additionalParams?: Record<string, any>;
    className?: string;
    disabled?: boolean;
    isInvalid?: boolean;
    onSelect?: () => void;
    excludedIds?: Extract<T, any>[]; // Or (string | number)[]
    id?: string;
}

export const AsyncCombobox = React.forwardRef<HTMLInputElement, AsyncComboboxProps<any>>(({
    value,
    onChange,
    endpoint,
    placeholder = 'Select item...',
    renderItem,
    itemValue = (item) => item.id,
    itemLabel = (item) => String((item as any).name || ''),
    queryParam = 'search',
    additionalParams = {},
    className,
    disabled = false,
    isInvalid = false,
    onKeyDown,
    onSelect,
    excludedIds = [],
    id,
}, ref) => {
    const [query, setQuery] = useState('');
    const [items, setItems] = useState<any[]>([]);
    const [loading, setLoading] = useState(false);
    const debouncedQuery = useDebounce(query, 300);
    const [selected, setSelected] = useState<any | null>(value || null);

    // Sync internal selected state with prop
    useEffect(() => {
        setSelected(value || null);
    }, [value]);

    useEffect(() => {
        let active = true;
        setLoading(true);

        axios.get(endpoint, {
            params: {
                [queryParam]: debouncedQuery,
                json: true,
                ...additionalParams
            }
        })
            .then(res => {
                if (active) {
                    // Ensure response is array
                    const data = Array.isArray(res.data) ? res.data : (res.data.data || []);
                    setItems(data);
                }
            })
            .catch(err => {
                console.error("AsyncCombobox search error:", err);
                setItems([]);
            })
            .finally(() => {
                if (active) setLoading(false);
            });

        return () => { active = false; };
    }, [debouncedQuery, endpoint, JSON.stringify(additionalParams)]);

    return (
        <div className={cn("relative w-full", className)}>
            <Combobox value={selected} onChange={(val) => {
                setSelected(val);
                onChange(val);
                if (val && onSelect) onSelect();
            }} disabled={disabled} nullable by="id">
                <div className="relative mt-1">
                    <div className={cn(
                        "relative w-full cursor-default overflow-hidden rounded-lg text-left border shadow-sm sm:text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-white/75 focus-visible:ring-offset-2 focus-visible:ring-offset-teal-300 h-11 flex items-center",
                        !isInvalid && "bg-white dark:bg-zinc-950 border-zinc-200 dark:border-zinc-800",
                        isInvalid && "border-red-500 ring-red-500/20 bg-red-500/10 text-red-500"
                    )}>
                        <Combobox.Input
                            ref={ref}
                            className={cn(
                                "w-full border-none py-2 pl-3 pr-10 text-sm leading-5 bg-transparent focus:ring-0 h-full",
                                !isInvalid && "text-zinc-900 dark:text-zinc-100",
                                isInvalid && "text-red-500 placeholder:text-red-500"
                            )}
                            displayValue={(item: any) => item ? itemLabel(item) : ''}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder={placeholder}
                            onKeyDown={onKeyDown}
                            id={id}
                        />
                        <Combobox.Button className="absolute inset-y-0 right-0 flex items-center pr-2">
                            {loading ? (
                                <Loader2 className="h-4 w-4 animate-spin text-zinc-400" />
                            ) : (
                                <ChevronsUpDown
                                    className="h-5 w-5 text-zinc-400"
                                    aria-hidden="true"
                                />
                            )}
                        </Combobox.Button>
                    </div>
                    <Transition
                        as={Fragment}
                        leave="transition ease-in duration-100"
                        leaveFrom="opacity-100"
                        leaveTo="opacity-0"
                        afterLeave={() => setQuery('')}
                    >
                        <Combobox.Options className="absolute mt-1 max-h-60 w-full overflow-auto rounded-md bg-white dark:bg-zinc-950 py-1 text-base shadow-lg ring-1 ring-black/5 focus:outline-none sm:text-sm z-50">
                            {(() => {
                                const filteredItems = items.filter(item => !excludedIds.map(String).includes(String(itemValue(item))));
                                return filteredItems.length === 0 && !loading && query !== '' ? (
                                    <div className="relative cursor-default select-none px-4 py-2 text-zinc-700 dark:text-zinc-400">
                                        Nothing found.
                                    </div>
                                ) : (
                                    filteredItems.map((item) => (
                                        <Combobox.Option
                                            key={itemValue(item)}
                                            className={({ active }) =>
                                                `relative cursor-default select-none py-2 pl-10 pr-4 ${active ? 'bg-blue-600 text-white' : 'text-zinc-900 dark:text-zinc-100'
                                                }`
                                            }
                                            value={item}
                                        >
                                            {({ selected, active }) => (
                                                <>
                                                    <span
                                                        className={`block truncate ${selected ? 'font-medium' : 'font-normal'
                                                            }`}
                                                    >
                                                        {renderItem ? renderItem(item) : itemLabel(item)}
                                                    </span>
                                                    {selected ? (
                                                        <span
                                                            className={`absolute inset-y-0 left-0 flex items-center pl-3 ${active ? 'text-white' : 'text-blue-600'
                                                                }`}
                                                        >
                                                            <Check className="h-5 w-5" aria-hidden="true" />
                                                        </span>
                                                    ) : null}
                                                </>
                                            )}
                                        </Combobox.Option>
                                    ))
                                );
                            })()}
                        </Combobox.Options>
                    </Transition>
                </div>
            </Combobox>
        </div>
    );
});
