import * as React from "react"

import { cn } from "@/lib/utils"

interface TabsOption {
  value: string
  label: string
}

interface TabsProps {
  items: TabsOption[]
  value: string
  onValueChange: (value: string) => void
  className?: string
}

/**
 * Lista de abas com sublinhado — controla só a seleção, não painéis. A tela
 * que a usa decide o que renderizar para cada `value` (ver telas do design
 * `aprova-app`, onde Tabs sempre aparece acima de conteúdo condicional).
 */
function Tabs({ items, value, onValueChange, className }: TabsProps) {
  return (
    <div
      role="tablist"
      data-slot="tabs"
      className={cn("flex gap-6 border-b border-border", className)}
    >
      {items.map((item) => {
        const active = item.value === value
        return (
          <button
            key={item.value}
            type="button"
            role="tab"
            aria-selected={active}
            data-slot="tabs-item"
            data-state={active ? "active" : "inactive"}
            onClick={() => onValueChange(item.value)}
            className={cn(
              "-mb-px border-b-2 border-transparent py-2.5 text-sm font-semibold whitespace-nowrap text-muted-foreground",
              "focus-visible:ring-ring/50 outline-none focus-visible:ring-[3px]",
              active && "border-focus-500 text-foreground"
            )}
          >
            {item.label}
          </button>
        )
      })}
    </div>
  )
}

export { Tabs }
export type { TabsOption }
