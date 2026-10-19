<?php

/*
 | One product, many schools: what is different about this school is switched on here (from its .env),
 | never by changing the code for one school.
 |
 |   SCHOOL_FEATURES   comma-separated switches (see FEATURES below)
 |   SCHOOL_ASSESSMENT the assessment scheme (see 'schemes'); empty = the standard one
 */
return [

    'features' => array_values(array_filter(array_map('trim', explode(',', (string) env('SCHOOL_FEATURES', ''))))),

    /* What each switch does (for reference; App\Support\School::has('…')). */
    'FEATURES' => [
        'subject_positions_only' => 'Results rank students per subject only: no overall position in class.',
        'promotions_academic_admin_only' => 'Only the academic admin (and LEF) promotes students and decides repeats.',
        'discount_to_youngest_sibling' => 'When a student with a discount graduates, the discount moves to their youngest sibling still in school.',
        'report_after_fees_cleared' => 'A report sheet is not sent to parents while the student owes fees (bills still can be).',
        'core_elective_subjects' => 'Subjects are core or elective: report cards, result sheets and mark entry group them.',
        'subject_teachers_per_section' => 'A subject can have its own teacher(s) in each section; each enters marks only for their sections.',
        'multiple_class_teachers' => 'A section can have several class teachers (each sees the class, takes the register and writes comments).',
        'hod_marks_approval' => 'Heads of department check and approve the marks of their subjects; approved marks are locked and reports wait for approval.',
    ],

    'assessment' => env('SCHOOL_ASSESSMENT') ?: 'standard',

    /*
     | Assessment schemes: the score boxes a teacher fills in for a subject, and how they make the total.
     |   parts    every box: key, label, max, group (boxes of a group share a heading and get a group total)
     |   exam     the key of the exam box
     |   weights  null: total = sum of all boxes;  ['class' => 0.5, 'exam' => 0.5]: class boxes (out of 100) and exam (out of 100) weighted
     |   report   labels and values of the report-card columns
     */
    'schemes' => [
        'standard' => [
            'parts' => [
                ['key' => 't1', 'label' => '1st CA', 'max' => 20],
                ['key' => 't2', 'label' => '2nd CA', 'max' => 20],
                ['key' => 'exm', 'label' => 'Exam', 'max' => 60],
            ],
            'exam' => 'exm',
            'weights' => null,
        ],

        // Desvy International School: Termly Standards-Based Assessment Recording Register.
        'desvy_sba' => [
            'title' => 'TERMLY STANDARD BASED ASSESSMENT RECORDING REGISTER',
            'groups' => ['asg' => 'Assignment', 'cex' => 'Class exercise', 'cat' => 'CATs'],
            'parts' => [
                ['key' => 'a1', 'label' => '1', 'max' => 10, 'group' => 'asg'],
                ['key' => 'a2', 'label' => '2', 'max' => 10, 'group' => 'asg'],
                ['key' => 'a3', 'label' => '3', 'max' => 10, 'group' => 'asg'],
                ['key' => 'e1', 'label' => '1', 'max' => 10, 'group' => 'cex'],
                ['key' => 'e2', 'label' => '2', 'max' => 10, 'group' => 'cex'],
                ['key' => 'e3', 'label' => '3', 'max' => 10, 'group' => 'cex'],
                ['key' => 'e4', 'label' => '4', 'max' => 10, 'group' => 'cex'],
                ['key' => 'ct1', 'label' => 'CT1', 'max' => 10, 'group' => 'cat'],
                ['key' => 'ct2', 'label' => 'CT2', 'max' => 10, 'group' => 'cat'],
                ['key' => 'pw', 'label' => 'PW', 'max' => 10, 'group' => 'cat'],
                ['key' => 'exm', 'label' => 'Exam score', 'max' => 100],
            ],
            'exam' => 'exm',
            'weights' => ['class' => 0.5, 'exam' => 0.5],
            'report' => ['ca1' => false, 'ca2' => false, 'ca_total' => 'CLASS SCORE (50%)', 'exam' => 'EXAM SCORE (50%)', 'total' => 'TOTAL (100%)'],
        ],
    ],
];
