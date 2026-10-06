<?php

namespace Database\Seeders;

use App\Helpers\Qs;
use App\Models\BusRoute;
use App\Models\ClassType;
use App\Models\Exam;
use App\Models\ExamRecord;
use App\Models\FeeDiscount;
use App\Models\FeeOption;
use App\Models\FinanceTransaction;
use App\Models\Grade;
use App\Models\InventoryItem;
use App\Models\Mark;
use App\Models\MyClass;
use App\Models\OptionalFeeCharge;
use App\Models\OptionalFeeReceipt;
use App\Models\ParentDetail;
use App\Models\Payment;
use App\Models\PaymentRecord;
use App\Models\Receipt;
use App\Models\Section;
use App\Models\StockMovement;
use App\Models\StudentDetail;
use App\Models\StudentRecord;
use App\Models\Subject;
use App\Repositories\MarkRepo;
use App\Support\Fees;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Replaces all school data with a realistic demo school (Ghanaian basic school,
 * 14 classes x 10 students, families, fees, services, results, timetables,
 * cash flow). Reference data, super admins and settings are kept.
 *
 *   php artisan db:seed --class=DemoSchoolSeeder
 */
class DemoSchoolSeeder extends Seeder
{
    const PREV = '2025-2026';
    const CURR = '2026-2027';
    const KEEP_USERS = [1, 55]; // super admins

    protected $code;
    protected $pw = [];
    protected $mark;
    protected $types = [];
    protected $classes = [];   // ordered list of class arrays
    protected $students = [];  // all seeded students (incl. graduates)
    protected $options = [];
    protected $routes = [];
    protected $discounts = [];
    protected $teachers = [];
    protected $used = [];      // unique phones/emails/usernames

    /* ------------------------------------------------------------ */

    public function run()
    {
        mt_srand(20261001);
        $this->mark = new MarkRepo();
        $this->code = Qs::getAppCode();
        foreach (['cj', 'parent', 'student', 'teacher', 'admin', 'accountant'] as $p) {
            $this->pw[$p] = Hash::make($p);
        }

        $this->wipe();
        $this->settings();
        $this->structure();
        $this->financeConfig();
        $this->families();
        $this->schoolFees();
        $this->optionalServices();
        $this->shop();
        $this->exams();
        $this->timetables();
        $this->pins();
        $this->promotions();
        $this->cashflow();
        // Added last so the rest of the demo data stays the same as before.
        $this->staff('Akosua Owusu', 'Female', 'academic_admin', 'academic', '2023-09-01', 'cj');
        $this->command->call('demo:payment-methods', ['--force' => true]);

        $this->command->info('Demo school seeded: '.StudentRecord::where('grad', 0)->count().' students, '
            .User::where('user_type', 'parent')->count().' parents, '.User::where('user_type', 'teacher')->count().' teachers.');
    }

    /* ------------------------------------------------------------ */

    protected function wipe()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ([
            'book_requests', 'books', 'notification_logs', 'optional_fee_receipts', 'optional_fee_charges', 'receipts', 'stock_movements', 'inventory_items',
            'payment_records', 'payment_items', 'payments', 'fee_options', 'bus_routes', 'fee_discounts', 'finance_transactions',
            'marks', 'exam_records', 'exams', 'pins', 'promotions', 'time_table_records', 'time_slots', 'time_tables',
            'student_details', 'parent_details', 'student_records', 'staff_records', 'subjects', 'sections', 'my_classes',
            'class_types', 'dorms', 'grades',
        ] as $t) {
            DB::table($t)->truncate();
        }
        DB::table('users')->whereNotIn('id', self::KEEP_USERS)->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    protected function settings()
    {
        $set = function ($type, $value) {
            DB::table('settings')->updateOrInsert(['type' => $type], ['description' => $value, 'updated_at' => now()]);
        };
        $set('current_session', self::CURR);
        // Next term fees by class type code (shown on report cards).
        DB::table('settings')->where('type', 'like', 'next_term_fees_%')->delete();
        foreach (['c' => 5150, 'n' => 5700, 'kg' => 6250, 'p' => 7300, 'j' => 8650] as $code => $fee) {
            $set('next_term_fees_'.$code, $fee);
        }
    }

    /* ------------------------------------------------------------ */

