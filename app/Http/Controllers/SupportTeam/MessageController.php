<?php

namespace App\Http\Controllers\SupportTeam;

use App\Helpers\Qs;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MyClass;
use App\Models\Section;
use App\Models\StudentRecord;
use App\Support\ClassOrder;
use App\Support\Notices;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Messages from the school by SMS and email: to all parents, the parents of one class,
 * all staff, teaching staff or non-teaching staff.
 *  - Admins send straight away and approve everything.
 *  - The academic admin's messages wait for an admin; the academic admin approves teachers' messages.
 *  - A class teacher writes to the parents of their own class only; an academic admin or admin approves it.
 */
class MessageController extends Controller
{
    const STAFF = ['super_admin', 'admin', 'academic_admin', 'teacher', 'accountant', 'librarian'];

    public function __construct()
    {
        $this->middleware('teamSAT');
    }

    protected static function isTeacher(): bool
    {
        return Qs::userIsTeacher();
    }

    /** Whether the current user may approve or reject this pending message. */
    protected static function canReview(Message $m): bool
    {
        if ($m->status !== 'pending') return false;
        if (Qs::userIsTeamAdmin()) return true;

        // The academic admin approves teachers' messages (not their own).
        return Qs::userIsAcademicAdmin() && optional($m->sender)->user_type === 'teacher';
    }

    public function index()
    {
        $teacher = self::isTeacher();
        $own = $teacher ? \App\Support\TeacherScope::ownSectionIds() : null;
        $classes = ClassOrder::sort($teacher ? MyClass::whereIn('id', Section::whereIn('id', $own)->pluck('my_class_id'))->get() : MyClass::all());
        $history = Message::with('sender')->latest()->when($teacher, function ($q) { $q->where('sent_by', Auth::id()); })->limit(200)->get();

        return Inertia::render('Messages/Index', [
            'audiences' => collect(Message::AUDIENCES)->when($teacher, function ($a) { return $a->only('class'); })
                ->map(function ($label, $key) use ($teacher) { return ['value' => $key, 'label' => $teacher ? 'Parents of my class' : $label]; })->values(),
            'counts' => $teacher ? (object) [] : collect(array_keys(Message::AUDIENCES))->reject(function ($a) { return $a === 'class'; })
                ->mapWithKeys(function ($a) { return [$a => $this->people($a)['people']]; }),
            'classes' => $classes->map(function ($c) use ($own) {
                return ['id' => $c->id, 'name' => $c->name, 'sections' => Section::where('my_class_id', $c->id)->when($own !== null, function ($q) use ($own) { $q->whereIn('id', $own); })->orderBy('name')->get(['id', 'name'])];
            })->values(),
            'isTeacher' => $teacher,
            'history' => $history->map(function ($m) {
                return [
                    'id' => $m->id, 'date' => $m->created_at->toIso8601String(), 'audience' => $m->audience_label, 'subject' => $m->subject,
                    'body' => $m->body, 'channels' => array_values(array_filter([$m->sms ? 'SMS' : null, $m->email ? 'Email' : null, $m->whatsapp ? 'WhatsApp' : null])),
                    'recipients' => $m->recipients, 'sent' => $m->sent, 'failed' => $m->failed, 'by' => optional($m->sender)->name,
                    'status' => $m->status, 'note' => $m->review_note,
                    'urls' => self::canReview($m) ? ['approve' => route('messages.approve', $m->id), 'reject' => route('messages.reject', $m->id)] : null,
                ];
            })->values(),
            'canApprove' => Qs::userIsTeamAdmin() || Qs::userIsAcademicAdmin(),
            'needsApproval' => !Qs::userIsTeamAdmin(),
            'whatsapp' => Notices::whatsappEnabled(),
            'demo' => (bool) config('sms.allowlist'),
            'urls' => ['send' => route('messages.store'), 'count' => route('messages.count')],
        ]);
    }

    /** How many people (and phones/emails) a choice reaches, for the preview before sending. */
    public function count(Request $req)
    {
        if (self::isTeacher() && !in_array((int) $req->query('section_id'), \App\Support\TeacherScope::ownSectionIds(), true)) {
            return response()->json(['people' => 0, 'phones' => 0, 'emails' => 0, 'label' => '']);
        }
        $r = $this->people((string) $req->query('audience'), $req->query('class_id'), $req->query('section_id'));

        return response()->json(['people' => $r['people'], 'phones' => count($r['phones']), 'emails' => count($r['emails']), 'label' => $r['label']]);
    }

