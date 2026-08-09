import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { TrackPace } from '@/types/study';
import { TrackPacePanel } from './track-pace';

function pace(overrides: Partial<TrackPace> = {}): TrackPace {
    return {
        tasksRemaining: 30,
        tasksTotal: 50,
        examOn: '14/03/2027',
        daysUntilExam: 70,
        requiredPerWeek: 3,
        currentPerWeek: 4,
        projectedOn: '20/01/2027',
        onTrack: true,
        ...overrides,
    };
}

describe('TrackPacePanel', () => {
    it('counts the days to the exam and puts both paces side by side', () => {
        render(<TrackPacePanel pace={pace()} />);

        expect(screen.getByText('70 dias')).toBeInTheDocument();
        expect(screen.getByText('14/03/2027')).toBeInTheDocument();
        expect(screen.getByText('30 de 50')).toBeInTheDocument();
        expect(screen.getByText('3 tarefas/semana')).toBeInTheDocument();
        expect(screen.getByText('4 tarefas/semana')).toBeInTheDocument();
    });

    it('says when the current pace does not reach the exam', () => {
        render(
            <TrackPacePanel
                pace={pace({
                    currentPerWeek: 1,
                    requiredPerWeek: 3,
                    onTrack: false,
                })}
            />,
        );

        expect(screen.getByText('Abaixo do ritmo')).toBeInTheDocument();
        expect(
            screen.getByText(
                'O ritmo atual não termina a trilha antes da prova.',
            ),
        ).toBeInTheDocument();
    });

    it('asks for a date when there is none, instead of inventing a pace', () => {
        render(
            <TrackPacePanel
                pace={pace({
                    examOn: null,
                    daysUntilExam: null,
                    requiredPerWeek: null,
                    onTrack: null,
                })}
            />,
        );

        expect(screen.getByText('Sem data de prova')).toBeInTheDocument();
        expect(screen.queryByText(/tarefas\/semana/)).toBeInTheDocument();
        expect(screen.queryByText('No ritmo')).not.toBeInTheDocument();
    });

    it('reports a past exam without a countdown', () => {
        render(
            <TrackPacePanel
                pace={pace({
                    daysUntilExam: -5,
                    requiredPerWeek: null,
                    onTrack: null,
                })}
            />,
        );

        expect(screen.getByText('Prova realizada')).toBeInTheDocument();
        expect(
            screen.getByText(
                'A data da prova já passou. Atualize o plano se houver uma nova.',
            ),
        ).toBeInTheDocument();
    });

    it('celebrates a finished track', () => {
        render(<TrackPacePanel pace={pace({ tasksRemaining: 0 })} />);

        expect(
            screen.getByText('Todas as tarefas da trilha estão concluídas.'),
        ).toBeInTheDocument();
    });

    it('points to the import when the track is empty', () => {
        render(
            <TrackPacePanel
                pace={pace({ tasksRemaining: 0, tasksTotal: 0 })}
            />,
        );

        expect(
            screen.getByText(/A trilha ainda não tem tarefas/),
        ).toBeInTheDocument();
    });

    it('writes fractional paces with a comma', () => {
        render(<TrackPacePanel pace={pace({ currentPerWeek: 2.5 })} />);

        expect(screen.getByText('2,5 tarefas/semana')).toBeInTheDocument();
    });
});
