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
        // Different neon colors for variety
        const neonColors = [
            { glow: 'shadow-cyan-500/50', border: 'hover:border-cyan-500/50', text: 'group-hover:text-cyan-400' },
            { glow: 'shadow-purple-500/50', border: 'hover:border-purple-500/50', text: 'group-hover:text-purple-400' },
            { glow: 'shadow-pink-500/50', border: 'hover:border-pink-500/50', text: 'group-hover:text-pink-400' },
            { glow: 'shadow-emerald-500/50', border: 'hover:border-emerald-500/50', text: 'group-hover:text-emerald-400' },
        ];

        const colorIndex = module.name.length % neonColors.length;
        const colors = neonColors[colorIndex];

        const content = (
            <div className={`
                group relative overflow-hidden
                ${featured ? 'p-6' : 'p-4'}
                rounded-2xl
                bg-gradient-to-br from-gray-900 via-gray-900 to-gray-800
                border border-gray-700/50
                ${colors.border}
                hover:shadow-lg ${colors.glow}
                transition-all duration-300
                ${module.soon ? 'opacity-40' : ''}
            `}>
                {/* Animated gradient border on hover */}
                <div className="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                    <div className="absolute inset-0 bg-gradient-to-r from-cyan-500/10 via-purple-500/10 to-pink-500/10" />
                </div>

                {/* Grid pattern background */}
                <div className="absolute inset-0 opacity-5">
                    <div className="w-full h-full" style={{
                        backgroundImage: 'linear-gradient(rgba(255,255,255,.1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.1) 1px, transparent 1px)',
                        backgroundSize: '20px 20px'
                    }} />
                </div>

                {/* Content */}
                <div className="relative z-10">
                    {/* Icon with glow effect */}
                    <div className={`
                        mb-3 transition-all duration-300
                        ${featured ? 'text-4xl' : 'text-2xl'}
                        group-hover:drop-shadow-[0_0_8px_rgba(255,255,255,0.5)]
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
                        font-bold text-white transition-colors duration-300
                        ${colors.text}
                        ${featured ? 'text-lg mb-2' : 'text-sm'}
                    `}>
                        {module.name}
                    </h3>

                    {/* Description for featured */}
                    {featured && (
                        <p className="text-gray-400 text-sm line-clamp-2">
                            {module.description}
                        </p>
                    )}

                    {/* Soon badge */}
                    {module.soon && (
                        <span className="inline-flex items-center gap-1 mt-2 text-xs bg-gray-800 text-gray-400 px-2 py-1 rounded border border-gray-700">
                            <Zap className="w-3 h-3" />
                            Próximamente
                        </span>
                    )}
                </div>

                {/* Corner accent */}
                <div className="absolute top-0 right-0 w-16 h-16 opacity-0 group-hover:opacity-100 transition-opacity">
                    <div className="absolute top-2 right-2 w-2 h-2 rounded-full bg-cyan-400 animate-pulse" />
                </div>
            </div>
        );

        if (module.soon || !module.href) {
            return <div className="cursor-not-allowed">{content}</div>;
        }

        return <Link href={module.href}>{content}</Link>;
    };

    return (
        <div className="min-h-screen bg-black -m-6 p-8 relative overflow-hidden">
            {/* Animated background gradient */}
            <div className="absolute inset-0 bg-gradient-to-br from-purple-900/20 via-black to-cyan-900/20" />

            {/* Scanlines effect */}
            <div className="absolute inset-0 opacity-[0.02] pointer-events-none" style={{
                backgroundImage: 'repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(255,255,255,0.1) 2px, rgba(255,255,255,0.1) 4px)'
            }} />

            {/* Content */}
            <div className="relative z-10">
                {/* Header */}
                <div className="mb-10">
                    <div className="flex items-center gap-3 mb-2">
                        <div className="w-2 h-2 rounded-full bg-cyan-400 animate-pulse" />
                        <span className="text-cyan-400 text-sm font-mono tracking-wider uppercase">
                            Sistema Online
                        </span>
                    </div>
                    <h1 className="text-4xl font-bold text-white mb-2 tracking-tight">
                        Control Center
                    </h1>
                    <p className="text-gray-500 font-light">
                        Acceso rápido a todos los módulos del sistema
                    </p>
                </div>

                {/* Featured modules */}
                <div className="mb-10">
                    <div className="flex items-center gap-2 mb-4">
                        <div className="h-px flex-1 bg-gradient-to-r from-cyan-500/50 to-transparent" />
                        <span className="text-xs font-mono text-gray-500 uppercase tracking-widest">
                            Acceso Rápido
                        </span>
                        <div className="h-px flex-1 bg-gradient-to-l from-purple-500/50 to-transparent" />
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
                        <div className="h-px flex-1 bg-gradient-to-r from-pink-500/50 to-transparent" />
                        <span className="text-xs font-mono text-gray-500 uppercase tracking-widest">
                            Todos los Módulos
                        </span>
                        <div className="h-px flex-1 bg-gradient-to-l from-emerald-500/50 to-transparent" />
                    </div>
                    <div className="grid grid-cols-3 md:grid-cols-5 lg:grid-cols-7 gap-3">
                        {otherModules.map((module) => (
                            <NeonCard key={module.name} module={module} />
                        ))}
                    </div>
                </div>

                {/* Footer */}
                <div className="mt-16 pt-8 border-t border-gray-800/50">
                    <div className="flex items-center justify-center gap-3 text-gray-600">
                        <Zap className="w-4 h-4 text-cyan-500" />
                        <span className="font-mono text-sm tracking-wider">
                            OpenSys ERP v2.0
                        </span>
                    </div>
                </div>
            </div>
        </div>
    );
}
