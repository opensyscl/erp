import { Link, usePage } from '@inertiajs/react';
import { ChevronRight, Home } from 'lucide-react';

interface BreadcrumbItem {
    label: string;
    href?: string;
}

export default function Breadcrumb() {
    const { url } = usePage();

    // Generate breadcrumb items from current URL
    const generateBreadcrumbs = (): BreadcrumbItem[] => {
        const items: BreadcrumbItem[] = [
            { label: 'Inicio', href: route('dashboard') }
        ];

        // Parse URL path
        const pathSegments = url.split('?')[0].split('/').filter(Boolean);

        // Map common routes to labels
        const routeLabels: Record<string, string> = {
            'app': '',
            'dashboard': 'Dashboard',
            'inventory': 'Inventario',
            'products': 'Productos',
            'create': 'Nuevo',
            'edit': 'Editar',
            'categories': 'Categorías',
            'suppliers': 'Proveedores',
            'settings': 'Configuración',
            'profile': 'Perfil',
            'pos': 'Punto de Venta',
            'reports': 'Reportes',
            'customers': 'Clientes',
        };

        let currentPath = '';

        pathSegments.forEach((segment, index) => {
            // Skip tenant ID in path
            if (segment === 'app' || /^[0-9a-f-]{36}$/i.test(segment)) {
                currentPath += `/${segment}`;
                return;
            }

            currentPath += `/${segment}`;

            const label = routeLabels[segment] || segment.charAt(0).toUpperCase() + segment.slice(1);

            if (label) {
                // Last segment doesn't get a link
                if (index === pathSegments.length - 1) {
                    items.push({ label });
                } else {
                    items.push({ label, href: currentPath });
                }
            }
        });

        return items;
    };

    const breadcrumbs = generateBreadcrumbs();

    // Don't show if only home
    if (breadcrumbs.length <= 1) {
        return null;
    }

    return (
        <nav className="flex items-center space-x-1 text-sm">
            {breadcrumbs.map((item, index) => (
                <div key={index} className="flex items-center">
                    {index > 0 && (
                        <ChevronRight className="w-4 h-4 text-gray-400 mx-1" />
                    )}
                    {index === 0 ? (
                        <Link
                            href={item.href || '#'}
                            className="text-gray-500 hover:text-gray-700 flex items-center gap-1"
                        >
                            <Home className="w-4 h-4" />
                        </Link>
                    ) : item.href ? (
                        <Link
                            href={item.href}
                            className="text-gray-500 hover:text-gray-700"
                        >
                            {item.label}
                        </Link>
                    ) : (
                        <span className="text-gray-900 font-medium">
                            {item.label}
                        </span>
                    )}
                </div>
            ))}
        </nav>
    );
}
