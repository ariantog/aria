
import * as React from "react"

import { cn } from "@/lib/utils"


export interface TextareaProps
    extends React.TextareaHTMLAttributes<HTMLTextAreaElement> {
    isInvalid?: boolean;
    isValid?: boolean;
}

const Textarea = React.forwardRef<HTMLTextAreaElement, TextareaProps>(
    ({ className, isInvalid, isValid, ...props }, ref) => {
        return (
            <textarea
                className={cn(
                    "flex min-h-[80px] w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm ring-offset-white placeholder:text-zinc-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-zinc-950 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-800 dark:bg-zinc-950 dark:ring-offset-zinc-950 dark:placeholder:text-zinc-400 dark:focus-visible:ring-zinc-300",
                    isInvalid && "border-red-500 ring-red-500/20 focus-visible:border-red-500 focus-visible:ring-red-500/20 bg-red-500/10 text-red-500 placeholder:text-red-500/50",
                    isValid && "border-green-500 ring-green-500/20 focus-visible:border-green-500 focus-visible:ring-green-500/20 bg-green-500/10 text-green-500",
                    className
                )}
                ref={ref}
                {...props}
            />
        )
    }
)
Textarea.displayName = "Textarea"

export { Textarea }
