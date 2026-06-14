<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;
use Padosoft\EvidenceRiskReview\Data\ReviewArtifact;
use Padosoft\EvidenceRiskReview\Data\ReviewOptions;
use Padosoft\EvidenceRiskReview\EvidenceRiskReview;
use Padosoft\EvidenceRiskReview\Support\ArrayData;
use Throwable;

final class EvidenceReviewCommand extends Command
{
    private const FLAGGED = 2;

    protected $signature = 'evidence:review
        {path? : Path to a JSON artifact or review envelope. Omit to read STDIN.}
        {--profile= : Override the review profile key.}
        {--dry-run : Do not append to the review log.}
        {--label-via-llm : Request LLM tier refinement when LLM is enabled.}
        {--pretty : Pretty-print JSON output.}';

    protected $description = 'Review an evidence artifact and return a JSON risk result.';

    public function __construct(
        private readonly EvidenceRiskReview $reviews,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $payload = $this->readPayload();
            $artifactPayload = array_key_exists('artifact', $payload)
                ? ArrayData::requireMap($payload['artifact'], 'artifact')
                : $payload;
            $optionsPayload = array_key_exists('options', $payload)
                ? ArrayData::requireMap($payload['options'], 'options')
                : [];

            $profile = $this->option('profile');
            if (is_string($profile) && $profile !== '') {
                $optionsPayload['profile_key'] = $profile;
            }

            if ($this->option('dry-run') === true) {
                $optionsPayload['dry_run'] = true;
            }

            if ($this->option('label-via-llm') === true) {
                $optionsPayload['label_via_llm'] = true;
            }

            $result = $this->reviews->review(
                ReviewArtifact::fromArray($artifactPayload),
                ReviewOptions::fromArray($optionsPayload),
            );

            $this->line($this->json($result->toArray()));

            return $result->findings === [] ? self::SUCCESS : self::FLAGGED;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function readPayload(): array
    {
        $path = $this->argument('path');

        if ($path !== null && ! is_string($path)) {
            throw new InvalidArgumentException('The path argument must be a string.');
        }

        $contents = $path === null
            ? stream_get_contents(STDIN)
            : $this->readFile($path);

        if (! is_string($contents) || trim($contents) === '') {
            throw new InvalidArgumentException('Review JSON payload is empty.');
        }

        $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        return ArrayData::requireMap($decoded, 'review payload');
    }

    private function readFile(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("Review JSON file [{$path}] is not readable.");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new InvalidArgumentException("Review JSON file [{$path}] could not be read.");
        }

        return $contents;
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     */
    private function json(array $payload): string
    {
        $flags = $this->option('pretty') === true ? JSON_PRETTY_PRINT : 0;

        return json_encode($payload, JSON_THROW_ON_ERROR | $flags);
    }
}
