<?php

namespace App\Filament\Resources\BranchLogs\Pages;

use App\Filament\Resources\BranchLogs\BranchLogResource;
use Filament\Resources\Pages\ListRecords;

class ListBranchLogs extends ListRecords
{
    protected static string $resource = BranchLogResource::class;
}
