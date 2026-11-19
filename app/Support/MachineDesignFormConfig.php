<?php

namespace App\Support;

final class MachineDesignFormConfig
{
    /** @return array<string, bool|array<string, bool>> */
    public static function defaults(): array
    {
        $fields = [
            'title' => false,
            'machineType' => true,
            'category' => true,
            'designTypes' => true,
            'designSpecifications' => true,
            'sequenceType' => true,
            'designFileType' => true,
            'previewImages' => true,
            'stitchFiles' => true,
            'pricing' => true,
        ];

        $specificationFields = [
            'image' => true,
            'nameOrNumber' => true,
            'stitchCount' => true,
            'needle' => true,
            'area' => true,
            'height' => true,
            'width' => true,
        ];

        return [
            ...$fields,
            'designTypeImageCards' => false,
            'required' => [
                ...$fields,
                'sequenceType' => false,
            ],
            'specificationFields' => [
                ...$specificationFields,
                'required' => [
                    ...$specificationFields,
                    'image' => false,
                ],
            ],
        ];
    }

    /** @return array<string, bool|array<string, bool>> */
    public static function normalize(?array $config): array
    {
        $normalized = self::defaults();
        $fieldKeys = array_keys($normalized['required']);

        $normalized['designTypeImageCards'] = (bool) ($config['designTypeImageCards'] ?? false);

        foreach ($fieldKeys as $key) {
            $defaultRequired = $normalized['required'][$key];

            if (array_key_exists($key, $config ?? [])) {
                $normalized[$key] = (bool) $config[$key];
            }

            // Old saved configurations only contained visibility booleans. Preserve
            // the validation behaviour that was used before required settings existed.
            $normalized['required'][$key] = isset($config['required']) && is_array($config['required'])
                ? (bool) ($config['required'][$key] ?? $defaultRequired)
                : ($defaultRequired && $normalized[$key]);

            if (! $normalized[$key]) {
                $normalized['required'][$key] = false;
            }
        }

        $savedSpecificationFields = isset($config['specificationFields']) && is_array($config['specificationFields'])
            ? $config['specificationFields']
            : [];
        $savedSpecificationRequired = isset($savedSpecificationFields['required']) && is_array($savedSpecificationFields['required'])
            ? $savedSpecificationFields['required']
            : [];
        $legacySpecificationsRequired = $normalized['required']['designSpecifications'];
        $defaultSpecificationRequired = $normalized['specificationFields']['required'];

        foreach (array_keys(array_filter($normalized['specificationFields'], fn ($value) => is_bool($value))) as $key) {
            if (array_key_exists($key, $savedSpecificationFields)) {
                $normalized['specificationFields'][$key] = (bool) $savedSpecificationFields[$key];
            }

            $normalized['specificationFields']['required'][$key] = array_key_exists($key, $savedSpecificationRequired)
                ? (bool) $savedSpecificationRequired[$key]
                : ($key === 'image'
                    ? (bool) $defaultSpecificationRequired[$key]
                    : ($legacySpecificationsRequired && $normalized['specificationFields'][$key]));

            if (! $normalized['specificationFields'][$key]) {
                $normalized['specificationFields']['required'][$key] = false;
            }
        }

        if (! $normalized['category']) {
            $normalized['designTypes'] = false;
            $normalized['designSpecifications'] = false;
            $normalized['required']['designTypes'] = false;
            $normalized['required']['designSpecifications'] = false;
        }

        if (! $normalized['designTypes']) {
            $normalized['designSpecifications'] = false;
            $normalized['required']['designSpecifications'] = false;
        }

        if (! $normalized['designSpecifications'] || ! $normalized['specificationFields']['image']) {
            $normalized['designTypeImageCards'] = false;
        }

        $normalized['required']['designSpecifications'] = $normalized['designSpecifications']
            && in_array(true, $normalized['specificationFields']['required'], true);

        if ($normalized['required']['designSpecifications']) {
            $normalized['required']['designTypes'] = true;
            $normalized['required']['category'] = true;
        } elseif ($normalized['required']['designTypes']) {
            $normalized['required']['category'] = true;
        }

        return $normalized;
    }
}
