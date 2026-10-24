<?php

namespace App\Domain\Counselling;

use App\Models\ApplicationForm;
use App\Models\ApplicationPlan;
use App\Models\Audit;
use App\Models\CounsellorMessage;
use App\Models\Document;
use App\Models\DocumentKind;
use App\Models\ServiceCase;
use App\Models\StudentFollowUp;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** One feed of everything that happened with a student, newest first, for the counsellor's Timeline. */
class Timeline
{
    /** @return Collection<int,array{at:Carbon,kind:string,text:string}> */
    public function for(User $student, int $limit = 80): Collection
    {
        $events = collect();
        CounsellorMessage::where('student_id', $student->id)->with('author')->get()->each(fn ($m) => $events->push([
            'at' => $m->created_at, 'kind' => 'Message',
            'text' => ($m->fromStudent() ? $student->name : ($m->author?->name ?: 'SAS')).': '.Str::limit($m->body, 140),
        ]));
        StudentFollowUp::where('student_id', $student->id)->with('author')->get()->each(fn ($f) => $events->push([
            'at' => $f->created_at, 'kind' => 'Status',
            'text' => ($f->author?->name ?: 'Staff').' set the status to "'.$f->statusLabel().'"'.($f->comment ? ': '.Str::limit($f->comment, 120) : ''),
        ]));
        Document::where('user_id', $student->id)->get()->each(fn ($d) => $events->push(['at' => $d->created_at, 'kind' => 'Document', 'text' => 'Uploaded '.DocumentKind::label($d->type).' ('.$d->title.')']));
        ApplicationPlan::where('user_id', $student->id)->with('cycle.programme')->get()->each(function (ApplicationPlan $p) use ($events) {
            $name = $p->cycle->programme->title.' ('.$p->application_no.')';
            $events->push(['at' => $p->created_at, 'kind' => 'Application', 'text' => 'Started an application: '.$name]);
            $p->review_requested_at && $events->push(['at' => $p->review_requested_at, 'kind' => 'Application', 'text' => 'Sent for SAS review: '.$name]);
            $p->reviewed_at && $events->push(['at' => $p->reviewed_at, 'kind' => 'Application', 'text' => 'Review result "'.$p->stageLabel().'": '.$name.($p->review_remarks ? ' - '.Str::limit($p->review_remarks, 100) : '')]);
        });
        ServiceCase::where('user_id', $student->id)->get()->each(fn ($c) => $events->push(['at' => $c->opened_at ?? $c->created_at, 'kind' => 'Case', 'text' => 'SAS case opened: '.$c->title]));
        ($form = ApplicationForm::where('user_id', $student->id)->first()) && $events->push(['at' => $form->updated_at, 'kind' => 'Form', 'text' => 'Application form last saved']);
        Audit::where('action', 'student.counsellor_assigned')->where('auditable_id', $student->id)->where('auditable_type', $student->getMorphClass())->get()
            ->each(fn ($a) => $events->push(['at' => $a->created_at, 'kind' => 'Counsellor', 'text' => 'A counsellor was assigned']));
        $events->push(['at' => $student->created_at, 'kind' => 'Account', 'text' => 'Registered on SAS']);

        return $events->sortByDesc('at')->take($limit)->values();
    }
}
