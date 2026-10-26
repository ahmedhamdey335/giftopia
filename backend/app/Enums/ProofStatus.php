<?php

namespace App\Enums;

enum ProofStatus: string
{
    case None = 'none';
    case Pending = 'pending';
    case Approved = 'approved';
    case ChangesRequested = 'changes_requested';
}
