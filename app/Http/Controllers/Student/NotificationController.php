<?php

namespace App\Http\Controllers\Student;

use App\Domain\Communication\Preferences;
use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        Preferences::ensureDefaults($user);

        return view('student.notifications', [
            'notifications' => $user->notifications()->latest()->limit(40)->get(),
            'preferences' => NotificationPreference::where('user_id', $user->id)->get()->keyBy(fn ($p) => $p->category.'.'.$p->channel),
        ]);
    }

    /** NOT-02: per-category / per-channel consent, recorded with wording, channel, timestamp and source. */
    public function update(Request $request)
    {
        $input = (array) $request->input('pref', []);
        foreach (Preferences::CATEGORIES as $category => $_) {
            foreach (Preferences::CHANNELS as $channel => $__) {
                Preferences::set($request->user(), $category, $channel, ! empty($input[$category][$channel]));
            }
        }

        return back()->with('status', 'Notification preferences saved.');
    }

    public function read(Request $request, string $id)
    {
        $request->user()->notifications()->whereKey($id)->first()?->markAsRead();

        return back();
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
