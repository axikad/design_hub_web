<?php

namespace App\Support;

final class MachineDesignFormConfig
{
    /** @return array<string, bool|array<string, bool>> */
    public static function defaults(): array
    {
        $fields = [
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

        return [
            ...$fields,
            'required' => [
                ...$fields,
                'sequenceType' => false,
            ],
        ];
    }

    /** @return array<string, bool|array<string, bool>> */
    public static function normalize(?array $config): array
    {
        $normalized = self::defaults();
        $fieldKeys = array_keys(array_filter($normalized, fn ($value) => is_bool($value)));

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

        if ($normalized['required']['designSpecifications']) {
            $normalized['required']['designTypes'] = true;
            $normalized['required']['category'] = true;
        } elseif ($normalized['required']['designTypes']) {
            $normalized['required']['category'] = true;
        }

        return $normalized;
    }
}
