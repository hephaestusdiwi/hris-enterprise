<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AccountActivationController extends Controller
{
    /**
     * Dipanggil begitu halaman /activate-account?token=... dibuka, buat
     * validasi token & nampilin nama employee sebelum minta password.
     */
    public function validateToken(Request $request)
    {
        $request->validate(['token' => ['required', 'string']]);

        $user = $this->findUserByToken($request->string('token'));

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Link aktivasi tidak valid atau sudah kedaluwarsa.',
                'data' => null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => ['name' => $user->name, 'email' => $user->email],
        ]);
    }

    /**
     * Submit password baru, aktivasi akun.
     */
    public function complete(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $this->findUserByToken($request->string('token'));

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Link aktivasi tidak valid atau sudah kedaluwarsa.',
                'data' => null,
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->string('password')),
            'account_status' => 'active',
            'activation_token_hash' => null,
            'activation_token_expires_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Akun berhasil diaktifkan. Silakan login.',
            'data' => null,
        ]);
    }

    private function findUserByToken(string $plainToken): ?User
    {
        // Token di-hash sebelum disimpan (tidak pernah simpan plain token
        // di database), jadi lookup-nya cocokkan hash-nya, bukan query
        // WHERE activation_token_hash langsung (hindari timing attack
        // sederhana lewat hash_equals di isActivationTokenValid()).
        $candidates = User::where('account_status', 'pending_invite')
            ->whereNotNull('activation_token_hash')
            ->get();

        foreach ($candidates as $candidate) {
            if ($candidate->isActivationTokenValid($plainToken)) {
                return $candidate;
            }
        }

        return null;
    }
}
