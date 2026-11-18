<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $moduls = ActivityLog::select('modul')->distinct()->whereNotNull('modul')->orderBy('modul')->pluck('modul');
        $logs = ActivityLog::query()
            ->when($request->filled('modul'), fn ($q) => $q->where('modul', $request->query('modul')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('deskripsi', 'like', '%'.$request->query('q').'%')->orWhere('user_nama', 'like', '%'.$request->query('q').'%')->orWhere('aksi', 'like', '%'.$request->query('q').'%')))
            ->orderByDesc('id')
            ->paginate(30)->withQueryString();

        return view('admin.logs', compact('logs', 'moduls'));
    }
}
