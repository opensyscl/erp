import { cn } from "@/lib/utils"
import { motion } from "motion/react"

interface SkeletonProps {
  className?: string;
}

function Skeleton({ className }: SkeletonProps) {
  return (
    <motion.div
      className={cn("rounded-md bg-gray-200", className)}
      initial={{ opacity: 0.3 }}
      animate={{ opacity: [0.3, 1, 0.3] }}
      transition={{
        duration: 1.4,
        repeat: Infinity,
        ease: [0.4, 0, 0.6, 1],
      }}
    />
  )
}

export { Skeleton }
