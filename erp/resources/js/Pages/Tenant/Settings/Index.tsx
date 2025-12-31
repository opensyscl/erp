import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { FormEventHandler } from 'react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { usePosSounds } from '@/Hooks/usePosSounds';
import {
    Store,
    CreditCard,
    ShoppingCart,
    Truck,
    MapPin,
    Bell,
    Volume2,
    VolumeX,
    Music,
    AlertCircle,
    CheckCircle,
    MousePointerClick
} from 'lucide-react';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { Slider } from '@/components/ui/slider';
import { cn } from '@/lib/utils';
import { toast } from 'sonner';

interface Props {
    settings: {
        brand_color: string;
        logo?: string;
        company_name?: string;
        company_rut?: string;
        company_address?: string;
        company_phone?: string;
        company_email?: string;
    };
}

type Section = 'sounds' | 'details' | 'payments' | 'checkout' | 'shipping' | 'location' | 'notifications';

export default function Index({ settings }: Props) {
    const tRoute = useTenantRoute();
    const user = usePage().props.auth.user;
    const [activeSection, setActiveSection] = useState<Section>('sounds');

    // --- SOUND SETTINGS STATE (Local Storage) ---
    const [soundSettings, setSoundSettings] = useState({
        enabled: true,
        volume: 50,
        scanSound: 'add-to-folder',
        saleSound: 'download',
        errorSound: 'delete',
        clickSound: 'toggle-theme'
    });

    useEffect(() => {
        const saved = localStorage.getItem('pos_sound_settings');
        if (saved) {
            setSoundSettings(JSON.parse(saved));
        }
    }, []);

    const updateSoundSetting = (key: string, value: any) => {
        const newSettings = { ...soundSettings, [key]: value };
        setSoundSettings(newSettings);
        localStorage.setItem('pos_sound_settings', JSON.stringify(newSettings));

        // Preview sound on change
        if (key !== 'enabled' && key !== 'volume' && newSettings.enabled) {
            playPreview(value);
        }
    };

    const playPreview = (soundName: string) => {
        const audio = new Audio(`/${soundName}.wav`);
        audio.volume = soundSettings.volume / 100;
        audio.play().catch(e => console.error("Preview error:", e));
    };

    const runSystemSoundCheck = async () => {
        const play = (name: string, delay: number) => new Promise<void>(resolve => {
            setTimeout(() => {
                const audio = new Audio(`/${name}.wav`);
                audio.volume = soundSettings.volume / 100;
                audio.play().catch(e => console.error(e));
                resolve();
            }, delay);
        });

        toast.info('🔊 Iniciando prueba de sonido...');
        await play(soundSettings.scanSound, 0);
        await play(soundSettings.saleSound, 800);
        await play(soundSettings.errorSound, 800);
    };

    // --- STORE SETTINGS FORM ---
    const { data, setData, post, processing, errors, recentlySuccessful } = useForm({
        brand_color: settings.brand_color,
        logo: null as File | null,
        company_name: settings.company_name || '',
        company_rut: settings.company_rut || '',
        company_address: settings.company_address || '',
        company_phone: settings.company_phone || '',
        company_email: settings.company_email || '',
        _method: 'patch',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(tRoute('settings.update'), {
            preserveScroll: true,
            onSuccess: () => toast.success('Configuración guardada correctamente')
        });
    };

    // --- SECTIONS ---
    const { playClick } = usePosSounds();

    const SidebarItem = ({ id, icon: Icon, label }: { id: Section, icon: any, label: string }) => (
        <button
            onClick={() => { playClick(); setActiveSection(id); }}
            className={cn(
                "w-full flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-lg transition-colors",
                activeSection === id
                    ? "bg-primary/10 text-primary"
                    : "text-gray-600 hover:bg-gray-50 hover:text-gray-900"
            )}
        >
            <Icon className="w-5 h-5" />
            {label}
        </button>
    );

    const SoundOptions = () => (
        <>
            <option value="add-to-folder">Add To Folder (Pop)</option>
            <option value="copy-svg">Copy SVG (Chime)</option>
            <option value="delete">Delete (Trash)</option>
            <option value="download">Download (Success)</option>
            <option value="slider">Slider (Swipe)</option>
            <option value="toggle-theme">Toggle Theme (Click)</option>
            <option value="tunning">Tunning (Ping)</option>
        </>
    );

    return (
        <AuthenticatedLayout
            settings={settings}
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    ⚙️ Configuración
                </h2>
            }
        >
            <Head title="Configuración" />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex flex-col md:flex-row gap-8">
                        {/* SIDEBAR */}
                        <aside className="w-full md:w-64 flex-shrink-0">
                            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sticky top-8">
                                <h3 className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4 px-4">
                                    General
                                </h3>
                                <nav className="space-y-1">
                                    <SidebarItem id="sounds" icon={Volume2} label="Sonidos y Alertas" />
                                    <SidebarItem id="details" icon={Store} label="Detalles de Tienda" />
                                    <SidebarItem id="payments" icon={CreditCard} label="Pagos" />
                                    <SidebarItem id="checkout" icon={ShoppingCart} label="Checkout" />
                                    <SidebarItem id="shipping" icon={Truck} label="Envíos" />
                                    <SidebarItem id="location" icon={MapPin} label="Ubicación" />
                                    <SidebarItem id="notifications" icon={Bell} label="Notificaciones" />
                                </nav>
                            </div>
                        </aside>

                        {/* CONTENT AREA */}
                        <main className="flex-1">
                            {/* SOUNDS SECTION */}
                            {activeSection === 'sounds' && (
                                <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                                    <div className="p-6 border-b border-gray-100">
                                        <h2 className="text-lg font-semibold text-gray-900">Sonidos del Sistema POS</h2>
                                        <p className="text-sm text-gray-500">Personaliza los efectos de sonido para las acciones de venta.</p>
                                    </div>

                                    <div className="p-6 space-y-8">
                                        {/* Master Switch & System Test */}
                                        <div className="flex flex-col sm:flex-row gap-4 p-4 bg-gray-50 rounded-lg border border-gray-100 items-start sm:items-center justify-between">
                                            <div className="space-y-0.5">
                                                <Label htmlFor="sound-enabled" className="text-base font-medium">Habilitar Sonidos</Label>
                                                <p className="text-sm text-gray-500">Reproducir sonidos al escanear o completar ventas.</p>
                                            </div>
                                            <div className="flex items-center gap-4">
                                                <button
                                                    onClick={runSystemSoundCheck}
                                                    disabled={!soundSettings.enabled}
                                                    className="text-sm font-medium text-primary hover:text-primary/80 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 px-3 py-2 rounded-md hover:bg-primary/5 transition-colors"
                                                >
                                                    <Volume2 className="w-4 h-4" />
                                                    Probar Sistema
                                                </button>
                                                <Switch
                                                    id="sound-enabled"
                                                    checked={soundSettings.enabled}
                                                    onCheckedChange={(checked) => updateSoundSetting('enabled', checked)}
                                                />
                                            </div>
                                        </div>

                                        {/* Volume */}
                                        <div className={cn("space-y-4 transition-opacity", !soundSettings.enabled && "opacity-50 pointer-events-none")}>
                                            <div className="flex items-center justify-between">
                                                <Label>Volumen Maestro ({soundSettings.volume}%)</Label>
                                                {soundSettings.volume === 0 ? <VolumeX className="w-4 h-4 text-gray-400" /> : <Volume2 className="w-4 h-4 text-gray-400" />}
                                            </div>
                                            <Slider
                                                value={soundSettings.volume}
                                                min={0}
                                                max={100}
                                                onChange={(e) => updateSoundSetting('volume', parseInt(e.target.value))}
                                            />
                                        </div>

                                        {/* Sound Types */}
                                        <div className={cn("grid gap-6 md:grid-cols-2", !soundSettings.enabled && "opacity-50 pointer-events-none")}>

                                            {/* Scan Sound */}
                                            <div className="space-y-3">
                                                <Label className="flex items-center gap-2">
                                                    <Music className="w-4 h-4 text-primary" /> Sonido de Escaneo
                                                </Label>
                                                <select
                                                    className="w-full rounded-md border-gray-300 text-sm focus:border-primary focus:ring-primary"
                                                    value={soundSettings.scanSound}
                                                    onChange={(e) => updateSoundSetting('scanSound', e.target.value)}
                                                >
                                                    <SoundOptions />
                                                </select>
                                                <button onClick={() => playPreview(soundSettings.scanSound)} className="text-xs text-primary hover:underline">
                                                    ▶ Probar sonido
                                                </button>
                                            </div>

                                            {/* Success Sound */}
                                            <div className="space-y-3">
                                                <Label className="flex items-center gap-2">
                                                    <CheckCircle className="w-4 h-4 text-green-500" /> Venta Exitosa
                                                </Label>
                                                <select
                                                    className="w-full rounded-md border-gray-300 text-sm focus:border-green-500 focus:ring-green-500"
                                                    value={soundSettings.saleSound}
                                                    onChange={(e) => updateSoundSetting('saleSound', e.target.value)}
                                                >
                                                    <SoundOptions />
                                                </select>
                                                <button onClick={() => playPreview(soundSettings.saleSound)} className="text-xs text-green-600 hover:underline">
                                                    ▶ Probar sonido
                                                </button>
                                            </div>

                                             {/* Error Sound */}
                                             <div className="space-y-3">
                                                <Label className="flex items-center gap-2">
                                                    <AlertCircle className="w-4 h-4 text-red-500" /> Alerta de Error
                                                </Label>
                                                <select
                                                    className="w-full rounded-md border-gray-300 text-sm focus:border-red-500 focus:ring-red-500"
                                                    value={soundSettings.errorSound}
                                                    onChange={(e) => updateSoundSetting('errorSound', e.target.value)}
                                                >
                                                    <SoundOptions />
                                                </select>
                                                <button onClick={() => playPreview(soundSettings.errorSound)} className="text-xs text-red-500 hover:underline">
                                                    ▶ Probar sonido
                                                </button>
                                            </div>

                                            {/* Click Sound */}
                                            <div className="space-y-3">
                                                <Label className="flex items-center gap-2">
                                                    <MousePointerClick className="w-4 h-4 text-gray-500" /> Sonido de Interacción
                                                </Label>
                                                <select
                                                    className="w-full rounded-md border-gray-300 text-sm focus:border-gray-500 focus:ring-gray-500"
                                                    value={soundSettings.clickSound}
                                                    onChange={(e) => updateSoundSetting('clickSound', e.target.value)}
                                                >
                                                    <SoundOptions />
                                                </select>
                                                <button onClick={() => playPreview(soundSettings.clickSound)} className="text-xs text-gray-600 hover:underline">
                                                    ▶ Probar sonido
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="bg-gray-50 px-6 py-4 border-t border-gray-100 flex justify-end">
                                        <button className="text-sm text-gray-500 hover:text-gray-900" onClick={() => toast.info('Los sonidos se guardan automáticamente en este navegador.')}>
                                            Restaurar predeterminados
                                        </button>
                                    </div>
                                </div>
                            )}

                            {/* STORE DETAILS SECTION (Existing Logic) */}
                            {activeSection === 'details' && (
                                <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                                    <div className="p-6 border-b border-gray-100">
                                        <h2 className="text-lg font-semibold text-gray-900">Detalles de la Tienda</h2>
                                        <p className="text-sm text-gray-500">Información pública y apariencia de tu negocio.</p>
                                    </div>

                                    <div className="p-6">
                                        <form onSubmit={submit} className="space-y-6 max-w-2xl">
                                            {/* Company Info */}
                                            <div className="space-y-4">
                                                <h3 className="text-sm font-medium text-gray-700 border-b pb-2">Información de Empresa</h3>
                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div className="space-y-2">
                                                        <Label htmlFor="company_name">Nombre de la Empresa</Label>
                                                        <input
                                                            id="company_name"
                                                            type="text"
                                                            placeholder="Tiendas Listto, SpA."
                                                            className="w-full rounded-md border-gray-300 text-sm"
                                                            value={data.company_name}
                                                            onChange={(e) => setData('company_name', e.target.value)}
                                                        />
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label htmlFor="company_rut">RUT</Label>
                                                        <input
                                                            id="company_rut"
                                                            type="text"
                                                            placeholder="78.169.866-0"
                                                            className="w-full rounded-md border-gray-300 text-sm"
                                                            value={data.company_rut}
                                                            onChange={(e) => setData('company_rut', e.target.value)}
                                                        />
                                                    </div>
                                                </div>
                                                <div className="space-y-2">
                                                    <Label htmlFor="company_address">Dirección</Label>
                                                    <input
                                                        id="company_address"
                                                        type="text"
                                                        placeholder="Av. Vicuña Mackenna 6617, LC7, La Florida"
                                                        className="w-full rounded-md border-gray-300 text-sm"
                                                        value={data.company_address}
                                                        onChange={(e) => setData('company_address', e.target.value)}
                                                    />
                                                </div>
                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div className="space-y-2">
                                                        <Label htmlFor="company_phone">Teléfono</Label>
                                                        <input
                                                            id="company_phone"
                                                            type="tel"
                                                            placeholder="+56 9 2021 0349"
                                                            className="w-full rounded-md border-gray-300 text-sm"
                                                            value={data.company_phone}
                                                            onChange={(e) => setData('company_phone', e.target.value)}
                                                        />
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label htmlFor="company_email">Email de Contacto</Label>
                                                        <input
                                                            id="company_email"
                                                            type="email"
                                                            placeholder="contacto@tiendaslistto.cl"
                                                            className="w-full rounded-md border-gray-300 text-sm"
                                                            value={data.company_email}
                                                            onChange={(e) => setData('company_email', e.target.value)}
                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Logo */}
                                            <div className="space-y-2">
                                                <Label htmlFor="logo">Logo de la Tienda</Label>
                                                <div className="flex items-start gap-6">
                                                    <div className="w-24 h-24 border-2 border-dashed border-gray-300 rounded-lg flex items-center justify-center bg-gray-50 overflow-hidden relative group">
                                                        {settings.logo ? (
                                                            <img src={settings.logo} alt="Logo" className="w-full h-full object-contain" />
                                                        ) : (
                                                            <Store className="w-8 h-8 text-gray-300" />
                                                        )}
                                                        <div className="absolute inset-0 bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                                             <p className="text-white text-xs font-medium">Cambiar</p>
                                                        </div>
                                                        <input
                                                            id="logo"
                                                            type="file"
                                                            accept="image/*"
                                                            className="absolute inset-0 opacity-0 cursor-pointer"
                                                            onChange={(e) => setData('logo', e.target.files ? e.target.files[0] : null)}
                                                        />
                                                    </div>
                                                    <div className="flex-1 text-sm text-gray-500">
                                                        <p>Sube tu logo oficial. Se mostrará en tickets y en el encabezado.</p>
                                                        <p className="mt-1">Formatos: PNG, JPG. Máx 2MB.</p>
                                                        {errors.logo && <p className="text-red-500 mt-2">{errors.logo}</p>}
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Color */}
                                            <div className="space-y-2">
                                                <Label htmlFor="brand_color">Color de Marca</Label>
                                                <div className="flex items-center gap-4">
                                                    <input
                                                        id="brand_color"
                                                        type="color"
                                                        className="h-10 w-20 cursor-pointer rounded border border-gray-300 p-1"
                                                        value={data.brand_color}
                                                        onChange={(e) => setData('brand_color', e.target.value)}
                                                    />
                                                    <div className="flex-1 p-3 rounded-lg bg-gray-50 border border-gray-200 text-sm">
                                                        Este color se usará en botones principales y acentos visuales.
                                                    </div>
                                                </div>
                                                {errors.brand_color && <p className="text-red-500">{errors.brand_color}</p>}
                                            </div>

                                            <div className="pt-4 border-t border-gray-100 flex items-center justify-end gap-4">
                                                {recentlySuccessful && (
                                                    <span className="text-sm text-green-600 animate-fade-in">Guardado con éxito</span>
                                                )}
                                                <button
                                                    type="submit"
                                                    disabled={processing}
                                                    className="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors disabled:opacity-50 font-medium"
                                                >
                                                    Guardar Cambios
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            )}

                            {/* PLACEHOLDERS for other sections */}
                            {['payments', 'checkout', 'shipping', 'location', 'notifications'].includes(activeSection) && (
                                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
                                    <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <Truck className="w-8 h-8 text-gray-400" />
                                    </div>
                                    <h3 className="text-lg font-medium text-gray-900">Sección en Construcción</h3>
                                    <p className="text-gray-500 mt-2 max-w-sm mx-auto">
                                        Estamos trabajando en el módulo de {activeSection}. Pronto podrás configurar estas opciones.
                                    </p>
                                </div>
                            )}
                        </main>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
