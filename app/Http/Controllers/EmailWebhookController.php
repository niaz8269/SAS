<?php

namespace App\Http\Controllers;

use App\Domain\Communication\EmailSuppressions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Provider-agnostic delivery webhook (INT-01 / OPS-06). The raw body must be signed with HMAC-SHA256 using SAS_MAIL_WEBHOOK_SECRET
 * (header X-Signature, hex). With no secret configured the endpoint does not exist. Events are processed once each.
 *
 * Body: {"events":[{"id":"evt_1","type":"hard_bounce"|"complaint"|"soft_bounce"|"delivered","email":"person@example.org"}]}
 * A provider-specific adapter can translate its own format into this shape.
 */
class EmailWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, EmailSuppressions $suppressions)
    {
        $secret = (string) config('sas.mail_webhook_secret');
        abort_if($secret === '', 404);

        $body = $request->getContent();
        $expected = hash_hmac('sha256', $body, $secret);
        abort_unless(hash_equals($expected, (string) $request->header('X-Signature')), 401);

        $events = (array) ($request->json('events') ?? []);
        abort_if(count($events) > 500, 413);
        $handled = 0;

        foreach ($events as $event) {
            $id = (string) ($event['id'] ?? '');
            $email = (string) ($event['email'] ?? '');
            $type = (string) ($event['type'] ?? '');
            if ($id === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            // Insert-first makes a replay or a parallel retry a no-op.
            $first = DB::table('webhook_events')->insertOrIgnore(['provider' => mb_substr($provider, 0, 32), 'event_id' => mb_substr($id, 0, 128), 'type' => mb_substr($type, 0, 32)]);
            if (! $first) {
                continue;
            }
            if ($type === 'hard_bounce') {
                $suppressions->suppress($email, 'bounce', $provider);
            } elseif ($type === 'complaint') {
                $suppressions->suppress($email, 'complaint', $provider);
            }
            $handled++;
        }

        return response()->json(['processed' => $handled]);
    }
}
