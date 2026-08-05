import * as ToggleGroupPrimitive from "@radix-ui/react-toggle-group"
import * as React from "react"

import { cn } from "@/lib/utils"

interface SegmentedControlOption {
  value: string
  label: string
}

interface SegmentedControlProps {
  options: SegmentedControlOption[]
  value: string
  onValueChange: (value: string) => void
  className?: string
}

function SegmentedControl({
  options,
  value,
  onValueChange,
  className,
}: SegmentedControlProps) {
  return (
    <ToggleGroupPrimitive.Root
      type="single"
      data-slot="segmented-control"
      value={value}
      onValueChange={(next) => {
        // O radix permite "desmarcar" o item ativo emitindo string vazia;
        // um segmented control sempre tem exatamente um selecionado.
        if (next) onValueChange(next)
      }}
      className={cn(
        "inline-flex gap-0.5 rounded-sm bg-muted p-[3px]",
        className
      )}
    >
      {options.map((option) => (
        <ToggleGroupPrimitive.Item
          key={option.value}
          value={option.value}
          data-slot="segmented-control-item"
          className={cn(
            "rounded-[6px] px-3 py-1.5 text-xs font-semibold whitespace-nowrap text-muted-foreground",
            "data-[state=on]:bg-card data-[state=on]:text-foreground data-[state=on]:shadow-sm",
            "focus-visible:ring-ring/50 outline-none focus-visible:ring-[3px]"
          )}
        >
          {option.label}
        </ToggleGroupPrimitive.Item>
      ))}
    </ToggleGroupPrimitive.Root>
  )
}

export { SegmentedControl }
export type { SegmentedControlOption }
