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

    /**
     * Store the given entry updates.
     *
     * @param  \Illuminate\Support\Collection|\PDFfiller\TelescopeClient\EntryUpdate[]  $updates
     * @return void
     */
    public function update(Collection $updates);

    /**
     * Load the monitored tags from storage.
     *
     * @return void
     */
    public function loadMonitoredTags();

    /**
     * Determine if any of the given tags are currently being monitored.
     *
     * @param  array  $tags
     * @return bool
     */
    public function isMonitoring(array $tags);

    /**
     * Get the list of tags currently being monitored.
     *
     * @return array
     */
    public function monitoring();

    /**
     * Begin monitoring the given list of tags.
     *
     * @param  array  $tags
     * @return void
     */
    public function monitor(array $tags);

    /**
     * Stop monitoring the given list of tags.
     *
     * @param  array  $tags
     * @return void
     */
    public function stopMonitoring(array $tags);
}
