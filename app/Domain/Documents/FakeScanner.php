<?php

namespace App\Domain\Documents;

/** Tests / local demos only. Flags the standard EICAR string; everything else is clean. Never used in production. */
class FakeScanner implements Scanner
{
    public function scan($stream): array
    {
        $content = stream_get_contents($stream);

        return str_contains((string) $content, 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE')
            ? ['clean' => false, 'detail' => 'EICAR test signature']
            : ['clean' => true, 'detail' => null];
    }
}