    public function store(Request $req)
    {
        $d = $req->validate([
            'audience' => 'required|in:'.implode(',', array_keys(Message::AUDIENCES)),
            'class_id' => 'required_if:audience,class|nullable|integer',
            'section_id' => 'nullable|integer',
            'subject' => 'nullable|string|max:150',
            'body' => 'required|string|min:5|max:1000',
            'sms' => 'nullable|boolean',
            'email' => 'nullable|boolean',
            'whatsapp' => 'nullable|boolean',
        ], [], ['body' => 'Message', 'class_id' => 'Class']);
        $sms = $req->boolean('sms');
        $email = $req->boolean('email');
        $wa = $req->boolean('whatsapp');
        if (!$sms && !$email && !$wa) {
            return response()->json(['message' => 'Invalid', 'errors' => ['sms' => ['Choose at least one: SMS, email or WhatsApp.']]], 422);
        }

        if (self::isTeacher()) {
            $own = \App\Support\TeacherScope::ownSectionIds();
            $sec = Section::find($d['section_id'] ?? 0);
            if ($d['audience'] !== 'class' || !$sec || !in_array((int) $sec->id, $own, true) || (int) $sec->my_class_id !== (int) ($d['class_id'] ?? 0)) {
                return Qs::json('You can only write to the parents of your own class.', false);
            }
        }

        $r = $this->people($d['audience'], $d['class_id'] ?? null, $d['section_id'] ?? null);
        if (!$r['people']) {
            return Qs::json('Nobody to send to for this choice.', false);
        }

        $m = Message::create([
            'audience' => $d['audience'], 'audience_label' => $r['label'], 'class_id' => $d['class_id'] ?? null, 'section_id' => $d['section_id'] ?? null,
            'subject' => $d['subject'] ?? null, 'body' => $d['body'], 'sms' => $sms, 'email' => $email, 'whatsapp' => $wa,
            'recipients' => $r['people'], 'sent_by' => Auth::id(),
            // Academic admin and teachers' messages wait for approval.
            'status' => Qs::userIsTeamAdmin() ? 'sending' : 'pending',
        ]);
        if ($m->status === 'pending') {
            return Qs::json(self::isTeacher()
                ? 'Message submitted for approval. The academic admin (or an administrator) must approve it before it is sent.'
                : 'Message submitted for approval. An administrator must approve it before it is sent.', true);
        }

        return Qs::json($this->deliver($m), true);
    }

    /** An administrator (or, for a teacher's message, the academic admin) approves; it is sent straight away. */
    public function approve($id)
    {
        $m = Message::where('status', 'pending')->findOrFail($id);
        if (!self::canReview($m)) {
            return Qs::json(__('msg.denied'), false);
        }
        $m->update(['approved_by' => Auth::id(), 'approved_at' => now()]);

        return Qs::json('Approved. '.$this->deliver($m), true);
    }

    public function reject(Request $req, $id)
    {
        $m = Message::where('status', 'pending')->findOrFail($id);
        if (!self::canReview($m)) {
            return Qs::json(__('msg.denied'), false);
        }
        $m->update(['status' => 'rejected', 'approved_by' => Auth::id(), 'approved_at' => now(), 'review_note' => mb_substr((string) $req->input('note'), 0, 255) ?: null]);

        return Qs::json('Message rejected; it was not sent.', true);
    }

    /** Send the message to its audience (worked out again at sending time) and record the counts. */
    protected function deliver(Message $m): string
    {
        $r = $this->people($m->audience, $m->class_id, $m->section_id);
        $count = Notices::broadcast($r['emails'], $r['phones'], $m->subject, $m->body, $m->sms, $m->email, (bool) $m->whatsapp);
        $m->update(['status' => 'sent', 'recipients' => $r['people'], 'sent' => $count['sent'], 'failed' => $count['failed']]);

        $msg = "Message sent: {$count['sent']} delivered";
        if ($count['held']) $msg .= ", {$count['held']} held back (demo mode)";
        if ($count['failed']) $msg .= ", {$count['failed']} failed";

        return $msg.'.';
    }

    /** People in an audience with their phone numbers and email addresses. */
    protected function people(string $audience, $classId = null, $sectionId = null): array
    {
        $phones = [];
        $emails = [];
        $people = 0;
        $label = Message::AUDIENCES[$audience] ?? $audience;

        if (in_array($audience, ['parents', 'class'], true)) {
            $q = StudentRecord::where('grad', 0)->whereNotNull('my_parent_id')->with('my_parent');
            if ($audience === 'class') {
                $q->where('my_class_id', (int) $classId);
                if ($sectionId) $q->where('section_id', (int) $sectionId);
                $class = MyClass::find($classId);
                $section = $sectionId ? Section::find($sectionId) : null;
                $label = 'Parents of '.trim(optional($class)->name.' '.optional($section)->name);
            }
            $families = $q->get()->unique('my_parent_id');
            $people = $families->count();
            foreach ($families as $sr) {
                $to = Notices::recipients($sr);
                $phones = array_merge($phones, $to['phones']);
                $emails = array_merge($emails, $to['emails']);
            }
        } else {
            $types = $audience === 'teachers' ? ['teacher'] : ($audience === 'non_teaching' ? array_values(array_diff(self::STAFF, ['teacher'])) : self::STAFF);
            $staff = User::whereIn('user_type', $types)->get(['email', 'phone']);
            $people = $staff->count();
            foreach ($staff as $u) {
                if ($p = Notices::normalisePhone((string) $u->phone)) $phones[] = $p;
                if (filter_var($u->email, FILTER_VALIDATE_EMAIL)) $emails[] = $u->email;
            }
        }

        return ['people' => $people, 'phones' => array_values(array_unique($phones)), 'emails' => array_values(array_unique($emails)), 'label' => $label];
    }
}
