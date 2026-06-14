<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;
use LogicException;
use Padosoft\EvidenceRiskReview\Checks\BoundaryConditionCheck;
use Padosoft\EvidenceRiskReview\Checks\ContraindicationCheck;
use Padosoft\EvidenceRiskReview\Checks\EvidenceStrengthCheck;
use Padosoft\EvidenceRiskReview\Checks\OverGeneralizationCheck;
use Padosoft\EvidenceRiskReview\Checks\RedFlagCheck;
use Padosoft\EvidenceRiskReview\Checks\SpecialPopulationCheck;
use Padosoft\EvidenceRiskReview\Contracts\RiskCheck;
use Padosoft\EvidenceRiskReview\Profiles\DomainProfileRegistry;
use Padosoft\EvidenceRiskReview\Support\EvidenceTierLabeler;
use Padosoft\EvidenceRiskReview\Support\RiskSweepEngine;
use Padosoft\EvidenceRiskReview\Support\TierResolver;

final class EvidenceRiskReviewServiceProvider extends ServiceProvider
{
    /**
     * @var list<class-string>
     */
    private const BUILT_IN_CHECKS = [
        EvidenceStrengthCheck::class,
        OverGeneralizationCheck::class,
        SpecialPopulationCheck::class,
        ContraindicationCheck::class,
        BoundaryConditionCheck::class,
        RedFlagCheck::class,
    ];

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

        $this->app->singleton(DomainProfileRegistry::class, static function ($app): DomainProfileRegistry {
            return new DomainProfileRegistry($app->make(ConfigRepository::class));
        });

        $this->app->singleton(RiskSweepEngine::class, function ($app): RiskSweepEngine {
            $checks = [];

            foreach (self::BUILT_IN_CHECKS as $checkClass) {
                $check = $app->make($checkClass);

                if (! $check instanceof RiskCheck) {
                    throw new LogicException("Built-in check [{$checkClass}] must implement RiskCheck.");
                }

                $checks[] = $check;
            }

            return new RiskSweepEngine($checks);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/evidence-risk-review.php' => config_path('evidence-risk-review.php'),
            __DIR__.'/../config/evidence-risk-review' => config_path('evidence-risk-review'),
        ], 'evidence-risk-review-config');
    }
}
