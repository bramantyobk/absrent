<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UserDutyStatusController extends Controller
{
    /**
     * Ubah status bertugas (aktif/nonaktif) operator untuk rotator WhatsApp.
     *
     * Request yang meminta JSON (fetch/AJAX) dijawab dengan JSON, request form biasa dengan redirect.
     */
    public function __invoke(Request $request, User $user): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $user);

        $request->merge(['is_on_duty' => $this->normalizeBoolean($request->input('is_on_duty'))]);

        $validated = $request->validate(['is_on_duty' => ['required', 'boolean']]);

        if ($user->role !== UserRole::Operator || ! $user->is_active) {
            throw ValidationException::withMessages([
                'is_on_duty' => 'Status bertugas hanya untuk operator yang aktif.',
            ]);
        }

        $user->update(['is_on_duty' => $validated['is_on_duty']]);

        $message = $user->is_on_duty
            ? "{$user->name} sekarang bertugas."
            : "{$user->name} tidak lagi bertugas.";

        if ($request->expectsJson()) {
            return response()->json([
                'is_on_duty' => $user->is_on_duty,
                'message' => $message,
            ]);
        }

        return back()->with('status', $message);
    }

    /**
     * FormData dari JavaScript mengirim boolean sebagai teks "true"/"false".
     */
    private function normalizeBoolean(mixed $value): mixed
    {
        return match ($value) {
            'true' => true,
            'false' => false,
            default => $value,
        };
    }
}
