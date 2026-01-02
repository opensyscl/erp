import { Link, usePage } from '@inertiajs/react';
import { DashboardProps, DashboardModule } from './types';
import { ArrowUpRight } from 'lucide-react';

export default function ModernDashboard({ modules }: DashboardProps) {
    const { tenant } = usePage().props as any;

    // Split modules for bento layout
    const heroModule = modules[0]; // POS - featured
    const primaryModules = modules.slice(1, 5); // Next 4 big ones
    const secondaryModules = modules.slice(5);

    const BentoCard = ({
        module,
        size = 'normal'
    }: {
        module: DashboardModule;
        size?: 'hero' | 'large' | 'normal' | 'small';
    }) => {
        const sizeClasses = {
            hero: 'md:col-span-2 md:row-span-2',
            large: 'md:col-span-2',
            normal: '',
            small: '',
        };

        const content = (
            <div className={`
                relative overflow-hidden rounded-3xl p-6 h-full
                bg-white/70 backdrop-blur-sm
                border border-white/50
                shadow-lg shadow-black/5
                hover:shadow-xl hover:shadow-black/10
                hover:bg-white/90
                transition-all duration-300 ease-out
                group
                ${module.soon ? 'opacity-60' : ''}
            `}>
                {/* Background gradient */}
                <div className={`
                    absolute inset-0 opacity-0 group-hover:opacity-100
                    bg-gradient-to-br ${module.color}
                    transition-opacity duration-500
                `} style={{ opacity: 0.05 }} />

                {/* Icon */}
                <div className={`
                    mb-4 transition-transform duration-300 group-hover:scale-110
                    ${size === 'hero' || size === 'large' ? 'text-5xl' : 'text-3xl'}
                `}>
                    {typeof module.icon === 'string' ? (
                        <span>{module.icon}</span>
                    ) : (
                        <div className={size === 'hero' || size === 'large' ? 'w-16 h-16' : 'w-10 h-10'}>
                            {module.icon}
                        </div>
                    )}
                </div>

                {/* Content */}
                <div className="relative z-10">
                    <h3 className={`
                        font-semibold text-gray-900 mb-1
                        ${size === 'hero' ? 'text-2xl' : size === 'large' ? 'text-xl' : 'text-base'}
                    `}>
                        {module.name}
                    </h3>
                    {(size === 'hero' || size === 'large') && (
                        <p className="text-gray-500 text-sm line-clamp-2">
                            {module.description}
                        </p>
                    )}
                    {module.soon && (
                        <span className="inline-block mt-2 text-xs bg-gray-200 text-gray-600 px-2 py-1 rounded-full">
                            Próximamente
                        </span>
                    )}
                </div>

                {/* Arrow indicator */}
                {!module.soon && (
                    <div className="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity">
                        <ArrowUpRight className="w-5 h-5 text-gray-400" />
                    </div>
                )}
            </div>
        );

        if (module.soon || !module.href) {
            return <div className={`cursor-not-allowed ${sizeClasses[size]}`}>{content}</div>;
        }

        return (
            <Link href={module.href} className={`block ${sizeClasses[size]}`}>
                {content}
            </Link>
        );
    };

    return (
        <div className="min-h-screen bg-gradient-to-br from-slate-50 via-white to-blue-50/50 -m-6 p-8">
            {/* Header */}
            <div className="mb-8">
                <h1 className="text-3xl font-bold text-gray-900 mb-2">
                    Panel de Control
                </h1>
                <p className="text-gray-500">
                    Gestiona todos los aspectos de tu negocio
                </p>
            </div>

            {/* Bento Grid */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 auto-rows-[180px]">
                {/* Hero module - POS */}
                <BentoCard module={heroModule} size="hero" />

                {/* Primary modules */}
                {primaryModules.map((module, index) => (
                    <BentoCard
                        key={module.name}
                        module={module}
                        size={index === 0 ? 'large' : 'normal'}
                    />
                ))}

                {/* Secondary modules */}
                {secondaryModules.map((module) => (
                    <BentoCard key={module.name} module={module} size="small" />
                ))}
            </div>
        </div>
    );
}
