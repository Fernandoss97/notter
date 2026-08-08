import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { RecentDay } from '@/types/study';
import { WeeklyBars } from './weekly-bars';

const week: RecentDay[] = [
    { date: '02/08', weekday: 'sáb', minutes: 30 },
    { date: '03/08', weekday: 'dom', minutes: 0 },
    { date: '04/08', weekday: 'seg', minutes: 50 },
    { date: '05/08', weekday: 'ter', minutes: 0 },
    { date: '06/08', weekday: 'qua', minutes: 90 },
    { date: '07/08', weekday: 'qui', minutes: 45 },
    { date: '08/08', weekday: 'sex', minutes: 60 },
];

function barOf(label: RegExp) {
    return screen.getByLabelText(label).firstElementChild as HTMLElement;
}

describe('WeeklyBars', () => {
    it('exposes each day value to assistive tech', () => {
        render(<WeeklyBars days={week} goalMinutes={60} />);

        expect(
            screen.getByLabelText('qua, 06/08: 1h 30min'),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('dom, 03/08: 0min')).toBeInTheDocument();
    });

    it('keeps empty days as columns instead of dropping them', () => {
        render(<WeeklyBars days={week} goalMinutes={60} />);

        expect(screen.getAllByRole('listitem')).toHaveLength(7);
        expect(barOf(/dom, 03\/08/)).toHaveStyle({ height: '0%' });
    });

    it('scales the bars against the busiest day', () => {
        render(<WeeklyBars days={week} goalMinutes={60} />);

        expect(barOf(/qua, 06\/08/)).toHaveStyle({ height: '100%' });
        // 45 de 90 minutos.
        expect(barOf(/qui, 07\/08/)).toHaveStyle({ height: '50%' });
    });

    it('stretches the scale so the goal line still fits a weak week', () => {
        render(
            <WeeklyBars
                days={week.map((day) => ({ ...day, minutes: 10 }))}
                goalMinutes={100}
            />,
        );

        // O teto passa a ser a meta: 10 de 100 minutos.
        expect(barOf(/sex, 08\/08/)).toHaveStyle({ height: '10%' });
    });

    it('gives a sliver of height to a day that was studied very little', () => {
        render(
            <WeeklyBars
                days={[
                    { date: '08/08', weekday: 'sex', minutes: 1 },
                    { date: '09/08', weekday: 'sáb', minutes: 600 },
                ]}
                goalMinutes={0}
            />,
        );

        // Sem o piso, 1 minuto contra 600 renderizaria uma barra invisível.
        expect(barOf(/sex, 08\/08/)).toHaveStyle({ height: '2%' });
    });

    it('names the goal the dashed line stands for', () => {
        render(<WeeklyBars days={week} goalMinutes={60} />);

        expect(
            screen.getByText(/Linha tracejada: meta diária de 1h 0min/),
        ).toBeInTheDocument();
    });

    it('drops the goal line when there is no goal', () => {
        render(<WeeklyBars days={week} goalMinutes={0} />);

        expect(screen.queryByText(/Linha tracejada/)).not.toBeInTheDocument();
    });

    it('labels every weekday under the plot', () => {
        render(<WeeklyBars days={week} goalMinutes={60} />);

        expect(screen.getAllByText('seg')).toHaveLength(1);
        expect(screen.getAllByText('sex')).toHaveLength(1);
    });
});
