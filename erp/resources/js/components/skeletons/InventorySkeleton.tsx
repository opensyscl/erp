import { Skeleton } from "@/components/ui/skeleton"

export function ProductCardSkeleton() {
    return (
        <div className="bg-white rounded-lg shadow overflow-hidden">
            {/* Image placeholder */}
            <Skeleton className="aspect-square w-full" />

            {/* Content */}
            <div className="p-4 space-y-3">
                {/* Title */}
                <Skeleton className="h-5 w-3/4" />

                {/* Price */}
                <Skeleton className="h-7 w-1/2" />

                {/* SKU & Cost */}
                <div className="space-y-1">
                    <Skeleton className="h-3 w-20" />
                    <Skeleton className="h-3 w-28" />
                </div>

                {/* Stock badge */}
                <Skeleton className="h-5 w-16 rounded-full" />

                {/* Actions */}
                <div className="flex items-center justify-between border-t pt-3 mt-3">
                    <Skeleton className="h-4 w-16" />
                    <Skeleton className="h-4 w-6" />
                </div>
            </div>
        </div>
    )
}

export function ProductGridSkeleton({ count = 8 }: { count?: number }) {
    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            {Array.from({ length: count }).map((_, i) => (
                <ProductCardSkeleton key={i} />
            ))}
        </div>
    )
}

export function SidebarFilterSkeleton() {
    return (
        <div className="bg-glass rounded-3xl shadow-panel p-[1.5rem] space-y-6">
            {/* Categories */}
            <div className="space-y-3">
                <div className="flex items-center gap-3">
                    <Skeleton className="h-5 w-5 rounded-full" />
                    <Skeleton className="h-5 w-24" />
                </div>
                <div className="space-y-2 ml-2">
                    {Array.from({ length: 5 }).map((_, i) => (
                        <div key={i} className="flex items-center justify-between">
                            <Skeleton className="h-4 w-32" />
                            <Skeleton className="h-4 w-6 rounded-full" />
                        </div>
                    ))}
                </div>
            </div>

            {/* Divider */}
            <div className="border-t" />

            {/* Suppliers */}
            <div className="space-y-3">
                <div className="flex items-center gap-3">
                    <Skeleton className="h-5 w-5 rounded-full" />
                    <Skeleton className="h-5 w-28" />
                </div>
                <div className="space-y-2 ml-2">
                    {Array.from({ length: 4 }).map((_, i) => (
                        <div key={i} className="flex items-center justify-between">
                            <Skeleton className="h-4 w-28" />
                            <Skeleton className="h-4 w-6 rounded-full" />
                        </div>
                    ))}
                </div>
            </div>
        </div>
    )
}

export function StatCardsSkeleton() {
    return (
        <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-4 mb-6">
            {Array.from({ length: 7 }).map((_, i) => (
                <div key={i} className="bg-white rounded-3xl shadow-panel p-4 text-center">
                    <Skeleton className="h-3 w-16 mx-auto mb-2" />
                    <Skeleton className="h-8 w-12 mx-auto" />
                </div>
            ))}
        </div>
    )
}
