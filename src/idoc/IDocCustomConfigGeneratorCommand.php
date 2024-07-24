<?php

namespace OVAC\IDoc;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class IDocCustomConfigGeneratorCommand extends Command
{
    // Command signature and description
    protected $signature = 'idoc:custom {config?} {--force}';
    protected $description = 'Generate API documentation for custom configuration';

    /**
     * Handle the command execution.
     */
    public function handle()
    {
        // Get the optional 'config' argument value
        $configFile = $this->argument('config');

        // Construct the config file name
        $configFileName = 'idoc.' . $configFile . '.php';

        // Check if a specific configuration file is provided
        if ($configFile) {
            // Construct the path to the override configuration file
            $configPath = config_path($configFileName);

            // Check if the override configuration file exists
            if (File::exists($configPath)) {
                // Load the override configuration
                $overrideConfig = require $configPath;

                // Merge the override configuration with the existing iDoc configuration
                Config::set('idoc', array_merge(config('idoc'), $overrideConfig));
                $this->info("Configuration file '{$configFileName}' loaded.");
            } else {
                // Display an error if the configuration file does not exist
                $this->error("Configuration file '{$configFileName}' does not exist in the config path.");
                return;
            }
        } else {
            // Inform the user that the default configuration will be used
            $this->info('No configuration provided, using default iDoc configuration.');
        }

        // Check if the --force option is provided
        $force = $this->option('force') ? ['--force' => true] : [];

        // Execute the 'idoc:generate' Artisan command with the --force option if provided
        Artisan::call('idoc:generate', $force);

        // Get the output of the Artisan command
        $output = Artisan::output();

        // Display the output of the Artisan command
        $this->info($output);

        // Inform the user that the iDoc command has been executed
        $this->info('iDoc command has been executed.');
    }
}