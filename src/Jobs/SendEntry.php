<?php

namespace PDFfiller\TelescopeClient\Jobs;

use GuzzleHttp\RequestOptions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use PDFfiller\TelescopeClient\Http\Client;

/**
 * Class SendEntry
 *
 * @package App\Jobs
 */
class SendEntry implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var \Illuminate\Support\Collection
     */
    public $entries;

    /**
     * SendEntry constructor.
     *
     * @param \Illuminate\Support\Collection|\PDFfiller\TelescopeClient\IncomingEntry[] $entries
     */
    public function __construct(Collection $entries)
    {
        $this->entries = $entries;
    }

    /**
     * @throws \Exception
     */
    public function handle()
    {
        $this->entries->chunk(1000)->each(function ($chunked) {
            $this->send($chunked);
        });
    }

    private function send($chunked): void
    {
        (new Client)->post('entries', [
            RequestOptions::FORM_PARAMS => $chunked->map(function ($entry) {
                $entry->uuid = $entry->uuid->toString();
                $entry->content = json_encode($entry->content);

                return $entry->toArray();
            })->toArray()
        ]);
    }
}
