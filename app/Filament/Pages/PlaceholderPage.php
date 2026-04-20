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

    protected function bullets(): array
    {
        return static::$pageBullets;
    }

    /**
     * @return array{
     *     badge_label: string,
     *     status_text: string,
     *     subheading: ?string,
     *     bullets: array<int, string>
     * }
     */
    public function pageViewState(): array
    {
        return [
            'badge_label' => 'Đang phát triển',
            'status_text' => 'Module này đang được xây dựng theo tài liệu tham chiếu.',
            'subheading' => $this->getSubheading(),
            'bullets' => $this->bullets(),
        ];
    }
}
