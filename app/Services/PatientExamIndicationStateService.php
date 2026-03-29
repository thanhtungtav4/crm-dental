<?php

namespace App\Services;

use App\Support\ClinicRuntimeSettings;

class PatientExamIndicationStateService
{
    public function normalizeKey(string $key): string
    {
        return ClinicRuntimeSettings::normalizeExamIndicationKey($key);
    }

    /**
     * @param  array<int, mixed>  $indications
     * @return array<int, string>
     */
    public function normalizeSelected(array $indications): array
    {
        return collect($indications)
            ->filter(fn ($item) => filled($item))
            ->map(fn ($item) => $this->normalizeKey((string) $item))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $indicationImages
     * @param  ?array<int, mixed>  $selectedIndications
     * @return array<string, array<int, string>>
     */
    public function normalizeImages(array $indicationImages, ?array $selectedIndications = null): array
    {
        $selected = $this->normalizeSelected($selectedIndications ?? []);
        $normalized = [];

        foreach ($indicationImages as $rawType => $paths) {
            $type = $this->normalizeKey((string) $rawType);

            if (! in_array($type, $selected, true)) {
                continue;
            }

            $normalized[$type] = collect(is_array($paths) ? $paths : [$paths])
                ->filter(fn ($path) => filled($path))
                ->values()
                ->all();
        }

        foreach ($selected as $type) {
            if (! array_key_exists($type, $normalized)) {
                $normalized[$type] = [];
            }
        }

        return $normalized;
    }

    /**
     * @param  array<int, mixed>  $indications
     * @param  array<string, mixed>  $indicationImages
     * @param  array<string, mixed>  $tempUploads
     * @return array{
     *     indications: array<int, string>,
     *     indicationImages: array<string, mixed>,
     *     tempUploads: array<string, mixed>
     * }
     */
    public function toggle(
        array $indications,
        array $indicationImages,
        array $tempUploads,
        string $type,
    ): array {
        $normalizedType = $this->normalizeKey($type);
        $normalizedIndications = $this->normalizeSelected($indications);

        if (in_array($normalizedType, $normalizedIndications, true)) {
            $normalizedIndications = array_values(array_diff($normalizedIndications, [$normalizedType]));
            unset($indicationImages[$normalizedType], $tempUploads[$normalizedType]);
        } else {
            $normalizedIndications[] = $normalizedType;
            $normalizedIndications = array_values(array_unique($normalizedIndications));
        }

        return [
            'indications' => $normalizedIndications,
            'indicationImages' => $indicationImages,
            'tempUploads' => $tempUploads,
        ];
    }
}
