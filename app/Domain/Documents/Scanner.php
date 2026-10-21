<?php

namespace App\Domain\Documents;

interface Scanner
{
    /**
     * @param  resource  $stream
     * @return array{clean:bool,detail:?string}
     *
     * @throws \RuntimeException when the scanner is unreachable (the file then stays pending and the job retries)
     */
    public function scan($stream): array;
}
