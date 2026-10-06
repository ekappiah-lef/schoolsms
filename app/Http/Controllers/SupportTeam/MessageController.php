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
 * all staff, teaching staff or non-teaching staff. Admins and the academic admin.
 */
class MessageController extends Controller
{
    const STAFF = ['super_admin', 'admin', 'academic_admin', 'teacher', 'accountant', 'librarian'];

    public function __construct()
    {
        $this->middleware('teamSA');
    }

    public function index()
    {
        return Inertia::render('Messages/Index', [
            'audiences' => collect(Message::AUDIENCES)->map(function ($label, $key) { return ['value' => $key, 'label' => $label]; })->values(),
            'counts' => collect(array_keys(Message::AUDIENCES))->reject(function ($a) { return $a === 'class'; })
                ->mapWithKeys(function ($a) { return [$a => $this->people($a)['people']]; }),
            'classes' => ClassOrder::sort(MyClass::all())->map(function ($c) {
                return ['id' => $c->id, 'name' => $c->name, 'sections' => Section::where('my_class_id', $c->id)->orderBy('name')->get(['id', 'name'])];
            })->values(),
            'history' => Message::with('sender')->latest()->limit(100)->get()->map(function ($m) {
                return [
                    'id' => $m->id, 'date' => $m->created_at->toIso8601String(), 'audience' => $m->audience_label, 'subject' => $m->subject,
                    'body' => $m->body, 'channels' => array_values(array_filter([$m->sms ? 'SMS' : null, $m->email ? 'Email' : null])),
                    'recipients' => $m->recipients, 'sent' => $m->sent, 'failed' => $m->failed, 'by' => optional($m->sender)->name,
                ];
            })->values(),
            'demo' => (bool) config('sms.allowlist'),
            'urls' => ['send' => route('messages.store'), 'count' => route('messages.count')],
        ]);
    }

    /** How many people (and phones/emails) a choice reaches, for the preview before sending. */
    public function count(Request $req)
    {
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
        ], [], ['body' => 'Message', 'class_id' => 'Class']);
        $sms = $req->boolean('sms');
        $email = $req->boolean('email');
        if (!$sms && !$email) {
            return response()->json(['message' => 'Invalid', 'errors' => ['sms' => ['Choose SMS, email or both.']]], 422);
        }

        $r = $this->people($d['audience'], $d['class_id'] ?? null, $d['section_id'] ?? null);
        if (!$r['people']) {
            return Qs::json('Nobody to send to for this choice.', false);
        }
        $count = Notices::broadcast($r['emails'], $r['phones'], $d['subject'] ?? null, $d['body'], $sms, $email);

        Message::create([
            'audience' => $d['audience'], 'audience_label' => $r['label'], 'subject' => $d['subject'] ?? null, 'body' => $d['body'],
            'sms' => $sms, 'email' => $email, 'recipients' => $r['people'], 'sent' => $count['sent'], 'failed' => $count['failed'], 'sent_by' => Auth::id(),
        ]);

        $msg = "Message sent: {$count['sent']} delivered";
        if ($count['held']) $msg .= ", {$count['held']} held back (demo mode)";
        if ($count['failed']) $msg .= ", {$count['failed']} failed";

        return Qs::json($msg.'.', true);
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
