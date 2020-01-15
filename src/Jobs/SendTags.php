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
class SendTags implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var \Illuminate\Support\Collection
     */
    public $tags;

    /**
     * SendEntry constructor.
     *
     * @param \Illuminate\Support\Collection|\PDFfiller\TelescopeClient\IncomingEntry[] $entries
     */
    public function __construct(array $tags)
    {
        $this->tags = $tags;
    }

    /**
     * @throws \Exception
     */
    public function handle()
    {
        (new Client)->post('entries', [RequestOptions::FORM_PARAMS => $this->tags]);
    }
}
