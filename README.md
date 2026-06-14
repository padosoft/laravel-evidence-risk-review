# laravel-evidence-risk-review

Standalone Laravel package for evidence-tier labeling and risk review guardrails.

## PHP Surface

```php
use Padosoft\EvidenceRiskReview\Data\ReviewArtifact;
use Padosoft\EvidenceRiskReview\Facades\EvidenceRiskReview;

$result = EvidenceRiskReview::review(new ReviewArtifact(
    artifactId: 'answer-123',
    answerText: 'No claims to check yet.',
));

$arrayResult = EvidenceRiskReview::reviewArray([
    'artifact_id' => 'answer-124',
    'answer_text' => 'This likely works.',
    'options' => ['dry_run' => true],
]);

$profiles = EvidenceRiskReview::listProfiles();
$taxonomy = EvidenceRiskReview::taxonomy();
```

## Artisan Surface

```bash
php artisan evidence:review artifact.json --dry-run
php artisan evidence:profiles
php artisan evidence:taxonomy
php artisan evidence:log --limit=25
```

`evidence:review` exits with `0` for clean reviews, `2` when findings are present, and `1` on failure.
