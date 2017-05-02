<?php

/*
 * This file is part of the hyn/multi-tenant package.
 *
 * (c) Daniël Klabbers <daniel@klabbers.email>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @see https://github.com/hyn/multi-tenant
 *
 */

namespace Hyn\Tenancy\Repositories;

use Hyn\Tenancy\Contracts\Repositories\WebsiteRepository as Contract;
use Hyn\Tenancy\Events\Websites as Events;
use Hyn\Tenancy\Models\Website;
use Hyn\Tenancy\Traits\DispatchesEvents;
use Hyn\Tenancy\Validators\WebsiteValidator;

class WebsiteRepository implements Contract
{
    use DispatchesEvents;
    /**
     * @var Website
     */
    protected $website;
    /**
     * @var WebsiteValidator
     */
    protected $validator;

    /**
     * WebsiteRepository constructor.
     * @param Website $website
     * @param WebsiteValidator $validator
     */
    public function __construct(Website $website, WebsiteValidator $validator)
    {
        $this->website = $website;
        $this->validator = $validator;
    }

    /**
     * @param string $uuid
     * @return Website|null
     */
    public function findByUuid(string $uuid): ?Website
    {
        return $this->website->newQuery()->where('uuid', $uuid)->first();
    }

    /**
     * @param Website $website
     * @return Website
     */
    public function create(Website &$website): Website
    {
        if ($website->exists) {
            return $this->update($website);
        }

        $this->emitEvent(
            new Events\Creating($website)
        );

        $this->validator->save($website);

        $website->save();

        $this->emitEvent(
            new Events\Created($website)
        );

        return $website;
    }

    /**
     * @param Website $website
     * @return Website
     */
    public function update(Website &$website): Website
    {
        if (!$website->exists) {
            return $this->create($website);
        }

        $this->emitEvent(
            new Events\Updating($website)
        );

        $this->validator->save($website);

        $dirty = $website->getDirty();

        $website->save();

        $this->emitEvent(
            new Events\Updated($website, $dirty)
        );

        return $website;
    }

    /**
     * @param Website $website
     * @param bool $hard
     * @return Website
     */
    public function delete(Website &$website, $hard = false): Website
    {
        $this->emitEvent(
            new Events\Deleting($website)
        );

        $this->validator->delete($website);

        if ($hard) {
            $website->forceDelete();
        } else {
            $website->delete();
        }

        $this->emitEvent(
            new Events\Deleted($website)
        );

        return $website;
    }
}
