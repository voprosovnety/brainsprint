<?php

namespace App\Analytics;

enum Dataset: string
{
    case Real = 'real';
    case Synthetic = 'synthetic';
}
