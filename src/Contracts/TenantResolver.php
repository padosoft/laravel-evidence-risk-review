<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Contracts;

use Padosoft\EvidenceRiskReview\Tenancy\NullTenantResolver;

/**
 * Resolves the current tenant for the running request/process.
 *
 * The package is multi-tenant-aware but tenancy-agnostic: it never assumes a
 * particular host tenancy implementation. A host that runs multiple tenants
 * binds its own resolver in the container; when bound, the review-log read path
 * (list + show) is scoped to the current tenant and the write path stamps every
 * persisted review with it — so one tenant can never read or pollute another's
 * reviews.
 *
 * The default binding ({@see NullTenantResolver})
 * returns null, which preserves the original single-tenant behaviour: no
 * scoping, client-supplied tenant filters honoured as-is.
 */
interface TenantResolver
{
    /**
     * The active tenant id, or null when running single-tenant / unscoped.
     */
    public function current(): ?string;
}
