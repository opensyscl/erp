import { Link, usePage } from '@inertiajs/react';
import { DashboardProps, DashboardModule } from './types';
import { Zap } from 'lucide-react';

export default function DarkDashboard({ modules }: DashboardProps) {
    const { tenant } = usePage().props as any;

    // Split modules
    const featuredModules = modules.slice(0, 4);
    const otherModules = modules.slice(4);

    const NeonCard = ({
        module,
        featured = false
    }: {
        module: DashboardModule;
        featured?: boolean;
    }) => {
        const content = (
            <div className={`
                group relative overflow-hidden
                ${featured ? 'p-6' : 'p-4'}
                rounded-2xl
                bg-card/80
                border border-border
                hover:border-primary/50
                hover:shadow-lg hover:shadow-primary/20
                transition-all duration-300
                ${module.soon ? 'opacity-40' : ''}
            `}>
                {/* Animated gradient border on hover */}
                <div className="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                    <div className="absolute inset-0 bg-primary/5" />
                </div>

                {/* Grid pattern background */}
                <div className="absolute inset-0 opacity-5">
                    <div className="w-full h-full" style={{
                        backgroundImage: 'linear-gradient(var(--foreground) 1px, transparent 1px), linear-gradient(90deg, var(--foreground) 1px, transparent 1px)',
                        backgroundSize: '20px 20px',
                        opacity: 0.05
                    }} />
                </div>

                {/* Content */}
                <div className="relative z-10">
                    {/* Icon with glow effect */}
                    <div className={`
                        mb-3 transition-all duration-300
                        ${featured ? 'text-4xl' : 'text-2xl'}
                        group-hover:drop-shadow-[0_0_8px_var(--primary)]
                    `}>
                        {typeof module.icon === 'string' ? (
                            <span>{module.icon}</span>
                        ) : (
                            <div className={featured ? 'w-12 h-12' : 'w-8 h-8'}>
                                {module.icon}
                            </div>
                        )}
                    </div>

                    {/* Title */}
                    <h3 className={`
                        font-bold text-foreground transition-colors duration-300
                        group-hover:text-primary
                        ${featured ? 'text-lg mb-2' : 'text-sm'}
                    `}>
                        {module.name}
                    </h3>

                    {/* Description for featured */}
                    {featured && (
                        <p className="text-muted-foreground text-sm line-clamp-2">
                            {module.description}
                        </p>
                    )}

                    {/* Soon badge */}
                    {module.soon && (
                        <span className="inline-flex items-center gap-1 mt-2 text-xs bg-secondary text-muted-foreground px-2 py-1 rounded border border-border">
                            <Zap className="w-3 h-3" />
                            Próximamente
                        </span>
                    )}
                </div>

                {/* Corner accent */}
                <div className="absolute top-0 right-0 w-16 h-16 opacity-0 group-hover:opacity-100 transition-opacity">
                    <div className="absolute top-2 right-2 w-2 h-2 rounded-full bg-primary animate-pulse" />
                </div>
            </div>
        );

        if (module.soon || !module.href) {
            return <div className="cursor-not-allowed">{content}</div>;
        }

        return <Link href={module.href}>{content}</Link>;
    };

    return (
        <div className="min-h-screen bg-background p-8 relative overflow-hidden">
            {/* Animated background gradient */}
            <div className="absolute inset-0 bg-gradient-to-br from-primary/10 via-background to-accent/10" />

            {/* Scanlines effect */}
            <div className="absolute inset-0 opacity-[0.02] pointer-events-none" style={{
                backgroundImage: 'repeating-linear-gradient(0deg, transparent, transparent 2px, var(--foreground) 2px, var(--foreground) 4px)',
                opacity: 0.02
            }} />

            {/* Content */}
            <div className="relative z-10">
                {/* Header */}
                <div className="mb-10">
                    <div className="flex items-center gap-3 mb-2">
                        <div className="w-2 h-2 rounded-full bg-primary animate-pulse" />
                        <span className="text-primary text-sm font-mono tracking-wider uppercase">
                            Sistema Online
                        </span>
                    </div>
                    <h1 className="text-4xl font-bold text-foreground mb-2 tracking-tight">
                        Control Center
                    </h1>
                    <p className="text-muted-foreground font-light">
                        Acceso rápido a todos los módulos del sistema
                    </p>
                </div>

                {/* Featured modules */}
                <div className="mb-10">
                    <div className="flex items-center gap-2 mb-4">
                        <div className="h-px flex-1 bg-gradient-to-r from-primary/50 to-transparent" />
                        <span className="text-xs font-mono text-muted-foreground uppercase tracking-widest">
                            Acceso Rápido
                        </span>
                        <div className="h-px flex-1 bg-gradient-to-l from-accent/50 to-transparent" />
                    </div>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        {featuredModules.map((module) => (
                            <NeonCard key={module.name} module={module} featured />
                        ))}
                    </div>
                </div>

                {/* Other modules */}
                <div>
                    <div className="flex items-center gap-2 mb-4">
                        <div className="h-px flex-1 bg-gradient-to-r from-accent/50 to-transparent" />
                        <span className="text-xs font-mono text-muted-foreground uppercase tracking-widest">
                            Todos los Módulos
                        </span>
                        <div className="h-px flex-1 bg-gradient-to-l from-primary/50 to-transparent" />
                    </div>
                    <div className="grid grid-cols-3 md:grid-cols-5 lg:grid-cols-7 gap-3">
                        {otherModules.map((module) => (
                            <NeonCard key={module.name} module={module} />
                        ))}
                    </div>
                </div>

                {/* Footer */}
                <div className="mt-16 pt-8 border-t border-border/50">
                    <div className="flex items-center justify-center gap-3 text-muted-foreground">
                        <Zap className="w-4 h-4 text-primary" />
                        <span className="font-mono text-sm tracking-wider">
                            OpenSys ERP v2.0
                        </span>
                    </div>
                </div>
            </div>
        </div>
    );
}
