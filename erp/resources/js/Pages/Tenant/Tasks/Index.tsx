import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { useState } from 'react';
import {
    Plus,
    MoreVertical,
    Calendar,
    CheckCircle2,
    Circle,
    Clock,
    XCircle,
    AlertCircle,
    Trash2,
    Edit2,
    BarChart3
} from 'lucide-react';
import { toast } from 'sonner';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';

interface Task {
    id: number;
    title: string;
    description: string | null;
    status: 'nuevo' | 'iniciado' | 'en_progreso' | 'completado' | 'cancelado';
    priority: 'alta' | 'media' | 'baja';
    due_date: string | null;
}

interface Props {
    tasks: Task[];
}

const STATUSES = {
    nuevo: { label: 'Pendiente', color: 'bg-blue-100 text-blue-700', icon: Circle },
    iniciado: { label: 'Iniciado', color: 'bg-emerald-100 text-emerald-700', icon: Clock },
    en_progreso: { label: 'En Proceso', color: 'bg-amber-100 text-amber-700', icon: Clock },
    completado: { label: 'Completado', color: 'bg-indigo-100 text-indigo-700', icon: CheckCircle2 },
    cancelado: { label: 'Cancelado', color: 'bg-gray-100 text-gray-600', icon: XCircle },
};

const PRIORITIES = {
    alta: { label: 'Alta', color: 'text-red-600 bg-red-50' },
    media: { label: 'Media', color: 'text-amber-600 bg-amber-50' },
    baja: { label: 'Baja', color: 'text-green-600 bg-green-50' },
};

