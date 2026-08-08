<?php

namespace App\Actions\Study;

use App\Models\Enums\FocusSessionState;
use App\Models\StudySession;
use App\Models\User;

class ResolveCurrentFocusSession
{
    public function __construct(private CloseAbandonedFocusSession $closeAbandoned) {}

    /**
     * A sessão de foco corrente do usuário, ou null — o único caminho até
     * ela. B7 garante que nunca há mais de uma, mas nada garantia que a
     * que existe ainda fizesse sentido: uma sessão de sexta continuava
     * "em andamento" na segunda e somava o fim de semana inteiro às
     * estatísticas. Aqui ela é encerrada antes de ser devolvida.
     *
     * @param  array<int, string>  $with
     */
    public function handle(User $user, array $with = []): ?StudySession
    {
        $session = $user->studySessions()
            ->where('state', '!=', FocusSessionState::Completed)
            ->with($with)
            ->latest()
            ->first();

        if (! $session instanceof StudySession) {
            return null;
        }

        if ($session->isStale()) {
            $this->closeAbandoned->handle($session);

            return null;
        }

        return $session;
    }
}
