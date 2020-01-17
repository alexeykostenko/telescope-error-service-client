<?php

namespace PDFfiller\TelescopeClient\Storage;

use DateTimeInterface;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PDFfiller\TelescopeClient\Contracts\PrunableRepository;
use PDFfiller\TelescopeClient\Contracts\TerminableRepository;
use PDFfiller\TelescopeClient\Contracts\EntriesRepository as Contract;
use PDFfiller\TelescopeClient\Http\Client;
use PDFfiller\TelescopeClient\EntryResult;
use PDFfiller\TelescopeClient\EntryType;

/**
 * Class TelescopeServerEntriesRepository
 *
 * @package PDFfiller\TelescopeClient\Storage
 */
class ApiEntriesRepository implements Contract
{
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

        $entries->chunk(1000)->each(function ($chunked) {
            app(Client::class)->post('entries', [
                RequestOptions::FORM_PARAMS => $chunked->map(function ($entry) {
                    $entry->uuid = $entry->uuid->toString();
                    $entry->content = json_encode($entry->content);

                    return $entry->toArray();
                })->values()->toArray()
            ]);
        });

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
        app(Client::class)->post('entries', [
                RequestOptions::FORM_PARAMS => $exceptions->map(function ($exception) {
                    $exception->uuid = $exception->uuid->toString();

                    return array_merge($exception->toArray(), [
                        'family_hash' => $exception->familyHash(),
                        'content' => json_encode($exception->content),
                    ]);
                })->values()->toArray()
        ]);

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
        app(Client::class)->post('entries-tags', [
            RequestOptions::FORM_PARAMS => $results->flatMap(function ($tags, $uuid) {
                return collect($tags)->map(function ($tag) use ($uuid) {
                    return [
                        'entry_uuid' => $uuid,
                        'tag' => $tag,
                    ];
                });
            })->values()->all()
        ]);
    }
}
