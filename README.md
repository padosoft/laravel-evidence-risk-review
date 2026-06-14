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

## HTTP Surface

The HTTP API is default-OFF. Enable it explicitly in `config/evidence-risk-review.php`:

```php
'api' => [
    'enabled' => env('EVIDENCE_RISK_REVIEW_API_ENABLED', false),
    'prefix' => env('EVIDENCE_RISK_REVIEW_API_PREFIX', 'evidence-risk-review/api'),
    'middleware' => [],
],
```

Available endpoints when enabled:

```text
POST /evidence-risk-review/api/reviews
GET  /evidence-risk-review/api/reviews/{review}
GET  /evidence-risk-review/api/profiles
GET  /evidence-risk-review/api/profiles/{key}
GET  /evidence-risk-review/api/taxonomy
GET  /evidence-risk-review/api/openapi.yaml
```

HTTP errors use a stable envelope:

```json
{
  "error": {
    "code": "validation_error",
    "message": "Expected non-empty string at [artifact_id].",
    "details": {}
  }
}
```

## MCP Surface

The MCP layer is framework-agnostic and can be resolved from Laravel's container:

```php
use Padosoft\EvidenceRiskReview\Mcp\McpToolRegistry;

$registry = app(McpToolRegistry::class);

$definitions = array_map(
    static fn ($definition) => $definition->toArray(),
    $registry->definitions(),
);

$result = $registry->handle('evidence_review.assess', [
    'artifact_id' => 'answer-125',
    'answer_text' => 'No claims to check.',
    'options' => ['dry_run' => true],
]);
```

Available tools:

```text
evidence_review.assess
evidence_review.label_tier
evidence_review.list_profiles
```
