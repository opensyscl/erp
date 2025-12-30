import { Link } from '@inertiajs/react';

export interface NavTab {
    label: string;
    href: string;
    active?: boolean;
    disabled?: boolean;
}

interface Props {
    tabs: NavTab[];
}

export default function SectionNav({ tabs }: Props) {
    if (!tabs || tabs.length === 0) return null;

    return (
        <div className="flex items-center gap-1 bg-gray-100 rounded-full p-1">
            {tabs.map((tab, index) => (
                tab.disabled ? (
                    <span
                        key={index}
                        className="px-4 py-2 text-sm font-medium rounded-full text-gray-400 cursor-not-allowed"
                    >
                        {tab.label}
                    </span>
                ) : (
                    <Link
                        key={index}
                        href={tab.href}
                        className={`px-4 py-2 text-sm font-medium rounded-full transition-all ${
                            tab.active
                                ? 'bg-primary text-white shadow-sm'
                                : 'text-gray-600 hover:text-gray-900 hover:bg-gray-200'
                        }`}
                    >
                        {tab.label}
                    </Link>
                )
            ))}
        </div>
    );
}
