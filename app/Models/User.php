<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'nip', 'jabatan', 'peran', 'satker_id', 'aktif'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'peran' => \App\Enums\PeranPengguna::class,
            'aktif' => 'boolean',
        ];
    }

    public function satker()
    {
        return $this->belongsTo(Satker::class);
    }

    /* Peran menentukan wewenang, dan hanya Setba yang boleh mengubah status —
       itu pun hanya dengan menyalin isi surat verifikasi bernomor. */
    public function bolehUbahStatus(): bool
    {
        return $this->peran?->bolehUbahStatus() ?? false;
    }
}
