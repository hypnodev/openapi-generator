<?php

namespace Hypnodev\OpenapiGenerator\Commands;

use Hypnodev\OpenapiGenerator\Exceptions\DefinitionException;
use Hypnodev\OpenapiGenerator\Facades\OpenapiGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

class OpenApiGenerate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'openapi:generator {prefix=api} {--output=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate OpenApi specification based on routes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (app()->isProduction()) {
            $reply = $this->confirm('You are in production environment. Are you sure you want to generate OpenApi specification?');
            if (!$reply) {
                $this->info('OpenApi specification generation aborted.');
                return 0;
            }
        }

        $output = $this->option('output') ?? storage_path('openapi.yaml');
        if (!Str::endsWith($output, ['.yaml', '.yml'])) {
            $this->error('Output file must be a YAML file.');
            return 1;
        }

        $this->info("OpenApi specification will be generated in $output");

        if (file_exists($output)) {
            $reply = $this->confirm('OpenApi specification already exists. Do you want to overwrite it?');
            if (!$reply) {
                $this->info('OpenApi specification generation aborted.');
                return 1;
            }
        }

        $this->info('Generating OpenApi specification...');

        try {
            $definitions = OpenapiGenerator::make();
            file_put_contents(
                $output,
                Yaml::dump($definitions, flags: Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE)
            );
        }
        catch (DefinitionException $e) {
            $this->error("Error during OpenApi specification generation: {$e->getMessage()}");
            return 1;
        }

        $this->info('OpenApi specification generated successfully!');
        return 0;
    }
}
