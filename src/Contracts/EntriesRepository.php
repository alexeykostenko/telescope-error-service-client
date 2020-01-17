<?php

namespace PDFfiller\TelescopeClient\Contracts;

use Illuminate\Support\Collection;
use PDFfiller\TelescopeClient\EntryResult;
use PDFfiller\TelescopeClient\Storage\EntryQueryOptions;

interface EntriesRepository
{
    /**
     * Store the given entries.
     *
     * @param  \Illuminate\Support\Collection|\PDFfiller\TelescopeClient\IncomingEntry[]  $entries
     * @return void
     */
    public function store(Collection $entries);
}
