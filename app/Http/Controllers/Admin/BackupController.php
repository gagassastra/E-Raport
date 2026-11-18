<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    public function index()
    {
        $files = collect(Storage::disk('local')->files('backups'))
            ->filter(fn ($f) => str_ends_with($f, '.sql'))
            ->map(fn ($f) => (object) [
                'name' => basename($f),
                'size' => round(Storage::disk('local')->size($f) / 1024, 1),
                'modified' => Storage::disk('local')->lastModified($f),
            ])
            ->sortByDesc('modified')->values();

        return view('admin.backups', compact('files'));
    }

    public function store()
    {
        $pdo = DB::connection()->getPdo();
        $driver = DB::connection()->getDriverName();
        $sql = "-- Backup Database Sistem Informasi E-Raport PKBM AL-QUDWAH\n-- Waktu: ".now()->toDateTimeString()."\n\n";

        if ($driver === 'mysql') {
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
            $tables = array_map(fn ($r) => array_values((array) $r)[0], DB::select('SHOW TABLES'));
            foreach ($tables as $tbl) {
                $create = (array) DB::selectOne("SHOW CREATE TABLE `{$tbl}`");
                $createSql = array_values($create)[1] ?? '';
                $sql .= "DROP TABLE IF EXISTS `{$tbl}`;\n{$createSql};\n\n";
                foreach (DB::table($tbl)->get() as $row) {
                    $arr = (array) $row;
                    $cols = implode('`, `', array_keys($arr));
                    $vals = implode(', ', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($arr)));
                    $sql .= "INSERT INTO `{$tbl}` (`{$cols}`) VALUES ({$vals});\n";
                }
                $sql .= "\n";
            }
            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        } else {
            $tables = collect(DB::select("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"));
            foreach ($tables as $t) {
                $sql .= "{$t->sql};\n";
            }
        }

        $filename = 'backup_erapor_'.now()->format('Ymd_His').'.sql';
        Storage::disk('local')->put('backups/'.$filename, $sql);
        ActivityLogger::log('backup', 'sistem', "Membuat cadangan database {$filename}.");

        return back()->with('success', "Backup database {$filename} berhasil dibuat.");
    }

    public function download(string $file)
    {
        $safe = basename($file);
        $path = 'backups/'.$safe;
        abort_unless(Storage::disk('local')->exists($path), 404);
        ActivityLogger::log('unduh_backup', 'sistem', "Mengunduh backup database {$safe}.");

        return Storage::disk('local')->download($path, $safe, ['Content-Type' => 'application/sql']);
    }

    public function destroy(string $file)
    {
        $safe = basename($file);
        $path = 'backups/'.$safe;
        Storage::disk('local')->delete($path);
        ActivityLogger::log('hapus_backup', 'sistem', "Menghapus berkas backup {$safe}.");

        return back()->with('success', "Berkas backup {$safe} dihapus.");
    }
}
