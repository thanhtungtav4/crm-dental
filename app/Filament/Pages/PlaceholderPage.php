<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

abstract class PlaceholderPage extends Page
{
    protected string $view = 'filament.pages.placeholder';

    protected static string $pageTitle = '';

    protected static ?string $pageDescription = null;

    protected static array $pageBullets = [];

    public function getHeading(): string
    {
        return static::$pageTitle !== '' ? static::$pageTitle : (static::$navigationLabel ?? parent::getHeading());
    }

    public function getSubheading(): ?string
    {
        return static::$pageDescription;
    }

    public function getTitle(): string
    {
        return $this->getHeading();
    }

    public function getBullets(): array
    {
        return static::$pageBullets;
    }
}
