<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MasukController extends Controller
{
    public function form()
    {
        /* Daftar akun contoh ditampilkan supaya peragaan tidak tersendat
           mengetik kata sandi. Pada aplikasi sungguhan bagian ini dihapus. */
        return view('masuk', ['akun' => User::with('satker')->orderBy('id')->get()]);
    }

    public function masuk(Request $r)
    {
        $data = $r->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($data, true)) {
            return back()->withInput()->withErrors(['email' => 'Surel atau kata sandi tidak cocok.']);
        }

        $r->session()->regenerate();

        return redirect()->intended(route('beranda'));
    }

    public function keluar(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('masuk');
    }
}
