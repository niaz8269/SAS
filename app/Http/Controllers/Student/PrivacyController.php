<?php

namespace App\Http\Controllers\Student;

use App\Domain\Identity\AccountPrivacy;
use App\Http\Controllers\Controller;
use App\Models\DeletionRequest;
use App\Notifications\ConfirmAccountDeletion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** PRIV-02. Export and erasure are re-authenticated (password) and, for erasure, confirmed through an emailed link. */
class PrivacyController extends Controller
{
    public function show(Request $request, AccountPrivacy $privacy)
    {
        return view('student.privacy', ['blocked' => $privacy->blockedReason($request->user())]);
    }

    private function confirmPassword(Request $request): void
    {
        $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check($request->input('password'), $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Password is incorrect.']);
        }
    }

    public function export(Request $request, AccountPrivacy $privacy)
    {
        $this->confirmPassword($request);
        $json = json_encode($privacy->export($request->user()), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response($json, 200, [
            'Content-Type' => 'application/json', 'Content-Disposition' => 'attachment; filename="sas-my-data.json"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function requestDeletion(Request $request, AccountPrivacy $privacy)
    {
        $this->confirmPassword($request);
        if ($reason = $privacy->blockedReason($request->user())) {
            return back()->with('error', $reason);
        }
        $user = $request->user();
        DeletionRequest::where('user_id', $user->id)->where('status', 'requested')->update(['status' => 'cancelled']);
        $token = Str::random(48);
        DeletionRequest::create(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHours(24)]);
        $user->notify(new ConfirmAccountDeletion(route('student.privacy.delete.confirm', $token)));

        return back()->with('status', 'We emailed you a confirmation link. It works once and expires in 24 hours. Nothing has been deleted yet.');
    }

    private function openRequest(Request $request, string $token): DeletionRequest
    {
        $req = DeletionRequest::where('user_id', $request->user()->id)->where('status', 'requested')
            ->where('token_hash', hash('sha256', $token))->where('expires_at', '>', now())->first();
        abort_unless($req, 404);

        return $req;
    }

    public function confirm(Request $request, string $token)
    {
        $this->openRequest($request, $token);

        return view('student.privacy-confirm', ['token' => $token]);
    }

    public function destroy(Request $request, string $token, AccountPrivacy $privacy)
    {
        $req = $this->openRequest($request, $token);
        $this->confirmPassword($request);
        $request->validate(['confirmation' => ['required', 'in:DELETE']], ['confirmation.in' => 'Type DELETE to confirm.']);

        $user = $request->user();
        // Log out first: logging out after the row is deleted would re-save the model and resurrect the account.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $privacy->erase($user);
        $req->update(['status' => 'completed', 'completed_at' => now(), 'user_id' => null]);

        return redirect()->route('home')->with('status', 'Your account and documents have been deleted. Backups expire on their normal schedule.');
    }
}
