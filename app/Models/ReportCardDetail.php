<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportCardDetail extends Model
{
    protected $fillable = ['report_card_id', 'subject_id', 'kode_mapel', 'nama_mapel', 'urutan', 'sumatif', 'asts', 'asas', 'nilai_akhir', 'deskripsi'];
}
