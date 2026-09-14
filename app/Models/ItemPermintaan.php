<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemPermintaan extends Model
{
    protected $fillable = ['permintaan_dokumen_id', 'nama', 'terpenuhi', 'dipenuhi_pada'];
    protected $casts = ['terpenuhi' => 'boolean', 'dipenuhi_pada' => 'date'];

    public function permintaan() { return $this->belongsTo(PermintaanDokumen::class, 'permintaan_dokumen_id'); }
    public function lampiran()   { return $this->belongsToMany(Lampiran::class, 'item_permintaan_lampiran'); }
}
