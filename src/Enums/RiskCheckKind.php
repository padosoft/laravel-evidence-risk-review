<?php

declare(strict_types=1);

namespace Padosoft\EvidenceRiskReview\Enums;

enum RiskCheckKind: string
{
    case EvidenceStrength = 'evidence_strength';
    case OverGeneralization = 'over_generalization';
    case SpecialPopulation = 'special_population';
    case Contraindication = 'contraindication';
    case BoundaryCondition = 'boundary_condition';
    case RedFlag = 'red_flag';
}
