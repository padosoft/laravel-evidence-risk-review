<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;
use Padosoft\EvidenceRiskReview\Support\EvidenceTierLabeler;
use Padosoft\EvidenceRiskReview\Support\TierResolver;

final class EvidenceRiskReviewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/evidence-risk-review.php', 'evidence-risk-review');

        $this->app->singleton(TierResolver::class, static function ($app): TierResolver {
            return new TierResolver($app->make(ConfigRepository::class));
        });

        $this->app->singleton(EvidenceTierLabeler::class, static function ($app): EvidenceTierLabeler {
            return new EvidenceTierLabeler(
                $app->make(TierResolver::class),
                $app->make(ConfigRepository::class),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/evidence-risk-review.php' => config_path('evidence-risk-review.php'),
        ], 'evidence-risk-review-config');
    }
}
