<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Padosoft\EvidenceRiskReview\EvidenceRiskReviewServiceProvider;
use RuntimeException;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            EvidenceRiskReviewServiceProvider::class,
        ];
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $abstract
     * @return T
     */
    protected function resolve(string $abstract): object
    {
        if ($this->app === null) {
            throw new RuntimeException('The Testbench application has not been booted.');
        }

        return $this->app->make($abstract);
    }
}