    protected function structure()
    {
        $now = now();
        $types = [
            ['Creche', 'C'], ['Nursery', 'N'], ['Kindergarten', 'KG'], ['Primary', 'P'], ['Junior High', 'J'],
        ];
        foreach ($types as $i => [$name, $code]) {
            $this->types[$code] = DB::table('class_types')->insertGetId(['name' => $name, 'code' => $code, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Grades: general A–F, and the BECE 1–9 scale for Junior High.
        foreach ([['A', 80, 100, 'Excellent'], ['B', 70, 79, 'Very Good'], ['C', 60, 69, 'Good'], ['D', 50, 59, 'Credit'], ['E', 40, 49, 'Pass'], ['F', 0, 39, 'Fail']] as [$n, $f, $t, $r]) {
            Grade::create(['name' => $n, 'class_type_id' => null, 'mark_from' => $f, 'mark_to' => $t, 'remark' => $r]);
        }
        foreach ([['1', 80, 100, 'Highest'], ['2', 70, 79, 'Higher'], ['3', 65, 69, 'High'], ['4', 60, 64, 'High Average'], ['5', 55, 59, 'Average'],
                  ['6', 50, 54, 'Low Average'], ['7', 45, 49, 'Low'], ['8', 40, 44, 'Lower'], ['9', 0, 39, 'Lowest']] as [$n, $f, $t, $r]) {
            Grade::create(['name' => $n, 'class_type_id' => $this->types['J'], 'mark_from' => $f, 'mark_to' => $t, 'remark' => $r]);
        }

        foreach ([['Boys\' Hostel', 'Junior High boarding for boys'], ['Girls\' Hostel', 'Junior High boarding for girls']] as [$n, $d]) {
            DB::table('dorms')->insert(['name' => $n, 'description' => $d, 'created_at' => $now, 'updated_at' => $now]);
        }

        // [class, type, age, sections => [name => capacity]]
        $plan = [
            ['Creche', 'C', 2, ['Gold' => 12]],
            ['Nursery 1', 'N', 3, ['Gold' => 15]],
            ['Nursery 2', 'N', 4, ['Gold' => 15]],
            ['KG 1', 'KG', 5, ['Gold' => 20]],
            ['KG 2', 'KG', 6, ['Gold' => 20]],
            ['Class 1', 'P', 7, ['Gold' => 25]],
            ['Class 2', 'P', 8, ['Gold' => 25]],
            ['Class 3', 'P', 9, ['Gold' => 10]],
            ['Class 4', 'P', 10, ['Gold' => 25]],
            ['Class 5', 'P', 11, ['Gold' => 25]],
            ['Class 6', 'P', 12, ['Gold' => 6, 'Diamond' => 8]],
            ['JHS 1', 'J', 13, ['Gold' => 30]],
            ['JHS 2', 'J', 14, ['Gold' => 30]],
            ['JHS 3', 'J', 15, ['Gold' => 6, 'Diamond' => 6]],
        ];

        // Staff
        $this->staff('Gifty Asante', 'Female', 'admin', 'admin', '2022-06-01', 'cj');
        $this->staff('Samuel Ofori-Atta', 'Male', 'admin', null, '2023-01-09');
        $this->staff('Comfort Adjei', 'Female', 'accountant', 'accountant', '2022-06-15', 'cj');
        $this->staff('Isaac Boakye', 'Male', 'accountant', null, '2024-08-12');

        $classTeacherNames = [
            ['Mercy Asamoah', 'Female'], ['Abigail Tetteh', 'Female'], ['Priscilla Owusu', 'Female'], ['Esther Ansah', 'Female'],
            ['Deborah Quaye', 'Female'], ['Josephine Lamptey', 'Female'], ['Patience Nyarko', 'Female'], ['Richard Amoah', 'Male'],
            ['Grace Bediako', 'Female'], ['Eric Danso', 'Male'], ['Kwabena Sarpong', 'Male'], ['Adelaide Mensah', 'Female'],
            ['Joseph Antwi', 'Male'], ['Stephen Agyeman', 'Male'], ['Rita Ankrah', 'Female'], ['Daniel Wiredu', 'Male'],
        ];
        $subjectTeachers = [
            'Mathematics' => ['Michael Osei-Bonsu', 'Male'],
            'English Language' => ['Ewurabena Hammond', 'Female'],
            'Integrated Science' => ['Edem Kpodo', 'Male'],
            'Social Studies' => ['Fuseini Abubakar', 'Male'],
            'Computing' => ['Selorm Dzidzornu', 'Male'],
            'French' => ['Akosua Frimpong', 'Female'],
        ];
        $subjectTeacherIds = [];
        foreach ($subjectTeachers as $subject => [$n, $g]) {
            $subjectTeacherIds[$subject] = $this->staff($n, $g, 'teacher', null, $this->randDate('2022-06-01', '2024-08-20'));
        }

        $subjects = [
            'C' => ['Language & Literacy', 'Numeracy', 'Creative Activities', 'Environmental Studies', 'Rhymes & Songs'],
            'N' => ['Language & Literacy', 'Numeracy', 'Creative Arts', 'Our World Our People', 'Phonics', 'Writing'],
            'KG' => ['Language & Literacy', 'Numeracy', 'Creative Arts', 'Our World Our People', 'Phonics', 'Writing'],
            'P' => ['English Language', 'Mathematics', 'Science', 'Our World Our People', 'Religious & Moral Education', 'Creative Arts', 'Ghanaian Language (Twi)', 'French', 'Computing'],
            'J' => ['English Language', 'Mathematics', 'Integrated Science', 'Social Studies', 'Computing', 'Career Technology', 'Creative Arts & Design', 'Religious & Moral Education', 'French', 'Ghanaian Language (Twi)'],
        ];

        $t = 0;
        foreach ($plan as $i => [$name, $type, $age, $sections]) {
            $class = MyClass::create(['name' => $name, 'class_type_id' => $this->types[$type]]);
            $secs = [];
            $leadTeacher = null;
            foreach ($sections as $sName => $cap) {
                [$tn, $tg] = $classTeacherNames[$t++];
                $teacherId = $this->staff($tn, $tg, 'teacher', ($name === 'Class 6' && $sName === 'Gold') ? 'teacher' : null, $this->randDate('2022-06-01', '2025-08-20'), ($name === 'Class 6' && $sName === 'Gold') ? 'cj' : null);
                $leadTeacher = $leadTeacher ?: $teacherId;
                $sec = Section::create(['name' => $sName, 'my_class_id' => $class->id, 'capacity' => $cap, 'active' => $sName === 'Gold' ? 1 : 0, 'teacher_id' => $teacherId]);
                $secs[] = ['id' => $sec->id, 'name' => $sName, 'capacity' => $cap];
            }
            $subs = [];
            foreach ($subjects[$type] as $s) {
                $teacher = ($type === 'J' && isset($subjectTeacherIds[$s])) ? $subjectTeacherIds[$s] : $leadTeacher;
                $subs[] = Subject::create(['name' => $s, 'slug' => $this->abbr($s), 'my_class_id' => $class->id, 'teacher_id' => $teacher])->id;
            }
            $this->classes[$i] = ['id' => $class->id, 'name' => $name, 'type' => $type, 'age' => $age, 'sections' => $secs, 'subjects' => $subs, 'index' => $i];
        }
    }

    /* ------------------------------------------------------------ */

    protected function financeConfig()
    {
        foreach ([['feeding', 'Breakfast', 2000], ['feeding', 'Lunch & Fruits', 4000],
                  ['extracurricular', 'Dance', 600], ['extracurricular', 'Music', 800], ['extracurricular', 'Ballet', 1200]] as $n => [$g, $name, $amt]) {
            $this->options[$name] = FeeOption::create(['group' => $g, 'name' => $name, 'amount' => $amt, 'active' => true, 'sort' => $n])->id;
        }
        foreach ([['Adenta', 4000, 2000], ['East Legon', 5000, 2500], ['Santeo', 3200, 1600], ['Spintex', 4500, 2250], ['Madina', 3800, 1900], ['Lashibi', 3000, 1500]] as [$n, $b, $o]) {
            $this->routes[$n] = BusRoute::create(['name' => $n, 'amount_both' => $b, 'amount_one_way' => $o, 'active' => true])->id;
        }
        foreach ([["Director's special package", 100], ['Academic scholarship (50% of tuition)', 50], ['Sibling discount (20% of tuition)', 20]] as [$n, $p]) {
            $this->discounts[$p] = FeeDiscount::create(['name' => $n, 'percent' => $p, 'active' => true])->id;
        }
    }

    /* ------------------------------------------------------------ */
    /* Five school years: 2022-2023 (opening) to 2026-2027 (current) */
    /* ------------------------------------------------------------ */

    const YEARS = [2022, 2023, 2024, 2025, 2026];
    const TARGET = [2022 => 6, 2023 => 7, 2024 => 8, 2025 => 9, 2026 => 10]; // students per class
    const PRICE = [2022 => 0.74, 2023 => 0.80, 2024 => 0.86, 2025 => 0.93, 2026 => 1.0];  // fees grow with inflation
    const LAST = 13; // JHS 3

    protected $families = [];
    protected $demoFamily = null;

    protected static function sess(int $y): string
    {
        return $y.'-'.($y + 1);
    }

    protected function idxAt(array $s, int $y): ?int
    {
        if ($y < $s['entry']) return null;
        $i = $s['entryIdx'] + ($y - $s['entry']);
        return $i <= self::LAST ? $i : null;
    }

    /** Simulate admissions year by year, then write the people to the database. */
    protected function families()
    {
        $sim = [];
        foreach (self::YEARS as $y) {
            foreach ($this->classes as $idx => $c) {
                $have = count(array_filter($sim, function ($s) use ($idx, $y) { return $this->idxAt($s, $y) === $idx; }));
                for ($n = $have; $n < self::TARGET[$y]; $n++) {
                    $sim[] = $this->newStudent($sim, $y, $idx);
                }
            }
        }

        // Current-year sections: Class 6 and JHS 3 are split into Gold and Diamond.
        foreach ($this->classes as $idx => $c) {
            $current = array_keys(array_filter($sim, function ($s) use ($idx) { return $this->idxAt($s, 2026) === $idx; }));
            $split = $c['name'] === 'Class 6' ? 6 : 5;
            foreach ($current as $k => $simIdx) {
                $sim[$simIdx]['section'] = count($c['sections']) > 1 && $k >= $split ? $c['sections'][1] : $c['sections'][0];
            }
        }

        // Write families and students.
        foreach ($this->families as $fid => &$fam) {
            $fam['parent'] = $this->makeParent($fid === $this->demoFamily, $fam['surname'], $fam['since']);
        }
        unset($fam);
        $studentLogin = false;
        foreach ($sim as $s) {
            $fam = $this->families[$s['family']];
            $isCurrent = $this->idxAt($s, 2026) !== null;
            $login = !$studentLogin && $isCurrent && $this->classes[$this->idxAt($s, 2026)]['name'] === 'JHS 2';
            $studentLogin = $studentLogin || $login;
            $this->makeStudent($s, $fam['parent'], $login);
        }
    }

    protected function newStudent(array $sim, int $y, int $idx): array
    {
        // A third of new pupils join an existing family (a younger brother or sister).
        $fid = null;
        if ($this->demoFamily !== null && $y === 2024 && $idx === 2 && count($this->families[$this->demoFamily]['kids']) < 2) {
            $fid = $this->demoFamily;
        } elseif ($this->families && mt_rand(1, 100) <= 30) {
            $candidates = array_keys(array_filter($this->families, function ($f) use ($sim, $y, $idx) {
                if (count($f['kids']) >= 3) return false;
                $active = false;
                foreach ($f['kids'] as $k) {
                    $i = $this->idxAt($sim[$k], $y);
                    if ($i === $idx) return false;
                    if ($i !== null) $active = true;
                }
                return $active;
            }));
            if ($candidates) $fid = $this->pick($candidates);
        }
        if ($fid === null) {
            $fid = count($this->families);
            $this->families[$fid] = ['surname' => $this->pick(self::SURNAMES), 'kids' => [], 'since' => $y];
            if ($this->demoFamily === null && $y === 2022 && $idx >= 4 && $idx <= 8) {
                $this->demoFamily = $fid;
                $this->families[$fid]['surname'] = 'Appiah';
            }
        }
        $this->families[$fid]['kids'][] = count($sim);

        $gender = mt_rand(0, 1) ? 'Male' : 'Female';
        return [
            'entry' => $y, 'entryIdx' => $idx, 'family' => $fid, 'gender' => $gender,
            'first' => $this->pick($gender === 'Male' ? self::MALE : self::FEMALE),
            'ability' => max(28, min(95, (int) round($this->gauss(63, 13)))),
            'section' => null,
        ];
    }

    protected function makeParent(bool $demo, string $surname, int $since): array
    {
        $father = $demo ? 'Emmanuel' : $this->pick(self::MALE);
        $mother = $this->pick(self::FEMALE);
        $structure = mt_rand(1, 100);
        $hasFather = $demo || $structure > 12;
        $hasMother = $structure > 4 || !$hasFather;
        $guardian = mt_rand(1, 100) <= 15;
        $primary = $hasFather && ($demo || mt_rand(1, 100) <= 70) ? 'father' : ($hasMother ? 'mother' : 'father');
        $motherSurname = mt_rand(1, 100) <= 70 ? $surname : $this->pick(self::SURNAMES);
        $area = $this->pick(self::AREAS);
        $address = 'Hse No. '.mt_rand(2, 48).', '.$area;

        $fatherName = $hasFather ? $father.' '.$surname : null;
        $motherName = $hasMother ? $mother.' '.$motherSurname : null;
        $fatherPhone = $hasFather ? ($demo ? '+233543150780' : $this->phone()) : null;
        $motherPhone = $hasMother ? $this->phone() : null;
        $fatherEmail = $hasFather ? ($demo ? 'emmanuel.appiah.dev@gmail.com' : (mt_rand(1, 100) <= 80 ? $this->email($father, $surname) : null)) : null;
        $motherEmail = $hasMother && mt_rand(1, 100) <= 70 ? $this->email($mother, $motherSurname) : null;

        $name = $primary === 'father' ? $fatherName : $motherName;
        $email = $primary === 'father' ? ($fatherEmail ?: $motherEmail) : ($motherEmail ?: $fatherEmail);
        $phone = $primary === 'father' ? $fatherPhone : $motherPhone;
        $joined = Carbon::create($since, 8, mt_rand(10, 31))->toDateString();

        $user = $this->user([
            'name' => $name, 'email' => $email, 'phone' => $phone, 'phone2' => $primary === 'father' ? $motherPhone : $fatherPhone,
            'gender' => $primary === 'father' ? 'Male' : 'Female', 'address' => $address, 'user_type' => 'parent',
            'username' => $demo ? 'parent' : $this->username('PARENT', $joined), 'password' => $demo ? $this->pw['cj'] : $this->pw['parent'],
            'created_at' => $joined,
        ]);

        $postal = 'P.O. Box '.$this->pick(['AD', 'TN', 'MD', 'CT', 'KN', 'DTD']).' '.mt_rand(100, 9999).', '.$this->pick(['Accra', 'Tema', 'Madina', 'Adenta']);
        $d = ['user_id' => $user->id];
        foreach (['father' => [$hasFather, $fatherName, $fatherPhone, $fatherEmail], 'mother' => [$hasMother, $motherName, $motherPhone, $motherEmail]] as $p => [$has, $n, $ph, $em]) {
            if (!$has) continue;
            $job = $this->pick(self::JOBS);
            $d += [
                "{$p}_name" => $n, "{$p}_phone" => $ph, "{$p}_ghana_card" => $this->ghanaCard(), "{$p}_occupation" => $job[0],
                "{$p}_workplace" => $job[1], "{$p}_email" => $em, "{$p}_residential_address" => $address, "{$p}_postal_address" => $postal,
            ];
        }
        if ($guardian) {
            $d += [
                'guardian_name' => $this->pick(array_merge(self::FEMALE, self::FEMALE, self::MALE)).' '.$this->pick(self::SURNAMES), 'guardian_phone' => $this->phone(),
                'guardian_ghana_card' => $this->ghanaCard(), 'guardian_relationship' => $this->pick(['Aunt', 'Uncle', 'Grandmother', 'Nanny', 'Elder sister']),
                'guardian_address' => 'Hse No. '.mt_rand(2, 48).', '.$this->pick(self::AREAS),
            ];
        }
        ParentDetail::create($d);

        return ['user' => $user, 'surname' => $surname, 'address' => $address];
    }

    protected function makeStudent(array $s, array $parent, bool $login)
    {
        $entryClass = $this->classes[$s['entryIdx']];
        $currentIdx = $this->idxAt($s, 2026);
        $gradYear = $s['entry'] + (self::LAST - $s['entryIdx']); // year they sat JHS 3
        $graduated = $currentIdx === null;
        $c = $graduated ? $this->classes[self::LAST] : $this->classes[$currentIdx];
        $section = $graduated ? $c['sections'][0] : $s['section'];

        $first = $s['first'];
        $middle = mt_rand(1, 100) <= 55 ? $this->pick($s['gender'] === 'Male' ? self::DAY_MALE : self::DAY_FEMALE) : null;
        if ($middle === $first) $middle = null;
        $last = $parent['surname'];
        $name = trim($first.' '.($middle ? $middle.' ' : '').$last);

        $dob = Carbon::create($s['entry'] - $entryClass['age'], mt_rand(1, 8), mt_rand(1, 28))->toDateString();
        $admission = Carbon::create($s['entry'], $s['entry'] === 2022 ? 9 : 8, $s['entry'] === 2022 ? mt_rand(1, 12) : mt_rand(17, 31))->addDays(mt_rand(0, 10));
        $year = (string) $s['entry'];
        $ct = $entryClass['type'];
        do {
            $admNo = strtoupper($this->code.'/'.$ct.'/'.$year.'/'.str_pad((string) mt_rand(100, 9999), 4, '0', STR_PAD_LEFT));
        } while (isset($this->used[$admNo]));
        $this->used[$admNo] = true;

        $user = $this->user([
            'name' => $name, 'gender' => $s['gender'], 'dob' => $dob, 'user_type' => 'student', 'address' => $parent['address'],
            'username' => $login ? 'student' : $admNo, 'password' => $login ? $this->pw['cj'] : $this->pw['student'],
            'bg_id' => mt_rand(1, 100) <= 75 ? $this->pick([1, 2, 2, 2, 3, 3, 5, 5, 7]) : null,
            'state_id' => 7, 'lga_id' => $this->pick([775, 776, 777, 778, 779, 782, 783, 784]), 'nal_id' => 1,
            'phone' => $c['type'] === 'J' && mt_rand(1, 100) <= 30 ? $this->phone() : null,
            'created_at' => $admission->copy()->setTime(10, mt_rand(0, 59)),
        ]);

        // Discounts: a few staff children (100%), JHS scholars (50%), younger siblings in big families (20%).
        $kids = $this->families[$s['family']]['kids'];
        $discount = null;
        $r = mt_rand(1, 100);
        if (count($kids) >= 3 && array_search(count($this->students), $kids, true) !== 0 && $r <= 70) $discount = $this->discounts[20];
        elseif (!$graduated && $r <= 2) $discount = $this->discounts[100];
        elseif ($c['type'] === 'J' && $r <= 8) $discount = $this->discounts[50];

        $boarder = $c['type'] === 'J' && !$graduated && mt_rand(1, 100) <= 20;
        $record = StudentRecord::create([
            'user_id' => $user->id, 'my_class_id' => $c['id'], 'section_id' => $section['id'], 'my_parent_id' => $parent['user']->id,
            'adm_no' => $admNo, 'year_admitted' => $year, 'admission_date' => $admission->toDateString(), 'admitted_session' => self::sess($s['entry']),
            'fee_discount_id' => $discount, 'session' => $graduated ? self::sess($gradYear) : self::CURR,
            'grad' => $graduated ? 1 : 0, 'grad_date' => $graduated ? self::sess($gradYear) : null,
            'house' => $this->pick(['Aggrey', 'Guggisberg', 'Nkrumah', 'Yaa Asantewaa']),
            'dorm_id' => $boarder ? ($s['gender'] === 'Male' ? 1 : 2) : null,
            'dorm_room_no' => $boarder ? ($s['gender'] === 'Male' ? 'B' : 'G').mt_rand(1, 6).'0'.mt_rand(1, 4) : null,
        ]);
        DB::table('student_records')->where('id', $record->id)->update(['created_at' => $admission, 'updated_at' => $admission]);

        $age = $entryClass['age'] + (2026 - $s['entry']);
        $insurer = mt_rand(1, 100) <= 70 ? ['National Health Insurance Scheme', 'Formal sector dependant', 'Basic NHIS benefit package'] : $this->pick(self::INSURERS);
        $doctor = mt_rand(1, 100) <= 60 ? $this->pick(self::DOCTORS) : null;
        $prevSchool = $s['entryIdx'] >= 3 && $s['entry'] > 2022 || ($s['entry'] === 2022 && $s['entryIdx'] >= 1) ? $this->pick(self::PREV_SCHOOLS) : null;
        StudentDetail::create([
            'user_id' => $user->id, 'first_name' => $first, 'middle_name' => $middle, 'last_name' => $last,
            'height' => (85 + (min($age, 16) - 2) * 6 + mt_rand(-4, 6)).' cm', 'weight' => round(12 + (min($age, 16) - 2) * 2.8 + mt_rand(-2, 3), 1).' kg',
            'birth_place' => $this->pick(['Accra', 'Accra', 'Accra', 'Tema', 'Kumasi', 'Cape Coast', 'Takoradi', 'Koforidua', 'Ho', 'Tamale', 'London', 'Lagos']),
            'religion' => $this->pick(['Christian', 'Christian', 'Christian', 'Christian', 'Christian', 'Muslim', 'Muslim']),
            'past_school' => $prevSchool[0] ?? null, 'past_school_address' => $prevSchool[1] ?? null,
            'past_qualification' => $prevSchool ? 'Completed '.($this->classes[$s['entryIdx'] - 1]['name'] ?? 'Nursery') : null,
            'allergies' => mt_rand(1, 100) <= 18 ? $this->pick(['Peanuts', 'Seafood (shellfish)', 'Penicillin', 'Dust and pollen', 'Eggs', 'Cow milk (lactose)']) : null,
            'medical_conditions' => mt_rand(1, 100) <= 10 ? $this->pick(['Asthma; has an inhaler in school bag', 'Sickle cell trait (AS)', 'G6PD deficiency', 'Eczema']) : null,
            'insurance_card_no' => (is_array($insurer) ? 'NHIS-' : '').mt_rand(10000000, 99999999),
            'insurance_provider' => $insurer[0], 'insurance_category' => $insurer[1], 'insurance_coverage_limit' => $insurer[2],
            'insurance_start_date' => Carbon::create(2026, mt_rand(1, 8), mt_rand(1, 28))->toDateString(),
            'insurance_valid_until' => Carbon::create(2027, mt_rand(1, 12), mt_rand(1, 28))->toDateString(),
            'doctor_name' => $doctor[0] ?? null, 'doctor_hospital' => $doctor[1] ?? null, 'doctor_contact' => $doctor ? $this->phone() : null,
            'terms_accepted_at' => $admission,
        ]);

        // A stable plan for optional services, adjusted a little each year.
        $young = $s['entryIdx'] <= 4;
        $this->students[] = $s + [
            'user_id' => $user->id, 'record' => $record, 'section' => $section, 'gradYear' => $gradYear,
            'pct' => $discount ? (int) FeeDiscount::find($discount)->percent : 0,
            'payer' => $this->pick(array_merge(array_fill(0, 15, 'good'), array_fill(0, 4, 'slow'), ['late'])),
            'boarder' => $boarder,
            'feeding' => mt_rand(1, 100) <= ($young ? 75 : 55) ? $this->pick([['Breakfast'], ['Lunch & Fruits'], ['Lunch & Fruits'], ['Breakfast', 'Lunch & Fruits'], ['Breakfast', 'Lunch & Fruits'], ['Breakfast', 'Lunch & Fruits'], ['Lunch & Fruits'], ['Breakfast']]) : [],
            'bus' => !$boarder && mt_rand(1, 100) <= 42 ? [$this->pick(array_keys($this->routes)), $this->pick(['both', 'both', 'both', 'in', 'out'])] : null,
            'acts' => mt_rand(1, 100) <= 33 ? ($s['gender'] === 'Male' ? $this->pick([['Music'], ['Dance'], ['Music', 'Dance']]) : $this->pick([['Dance'], ['Music'], ['Ballet'], ['Music', 'Dance'], ['Ballet', 'Music']])) : [],
            'books' => mt_rand(1, 100) <= 55,
        ];
    }

    /* ------------------------------------------------------------ */

    const FEE_TABLE = [
        'C' => [3500, 250, 400, 150, 300, 450, 50, 200, 550],
        'N' => [4000, 250, 400, 150, 300, 450, 50, 200, 600],
        'KG' => [4500, 250, 450, 150, 300, 500, 50, 200, 650],
        'P' => [5200, 300, 500, 200, 300, 550, 50, 250, 800],
        'J' => [6300, 300, 550, 200, 300, 600, 50, 250, 950],
    ];

    protected function termWindows(int $y): array
    {
        if ($y === 2026) return [1 => ['2026-08-24', '2026-09-30']];
        return [1 => [$y.'-08-25', $y.'-11-30'], 2 => [($y + 1).'-01-05', ($y + 1).'-03-31'], 3 => [($y + 1).'-04-27', ($y + 1).'-07-24']];
    }

    protected function schoolFees()
    {
        $r = function ($n) { return (int) (round($n / 10) * 10); };
        foreach (self::YEARS as $y) {
            $f = self::PRICE[$y];
            $year = self::sess($y);
            $payments = [];
            foreach ($this->classes as $idx => $c) {
                $t = self::FEE_TABLE[$c['type']];
                $base = [['Tuition', $t[0]], ['Medicals', $t[1]], ['Maintenance', $t[2]], ['P.T.A', $t[3]], ['Toiletries', $t[4]]];
                $mk = function ($title, $cat, $items, $desc, $term) use ($c, $year, $f, $r, $y) {
                    $p = Payment::create(['title' => $title, 'amount' => 0, 'my_class_id' => $c['id'], 'student_category' => $cat, 'year' => $year, 'term' => $term,
                        'ref_no' => $y.'/'.mt_rand(100000, 999999), 'method' => 'cash', 'description' => $desc]);
                    Fees::syncItems($p, array_map(function ($i) use ($f, $r) { return ['name' => $i[0], 'amount' => $r($i[1] * $f)]; }, $items));
                    DB::table('payments')->where('id', $p->id)->update(['created_at' => $y.'-08-10 09:00:00']);
                    return $p->fresh('items');
                };
                $payments[$idx][1]['new'] = $mk('First Term Fees (New Students)', 'new', array_merge($base, [['Uniform', $t[5]], ['ID card', $t[6]], ['Admission form', $t[7]]]), 'Includes uniform, ID card and admission form', 1);
                $payments[$idx][1]['old'] = $mk('First Term Fees (Continuing Students)', 'old', array_merge($base, [['Books', $t[8]]]), 'Includes textbooks for the term', 1);
                if ($y < 2026) {
                    $payments[$idx][2]['all'] = $mk('Second Term Fees', 'all', $base, 'Termly school fees', 2);
                    $payments[$idx][3]['all'] = $mk('Third Term Fees', 'all', $base, 'Termly school fees', 3);
                }
            }

            foreach ($this->students as $s) {
                $idx = $this->idxAt($s, $y);
                if ($idx === null) continue;
                $isNew = $s['entry'] === $y;
                foreach ($this->termWindows($y) as $term => $window) {
                    $p = $term === 1 ? $payments[$idx][1][$isNew ? 'new' : 'old'] : $payments[$idx][$term]['all'];
                    $discount = (int) round(Fees::tuitionBase($p) * $s['pct'] / 100);
                    $owed = max($p->amount - $discount, 0);
                    $pr = PaymentRecord::create(['student_id' => $s['user_id'], 'payment_id' => $p->id, 'year' => $year, 'discount' => $discount,
                        'ref_no' => (string) mt_rand(100000, 99999999), 'amt_paid' => 0, 'balance' => $owed, 'paid' => 0]);

                    if ($y === 2026) {
                        // Current term is still being collected.
                        $roll = mt_rand(1, 100);
                        $target = $roll <= 42 ? $owed : ($roll <= 78 ? $r($owed * $this->pick([0.3, 0.4, 0.5, 0.6, 0.75])) : 0);
                        if ($isNew && $target === 0 && mt_rand(1, 2) === 1) $target = $r($owed * 0.5);
                    } else {
                        $target = $owed;
                        if ($s['payer'] === 'slow' && $term === 3) $target = $r($owed * $this->pick([0.5, 0.6, 0.8]));
                        if ($s['payer'] === 'late' && $term >= 2) $target = $r($owed * $this->pick([0, 0.3, 0.5]));
                    }
                    $this->pay($pr, $owed, $target, $window, $year);
                }
            }
        }
    }

    protected function pay(PaymentRecord $pr, int $owed, int $target, array $window, string $year)
    {
        if ($target <= 0 || $owed <= 0) {
            $pr->update(['paid' => $owed <= 0 ? 1 : 0, 'balance' => $owed]);
            return;
        }
        $parts = $target === $owed ? $this->pick([1, 1, 1, 2, 2, 3]) : $this->pick([1, 1, 2]);
        $amounts = $this->split($target, $parts);
        $dates = $this->sortedDates($window[0], $window[1], count($amounts));
        $paid = 0;
        foreach ($amounts as $i => $a) {
            $paid += $a;
            $rc = Receipt::create(['pr_id' => $pr->id, 'amt_paid' => $a, 'balance' => $owed - $paid, 'year' => $year]);
            DB::table('receipts')->where('id', $rc->id)->update(['created_at' => $dates[$i], 'updated_at' => $dates[$i]]);
        }
        $pr->update(['amt_paid' => $paid, 'balance' => max($owed - $paid, 0), 'paid' => $paid >= $owed ? 1 : 0]);
    }

    /* ------------------------------------------------------------ */

    protected function optionalServices()
    {
        $prices = FeeOption::pluck('amount', 'name');
        $groups = FeeOption::pluck('group', 'name');
        $routes = BusRoute::all()->keyBy('name');
        foreach (self::YEARS as $y) {
            $f = self::PRICE[$y];
            $year = self::sess($y);
            $window = $y === 2026 ? ['2026-08-28', '2026-09-30'] : [$y.'-08-28', ($y + 1).'-06-30'];
            foreach ($this->students as $s) {
                $idx = $this->idxAt($s, $y);
                if ($idx === null) continue;
                $type = $this->classes[$idx]['type'];
                $lines = [];
                foreach ($s['feeding'] as $n) $lines[] = ['feeding', $n, $this->options[$n], null, null, $prices[$n]];
                if ($s['bus'] && !($s['boarder'] && $y === 2026)) {
                    [$route, $dir] = $s['bus'];
                    $rt = $routes[$route];
                    $lines[] = ['bus', 'Bus · '.$route.' ('.BusRoute::DIRECTIONS[$dir].')', null, $rt->id, $dir, $rt->priceFor($dir)];
                }
                if ($type !== 'C') foreach ($s['acts'] as $n) $lines[] = ['extracurricular', $n, $this->options[$n], null, null, $prices[$n]];

                $style = mt_rand(1, 100);
                foreach ($lines as [$group, $label, $optionId, $routeId, $dir, $price]) {
                    $amount = (int) (round($price * $f / 10) * 10);
                    $c = OptionalFeeCharge::create(['student_id' => $s['user_id'], 'year' => $year, 'group' => $group, 'label' => $label,
                        'fee_option_id' => $optionId, 'bus_route_id' => $routeId, 'bus_direction' => $dir, 'amount' => $amount, 'amt_paid' => 0]);
                    DB::table('optional_fee_charges')->where('id', $c->id)->update(['created_at' => $y.'-08-20 10:00:00']);
                    if ($y === 2026) {
                        $target = $style <= 35 ? $amount : ($style <= 60 ? (mt_rand(0, 1) ? $amount : 0) : ($style <= 85 ? (int) round($amount * $this->pick([0.25, 0.5, 0.5, 0.75]) / 10) * 10 : 0));
                    } else {
                        $target = $style <= 88 ? $amount : (int) round($amount * $this->pick([0.5, 0.75]) / 10) * 10;
                    }
                    if ($target <= 0) continue;
                    $parts = $target === $amount ? $this->pick([1, 1, 2, 3]) : 1;
                    $paid = 0;
                    $dates = $this->sortedDates($window[0], $window[1], $parts);
                    foreach ($this->split($target, $parts) as $i => $a) {
                        $paid += $a;
                        $rc = OptionalFeeReceipt::create(['charge_id' => $c->id, 'amt_paid' => $a, 'balance' => $amount - $paid, 'year' => $year]);
                        DB::table('optional_fee_receipts')->where('id', $rc->id)->update(['created_at' => $dates[$i] ?? $dates[0], 'updated_at' => $dates[$i] ?? $dates[0]]);
                    }
                    $c->update(['amt_paid' => $paid]);
                }
            }
        }
    }

    /* ------------------------------------------------------------ */

    /* ------------------------------------------------------------ */
    /* School shop: uniforms, socks, books and stationery            */
    /* ------------------------------------------------------------ */

    const SHOP = [
        // name, category, price (2026), reorder level
        ['School uniform', 'Uniform', 280, 15],
        ['Sweater', 'Uniform', 150, 10],
        ['Socks (pair)', 'Uniform', 25, 30],
        ['PE kit', 'Uniform', 180, 10],
        ['Books & stationery pack', 'Books & stationery', 850, 10],
        ['Exercise books (dozen)', 'Books & stationery', 60, 20],
        ['Drawing book', 'Books & stationery', 20, 15],
        ['School bag', 'Accessories', 220, 5],
    ];

    protected function shop()
    {
        $items = [];
        foreach (self::SHOP as [$name, $cat, $price, $reorder]) {
            $items[$name] = InventoryItem::create(['name' => $name, 'category' => $cat, 'price' => $price, 'stock' => 0, 'reorder_level' => $reorder, 'active' => true]);
        }
        $by = User::where('username', 'accountant')->value('id');
        $booksBought = []; // family => [class idx => student id] who bought the pack

        foreach (self::YEARS as $y) {
            $f = self::PRICE[$y];
            $year = self::sess($y);
            $window = $y === 2026 ? ['2026-08-24', '2026-09-30'] : [$y.'-08-24', ($y + 1).'-06-30'];

            // What each student buys this year.
            $sales = [];
            foreach ($this->students as $s) {
                $idx = $this->idxAt($s, $y);
                if ($idx === null) continue;
                $type = $this->classes[$idx]['type'];
                $new = $s['entry'] === $y;
                $date = $new ? $this->randDate($y.'-08-20', $y.'-09-12') : $this->randDate($window[0], $window[1]);
                $buy = function ($name, $qty, $from = null) use (&$sales, $s, $date) { $sales[] = [$s, $name, $qty, $from, $date]; };

                if ($new) {
                    $buy('School uniform', 2);
                    $buy('Socks (pair)', $this->pick([2, 3, 3]));
                    if (mt_rand(1, 100) <= 60) $buy('Sweater', 1);
                    if ($type !== 'C') $buy('PE kit', 1);
                    if (mt_rand(1, 100) <= 40) $buy('School bag', 1);
                } else {
                    if (mt_rand(1, 100) <= 30) $buy('School uniform', 1);
                    if (mt_rand(1, 100) <= 50) $buy('Socks (pair)', $this->pick([1, 2]));
                    if (mt_rand(1, 100) <= 15) $buy('Sweater', 1);
                    if (mt_rand(1, 100) <= 12) $buy('School bag', 1);
                }
                if (!in_array($type, ['C', 'N'], true) && mt_rand(1, 100) <= 60) $buy('Exercise books (dozen)', $this->pick([1, 1, 2]));
                if (in_array($type, ['N', 'KG', 'P'], true) && mt_rand(1, 100) <= 35) $buy('Drawing book', $this->pick([1, 2]));

                // Books pack: now and then an older brother or sister's books from the same class are passed down.
                if ($s['books'] && !in_array($type, ['C', 'N', 'KG'], true)) {
                    [$older, $whenBought] = $booksBought[$s['family']][$idx] ?? [null, null];
                    if ($older && $whenBought < $y && mt_rand(1, 100) <= 60) {
                        $buy('Books & stationery pack', 1, $older);
                    } else {
                        $buy('Books & stationery pack', 1);
                        $booksBought[$s['family']][$idx] = $booksBought[$s['family']][$idx] ?? [$s['user_id'], $y];
                    }
                }
            }

            // Restock before the year opens: what will sell plus some spare.
            $need = [];
            foreach ($sales as [, $name, $qty, $from]) if (!$from) $need[$name] = ($need[$name] ?? 0) + $qty;
            foreach ($items as $name => $item) {
                $item->refresh();
                $qty = max(($need[$name] ?? 0) - $item->stock, 0) + mt_rand(5, 20);
                $cost = (int) (round($item->price * $f * 0.62 / 5) * 5);
                $date = $y.'-08-1'.mt_rand(2, 8);
                $item->increment('stock', $qty);
                StockMovement::create(['item_id' => $item->id, 'qty' => $qty, 'type' => 'restock', 'unit_cost' => $cost, 'note' => 'Stock for '.$year, 'user_id' => $by, 'date' => $date]);
                FinanceTransaction::create(['type' => 'expense', 'category' => 'Inventory purchases', 'amount' => $cost * $qty, 'date' => $date, 'method' => 'Bank transfer',
                    'description' => 'Restock: '.$name.' × '.$qty, 'recorded_by' => $by]);
            }

            usort($sales, function ($a, $b) { return strcmp($a[4], $b[4]); });
            foreach ($sales as [$s, $name, $qty, $from, $date]) {
                if ($date > now()->toDateString()) $date = now()->toDateString();
                $item = $items[$name]->fresh();
                $c = Fees::sell($s['user_id'], $year, $item, $qty, $from, $date);
                $amount = $from ? 0 : (int) (round($item->price * $f / 5) * 5) * $qty;
                DB::table('optional_fee_charges')->where('id', $c->id)->update(['amount' => $amount, 'created_at' => $date.' 10:00:00', 'updated_at' => $date.' 10:00:00']);
                DB::table('stock_movements')->where('charge_id', $c->id)->update(['user_id' => $by]);
                if (!$amount) continue;

                // Most pay at the counter; some add it to their fees and pay later (or still owe).
                $roll = mt_rand(1, 100);
                $target = $roll <= 70 ? $amount : ($roll <= 85 ? (int) (round($amount * 0.5 / 5) * 5) : ($y < 2026 ? $amount : 0));
                if ($target <= 0) continue;
                $payDate = $roll <= 70 ? $date : min(Carbon::parse($date)->addDays(mt_rand(7, 60))->toDateString(), now()->toDateString());
                $rc = OptionalFeeReceipt::create(['charge_id' => $c->id, 'amt_paid' => $target, 'balance' => $amount - $target, 'year' => $year]);
                DB::table('optional_fee_receipts')->where('id', $rc->id)->update(['created_at' => $payDate, 'updated_at' => $payDate]);
                DB::table('optional_fee_charges')->where('id', $c->id)->update(['amt_paid' => $target]);
            }
        }
    }

    protected function exams()
    {
        foreach (self::YEARS as $y) {
            $year = self::sess($y);
            $terms = $y === 2026 ? [1] : [1, 2, 3];
            foreach ($terms as $term) {
                $exam = Exam::create(['name' => [1 => 'First', 2 => 'Second', 3 => 'Third'][$term].' Term Examination', 'term' => $term, 'year' => $year]);
                foreach ($this->classes as $idx => $c) {
                    $sections = $y === 2026 ? $c['sections'] : [$c['sections'][0]];
                    foreach ($sections as $sec) {
                        $students = array_values(array_filter($this->students, function ($s) use ($idx, $y, $sec) {
                            return $this->idxAt($s, $y) === $idx && ($y !== 2026 || $s['section']['id'] === $sec['id']);
                        }));
                        // Pupils grow a little each year and each term.
                        $students = array_map(function ($s) use ($y, $term) {
                            $s['ability'] = min(97, $s['ability'] + ($y - $s['entry']) * 1.2);
                            return $s;
                        }, $students);
                        $this->examFor($exam, $c, $sec, $students, $year, $term + 1);
                    }
                }
            }
        }
    }

    /* ------------------------------------------------------------ */

    protected function promotions()
    {
        foreach ($this->students as $s) {
            foreach ([2022, 2023, 2024, 2025] as $y) {
                $idx = $this->idxAt($s, $y);
                if ($idx === null) continue;
                $from = $this->classes[$idx];
                $grad = $idx === self::LAST;
                $to = $grad ? $from : $this->classes[$idx + 1];
                $toSection = ($y === 2025 && !$grad) ? $s['section'] : $to['sections'][0];
                DB::table('promotions')->insert([
                    'student_id' => $s['user_id'], 'from_class' => $from['id'], 'from_section' => $from['sections'][0]['id'],
                    'to_class' => $to['id'], 'to_section' => $toSection['id'], 'grad' => $grad ? 1 : 0,
                    'from_session' => self::sess($y), 'to_session' => self::sess($y + 1), 'status' => $grad ? 'G' : 'P',
                    'created_at' => ($y + 1).'-07-30 12:00:00', 'updated_at' => ($y + 1).'-07-30 12:00:00',
                ]);
            }
        }
    }

    /* ------------------------------------------------------------ */

    protected function cashflow()
    {
        $by = User::where('username', 'accountant')->value('id');
        $add = function ($type, $cat, $amount, $date, $desc, $method = 'Bank transfer', $ref = null) use ($by) {
            if ($date > now()->toDateString()) return; // nothing dated in the future
            FinanceTransaction::create(['type' => $type, 'category' => $cat, 'amount' => (int) $amount, 'date' => $date, 'method' => $method, 'reference' => $ref, 'description' => $desc, 'recorded_by' => $by]);
        };

        // Capital from the proprietor: opening the school, then top-ups before each year.
        $add('income', 'Capital injection', 1800000, '2022-07-18', 'Proprietor capital to open the school', 'Bank transfer', 'GCB-TRF-220718');
        foreach ([2023 => 300000, 2024 => 250000, 2025 => 250000, 2026 => 200000] as $y => $amt) {
            $add('income', 'Capital injection', $amt, $y.'-08-0'.mt_rand(3, 9), 'Proprietor top-up before the '.self::sess($y).' year', 'Bank transfer', 'GCB-TRF-'.$y.'08');
        }
        $add('income', 'Donation', 15000, '2023-12-12', 'PTA donation towards the computer lab', 'Cheque', 'CHQ 004512');
        $add('income', 'Grant', 30000, '2025-03-18', 'Old students association library grant', 'Bank transfer', 'OSA-2025-03');
        $add('income', 'Donation', 12000, '2026-04-09', 'Parent donation of sports equipment (cash value)', 'Cash');

        // One-off spending.
        $add('expense', 'Furniture', 165000, '2022-08-05', 'Desks, chairs and cabinets for the new school', 'Bank transfer', 'INV-0805');
        $add('expense', 'Maintenance & repairs', 120000, '2022-08-12', 'Renovation of the classroom block before opening', 'Bank transfer', 'INV-0812');
        $add('expense', 'Vehicles', 210000, '2023-01-16', 'Purchase of school bus (used Toyota Coaster)', 'Bank transfer', 'VEH-2301');
        $add('expense', 'Equipment', 68000, '2024-01-22', 'Computer lab: 20 desktops and networking', 'Bank transfer', 'INV-2401');
        $add('expense', 'Maintenance & repairs', 86000, '2025-08-20', 'Painting of classroom block and repairs before reopening', 'Bank transfer', 'INV-0821');
        $add('expense', 'Furniture', 48500, '2026-08-14', 'New desks and chairs for Creche and Nursery', 'Bank transfer', 'INV-1134');

        // Monthly running costs grow with the school.
        $salaries = [2022 => 48000, 2023 => 57000, 2024 => 66000, 2025 => 74500, 2026 => 81500];
        $start = Carbon::create(2022, 8, 1);
        for ($m = $start->copy(); $m->lte(Carbon::create(2026, 9, 1)); $m->addMonth()) {
            $y = $m->month >= 8 ? $m->year : $m->year - 1;
            $scale = self::PRICE[$y] ?? 1;
            $inTerm = $m->month !== 8;
            $end = $m->copy()->setDay(min(27, $m->copy()->endOfMonth()->day));
            $sal = $salaries[$y] + mt_rand(0, 4) * 500;
            $add('expense', 'Salaries', $sal, $end->toDateString(), 'Teaching and non-teaching staff salaries, '.$m->format('F Y'));
            $add('expense', 'SSNIT contributions', (int) round($sal * 0.13), $m->copy()->addMonth()->setDay(14)->toDateString(), 'Employer SSNIT contribution for '.$m->format('F Y'));
            $add('expense', 'Utilities', (int) round((4200 + mt_rand(0, 30) * 50) * $scale), $m->copy()->setDay(18)->toDateString(), 'ECG electricity, Ghana Water and internet', 'Mobile money');
            if ($inTerm) {
                $add('expense', 'Food supplies', (int) round((19000 + mt_rand(0, 40) * 100) * $scale * (0.7 + 0.3 * ($y - 2021) / 5)), $m->copy()->setDay(6)->toDateString(), 'Foodstuff for the school feeding programme', 'Cash');
                $add('expense', 'Transport & fuel', (int) round((7000 + mt_rand(0, 20) * 50) * $scale), $m->copy()->setDay(10)->toDateString(), 'Fuel and servicing for the school buses', 'Cash');
            }
            if (in_array($m->month, [9, 1, 5], true)) {
                $add('expense', 'Stationery', (int) round((6000 + mt_rand(0, 20) * 100) * $scale), $m->copy()->setDay(3)->toDateString(), 'Exercise books, chalk, printer paper and toner', 'Cash');
            }
            if (mt_rand(1, 3) === 1) {
                $add('expense', 'Maintenance & repairs', 1500 + mt_rand(0, 40) * 100, $m->copy()->setDay(21)->toDateString(), $this->pick(['Plumbing repairs, toilet block', 'Air conditioner servicing, admin block', 'Replaced louvre blades and door locks', 'Generator servicing']), 'Cash');
            }
            if (in_array($m->month, [6, 12], true)) {
                $add('expense', 'Bank charges', 900 + mt_rand(0, 6) * 50, $m->copy()->endOfMonth()->toDateString(), 'Half-year bank charges', 'Bank transfer');
            }
        }
    }

    protected function examFor(Exam $exam, array $c, array $sec, array $students, string $year, int $trend)
    {
        if (!$students) return;
        $typeId = $this->types[$c['type']];
        $tex = 'tex'.$exam->term;
        $markIds = [];

        foreach ($students as $s) {
            foreach ($c['subjects'] as $k => $subjectId) {
                // Ability + subject strength + a little term-to-term improvement.
                $score = $s['ability'] + (($s['user_id'] * 7 + $k * 13) % 21 - 10) + ($trend - 2) * 1.5 + $this->gauss(0, 4);
                $score = max(18, min(99, (int) round($score)));
                $t1 = max(0, min(20, (int) round($score / 5 + mt_rand(-2, 2))));
                $t2 = max(0, min(20, (int) round($score / 5 + mt_rand(-2, 2))));
                $exm = max(0, min(60, $score - $t1 - $t2 + mt_rand(-4, 4)));
                $total = min(100, $t1 + $t2 + $exm);
                $grade = $this->mark->getGrade($total, $typeId);
                $markIds[] = Mark::create([
                    'student_id' => $s['user_id'], 'subject_id' => $subjectId, 'my_class_id' => $c['id'], 'section_id' => $sec['id'],
                    'exam_id' => $exam->id, 't1' => $t1, 't2' => $t2, 'tca' => $t1 + $t2, 'exm' => $exm, $tex => $total,
                    'grade_id' => $grade ? $grade->id : null, 'year' => $year,
                ])->id;
            }
        }

        foreach (Mark::whereIn('id', $markIds)->get() as $mk) {
            $mk->update(['sub_pos' => $this->mark->getSubPos($mk->student_id, $exam, $c['id'], $mk->subject_id, $year)]);
        }

        foreach ($students as $s) {
            $p = ['exam_id' => $exam->id, 'student_id' => $s['user_id'], 'my_class_id' => $c['id'], 'section_id' => $sec['id'], 'year' => $year];
            ExamRecord::create($p);
        }
        foreach ($students as $s) {
            $ave = $this->mark->getExamAvgTerm($exam, $s['user_id'], $c['id'], $sec['id'], $year);
            ExamRecord::where(['exam_id' => $exam->id, 'student_id' => $s['user_id'], 'year' => $year])->update([
                'total' => $this->mark->getExamTotalTerm($exam, $s['user_id'], $c['id'], $year),
                'ave' => $ave,
                'class_ave' => $this->mark->getClassAvg($exam, $c['id'], $year),
                'pos' => $this->mark->getPos($s['user_id'], $exam, $c['id'], $sec['id'], $year),
                'af' => implode(',', array_map(function () use ($ave) { return (string) max(2, min(5, (int) round($ave / 20) + mt_rand(-1, 1))); }, range(1, 7))),
                'ps' => implode(',', array_map(function () use ($ave) { return (string) max(2, min(5, (int) round($ave / 20) + mt_rand(-1, 1))); }, range(1, 7))),
                't_comment' => $this->teacherComment((float) $ave, $s['first']),
                'p_comment' => $this->headComment((float) $ave),
            ]);
        }
    }

    protected function teacherComment(float $ave, string $first): string
    {
        if ($ave >= 80) return $this->pick(["An excellent term. {$first} is focused, hardworking and a joy to teach.", "Outstanding work. {$first} leads by example in class.", 'Brilliant performance. Keep it up!']);
        if ($ave >= 70) return $this->pick(["Very good work. {$first} participates actively in class.", 'A very good term. More reading will push you higher.', 'Consistent and dependable. Well done.']);
        if ($ave >= 60) return $this->pick(["Good effort. {$first} can do even better with more practice.", 'Good progress this term. Pay more attention to Mathematics.', 'Steady improvement. Keep working hard.']);
        if ($ave >= 50) return $this->pick(["Fair performance. {$first} needs to complete homework on time.", 'Average work. More concentration in class is needed.', 'There is room for improvement. Extra reading at home will help.']);
        return $this->pick(["{$first} needs extra support. Let us work together at home and in school.", 'Weak performance. Must take studies more seriously.', 'Needs to improve attendance and concentration.']);
    }

    protected function headComment(float $ave): string
    {
        if ($ave >= 75) return $this->pick(['Excellent result. Promising learner.', 'Keep up the good work.', 'Well done. We are proud of you.']);
        if ($ave >= 55) return $this->pick(['Good. Aim higher next term.', 'Satisfactory. Keep improving.', 'Good effort, can do better.']);
        return $this->pick(['Parents should please see the class teacher.', 'Needs more effort and supervision at home.', 'Must work harder next term.']);
    }

    /* ------------------------------------------------------------ */

    protected function timetables()
    {
        $slots = [['8', '00', 'AM', '8', '40', 'AM'], ['8', '40', 'AM', '9', '20', 'AM'], ['9', '20', 'AM', '10', '00', 'AM'], ['10', '00', 'AM', '10', '30', 'AM'],
                  ['10', '30', 'AM', '11', '10', 'AM'], ['11', '10', 'AM', '11', '50', 'AM'], ['11', '50', 'AM', '12', '30', 'PM'], ['12', '30', 'PM', '1', '10', 'PM'],
                  ['1', '10', 'PM', '1', '50', 'PM'], ['1', '50', 'PM', '2', '30', 'PM']];
        $breaks = [3, 7]; // snack break and lunch
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $now = Carbon::create(2026, 8, 25, 11);

        foreach ($this->classes as $c) {
            $ttrId = DB::table('time_table_records')->insertGetId(['name' => $c['name'].' Class Timetable', 'my_class_id' => $c['id'], 'exam_id' => null, 'year' => self::CURR, 'created_at' => $now, 'updated_at' => $now]);
            $tsIds = [];
            $daySlots = $c['type'] === 'C' ? array_slice($slots, 0, 8) : $slots; // creche closes earlier
            foreach ($daySlots as $i => $s) {
                $tsIds[$i] = $this->timeSlot($ttrId, $s, $now);
            }
            $subjects = $c['subjects'];
            $n = 0;
            foreach ($days as $d => $day) {
                foreach ($tsIds as $i => $tsId) {
                    if (in_array($i, $breaks, true)) continue;
                    $subject = $subjects[($n + $d * 2) % count($subjects)];
                    $n++;
                    $this->ttRecord($ttrId, $tsId, $subject, $day, null, $now);
                }
            }
        }

        // First term exam timetable for the Junior High classes.
        $exam = Exam::where(['year' => self::CURR, 'term' => 1])->first();
        $dates = ['2026-12-01', '2026-12-02', '2026-12-03', '2026-12-04', '2026-12-07'];
        foreach ($this->classes as $c) {
            if ($c['type'] !== 'J') continue;
            $ttrId = DB::table('time_table_records')->insertGetId(['name' => $c['name'].' First Term Exam Timetable', 'my_class_id' => $c['id'], 'exam_id' => $exam->id, 'year' => self::CURR, 'created_at' => $now, 'updated_at' => $now]);
            $am = $this->timeSlot($ttrId, ['9', '00', 'AM', '11', '00', 'AM'], $now);
            $pm = $this->timeSlot($ttrId, ['12', '00', 'PM', '2', '00', 'PM'], $now);
            foreach ($c['subjects'] as $k => $subject) {
                $date = $dates[intdiv($k, 2) % count($dates)];
                $this->ttRecord($ttrId, $k % 2 ? $pm : $am, $subject, Carbon::parse($date)->format('l'), $date, $now);
            }
        }
    }

    protected function timeSlot(int $ttrId, array $s, $now): int
    {
        [$hf, $mf, $af, $ht, $mt, $at] = $s;
        $tf = "$hf:$mf $af";
        $tt = "$ht:$mt $at";
        return DB::table('time_slots')->insertGetId([
            'ttr_id' => $ttrId, 'hour_from' => $hf, 'min_from' => $mf, 'meridian_from' => $af, 'hour_to' => $ht, 'min_to' => $mt, 'meridian_to' => $at,
            'time_from' => $tf, 'time_to' => $tt, 'timestamp_from' => strtotime($tf), 'timestamp_to' => strtotime($tt), 'full' => "$tf - $tt",
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    protected function ttRecord(int $ttrId, int $tsId, int $subjectId, string $day, ?string $examDate, $now)
    {
        $ts = DB::table('time_slots')->find($tsId);
        $d = $examDate ?? $day;
        DB::table('time_tables')->insert([
            'ttr_id' => $ttrId, 'ts_id' => $tsId, 'subject_id' => $subjectId, 'exam_date' => $examDate, 'day' => $day,
            'timestamp_from' => strtotime($d.' '.$ts->time_from), 'timestamp_to' => strtotime($d.' '.$ts->time_to),
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    /* ------------------------------------------------------------ */

    protected function pins()
    {
        $students = collect($this->students)->filter(function ($s) { return $this->idxAt($s, 2026) !== null; })->shuffle()->take(9)->values();
        for ($i = 0; $i < 40; $i++) {
            $used = $i < 9;
            $s = $used ? $students[$i] : null;
            DB::table('pins')->insert([
                'code' => strtoupper(Str::random(5).'-'.Str::random(5).'-'.Str::random(6)), 'used' => $used ? 1 : 0, 'times_used' => $used ? mt_rand(1, 4) : 0,
                'user_id' => $s ? $s['record']->my_parent_id : null, 'student_id' => $s ? $s['user_id'] : null,
                'created_at' => '2026-07-20 10:00:00', 'updated_at' => $used ? '2026-07-28 18:30:00' : '2026-07-20 10:00:00',
            ]);
        }
    }

    /* ------------------------------------------------------------ */
    /* Helpers                                                       */
    /* ------------------------------------------------------------ */

    protected function staff(string $name, string $gender, string $type, ?string $username, string $empDate, ?string $password = null): int
    {
        [$first, $last] = explode(' ', $name, 2);
        $staffId = $this->username('STAFF', $empDate);
        $user = $this->user([
            'name' => $name, 'gender' => $gender, 'user_type' => $type, 'username' => $username ?: $staffId,
            'email' => $this->email($first, $last, 'lefschool.edu.gh'), 'phone' => $this->phone(),
            'address' => 'Hse No. '.mt_rand(2, 48).', '.$this->pick(self::AREAS), 'password' => $this->pw[$password ?: $type],
            'dob' => Carbon::create(mt_rand(1972, 1998), mt_rand(1, 12), mt_rand(1, 28))->toDateString(),
            'state_id' => 7, 'lga_id' => $this->pick([775, 777, 779, 782, 784]), 'nal_id' => 1, 'bg_id' => $this->pick([1, 2, 3, 5]),
            'created_at' => $empDate,
        ]);
        DB::table('staff_records')->insert(['user_id' => $user->id, 'code' => $staffId, 'emp_date' => $empDate, 'created_at' => $empDate, 'updated_at' => $empDate]);
        if ($type === 'teacher') $this->teachers[] = $user->id;

        return $user->id;
    }

    protected function user(array $d): User
    {
        $created = $d['created_at'] ?? now();
        unset($d['created_at']);
        $u = User::create($d + ['code' => strtoupper(Str::random(10)), 'photo' => Qs::getDefaultUserImage()]);
        DB::table('users')->where('id', $u->id)->update(['created_at' => $created, 'updated_at' => $created]);

        return $u;
    }

    protected function username(string $kind, $date): string
    {
        $d = Carbon::parse($date);
        do {
            $u = strtoupper($this->code.'/'.$kind.'/'.$d->format('Y/m').'/'.mt_rand(1000, 9999));
        } while (isset($this->used[$u]));
        $this->used[$u] = true;

        return $u;
    }

    protected function phone(): string
    {
        do {
            $p = $this->pick(['024', '054', '055', '059', '020', '050', '027', '057', '026', '053']).mt_rand(1000000, 9999999);
        } while (isset($this->used[$p]));
        $this->used[$p] = true;

        return $p;
    }

    protected function email(string $first, string $last, ?string $domain = null): string
    {
        $base = strtolower(preg_replace('/[^a-z]/i', '', $first).'.'.preg_replace('/[^a-z]/i', '', $last));
        $domain = $domain ?: $this->pick(['gmail.com', 'gmail.com', 'gmail.com', 'yahoo.com', 'outlook.com']);
        $e = $base.'@'.$domain;
        while (isset($this->used[$e])) {
            $e = $base.mt_rand(1, 99).'@'.$domain;
        }
        $this->used[$e] = true;

        return $e;
    }

    protected function ghanaCard(): string
    {
        return 'GHA-'.mt_rand(700000000, 799999999).'-'.mt_rand(0, 9);
    }

    protected function abbr(string $s): string
    {
        $words = preg_split('/[\s&()]+/', $s, -1, PREG_SPLIT_NO_EMPTY);
        return strtoupper(count($words) > 1 ? implode('', array_map(function ($w) { return $w[0]; }, $words)) : substr($s, 0, 4));
    }

    protected function previousClassName(array $c): string
    {
        return $this->classes[$c['index'] - 1]['name'] ?? $c['name'];
    }

    protected function randDate(string $from, string $to): string
    {
        return date('Y-m-d', mt_rand(strtotime($from), strtotime($to)));
    }

    protected function sortedDates(string $from, string $to, int $n): array
    {
        $d = [];
        for ($i = 0; $i < $n; $i++) {
            $d[] = mt_rand(strtotime($from.' 08:00'), strtotime($to.' 16:00'));
        }
        sort($d);
        return array_map(function ($t) {
            $c = Carbon::createFromTimestamp($t);
            if ($c->isWeekend()) $c->next(Carbon::MONDAY);
            return $c->setTime(mt_rand(8, 15), mt_rand(0, 59))->toDateTimeString();
        }, $d);
    }

    protected function split(int $total, int $parts): array
    {
        if ($parts <= 1) return [$total];
        $out = [];
        $left = $total;
        for ($i = 1; $i < $parts; $i++) {
            $a = (int) round($left * mt_rand(35, 60) / 100 / 10) * 10;
            $out[] = $a;
            $left -= $a;
        }
        $out[] = $left;
        return array_values(array_filter($out, function ($a) { return $a > 0; }));
    }

    protected function pick(array $a)
    {
        return $a[mt_rand(0, count($a) - 1)];
    }

    protected function gauss(float $mean, float $sd): float
    {
        $u = mt_rand(1, mt_getrandmax()) / mt_getrandmax();
        $v = mt_rand(1, mt_getrandmax()) / mt_getrandmax();
        return $mean + $sd * sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    }

    /* ------------------------------------------------------------ */

    const MALE = ['Kwame', 'Kofi', 'Kwabena', 'Kwaku', 'Yaw', 'Kojo', 'Kwesi', 'Kweku', 'Fiifi', 'Ebo', 'Nana', 'Emmanuel', 'Samuel', 'Daniel', 'Michael',
        'Isaac', 'Joseph', 'Prince', 'Richard', 'Eric', 'Benjamin', 'Jeremiah', 'Caleb', 'Elijah', 'Nathaniel', 'Jayden', 'Ethan', 'Mawuli', 'Selorm',
        'Edem', 'Nii', 'Atsu', 'Kwadwo', 'Abdul', 'Ibrahim', 'Yusif', 'Jesse', 'Jordan', 'Kelvin', 'Desmond', 'Godwin', 'Bernard', 'Clement', 'Felix'];
    const FEMALE = ['Ama', 'Akosua', 'Adwoa', 'Abena', 'Akua', 'Yaa', 'Afua', 'Esi', 'Efua', 'Adjoa', 'Araba', 'Ekua', 'Abla', 'Naa', 'Dede', 'Mansa',
        'Nhyira', 'Maame', 'Gifty', 'Grace', 'Comfort', 'Mercy', 'Abigail', 'Priscilla', 'Deborah', 'Esther', 'Jessica', 'Elorm', 'Sena', 'Dzifa',
        'Selasi', 'Ayisha', 'Fatima', 'Zainab', 'Adelaide', 'Josephine', 'Princess', 'Ewurabena', 'Hannah', 'Nadia', 'Sharon', 'Benedicta', 'Christabel', 'Linda'];
    const DAY_MALE = ['Kwasi', 'Kwadwo', 'Kwabena', 'Kwaku', 'Yaw', 'Kofi', 'Kwame', 'Nana Kwame', 'Junior'];
    const DAY_FEMALE = ['Akosua', 'Adwoa', 'Abenaa', 'Akua', 'Yaa', 'Afua', 'Ama', 'Nana Ama', 'Serwaa', 'Pokua'];
    const SURNAMES = ['Mensah', 'Owusu', 'Boateng', 'Asante', 'Osei', 'Agyeman', 'Appiah', 'Ofori', 'Darko', 'Amoah', 'Addo', 'Tetteh', 'Quaye', 'Lamptey',
        'Ansah', 'Acheampong', 'Adjei', 'Danso', 'Frimpong', 'Gyamfi', 'Nkansah', 'Opoku', 'Sarpong', 'Yeboah', 'Antwi', 'Asamoah', 'Bediako', 'Kyei',
        'Manu', 'Nyarko', 'Obeng', 'Poku', 'Wiredu', 'Agbeko', 'Kpodo', 'Amegashie', 'Tsikata', 'Ankrah', 'Aryee', 'Annan', 'Hammond', 'Arthur',
        'Quansah', 'Essien', 'Baidoo', 'Eshun', 'Fosu', 'Sackey', 'Abubakar', 'Mahama', 'Issah', 'Ocran', 'Bonsu', 'Amponsah', 'Kumi', 'Nartey'];
    const AREAS = ['Adenta Housing Down, Adenta', 'Nii Okine Street, East Legon', 'Spintex Road, Baatsona', 'Community 18, Lashibi', 'Sakumono Estates, Tema',
        'Santeo Road, Lashibi', 'Madina Estates, Madina', 'Teshie-Nungua Estates', 'Klagon, Tema', 'Community 25, Tema', 'Ashaley Botwe', 'Trasacco Valley, East Legon',
        'Oyarifa, Adenta', 'Regimanuel Gray Estates, Sakumono', 'Lakeside Estates, Ashaley Botwe', 'Manet Cottage, Spintex'];
    const JOBS = [['Banker', 'GCB Bank, Ridge Branch'], ['Nurse', 'Korle Bu Teaching Hospital'], ['Civil Engineer', 'Ghana Highway Authority'], ['Teacher', 'Ghana Education Service, Tema'],
        ['Trader', 'Makola Market, Accra'], ['Pharmacist', 'Ernest Chemists, Spintex'], ['Accountant', 'Ernst & Young Ghana, Airport City'], ['Lawyer', 'Bentsi-Enchill, Letsa & Ankomah'],
        ['Medical Doctor', '37 Military Hospital'], ['Software Developer', 'MTN Ghana, Airport City'], ['Police Officer', 'Ghana Police Service, Tema'], ['Businesswoman', 'Self-employed, Kaneshie'],
        ['Caterer', 'Self-employed, Lashibi'], ['Architect', 'Self-employed, East Legon'], ['Procurement Officer', 'Ghana Ports and Harbours Authority'], ['Customs Officer', 'Ghana Revenue Authority, Tema'],
        ['Journalist', 'Joy FM, Accra'], ['Pastor', 'Church of Pentecost, Sakumono'], ['Fashion Designer', 'Self-employed, Osu'], ['Real Estate Agent', 'Regimanuel Gray'],
        ['Marketing Manager', 'Unilever Ghana, Tema'], ['Pilot', 'Africa World Airlines'], ['Lecturer', 'University of Ghana, Legon'], ['Electrician', 'Self-employed, Ashaiman']];
    const INSURERS = [['Acacia Health Insurance', 'Family plan', '20,000'], ['Nationwide Medical Insurance', 'Gold plan', '35,000'], ['GLICO Healthcare', 'Silver plan', '15,000'], ['Premier Health Insurance', 'Corporate (parent employer)', '50,000']];
    const DOCTORS = [['Dr. Kofi Ampofo', 'Nyaho Medical Centre'], ['Dr. Akua Darko', 'Lekma Hospital, Teshie'], ['Dr. Yaw Mensah', 'Holy Trinity Medical Centre, Spintex'],
        ['Dr. Esi Amankwah', 'Tema General Hospital'], ['Dr. Kwame Boateng', '37 Military Hospital'], ['Dr. Abena Owusu', 'Lister Hospital, Airport'], ['Dr. Selasi Agbeko', 'Adenta Polyclinic']];
    const PREV_SCHOOLS = [['Ridge Church School', 'Ridge, Accra'], ['Morning Star School', 'Cantonments, Accra'], ['Tema International School', 'Community 11, Tema'],
        ['Faith Montessori School', 'Gbawe, Accra'], ['Christ the King School', 'Cantonments, Accra'], ['Adenta Community School', 'Adenta, Accra'], ['St. Martin de Porres School', 'Dansoman, Accra']];
}
