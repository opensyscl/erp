import { Link, usePage } from '@inertiajs/react';
import { DashboardProps, DashboardModule } from './types';
import { ChevronRight, Search } from 'lucide-react';
import { useState, useMemo } from 'react';

export default function MinimalDashboard({ modules }: DashboardProps) {
    const { tenant } = usePage().props as any;
    const [searchQuery, setSearchQuery] = useState('');

    // Filter modules based on search
    const filteredModules = useMemo(() => {
        if (!searchQuery.trim()) return modules;
        const query = searchQuery.toLowerCase();
        return modules.filter(m =>
            m.name.toLowerCase().includes(query) ||
            m.description.toLowerCase().includes(query)
        );
    }, [modules, searchQuery]);

    // Group modules by category
    const groupedModules = useMemo(() => {
        const ventas = ['Punto de Venta', 'Ventas', 'Cuadres de Caja'];
        const inventario = ['Inventario', 'Compras', 'Packs y Promos', 'Ranking de Productos', 'Centro de Etiquetas', 'Catálogo de Productos'];
        const finanzas = ['Análisis de Capital', 'Pagos a Proveedores', 'Gastos Operativos'];
        const gestion = ['Gestión de Terceros', 'Cotizaciones', 'Pedidos', 'Mermas y Devoluciones', 'Consumo Interno'];
        const equipo = ['Horarios y Turnos', 'Registro de Asistencias', 'Centro de Tareas'];
        const config = ['Configuración'];

        return [
            { title: 'Ventas', modules: filteredModules.filter(m => ventas.includes(m.name)) },
            { title: 'Inventario', modules: filteredModules.filter(m => inventario.includes(m.name)) },
            { title: 'Finanzas', modules: filteredModules.filter(m => finanzas.includes(m.name)) },
            { title: 'Gestión', modules: filteredModules.filter(m => gestion.includes(m.name)) },
            { title: 'Equipo', modules: filteredModules.filter(m => equipo.includes(m.name)) },
            { title: 'Sistema', modules: filteredModules.filter(m => config.includes(m.name)) },
        ].filter(group => group.modules.length > 0);
    }, [filteredModules]);

    const ModuleRow = ({ module, isFirst, isLast }: { module: DashboardModule; isFirst: boolean; isLast: boolean }) => {
        const content = (
            <div className={`
                flex items-center justify-between px-4 py-3.5 bg-card
                ${isFirst ? 'rounded-t-xl' : ''}
                ${isLast ? 'rounded-b-xl' : 'border-b border-border'}
                ${!module.soon ? 'hover:bg-secondary active:bg-secondary/80 transition-colors' : 'opacity-50'}
            `}>
                <div className="flex items-center gap-4">
                    {/* Icon container */}
                    <div className="w-8 h-8 flex items-center justify-center">
                        {typeof module.icon === 'string' ? (
                            <span className="text-2xl">{module.icon}</span>
                        ) : (
                            <div className="w-8 h-8">{module.icon}</div>
                        )}
                    </div>

                    {/* Text */}
                    <div className="flex-1">
                        <h3 className="font-medium text-foreground text-[15px]">
                            {module.name}
                        </h3>
                    </div>
                </div>

                {/* Right side */}
                <div className="flex items-center gap-2">
                    {module.soon ? (
                        <span className="text-xs text-muted-foreground bg-secondary px-2 py-0.5 rounded">
                            Próximamente
                        </span>
                    ) : (
                        <ChevronRight className="w-5 h-5 text-muted-foreground/50" />
                    )}
                </div>
            </div>
        );

        if (module.soon || !module.href) {
            return <div className="cursor-not-allowed">{content}</div>;
        }

        return <Link href={module.href}>{content}</Link>;
    };

    return (
        <div className="min-h-screen bg-secondary">
            {/* iOS-style header */}
            <div className="bg-secondary pt-6 pb-4 px-4 sticky top-0 z-10">
                <h1 className="text-3xl font-bold text-foreground mb-4 px-2">
                    Panel
                </h1>

                {/* Search bar */}
                <div className="relative">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                    <input
                        type="text"
                        placeholder="Buscar"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        className="w-full pl-10 pr-4 py-2 bg-muted rounded-xl text-[15px] placeholder-muted-foreground border-0 focus:ring-2 focus:ring-primary"
                    />
                </div>
            </div>

            {/* Grouped modules */}
            <div className="px-4 pb-8 space-y-8">
                {groupedModules.map((group) => (
                    <div key={group.title}>
                        <h2 className="text-sm font-medium text-muted-foreground uppercase tracking-wider px-4 mb-2">
                            {group.title}
                        </h2>
                        <div className="bg-card rounded-xl shadow-sm overflow-hidden">
                            {group.modules.map((module, index) => (
                                <ModuleRow
                                    key={module.name}
                                    module={module}
                                    isFirst={index === 0}
                                    isLast={index === group.modules.length - 1}
                                />
                            ))}
                        </div>
                    </div>
                ))}

                {filteredModules.length === 0 && (
                    <div className="text-center py-12">
                        <Search className="w-12 h-12 text-muted-foreground/30 mx-auto mb-4" />
                        <p className="text-muted-foreground">No se encontraron módulos</p>
                    </div>
                )}
            </div>
        </div>
    );
}
