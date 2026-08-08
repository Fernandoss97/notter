import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

// O preenchimento ocupa a largura toda e desliza com `translateX`, em vez de
// animar `width`: na tela de foco o valor muda 1x/s e animar largura fazia
// layout + paint a cada tick. `translateX` também preserva o raio da ponta
// direita, que `scaleX` achataria.
const progressBarFillVariants = cva(
  "h-full w-full rounded-full transition-transform duration-base ease-out",
  {
    variants: {
      color: {
        focus: "bg-focus-500",
        pausa: "bg-pausa-500",
        info: "bg-status-info",
        success: "bg-status-success",
        warning: "bg-status-warning",
        danger: "bg-status-danger",
      },
    },
    defaultVariants: {
      color: "focus",
    },
  }
)

interface ProgressBarProps extends VariantProps<typeof progressBarFillVariants> {
  value: number
  height?: number
  showLabel?: boolean
  className?: string
  trackClassName?: string
}

function ProgressBar({
  value,
  color,
  height = 8,
  showLabel = false,
  className,
  trackClassName,
}: ProgressBarProps) {
  const pct = Math.max(0, Math.min(100, value))

  return (
    <div
      data-slot="progress-bar"
      className={cn("flex w-full flex-col gap-1", className)}
    >
      <div
        className={cn("overflow-hidden rounded-full bg-muted", trackClassName)}
        style={{ height }}
      >
        <div
          role="progressbar"
          aria-valuenow={pct}
          aria-valuemin={0}
          aria-valuemax={100}
          className={cn(progressBarFillVariants({ color }))}
          style={{ transform: `translateX(-${100 - pct}%)` }}
        />
      </div>
      {showLabel && (
        <span className="font-mono text-xs text-muted-foreground">
          {pct}%
        </span>
      )}
    </div>
  )
}

export { ProgressBar }
