<?php

namespace App\Domain\Documents;

use RuntimeException;

/** clamd INSTREAM protocol over TCP. Timeouts are bounded (INT-01). */
class ClamAvScanner implements Scanner
{
    public function __construct(private string $host, private int $port, private int $timeout = 20) {}

    public function scan($stream): array
    {
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, 5);
        if (! $socket) {
            throw new RuntimeException("ClamAV unreachable: {$errstr}");
        }
        stream_set_timeout($socket, $this->timeout);
        fwrite($socket, "zINSTREAM\0");
        while (! feof($stream)) {
            $chunk = fread($stream, 8192);
            if ($chunk === false || $chunk === '') {
                break;
            }
            fwrite($socket, pack('N', strlen($chunk)).$chunk);
        }
        fwrite($socket, pack('N', 0));
        $response = trim((string) stream_get_contents($socket), "\0 \n");
        fclose($socket);

        if (str_ends_with($response, 'OK')) {
            return ['clean' => true, 'detail' => null];
        }
        if (str_contains($response, 'FOUND')) {
            return ['clean' => false, 'detail' => substr($response, 0, 200)];
        }
        throw new RuntimeException('Unexpected ClamAV response: '.substr($response, 0, 120));
    }
}
