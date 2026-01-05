<?php

namespace App;

/**
 * Helper class to generate Game Jam pages for all years
 */
class GenerateGameJamPages
{
    public static function generateAll(): void
    {
        $years = [2015, 2016, 2017, 2018, 2019, 2020, 2022, 2023];

        foreach ($years as $year) {
            self::generatePage($year);
        }
    }

    protected static function generatePage(int $year): void
    {
        $templatePath = base_path('_pages/2024.blade.php');
        $template = file_get_contents($templatePath);
        if ($template === false) {
            return;
        }
        $template = str_replace('$year = 2024;', "\$year = {$year};", $template);

        $filePath = base_path("_pages/{$year}.blade.php");
        file_put_contents($filePath, $template);
    }
}
