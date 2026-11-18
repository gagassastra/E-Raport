<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        return view('admin.settings', [
            'verification_base_url' => Setting::get('verification_base_url', config('app.url')),
            'catatan_kaki_rapor' => Setting::get('catatan_kaki_rapor', ''),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'verification_base_url' => ['required', 'url', 'max:200'],
            'catatan_kaki_rapor' => ['nullable', 'string', 'max:300'],
        ]);
        Setting::put('verification_base_url', rtrim($data['verification_base_url'], '/'));
        Setting::put('catatan_kaki_rapor', $data['catatan_kaki_rapor'] ?? '');
        ActivityLogger::log('ubah', 'pengaturan', 'Memperbarui konfigurasi aplikasi.');

        return back()->with('success', 'Konfigurasi aplikasi berhasil disimpan.');
    }
}
