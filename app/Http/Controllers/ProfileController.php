<?php

namespace App\Http\Controllers;

use App\Models\TeacherSignature;
use App\Services\ActivityLogger;
use App\Services\SignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load('role', 'teacher', 'student.program');
        $enrollment = $user->student?->currentEnrollment();
        $enrollment?->load('classroom.academicYear', 'classroom.program');

        return view('profile.show', compact('user', 'enrollment'));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        abort_if($user->hasRole('siswa'), 403, 'Data peserta didik hanya dapat diubah oleh Operator.');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);
        $user->update($data);
        $user->teacher?->update(['nama' => $data['name'], 'email' => $data['email'] ?? $user->teacher->email, 'telepon' => $data['phone'] ?? $user->teacher->telepon]);
        ActivityLogger::log('ubah', 'profil', 'Memperbarui profil sendiri.', $user);

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function passwordForm()
    {
        return view('profile.password');
    }

    public function passwordUpdate(Request $request)
    {
        $data = $request->validate([
            'password_lama' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers(), 'different:password_lama'],
        ]);
        $request->user()->forceFill(['password' => Hash::make($data['password']), 'must_change_password' => false])->save();
        ActivityLogger::log('ubah_password', 'profil', 'Mengganti kata sandi.', $request->user());

        return redirect()->route('dashboard')->with('success', 'Kata sandi berhasil diganti.');
    }

    public function signature(Request $request)
    {
        abort_unless($request->user()->hasPermission('signature.manage'), 403);

        return view('profile.signature', ['signature' => $request->user()->signature, 'gdLoaded' => extension_loaded('gd')]);
    }

    public function signatureStore(Request $request, SignatureService $svc)
    {
        abort_unless($request->user()->hasPermission('signature.manage'), 403);
        $request->validate([
            'mode' => ['nullable', 'in:qr'],
            'svg' => ['nullable', 'string', 'max:600000'],
            'file' => ['nullable', 'file', 'max:1024'],
        ]);
        if ($request->input('mode') === 'qr') {
            $svc->ensureQrSignature($request->user());
        } elseif ($request->hasFile('file')) {
            $svc->storeUploaded($request->user(), $request->file('file'));
        } elseif (filled($request->input('svg'))) {
            $svc->storeDrawn($request->user(), $request->input('svg'));
        } else {
            $svc->ensureQrSignature($request->user());
        }
        ActivityLogger::log('ubah', 'tanda_tangan', 'Memperbarui tanda tangan digital QR.', $request->user());

        return back()->with('success', 'Tanda tangan digital (QR Code) berhasil disimpan.');
    }

    public function signatureDestroy(Request $request, SignatureService $svc)
    {
        abort_unless($request->user()->hasPermission('signature.manage'), 403);
        $svc->delete($request->user());
        ActivityLogger::log('hapus', 'tanda_tangan', 'Menghapus tanda tangan digital.', $request->user());

        return back()->with('success', 'Tanda tangan digital dihapus.');
    }

    public function signatureImage(Request $request)
    {
        $sig = TeacherSignature::where('user_id', $request->user()->id)->firstOrFail();

        return response($sig->contents(), 200, ['Content-Type' => $sig->mime, 'Cache-Control' => 'private, max-age=0', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'"]);
    }

    public function notificationsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
