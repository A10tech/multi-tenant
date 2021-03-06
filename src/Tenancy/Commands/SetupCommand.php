<?php

namespace Hyn\Tenancy\Commands;

use File;
use Hyn\Tenancy\Contracts\CustomerRepositoryContract;
use Hyn\Tenancy\Contracts\HostnameRepositoryContract;
use Hyn\Tenancy\Contracts\WebsiteRepositoryContract;
use Hyn\Tenancy\Models\Customer;
use Hyn\Tenancy\Models\Hostname;
use Hyn\Tenancy\Models\Website;
use Hyn\Tenancy\Tenant\DatabaseConnection;
use Hyn\Webserver\Helpers\ServerConfigurationHelper;
use Illuminate\Console\Command;

class SetupCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'multi-tenant:setup
        {--customer= : Name of the first customer}
        {--email= : Email address of the first customer}
        {--hostname= : Domain- or hostname for the first customer website}
        {--webserver= : Hook into webserver (nginx|apache|no)}
        {--identifier= : Website identifier}
        {--tenant-config= : Location of a preset of configuration items to use for multi-tenant.php}';

    /**
     * @var string
     */
    protected $description = 'Final configuration step for hyn multi tenancy packages';

    /**
     * @var ServerConfigurationHelper
     */
    protected $helper;

    /**
     * @var HostnameRepositoryContract
     */
    protected $hostname;
    /**
     * @var WebsiteRepositoryContract
     */
    protected $website;
    /**
     * @var CustomerRepositoryContract
     */
    protected $customer;

    /**
     * @var array
     */
    protected $configuration;

    /**
     * @var int
     */
    protected $step = 1;

    /**
     * @param HostnameRepositoryContract $hostname
     * @param WebsiteRepositoryContract  $website
     * @param CustomerRepositoryContract $customer
     */
    public function __construct(
        HostnameRepositoryContract $hostname,
        WebsiteRepositoryContract $website,
        CustomerRepositoryContract $customer
    ) {
        parent::__construct();

        $this->hostname = $hostname;
        $this->website = $website;
        $this->customer = $customer;

        $this->helper = new ServerConfigurationHelper();
    }

    /**
     * Handles the set up.
     */
    public function handle()
    {
        $this->configuration = config('webserver');

        $name = $this->option('customer');
        $email = $this->option('email');
        $hostname = $this->option('hostname');
        $identifier = $this->option('identifier');
        $tenantConfig = $this->option('tenant-config');

        if (empty($name)) {
            $name = $this->ask('Please provide a customer name or restart command with --customer');
        }

        if (empty($email)) {
            $email = $this->ask('Please provide a customer email address or restart command with --email');
        }

        if (empty($hostname)) {
            $hostname = $this->ask('Please provide a customer hostname or restart command with --hostname');
        }

        if (!empty($identifier) && strlen($identifier) > 10) {
            $identifier = $this->ask('Please provide an identifier with a max length of 10 or restart command with --identifier');
        }

        $this->comment('Welcome to hyn multi tenancy.');

        $this->publishFiles();

        // Give the user a chance to change the config or check whether it's been provided as option.
        if (null !== $tenantConfig && File::exists($tenantConfig)) {
            File::copy($tenantConfig, config_path('multi-tenant.php'));
        } elseif (null !== $tenantConfig) {
            $this->error("Ignored $tenantConfig, it does not exist");
        } else {
            $this->confirm(
                "You are now able to edit the published multi-tenant.php configuration file before continuing. Ready?",
                true
            );
        }

        // If the dashboard is installed we need to prevent default laravel migrations
        // so we run the dashboard setup command before running any migrations
        if (class_exists('Hyn\ManagementInterface\ManagementInterfaceServiceProvider')) {
            $this->info('The management interface will be installed first.');
            $this->call('dashboard:setup');
        } else {
            $this->comment('First off, migrations for the packages will run.');

            // Migrations are run during dashboard setup or here.
            $this->runMigrations();
        }

        $tenantDirectory = config('multi-tenant.tenant-directory') ? config('multi-tenant.tenant-directory') : storage_path('multi-tenant');

        if (! File::isDirectory($tenantDirectory) && File::makeDirectory($tenantDirectory, 0755, true)) {
            $this->comment("The directory to hold your tenant websites has been created under {$tenantDirectory}.");
        }

        $webserver = null;

        // Setup webserver
        if ($this->helper) {
            $this->helper->createDirectories();

            $webserver = $this->option('webserver');

            if (empty($webserver)) {
                $webserver = $this->anticipate('Integrate into a webserver?', ['no', 'apache', 'nginx'], 'no');
            }

            if ($webserver != 'no') {
                $webserverConfiguration = array_get($this->configuration, $webserver);
                $webserverClass = array_get($webserverConfiguration, 'class');
            } else {
                $webserver = null;
            }

            // Create the first tenant configurations
            /** @var Customer $customer */
            $customer = $this->customer->create(compact('name', 'email'));

            if (empty($identifier)) {
                $identifier = substr(str_replace(['.'], '-', $hostname), 0, 10);
            }

            /** @var Website $website */
            $website = $this->website->create([
                'customer_id' => $customer->id,
                'identifier'  => $identifier
            ]);

            /** @var Hostname $host */
            $host = $this->hostname->create([
                'hostname'    => $hostname,
                'website_id'  => $website->id,
                'customer_id' => $customer->id,
            ]);

            // hook into the webservice of choice once object creation succeeded
            if ($webserver) {
                (new $webserverClass($website))->register();
            }

            if ($customer->exists && $website->exists && $host->exists) {
                $this->info('Configuration successful');
            }
        } else {
            $this->error('The hyn/webserver package is not installed. Visit http://hyn.me/packages/webserver for more information.');
        }
    }

    /**
     * Publish files for all Hyn packages.
     */
    protected function publishFiles()
    {
        foreach (config('hyn.packages', []) as $name => $package) {
            if (class_exists(array_get($package, 'service-provider'))) {
                $this->call('vendor:publish', [
                    '--provider' => array_get($package, 'service-provider'),
                    '-n',
                ]);
            }
        }
    }

    /**
     * Run migrations for all depending service providers.
     */
    protected function runMigrations()
    {
        $this->call('migrate', [
            '--database' => DatabaseConnection::systemConnectionName(),
            '-n',
        ]);
    }
}
