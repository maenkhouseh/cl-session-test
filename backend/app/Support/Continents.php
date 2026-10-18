<?php

namespace App\Support;

/** ISO-3166 alpha-2 country → continent code (AF, AN, AS, EU, NA, OC, SA). */
final class Continents
{
    private const BY_CONTINENT = [
        'AF' => 'DZ AO BJ BW BF BI CM CV CF TD KM CG CD CI DJ EG GQ ER SZ ET GA GM GH GN GW KE LS LR LY MG MW ML MR MU MA MZ NA NE NG RW ST SN SC SL SO ZA SS SD TZ TG TN UG ZM ZW RE YT SH EH',
        'AN' => 'AQ BV GS HM TF',
        'AS' => 'AF AM AZ BH BD BT BN KH CN CY GE HK IN ID IR IQ IL JP JO KZ KW KG LA LB MO MY MV MN MM NP KP OM PK PS PH QA SA SG KR LK SY TW TJ TH TL TR TM AE UZ VN YE',
        'EU' => 'AL AD AT BY BE BA BG HR CZ DK EE FO FI FR DE GI GR GG HU IS IE IM IT JE XK LV LI LT LU MT MD MC ME NL MK NO PL PT RO RU SM RS SK SI ES SJ SE CH UA GB VA AX',
        'NA' => 'AI AG AW BS BB BZ BM BQ VG CA KY CR CU CW DM DO SV GL GD GP GT HT HN JM MQ MX MS NI PA PR BL KN LC MF PM VC SX TT TC US VI',
        'OC' => 'AS AU CK FJ PF GU KI MH FM NR NC NZ NU NF MP PW PG PN WS SB TK TO TV UM VU WF',
        'SA' => 'AR BO BR CL CO EC FK GF GY PY PE SR UY VE',
    ];

    private static ?array $lookup = null;

    public static function for(?string $country): ?string
    {
        if (! $country) {
            return null;
        }

        self::$lookup ??= collect(self::BY_CONTINENT)
            ->flatMap(fn (string $codes, string $continent) => collect(explode(' ', $codes))->mapWithKeys(fn ($c) => [$c => $continent]))
            ->all();

        return self::$lookup[strtoupper($country)] ?? null;
    }
}
