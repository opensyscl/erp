<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        // Fetch tasks for the current user
        $tasks = Task::where('user_id', $userId)
            ->orderByRaw("FIELD(priority, 'alta', 'media', 'baja')")
            ->orderBy('created_at', 'asc')
            ->get();

        return Inertia::render('Tenant/Tasks/Index', [
            'tasks' => $tasks
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'priority' => 'required|in:alta,media,baja',
            'due_date' => 'nullable|date',
        ]);

        Task::create([
            'user_id' => Auth::id(),
            'title' => $request->title,
            'description' => $request->description,
            'priority' => $request->priority,
            'status' => 'nuevo',
            'due_date' => $request->due_date,
        ]);

        return redirect()->back()->with('success', 'Tarea creada exitosamente.');
    }

    public function update(Request $request, Task $task)
    {
        // Ensure user owns the task
        if ($task->user_id !== Auth::id()) {
            return redirect()->back()->with('error', 'No autorizado.');
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|in:nuevo,iniciado,en_progreso,completado,cancelado',
            'priority' => 'sometimes|required|in:alta,media,baja',
            'due_date' => 'nullable|date',
        ]);

        $task->update($validated);

        return redirect()->back()->with('success', 'Tarea actualizada.');
    }

    public function destroy(Task $task)
    {
        if ($task->user_id !== Auth::id()) {
            return redirect()->back()->with('error', 'No autorizado.');
        }

        $task->delete();

        return redirect()->back()->with('success', 'Tarea eliminada.');
    }

    public function metrics(Request $request)
    {
        $userId = Auth::id();

        // 1. Basic Counts
        $statusCounts = Task::where('user_id', $userId)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $priorityCounts = Task::where('user_id', $userId)
            ->select('priority', DB::raw('count(*) as total'))
            ->groupBy('priority')
            ->pluck('total', 'priority')
            ->toArray();

        $totalTasks = array_sum($statusCounts);
        $completedTasks = $statusCounts['completado'] ?? 0;
        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;

        // 2. Throughput (Completed per day, last 30 days)
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        $throughput = Task::where('user_id', $userId)
            ->where('status', 'completado')
            ->where('updated_at', '>=', $thirtyDaysAgo)
            ->select(DB::raw('DATE(updated_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Fill missing days
        $throughputData = [];
        $currentDate = $thirtyDaysAgo->copy();
        $today = Carbon::now();

        // Map existing data for quick lookup
        $throughputMap = $throughput->pluck('count', 'date')->toArray(); // Key is YYYY-MM-DD (from DATE() cast) usually string

        while ($currentDate <= $today) {
            $dateStr = $currentDate->format('Y-m-d');
            // If the key exists in our map, use it, otherwise 0
            // Note: Postgres/MySQL date format might match, assuming Y-m-d
            // The map keys from pluck might need adjustment if DB returns diverse formats
            // But usually DATE() returns Y-m-d.

            // To be safe, let's normalize keys if needed, but standard is Y-m-d
            $count = 0;
            // Iterate map keys to check equality (safer than direct access if formats slightly differ)
            foreach($throughputMap as $k => $v) {
                if (str_starts_with($k, $dateStr)) {
                    $count = $v;
                    break;
                }
            }

            $throughputData[] = [
                'date' => $currentDate->format('d/M'),
                'full_date' => $dateStr,
                'count' => $count
            ];
            $currentDate->addDay();
        }

        // 3. Avg Cycle Time (Time from creation to completion)
        // Average days for completed tasks
        $avgCycleTime = Task::where('user_id', $userId)
            ->where('status', 'completado')
            ->select(DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at))/24 as avg_days'))
            ->value('avg_days');

        $avgCycleTime = $avgCycleTime ? round($avgCycleTime, 1) : 0;

        return Inertia::render('Tenant/Tasks/Metrics', [
            'metrics' => [
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'completion_rate' => $completionRate,
                'avg_cycle_time' => $avgCycleTime,
                'status_counts' => $statusCounts,
                'priority_counts' => $priorityCounts,
                'throughput' => $throughputData
            ]
        ]);
    }
}
