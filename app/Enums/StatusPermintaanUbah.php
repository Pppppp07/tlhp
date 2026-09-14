<?php

namespace App\Enums;

enum StatusPermintaanUbah: string
{
    case MENUNGGU  = 'menunggu';
    case DISETUJUI = 'disetujui';
    case DITOLAK   = 'ditolak';
}
