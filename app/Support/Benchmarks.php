<?php

namespace App\Support;

/**
 * Benchmarks report (ReportTemplate layout 'benchmarks', e.g. creche and nursery): no scores; areas
 * (ACADEMICS, GENERAL AWARENESS …) each with numbered statements, and the class teacher's remark for each.
 *
 * A list is written as text, one statement per line, with each area on a line starting with "#":
 *   # ACADEMICS
 *   Maths
 *   Language art
 * The class type has one list ("all"); a class can have its own instead (keyed by class id).
 */
class Benchmarks
{
    const OPTIONS = ['Excellent', 'Super', 'Great', 'Wonderful', 'Awesome', 'Amazing', 'Incredible', 'Splendid', 'Fantastic', 'Lovely', 'Bravo',
        'Very Good', 'Good', 'Yes', 'No', 'Needs little support', 'Needs consistent support', 'Under supervision'];

    /** [['area' => 'ACADEMICS', 'items' => [['no' => '001', 'text' => 'Maths'], …]], …] for a class. */
    public static function forClass(array $tpl, ?int $classId): array
    {
        $lists = (array) ($tpl['benchmarks'] ?? []);
        $text = ($classId && trim((string) ($lists[(string) $classId] ?? '')) !== '') ? $lists[(string) $classId] : ($lists['all'] ?? '');

        return self::parse((string) $text);
    }

    public static function parse(string $text): array
    {
        $areas = [];
        $n = 0;
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if ($line[0] === '#') {
                $areas[] = ['area' => trim(ltrim($line, '#')), 'items' => []];
                continue;
            }
            if (!$areas) $areas[] = ['area' => '', 'items' => []];
            $line = preg_replace('/^\d{1,3}[.)]?\s+/', '', $line);   // numbers are added here
            $areas[count($areas) - 1]['items'][] = ['no' => str_pad((string) ++$n, 3, '0', STR_PAD_LEFT), 'text' => $line];
        }

        return array_values(array_filter($areas, function ($a) { return count($a['items']); }));
    }

    /** Statements in order, for saving a pupil's remarks against them. */
    public static function statements(array $tpl, ?int $classId): array
    {
        return collect(self::forClass($tpl, $classId))->flatMap(function ($a) { return array_column($a['items'], 'text'); })->values()->all();
    }

    public static function options(array $tpl): array
    {
        $o = array_values(array_filter(array_map('trim', (array) ($tpl['benchmark_options'] ?? []))));

        return $o ?: self::OPTIONS;
    }

    /** Lists from Desvy's preschool report cards (Creche; Nursery 1; Nursery 2). */
    const CRECHE = "# ACADEMICS\nMaths\nLanguage art\nGeography\nScience\nReligious and moral education\nPractical life\nSensorial\nGrammar\nArt Work\nRhymes and songs\nJolly Phonics\n"
        ."# GENERAL AWARENESS\nAsk questions\nRecognizes own name when called or seen in print\nKnows basic personal information such as age and gender\nIdentify own belongings (shoes, bags, bottles)\nKnows body parts and can point to them\nKnows about family and home\nNames objects in their surroundings\nFollows daily routines such as snack, clean up and nap time\n"
        ."# LANGUAGE SKILLS FOR INTERACTION AND UNDERSTANDING\nParticipate in circle time activities\nGreet others with prompting\nListens to stories and conversation\nRespond appropriately to simple instructions and questions\nExpresses a range of emotions such as happy, sad and angry\nUnderstands familiar words and phrase\nFollows two-step directions\n"
        ."# PERSONAL CARE SKILLS/INDEPENDENT SKILLS\nClean up after a task and able to put away materials after use\nEats independently\nUses the washroom independently\nWashes hands before meals and after outdoor play\nCommunicate physical needs such as I'm hungry, I'm thirsty\n"
        ."# SOCIAL AND EMOTIONAL DEVELOPMENT\nInteract with peers\nLies down independently when tired or feeling sleepy";

    const NURSERY_1 = "# ACADEMICS\nMaths\nEnglish\nGeography\nScience\nReligious and moral education\nPractical life\nSensorial\nRhymes and songs\nFloppy's Phonics\nExpressive Art and Design\n"
        ."# GENERAL AWARENESS\nAsk questions\nRecognizes own name when called or seen in print\nKnows basic personal information such as age and gender\nIdentify own belongings (shoes, bags, bottles)\nKnows body parts and can point to them\nKnows about family and home\nNames objects in their surroundings\nFollows daily routines such as snack, clean up and nap time\n"
        ."# LANGUAGE SKILLS FOR INTERACTION AND UNDERSTANDING\nParticipate in circle time activities\nGreet others with prompting\nListens to stories and conversation\nRespond appropriately to simple instructions and questions\nExpresses a range of emotions such as happy, sad and angry\nUnderstands familiar words and phrase\nFollows two-step directions\n"
        ."# PERSONAL CARE SKILLS/INDEPENDENT SKILLS\nClean up after a task and able to put away materials after use\nEats independently\nUses the washroom independently\nWashes hands before meals and after outdoor play\nCommunicate physical needs such as I'm hungry, I'm thirsty\n"
        ."# SOCIAL AND EMOTIONAL DEVELOPMENT\nInteract with peers\nLies down independently when tired or feeling sleepy";

    const NURSERY_2 = "# ACADEMICS\nMaths\nEnglish\nGeography\nScience\nReligious and moral education\nPractical life\nSensorial\nRhymes and songs\nFloppy's Phonics\nExpressive Art and Design\nHistory\n"
        ."# GENERAL AWARENESS\nAsk questions\nRecognizes own name when called or seen in print\nKnows basic personal information such as age and gender\nIdentify own belongings (shoes, bags, bottles)\nKnows body parts and can point to them\nKnows about family and home\nNames objects in their surroundings\nFollows daily routines such as snack, clean up and nap time\n"
        ."# LANGUAGE SKILLS FOR INTERACTION AND UNDERSTANDING\nParticipate in circle time activities\nGreet others with prompting\nListens to stories and conversation\nRespond appropriately to simple instructions and questions\nExpresses a range of emotions such as happy, sad and angry\nUnderstands familiar words and phrase\nFollows two-step directions\n"
        ."# PERSONAL CARE SKILLS/INDEPENDENT SKILLS\nClean up after a task and able to put away materials after use\nEats independently\nUses the washroom independently\nWashes hands before meals and after outdoor play\nCommunicate physical needs such as I'm hungry, I'm thirsty\n"
        ."# SOCIAL AND EMOTIONAL DEVELOPMENT\nInteract with peers\nLies down independently when tired or feeling sleepy";
}
