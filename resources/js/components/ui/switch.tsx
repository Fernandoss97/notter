import * as React from "react"

import { cn } from "@/lib/utils"

interface SwitchProps
  extends Omit<
    React.ComponentProps<"button">,
    "onClick" | "role" | "type"
  > {
  checked: boolean
  onCheckedChange?: (checked: boolean) => void
}

function Switch({
  checked,
  onCheckedChange,
  disabled,
  className,
  ...props
}: SwitchProps) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      data-slot="switch"
      data-state={checked ? "checked" : "unchecked"}
      disabled={disabled}
      onClick={() => onCheckedChange?.(!checked)}
      className={cn(
        "focus-visible:ring-ring/50 inline-flex h-[22px] w-[38px] shrink-0 items-center rounded-full p-0.5 outline-none transition-colors duration-fast focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50",
        checked ? "bg-focus-500" : "bg-border-strong",
        className
      )}
      {...props}
    >
      <span
        data-slot="switch-thumb"
        className={cn(
          "pointer-events-none block size-[18px] rounded-full bg-card shadow-sm transition-transform duration-fast",
          checked && "translate-x-4"
        )}
      />
    </button>
  )
}

export { Switch }
