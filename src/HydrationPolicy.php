<?php

namespace Splitstack\Invariants;

enum HydrationPolicy
{
    case Strict;
    case Lenient;
    case Quarantine;
    case AutoCorrect;
}
