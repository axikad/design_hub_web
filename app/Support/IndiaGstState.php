<?php

namespace App\Support;

final class IndiaGstState
{
    /** @return array<string, string> */
    public static function all(): array
    {
        return [
            '01' => 'Jammu and Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab',
            '04' => 'Chandigarh', '05' => 'Uttarakhand', '06' => 'Haryana', '07' => 'Delhi',
            '08' => 'Rajasthan', '09' => 'Uttar Pradesh', '10' => 'Bihar', '11' => 'Sikkim',
            '12' => 'Arunachal Pradesh', '13' => 'Nagaland', '14' => 'Manipur', '15' => 'Mizoram',
            '16' => 'Tripura', '17' => 'Meghalaya', '18' => 'Assam', '19' => 'West Bengal',
            '20' => 'Jharkhand', '21' => 'Odisha', '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh',
            '24' => 'Gujarat', '26' => 'Dadra and Nagar Haveli and Daman and Diu',
            '27' => 'Maharashtra', '29' => 'Karnataka', '30' => 'Goa', '31' => 'Lakshadweep',
            '32' => 'Kerala', '33' => 'Tamil Nadu', '34' => 'Puducherry',
            '35' => 'Andaman and Nicobar Islands', '36' => 'Telangana',
            '37' => 'Andhra Pradesh', '38' => 'Ladakh',
        ];
    }

    public static function name(?string $code): ?string
    {
        return $code === null ? null : (self::all()[$code] ?? null);
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_map(
            static fn (int|string $code): string => str_pad((string) $code, 2, '0', STR_PAD_LEFT),
            array_keys(self::all()),
        );
    }
}
