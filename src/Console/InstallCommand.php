<?php

namespace PDFfiller\TelescopeClient\Console;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Console\DetectsApplicationNamespace;

class InstallCommand extends Command
{
    use DetectsApplicationNamespace;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telescope:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install all of the Telescope resources';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->comment('Publishing Telescope Configuration...');
        $this->callSilent('vendor:publish', ['--tag' => 'telescope-client-config']);

        $this->info('Telescope client installed successfully.');
    }
}
