<?php

namespace App\Console\Commands;

use App\Domain\Scholarship\SearchIndexer;
use Illuminate\Console\Command;

class ReindexSearch extends Command
{
    protected $signature = 'sas:reindex-search';

    protected $description = 'Rebuild the keyword-search text for every scholarship cycle (run after deploying this feature and nightly).';

    public function handle(SearchIndexer $indexer): int
    {
        $this->info('Reindexed '.$indexer->reindexAll().' cycle(s).');

        return self::SUCCESS;
    }
}