export default function Index({ tasks: initialTasks }: Props) {
    const tRoute = useTenantRoute();
    const [tasks, setTasks] = useState(initialTasks);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingTask, setEditingTask] = useState<Task | null>(null);

    // Form State
    const [formData, setFormData] = useState({
        title: '',
        description: '',
        priority: 'media',
        due_date: '',
        status: 'nuevo'
    });

    const columns = Object.keys(STATUSES) as Array<keyof typeof STATUSES>;

    const handleOpenModal = (task?: Task) => {
        if (task) {
            setEditingTask(task);
            setFormData({
                title: task.title,
                description: task.description || '',
                priority: task.priority,
                due_date: task.due_date || '',
                status: task.status
            });
        } else {
            setEditingTask(null);
            setFormData({
                title: '',
                description: '',
                priority: 'media',
                due_date: '',
                status: 'nuevo'
            });
        }
        setIsModalOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (editingTask) {
            router.put(tRoute('tasks.update', { task: editingTask.id }), formData, {
                onSuccess: () => {
                    toast.success('Tarea actualizada');
                    setIsModalOpen(false);
                },
                onError: () => toast.error('Error al actualizar tarea')
            });
        } else {
            router.post(tRoute('tasks.store'), formData, {
                onSuccess: () => {
                    toast.success('Tarea creada');
                    setIsModalOpen(false);
                },
                onError: () => toast.error('Error al crear tarea')
            });
        }
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Estás seguro de eliminar esta tarea?')) {
            router.delete(tRoute('tasks.destroy', { task: id }), {
                onSuccess: () => toast.success('Tarea eliminada'),
                onError: () => toast.error('Error al eliminar')
            });
        }
    };

    // Drag and Drop Logic (Simple HTML5)
    const handleDragStart = (e: React.DragEvent, id: number) => {
        e.dataTransfer.setData('taskId', id.toString());
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
    };

    const handleDrop = (e: React.DragEvent, status: string) => {
        e.preventDefault();
        const taskId = Number(e.dataTransfer.getData('taskId'));
        const task = tasks.find(t => t.id === taskId);

        if (task && task.status !== status) {
            // Optimistic update
            const updatedTasks = tasks.map(t =>
                t.id === taskId ? { ...t, status: status as any } : t
            );
            setTasks(updatedTasks); // Update UI immediately

            router.put(tRoute('tasks.update', { task: taskId }), {
                status: status
            }, {
                preserveScroll: true,
                onSuccess: () => {}, // Server sync happens automatically due to Inertia reload
                onError: () => {
                     toast.error('Error al mover tarea');
                     setTasks(initialTasks); // Revert on error
                }
            });
        }
    };

    // Sync state with props when they change (e.g. after server reload)
    if (initialTasks !== tasks) {
         setTasks(initialTasks);
    }


    return (
        <AuthenticatedLayout
            header={
                <div className="flex justify-between items-center">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 flex items-center gap-2">
                        Centro de Tareas
                    </h2>
                    <div className="flex gap-2">
                        <button
                            onClick={() => router.get(tRoute('tasks.metrics'))}
                            className="flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium"
                        >
                            <BarChart3 className="w-4 h-4" />
                            Métricas
                        </button>
                        <button
                            onClick={() => handleOpenModal()}
                            className="flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium shadow-sm"
                        >
                            <Plus className="w-4 h-4" />
                            Nueva Tarea
                        </button>
                    </div>
                </div>
            }
        >
            <Head title="Centro de Tareas" />

            <div className="h-[calc(100vh-10rem)] p-6 overflow-x-auto">
                <div className="flex gap-6 h-full min-w-max">
                    {columns.map((colStatus) => (
                        <div
                            key={colStatus}
                            className="w-80 flex flex-col bg-gray-100/50 rounded-xl border border-gray-200/60"
                            onDragOver={handleDragOver}
                            onDrop={(e) => handleDrop(e, colStatus)}
                        >
                            {/* Header */}
                            <div className={`p-3 border-b border-gray-200 flex justify-between items-center rounded-t-xl bg-white`}>
                                <div className="flex items-center gap-2 font-medium text-gray-700">
                                    {(() => {
                                        const Icon = STATUSES[colStatus].icon;
                                        return Icon ? <Icon className="w-4 h-4" /> : null;
                                    })()}
                                    {STATUSES[colStatus].label}
                                </div>
                                <span className="bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full text-xs font-semibold">
                                    {tasks.filter(t => t.status === colStatus).length}
                                </span>
                            </div>

                            {/* Task List */}
                            <div className="flex-1 p-3 overflow-y-auto space-y-3">
                                {tasks
                                    .filter(t => t.status === colStatus)
                                    .map(task => (
                                        <div
                                            key={task.id}
                                            draggable
                                            onDragStart={(e) => handleDragStart(e, task.id)}
                                            className="bg-white p-3 rounded-lg shadow-sm border border-gray-200 cursor-move hover:shadow-md transition-shadow group relative"
                                        >
                                            <div className="flex justify-between items-start mb-2">
                                                <span className={`text-[10px] px-2 py-0.5 rounded uppercase font-bold tracking-wider ${PRIORITIES[task.priority].color}`}>
                                                    {PRIORITIES[task.priority].label}
                                                </span>
                                                <div className="opacity-0 group-hover:opacity-100 transition-opacity flex gap-1">
                                                    <button onClick={() => handleOpenModal(task)} className="p-1 hover:bg-gray-100 rounded text-gray-500">
                                                        <Edit2 className="w-3 h-3" />
                                                    </button>
                                                    <button onClick={() => handleDelete(task.id)} className="p-1 hover:bg-red-50 rounded text-red-500">
                                                        <Trash2 className="w-3 h-3" />
                                                    </button>
                                                </div>
                                            </div>

                                            <h4 className="font-medium text-gray-900 mb-1 leading-snug">{task.title}</h4>

                                            {task.description && (
                                                <p className="text-gray-500 text-xs mb-3 line-clamp-2">{task.description}</p>
                                            )}

                                            <div className="flex items-center gap-2 text-xs text-gray-400 mt-2 border-t pt-2 border-dashed">
                                                <Calendar className="w-3 h-3" />
                                                <span>{task.due_date || 'Sin fecha'}</span>
                                            </div>
                                        </div>
                                    ))}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Create/Edit Modal */}
            <Dialog open={isModalOpen} onOpenChange={setIsModalOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editingTask ? 'Editar Tarea' : 'Nueva Tarea'}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Título</label>
                            <input
                                type="text"
                                required
                                value={formData.title}
                                onChange={e => setFormData({ ...formData, title: e.target.value })}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm"
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700">Descripción</label>
                            <textarea
                                value={formData.description}
                                onChange={e => setFormData({ ...formData, description: e.target.value })}
                                rows={3}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm"
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Prioridad</label>
                                <select
                                    value={formData.priority}
                                    onChange={e => setFormData({ ...formData, priority: e.target.value as any })}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm"
                                >
                                    <option value="alta">Alta</option>
                                    <option value="media">Media</option>
                                    <option value="baja">Baja</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Fecha Límite</label>
                                <input
                                    type="date"
                                    value={formData.due_date}
                                    onChange={e => setFormData({ ...formData, due_date: e.target.value })}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm"
                                />
                            </div>
                        </div>

                        {editingTask && (
                             <div>
                                <label className="block text-sm font-medium text-gray-700">Estado</label>
                                <select
                                    value={formData.status}
                                    onChange={e => setFormData({ ...formData, status: e.target.value as any })}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm"
                                >
                                    {Object.entries(STATUSES).map(([key, val]) => (
                                        <option key={key} value={key}>{val.label}</option>
                                    ))}
                                </select>
                            </div>
                        )}

                        <DialogFooter>
                            <button
                                type="button"
                                onClick={() => setIsModalOpen(false)}
                                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                className="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-primary/90"
                            >
                                {editingTask ? 'Guardar Cambios' : 'Crear Tarea'}
                            </button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

        </AuthenticatedLayout>
    );
}
