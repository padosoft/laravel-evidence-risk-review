<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Tenancy;

use Padosoft\EvidenceRiskReview\Contracts\TenantResolver;

/**
 * Default no-op resolver: the package runs single-tenant / unscoped. A
 * multi-tenant host rebinds {@see TenantResolver} to its own implementation.
 */
final class NullTenantResolver implements TenantResolver
{
    public function current(): ?string
    {
        return null;
    }
}
