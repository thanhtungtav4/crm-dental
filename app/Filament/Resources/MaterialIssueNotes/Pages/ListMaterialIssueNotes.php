<?php

namespace App\Filament\Resources\MaterialIssueNotes\Pages;

use App\Filament\Resources\MaterialIssueNotes\MaterialIssueNoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaterialIssueNotes extends ListRecords
{
    protected static string $resource = MaterialIssueNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
