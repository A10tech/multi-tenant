<?php

namespace Hyn\Tenancy\Commands\Migrate;

use Hyn\Tenancy\Traits\TenantDatabaseCommandTrait;
use Illuminate\Database\Migrations\Migrator;

class StatusCommand extends \Illuminate\Database\Console\Migrations\StatusCommand
{
    use TenantDatabaseCommandTrait;

    /**
     * @var \Hyn\Tenancy\Contracts\WebsiteRepositoryContract
     */
    protected $website;

    /**
     * MigrateCommand constructor.
     *
     * @param Migrator $migrator
     */
    public function __construct(Migrator $migrator)
    {
        parent::__construct($migrator);

        $this->website = app('Hyn\Tenancy\Contracts\WebsiteRepositoryContract');
    }

    public function fire()
    {
        // fallback to default behaviour if we're not talking about multi tenancy
        if (! $this->option('tenant')) {
            $this->info('Not running tenancy migration, falling back on native laravel migrate command due to missing tenant option.');

            //$this->input->setOption('database', config('multi-tenant.db.system-connection-name', 'hyn')); // use multi-tenant system database
            return parent::fire();
        }

        $websites = $this->getWebsitesFromOption();

        foreach ($websites as $website) {
            $this->info("Migration status for {$website->id}: {$website->present()->name}");

            $website->database->setCurrent();

            parent::fire();
        }
    }

    /**
     * @return array
     */
    protected function getOptions()
    {
        return array_merge(
            parent::getOptions(),
            $this->getTenantOption()
            );
    }
}
