<?php

namespace App\Http\Controllers;

use App\Actions\Study\CompleteTopic;
use App\Http\Requests\Study\StoreTopicRequest;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TopicController extends Controller
{
    /**
     * subject_id is already ownership-validated by StoreTopicRequest's
     * after() (must belong to the user's active plan).
     */
    public function store(StoreTopicRequest $request): RedirectResponse
    {
        $subject = Subject::findOrFail($request->integer('subject_id'));

        $subject->topics()->create([
            'name' => $request->string('name')->toString(),
            'position' => $subject->topics()->count(),
        ]);

        return to_route('plans.index');
    }

    public function complete(Request $request, Topic $topic, CompleteTopic $action): RedirectResponse
    {
        abort_unless($topic->subject->studyPlan->user_id === $request->user()->id, 403);

        $action->handle($topic);

        return to_route('plans.index');
    }
}
