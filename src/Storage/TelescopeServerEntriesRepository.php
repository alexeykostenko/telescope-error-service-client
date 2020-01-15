<?php

namespace PDFfiller\TelescopeClient\Storage;

use DateTimeInterface;
use PDFfiller\TelescopeClient\EntryType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PDFfiller\TelescopeClient\EntryResult;
use PDFfiller\TelescopeClient\Contracts\PrunableRepository;
use PDFfiller\TelescopeClient\Contracts\TerminableRepository;
use PDFfiller\TelescopeClient\Contracts\EntriesRepository as Contract;
use GuzzleHttp\RequestOptions;
use PDFfiller\TelescopeClient\Http\Client;
use PDFfiller\TelescopeClient\Jobs\SendEntry;
use PDFfiller\TelescopeClient\Jobs\SendException;
use PDFfiller\TelescopeClient\Jobs\SendTags;

class TelescopeServerEntriesRepository implements Contract
{
    /**
     * @var \PDFfiller\TelescopeClient\Http\Client
     */
    protected $httpClient;

    /**
     * The tags currently being monitored.
     *
     * @var array|null
     */
    protected $monitoredTags;

    /**
     * Store the given array of entries.
     *
     * @param  \Illuminate\Support\Collection|\PDFfiller\TelescopeClient\IncomingEntry[]  $entries
     * @return void
     */
    public function store(Collection $entries)
    {
        if ($entries->isEmpty()) {
            return;
        }

        [$exceptions, $entries] = $entries->partition->isException();

        $this->storeExceptions($exceptions);

        SendEntry::dispatch($entries)->onQueue('send-exception');

        $this->storeTags($entries->pluck('tags', 'uuid'));
    }

    /**
     * Store the given array of exception entries.
     *
     * @param  \Illuminate\Support\Collection|\PDFfiller\TelescopeClient\IncomingEntry[]  $exceptions
     * @return void
     */
    protected function storeExceptions(Collection $exceptions)
    {
        $exceptionsArray = $exceptions->map(function ($exception) {
            $exception->uuid = $exception->uuid->toString();

            return array_merge($exception->toArray(), [
                'family_hash' => $exception->familyHash(),
                'content' => json_encode($exception->content),
            ]);
        })->toArray();

        SendException::dispatch($exceptionsArray)->onQueue('send-exception');

        $this->storeTags($exceptions->pluck('tags', 'uuid'));
    }

    /**
     * Store the tags for the given entries.
     *
     * @param  \Illuminate\Support\Collection  $results
     * @return void
     */
    protected function storeTags($results)
    {
        SendTags::dispatch($results->flatMap(function ($tags, $uuid) {
            return collect($tags)->map(function ($tag) use ($uuid) {
                return [
                    'entry_uuid' => $uuid,
                    'tag' => $tag,
                ];
            });
        })->all())->onQueue('send-exception');
    }

    /**
     * Store the given entry updates.
     *
     * @param  \Illuminate\Support\Collection|\PDFfiller\TelescopeClient\EntryUpdate[]  $updates
     * @return void
     */
    public function update(Collection $updates)
    {
        foreach ($updates as $update) {
            $entry = $this->table('telescope_entries')
                            ->where('uuid', $update->uuid)
                            ->where('type', $update->type)
                            ->first();

            if (! $entry) {
                continue;
            }

            $content = json_encode(array_merge(
                json_decode($entry->content, true), $update->changes
            ));

            $this->table('telescope_entries')
                            ->where('uuid', $update->uuid)
                            ->where('type', $update->type)
                            ->update(['content' => $content]);

            $this->updateTags($update);
        }
    }

    /**
     * Update tags of the given entry.
     *
     * @param  \PDFfiller\TelescopeClient\EntryUpdate  $entry
     * @return void
     */
    protected function updateTags($entry)
    {
        if (! empty($entry->tagsChanges['added'])) {
            $this->table('telescope_entries_tags')->insert(
                collect($entry->tagsChanges['added'])->map(function ($tag) use ($entry) {
                    return [
                        'entry_uuid' => $entry->uuid,
                        'tag' => $tag,
                    ];
                })->toArray()
            );
        }

        collect($entry->tagsChanges['removed'])->each(function ($tag) use ($entry) {
            $this->table('telescope_entries_tags')->where([
                'entry_uuid' => $entry->uuid,
                'tag' => $tag,
            ])->delete();
        });
    }

    /**
     * Load the monitored tags from storage.
     *
     * @return void
     */
    public function loadMonitoredTags()
    {
        try {
            $this->monitoredTags = $this->monitoring();
        } catch (\Throwable $e) {
            $this->monitoredTags = [];
        }
    }

    /**
     * Determine if any of the given tags are currently being monitored.
     *
     * @param  array  $tags
     * @return bool
     */
    public function isMonitoring(array $tags)
    {
        if (is_null($this->monitoredTags)) {
            $this->loadMonitoredTags();
        }

        return count(array_intersect($tags, $this->monitoredTags)) > 0;
    }

    /**
     * Get the list of tags currently being monitored.
     *
     * @return array
     */
    public function monitoring()
    {
        return $this->table('telescope_monitoring')->pluck('tag')->all();
    }

    /**
     * Begin monitoring the given list of tags.
     *
     * @param  array  $tags
     * @return void
     */
    public function monitor(array $tags)
    {
        $tags = array_diff($tags, $this->monitoring());

        if (empty($tags)) {
            return;
        }

        $this->table('telescope_monitoring')
                    ->insert(collect($tags)
                    ->mapWithKeys(function ($tag) {
                        return ['tag' => $tag];
                    })->all());
    }

    /**
     * Stop monitoring the given list of tags.
     *
     * @param  array  $tags
     * @return void
     */
    public function stopMonitoring(array $tags)
    {
        $this->table('telescope_monitoring')->whereIn('tag', $tags)->delete();
    }
}
