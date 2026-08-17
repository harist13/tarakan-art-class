<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Daftar log aktivitas — hanya Super Admin (dijaga di route middleware).
     */
    public function index(Request $request)
    {
        $userId = $request->string('user')->toString();
        $action = $request->string('action')->toString();
        $search = $request->string('search')->toString();

        $query = ActivityLog::query()
            ->with('user')
            ->when($userId !== '', fn ($q) => $q->where('user_id', $userId))
            ->when(in_array($action, ['created', 'updated', 'deleted', 'sent'], true), fn ($q) => $q->where('action', $action))
            ->when($search !== '', fn ($q) => $q->where('description', 'like', "%{$search}%"));

        $logs = $query->orderByDesc('logged_at')->orderByDesc('id')->paginate(20)->withQueryString();

        $users = User::orderBy('full_name')->get(['id', 'full_name']);

        return view('activity-logs.index', compact('logs', 'users', 'userId', 'action', 'search'));
    }
}
