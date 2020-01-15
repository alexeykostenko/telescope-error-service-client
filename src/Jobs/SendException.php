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
class SendException implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var \Illuminate\Support\Collection
     */
    public $exceptions;

    /**
     * SendEntry constructor.
     *
     * @param \Illuminate\Support\Collection|\PDFfiller\TelescopeClient\IncomingEntry[] $entries
     */
    public function __construct(array $exceptions)
    {
        $this->exceptions = $exceptions;
    }

    /**
     * @throws \Exception
     */
    public function handle()
    {
        (new Client)->post('entries', [RequestOptions::FORM_PARAMS => $this->exceptions]);
    }
}
