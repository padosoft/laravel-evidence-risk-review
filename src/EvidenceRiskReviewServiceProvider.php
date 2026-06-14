<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use LogicException;
use Padosoft\EvidenceRiskReview\Checks\BoundaryConditionCheck;
use Padosoft\EvidenceRiskReview\Checks\ContraindicationCheck;
use Padosoft\EvidenceRiskReview\Checks\EvidenceStrengthCheck;
use Padosoft\EvidenceRiskReview\Checks\LlmEvidenceStrengthCheck;
use Padosoft\EvidenceRiskReview\Checks\OverGeneralizationCheck;
use Padosoft\EvidenceRiskReview\Checks\RedFlagCheck;
use Padosoft\EvidenceRiskReview\Checks\SpecialPopulationCheck;
use Padosoft\EvidenceRiskReview\Console\EvidenceLogCommand;
use Padosoft\EvidenceRiskReview\Console\EvidenceProfilesCommand;
use Padosoft\EvidenceRiskReview\Console\EvidenceReviewCommand;
use Padosoft\EvidenceRiskReview\Console\EvidenceTaxonomyCommand;
use Padosoft\EvidenceRiskReview\Contracts\EvidenceReviewerLlmContract;
use Padosoft\EvidenceRiskReview\Contracts\ReviewLogStore;
use Padosoft\EvidenceRiskReview\Contracts\RiskCheck;
use Padosoft\EvidenceRiskReview\Llm\NullEvidenceReviewerLlm;
use Padosoft\EvidenceRiskReview\Log\ArrayReviewLogStore;
use Padosoft\EvidenceRiskReview\Log\DatabaseReviewLogStore;
use Padosoft\EvidenceRiskReview\Log\NullReviewLogStore;
use Padosoft\EvidenceRiskReview\Profiles\DomainProfileRegistry;
use Padosoft\EvidenceRiskReview\Support\EvidenceTierLabeler;
use Padosoft\EvidenceRiskReview\Support\ReviewEngine;
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
        LlmEvidenceStrengthCheck::class,
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

        $this->app->singleton(EvidenceReviewerLlmContract::class, static fn (): EvidenceReviewerLlmContract => new NullEvidenceReviewerLlm);

        $this->app->singleton(ReviewLogStore::class, static function ($app): ReviewLogStore {
            $config = $app->make(ConfigRepository::class);
            $store = $config->get('evidence-risk-review.review_log.store', 'null');

            if ($store === 'array') {
                return new ArrayReviewLogStore;
            }

            if ($store === 'database') {
                $connection = $config->get('evidence-risk-review.review_log.connection');
                $table = $config->get('evidence-risk-review.review_log.table', 'evidence_risk_review_logs');

                return new DatabaseReviewLogStore(
                    $app->make(DatabaseManager::class)->connection(is_string($connection) ? $connection : null),
                    is_string($table) ? $table : 'evidence_risk_review_logs',
                );
            }

            if ($store === 'null') {
                return new NullReviewLogStore;
            }

            throw new InvalidArgumentException('Unknown evidence risk review log store ['.(is_scalar($store) ? (string) $store : get_debug_type($store)).'].');
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

        $this->app->singleton(ReviewEngine::class, static function ($app): ReviewEngine {
            return new ReviewEngine(
                $app->make(DomainProfileRegistry::class),
                $app->make(RiskSweepEngine::class),
                $app->make(EvidenceTierLabeler::class),
                $app->make(TierResolver::class),
                $app->make(EvidenceReviewerLlmContract::class),
                $app->make(ReviewLogStore::class),
                $app->make(ConfigRepository::class),
            );
        });

        $this->app->singleton(EvidenceRiskReview::class, static function ($app): EvidenceRiskReview {
            return new EvidenceRiskReview(
                $app->make(ReviewEngine::class),
                $app->make(EvidenceTierLabeler::class),
                $app->make(DomainProfileRegistry::class),
                $app->make(TierResolver::class),
            );
        });

        $this->app->alias(EvidenceRiskReview::class, 'evidence-risk-review');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/evidence-risk-review.php' => config_path('evidence-risk-review.php'),
            __DIR__.'/../config/evidence-risk-review' => config_path('evidence-risk-review'),
        ], 'evidence-risk-review-config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_evidence_risk_review_logs_table.php.stub' => database_path('migrations/'.date('Y_m_d_His').'_create_evidence_risk_review_logs_table.php'),
        ], 'evidence-risk-review-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                EvidenceReviewCommand::class,
                EvidenceProfilesCommand::class,
                EvidenceTaxonomyCommand::class,
                EvidenceLogCommand::class,
            ]);
        }
    }
}
