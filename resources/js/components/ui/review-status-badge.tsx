import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

/**
 * Estados de revisão espaçada e meta: `overdue`/`ontrack` vêm da agenda de
 * revisão de um tópico; `goalHit`/`goalMiss` vêm da meta de horas de uma
 * disciplina. As quatro variantes compartilham croma/luminosidade e variam
 * só o matiz (ver tokens/colors.css do design system).
 */
const STATUS_LABEL = {
  overdue: "Atrasada",
  ontrack: "Em dia",
  goalHit: "Meta batida",
  goalMiss: "Meta não batida",
} as const

const reviewStatusBadgeVariants = cva(
  "inline-flex w-fit items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold",
  {
    variants: {
      status: {
        overdue: "bg-status-danger-bg text-status-danger",
        ontrack: "bg-status-success-bg text-status-success",
        goalHit: "bg-status-info-bg text-status-info",
        goalMiss: "bg-status-warning-bg text-status-warning",
      },
    },
    defaultVariants: {
      status: "ontrack",
    },
  }
)

interface ReviewStatusBadgeProps
  extends VariantProps<typeof reviewStatusBadgeVariants> {
  label?: string
  className?: string
}

function ReviewStatusBadge({
  status = "ontrack",
  label,
  className,
}: ReviewStatusBadgeProps) {
  return (
    <span
      data-slot="review-status-badge"
      className={cn(reviewStatusBadgeVariants({ status }), className)}
    >
      <span className="size-1.5 shrink-0 rounded-full bg-current" />
      {label ?? STATUS_LABEL[status ?? "ontrack"]}
    </span>
  )
}

export { ReviewStatusBadge, STATUS_LABEL }
