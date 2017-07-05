<?php

namespace Hyn\Webserver\Models;

use Cache;
use Config;
use Hyn\Tenancy\Abstracts\Models\SystemModel;
use Hyn\Tenancy\Models\Customer;
use Hyn\Tenancy\Models\Hostname;
use Hyn\Webserver\Tools\CertificateParser;
use Laracasts\Presenter\PresentableTrait;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

/**
 * Class SslCertificate.
 *
 * @property string pathCrt
 * @property string pathKey
 * @property string pathPem
 * @property string pathCa
 * @property Array pathCas
 * @property Carbon validates_at
 * @property Carbon invalidates_at
 */
class SslCertificate extends SystemModel
{
    use PresentableTrait;

    /**
     * @var string
     */
    protected $presenter = 'Hyn\Webserver\Presenters\SslCertificatePresenter';

    /**
     * @var array
     */
    protected $fillable = ['customer_id', 'certificate', 'authority_bundle', 'key'];

    /**
     * @var array
     */
    protected $appends = ['pathKey', 'pathPem', 'pathCrt', 'pathCa', 'pathCas'];

    /**
     * @return array
     */
    public function getDates()
    {
        return ['validates_at', 'invalidates_at'];
    }

    public function getIsExpired()
    {
        return $this->invalidates_at ? $this->invalidates_at->isPast() : null;
    }

    /**
     * @return CertificateParser|null
     */
    public function getX509Attribute()
    {
        if (! Cache::has('ssl-x509-'.$this->id)) {
            $sslCertificate = null;
            if ($this->certificate) {
                if (File::exists($this->certificate)) {
                    $certificate = File::get($this->certificate);
                    $sslCertificate = new CertificateParser($certificate);
                } else {
                    $sslCertificate = new CertificateParser($this->certificate);
                }
            }
            Cache::add('ssl-x509-'.$this->id, $sslCertificate, 3600);
        }

        return Cache::get('ssl-x509-'.$this->id);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Hostnames which uses this certificate
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function hostnames()
    {
        return $this->hasMany(Hostname::class);
    }

    /**
     * Hostnames defined in this certificate
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function certificateHostnames()
    {
        return $this->hasMany(sslHostname::class);
    }

    /**
     * @return string
     */
    public function getPathKeyAttribute()
    {
        $keyPath = $this->key;
        if (File::exists($keyPath)) {
            return $keyPath;
        } else {
            return $this->publishPath('key');
        }
    }

    /**
     * @param string $postfix
     *
     * @return string
     */
    public function publishPath($postfix = 'key')
    {
        return sprintf('%s/%s/certificate.%s', Config::get('webserver.ssl.path'), $this->id, $postfix);
    }

    /**
     * @return string
     */
    public function getPathPemAttribute()
    {
        return $this->publishPath('pem');
    }

    /**
     * @return string
     */
    public function getPathCrtAttribute()
    {
        $crtPath = $this->certificate;
        if (File::exists($crtPath)) {
            return $crtPath;
        } else {
            return $this->publishPath('crt');
        }
    }

    /**
     * @return string
     */
    public function getPathCaAttribute()
    {
        $caPaths = $this->getPathCasAttribute();
        if (is_null($caPaths))
            return $this->publishPath('ca');
        if (empty($caPaths))
            return "";
        return $caPaths[0];
    }
    /**
     *
     * @return array|null
     */
    public function getPathCasAttribute()
    {
        if (empty($this->authority_bundle))
            return []; // to distinguish valid empty, must return empty array and not null
            $caPaths = explode('|', $this->authority_bundle);
            foreach ($caPaths as $caPath) {
                if (! File::exists($caPath))
                    return null;
            }
            return $caPaths;
    }

    /**
     *
     * @param string $newFilePath
     *
     * @return void
     */
    public function setPathKeyAttribute($newFilePath)
    {
        if (File::exists($newFilePath)) {
            $this->key = $newFilePath;
        } else {
            // error
        }
    }

    /**
     *
     * @param string $newFilePath
     *
     * @return void
     */
    public function setPathCrtAttribute($newFilePath)
    {
        if (File::exists($newFilePath)) {
            $this->certificate = $newFilePath;
        } else {
            // error
        }
    }

    /**
     *
     * @param string $newFilePath
     *
     * @return void
     */
    public function setPathCaAttribute($newFilePath)
    {
        $this->setPathCasAttribute([
            $newFilePath
        ]);
    }

    /**
     *
     * @param array $newFilePaths
     *
     * @return void
     */
    public function setPathCasAttribute($newFilePaths)
    {
        foreach ($newFilePaths as $caPath) {
            if (! File::exists($caPath))
                return;
        }
        $this->authority_bundle = implode('|', $newFilePaths);
    }
}
