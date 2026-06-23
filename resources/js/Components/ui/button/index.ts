import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Button } from "./Button.vue"

export const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0",
  {
    variants: {
      variant: {
        default: "bg-primary text-primary-foreground shadow hover:bg-primary/90 rounded-lg",
        destructive:
          "bg-destructive hover:opacity-70 text-white shadow-sm rounded-[14px]",
        outline:
          "border border-input bg-background shadow-sm hover:bg-muted hover:text-foreground",
        secondary:
          "bg-secondary text-secondary-foreground shadow-sm hover:bg-secondary/80",
        ghost: "hover:bg-muted hover:text-foreground",
        link: "text-primary underline-offset-4 hover:underline",
        primary: "bg-gradient-primary hover:opacity-90 text-primary-foreground shadow-sm rounded-[14px] font-semibold",
        white: "border border-input bg-background hover:bg-muted hover:text-foreground shadow-sm rounded-[14px]",
        "table-destructive": "bg-destructive hover:opacity-70 text-white rounded-[13px] shadow-sm focus:outline-none focus:ring-2 focus:ring-rose-500/50",
        "table-edit": "bg-amber-400 hover:opacity-70 text-white rounded-[13px] shadow-sm focus:outline-none focus:ring-2 focus:ring-amber-400/50",
        "table-view": "bg-cyan-500 hover:opacity-70 text-white rounded-[13px] shadow-sm focus:outline-none focus:ring-2 focus:ring-cyan-500/50",
        "table-primary": "bg-gradient-primary hover:opacity-70 text-white rounded-[13px] shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50",
        "more-round-warning": "bg-amber-500 hover:opacity-70 text-white rounded-[14px] shadow-sm",
        warning: "bg-amber-500 hover:opacity-70 text-white rounded-lg shadow-sm",
        success: "bg-[#66BB6A] hover:opacity-70 text-white shadow-sm rounded-[14px]",
      },
      size: {
        "default": "h-9 px-4 py-2",
        "xs": "h-7 rounded px-2",
        "sm": "h-8 rounded-md px-3 text-xs",
        "lg": "h-10 px-4 py-2.5",
        "xl": "h-10 px-8 py-2.5",
        "icon": "h-9 w-9",
        "icon-sm": "size-8",
        "icon-lg": "size-10",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
)

export type ButtonVariants = VariantProps<typeof buttonVariants>
