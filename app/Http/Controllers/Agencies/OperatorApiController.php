<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agencies;

use App\Http\Controllers\Controller;
use App\Models\Operatore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * API interna operatori: api/v1/{reg,addop,activate_operator,update_agenzia}.php.
 * Contratto JSON identico al legacy.
 */
class OperatorApiController extends Controller
{
    // ─── POST api/v1/reg.php | api/v1/addop.php ──────────────────────────

    public function store(Request $request)
    {
        $username = trim((string) $request->input('username', ''));
        $email = trim((string) $request->input('email', ''));
        $password = trim((string) $request->input('password', ''));

        if ($username === '' || $email === '' || $password === '') {
            return response()->json(['success' => false, 'message' => 'Tutti i campi sono obbligatori.']);
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['success' => false, 'message' => 'Email non valida.']);
        }

        if (Operatore::where('email', $email)->exists()) {
            return response()->json(['success' => false, 'message' => 'Email già in uso.']);
        }

        Operatore::create([
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'first_login' => now()->format('Y-m-d H:i:s'),
            'last_login' => now()->format('Y-m-d H:i:s'),
            'active' => '0',
        ]);

        return response()->json(['success' => true, 'message' => 'Registrazione completata']);
    }

    // ─── POST api/v1/activate_operator.php (JSON body) ───────────────────

    public function activate(Request $request)
    {
        $input = json_decode((string) $request->getContent(), true);
        $id = is_array($input) ? ($input['id'] ?? null) : null;
        $active = is_array($input) ? ($input['active'] ?? 0) : 0;

        if (! $id) {
            return response()->json(['success' => false, 'message' => 'ID non fornito']);
        }

        Operatore::where('id', (int) $id)->update(['active' => (string) (int) $active]);

        return response()->json(['success' => true, 'message' => 'Operatore aggiornato con successo.']);
    }

    // ─── POST api/v1/update_agenzia.php (JSON body) ──────────────────────

    public function updateAgenzia(Request $request)
    {
        $data = json_decode((string) $request->getContent(), true);

        if (! is_array($data) || ! isset($data['id']) || ! is_numeric($data['id'])) {
            return response()->json(['success' => false, 'message' => 'ID mancante o non valido']);
        }

        // agid vuoto/non numerico → NULL (azzeramento), come il legacy
        $agid = isset($data['agid']) && is_numeric($data['agid']) ? (int) $data['agid'] : null;

        Operatore::where('id', (int) $data['id'])->update(['agid' => $agid]);

        return response()->json(['success' => true]);
    }
}
