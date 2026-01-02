import { Fragment, ReactNode } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import { X } from 'lucide-react';

interface DrawerProps {
    open?: boolean;
    isOpen?: boolean;
    onClose: () => void;
    title?: string;
    children: ReactNode;
    size?: 'sm' | 'md' | 'lg' | 'xl';
    position?: 'right' | 'left';
}

const sizeClasses = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
};

export default function Drawer({
    open,
    isOpen,
    onClose,
    title,
    children,
    size = 'md',
    position = 'right',
}: DrawerProps) {
    const showDrawer = open ?? isOpen ?? false;

    return (
        <Transition.Root show={showDrawer} as={Fragment}>
            <Dialog as="div" className="relative z-50" onClose={onClose}>
                {/* Backdrop */}
                <Transition.Child
                    as={Fragment}
                    enter="ease-in-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in-out duration-300"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-black/40 transition-opacity" />
                </Transition.Child>

                <div className="fixed inset-0 overflow-hidden">
                    <div className="absolute inset-0 overflow-hidden">
                        <div className={`pointer-events-none fixed inset-y-0 px-2 py-2 ${position === 'right' ? 'right-0' : 'left-0'} flex max-w-full ${position === 'right' ? 'pl-10' : 'pr-10'}`}>
                            <Transition.Child
                                as={Fragment}
                                enter="transform transition ease-in-out duration-300"
                                enterFrom={position === 'right' ? 'translate-x-full' : '-translate-x-full'}
                                enterTo="translate-x-0"
                                leave="transform transition ease-in-out duration-300"
                                leaveFrom="translate-x-0"
                                leaveTo={position === 'right' ? 'translate-x-full' : '-translate-x-full'}
                            >
                                <Dialog.Panel className={`pointer-events-auto w-screen ${sizeClasses[size]}`}>
                                    <div className="flex h-full flex-col bg-white shadow-2xl rounded-3xl overflow-hidden">
                                        {/* Header */}
                                        {title && (
                                            <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                                                <Dialog.Title className="text-lg font-semibold text-gray-900">
                                                    {title}
                                                </Dialog.Title>
                                                <button
                                                    type="button"
                                                    className="rounded-lg p-2 text-gray-400 hover:text-gray-500 hover:bg-gray-100 transition-colors"
                                                    onClick={onClose}
                                                >
                                                    <span className="sr-only">Cerrar</span>
                                                    <X className="h-5 w-5" />
                                                </button>
                                            </div>
                                        )}

                                        {/* Content */}
                                        <div className="flex-1 overflow-y-auto">
                                            {children}
                                        </div>
                                    </div>
                                </Dialog.Panel>
                            </Transition.Child>
                        </div>
                    </div>
                </div>
            </Dialog>
        </Transition.Root>
    );
}
