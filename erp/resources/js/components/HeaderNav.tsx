import { Link, usePage } from '@inertiajs/react';

interface NavItem {
    label: string;
    href: string;
    pattern: string; // Route pattern to match for active state
}

interface Props {
    items: NavItem[];
}

export default function HeaderNav({ items }: Props) {
    const { url } = usePage();

    const isActive = (pattern: string) => {
        return url.includes(pattern);
    };

    return (
        <nav className="flex items-center gap-1 bg-gray-100 rounded-full p-1">
            {items.map((item, index) => (
                <Link
                    key={index}
                    href={item.href}
                    className={`px-4 py-2 text-sm font-medium rounded-full transition-all ${
                        isActive(item.pattern)
                            ? 'bg-primary text-white shadow-sm'
                            : 'text-gray-600 hover:text-gray-900 hover:bg-gray-200'
                    }`}
                >
                    {item.label}
                </Link>
            ))}
        </nav>
    );
}
