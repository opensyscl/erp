"use client"

import * as React from "react"
import { motion, AnimatePresence } from "motion/react"
import { useMediaQuery } from "@/Hooks/use-media-query"
import { Button } from "@/components/ui/button"
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog"
import {
    Drawer,
    DrawerClose,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from "@/components/ui/drawer"

interface ConfirmDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    confirmLabel?: string;
    cancelLabel?: string;
    onConfirm: () => void;
    variant?: 'danger' | 'warning' | 'default';
    loading?: boolean;
}

// Spinner component
const LoadingSpinner = () => (
    <motion.div
        className="flex items-center justify-center gap-1"
        initial={{ opacity: 0, scale: 0.8 }}
        animate={{ opacity: 1, scale: 1 }}
        exit={{ opacity: 0, scale: 0.8 }}
    >
        <motion.span
            className="w-1.5 h-1.5 bg-white rounded-full"
            animate={{ y: [0, -4, 0] }}
            transition={{ duration: 0.5, repeat: Infinity, delay: 0 }}
        />
        <motion.span
            className="w-1.5 h-1.5 bg-white rounded-full"
            animate={{ y: [0, -4, 0] }}
            transition={{ duration: 0.5, repeat: Infinity, delay: 0.1 }}
        />
        <motion.span
            className="w-1.5 h-1.5 bg-white rounded-full"
            animate={{ y: [0, -4, 0] }}
            transition={{ duration: 0.5, repeat: Infinity, delay: 0.2 }}
        />
    </motion.div>
)

// Animated confirm button
const AnimatedConfirmButton = ({
    loading,
    confirmLabel,
    onClick,
    className
}: {
    loading: boolean;
    confirmLabel: string;
    onClick: () => void;
    className: string;
}) => (
    <motion.button
        className={`inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 h-9 px-4 py-2 min-w-[100px] ${className}`}
        onClick={onClick}
        disabled={loading}
        whileTap={{ scale: 0.95 }}
        layout
    >
        <AnimatePresence mode="wait">
            {loading ? (
                <LoadingSpinner key="spinner" />
            ) : (
                <motion.span
                    key="text"

                    initial={{ opacity: 0, filter: "blur(4px)" }}
                    animate={{ opacity: 1, filter: "blur(0px)" }}
                    exit={{ opacity: 0, filter: "blur(4px)" }}
                    transition={{ duration: 0.2 }}
                >
                    {confirmLabel}
                </motion.span>
            )}
        </AnimatePresence>
    </motion.button>
)

export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel = "Confirmar",
    cancelLabel = "Cancelar",
    onConfirm,
    variant = 'danger',
    loading = false,
}: ConfirmDialogProps) {
    const isDesktop = useMediaQuery("(min-width: 768px)");

    const handleConfirm = () => {
        onConfirm();
    };

    const confirmButtonClass = {
        danger: "bg-danger hover:bg-danger/80 text-white",
        warning: "bg-yellow-600 hover:bg-yellow-700 text-white",
        default: "bg-blue-600 hover:bg-blue-700 text-white",
    }[variant];

    const Actions = () => (
        <div className="flex gap-2 justify-end">
            <Button
                variant="outline"
                onClick={() => onOpenChange(false)}
                disabled={loading}
            >
                {cancelLabel}
            </Button>
            <AnimatedConfirmButton
                loading={loading}
                confirmLabel={confirmLabel}
                onClick={handleConfirm}
                className={confirmButtonClass}
            />
        </div>
    );

    if (isDesktop) {
        return (
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Actions />
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        );
    }

    return (
        <Drawer open={open} onOpenChange={onOpenChange}>
            <DrawerContent>
                <DrawerHeader className="text-left">
                    <DrawerTitle>{title}</DrawerTitle>
                    <DrawerDescription>{description}</DrawerDescription>
                </DrawerHeader>
                <DrawerFooter className="pt-2">
                    <AnimatedConfirmButton
                        loading={loading}
                        confirmLabel={confirmLabel}
                        onClick={handleConfirm}
                        className={confirmButtonClass}
                    />
                    <DrawerClose asChild>
                        <Button variant="outline" disabled={loading}>
                            {cancelLabel}
                        </Button>
                    </DrawerClose>
                </DrawerFooter>
            </DrawerContent>
        </Drawer>
    );
}
