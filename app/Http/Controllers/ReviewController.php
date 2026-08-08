<?php

namespace App\Http\Controllers;

use App\Actions\Study\CompleteReview;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReviewController extends Controller
{
    /**
     * Marca uma revisão da fila como feita (C1). A Action já agenda a
     * próxima da sequência 1/3/7/14/30 — aqui só resta garantir que a
     * revisão é mesmo do usuário antes de tocá-la.
     */
    public function complete(Request $request, Review $review, CompleteReview $action): RedirectResponse
    {
        abort_unless(
            Review::query()->forUser($request->user())->whereKey($review->id)->exists(),
            403
        );

        $action->handle($review);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Revisão concluída.')]);

        return back();
    }
}
