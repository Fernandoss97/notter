import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { SubjectCard } from './subject-card';

describe('SubjectCard', () => {
    it('renders the subject name and topic progress', () => {
        render(
            <SubjectCard
                subject="Álgebra Linear"
                topicsDone={12}
                topicsTotal={20}
                progress={60}
            />,
        );

        expect(screen.getByText('Álgebra Linear')).toBeInTheDocument();
        expect(
            screen.getByText('12/20 tópicos concluídos'),
        ).toBeInTheDocument();
        expect(screen.getByText('60%')).toBeInTheDocument();
    });

    it('renders the review status badge when provided', () => {
        render(
            <SubjectCard
                subject="Álgebra Linear"
                topicsDone={12}
                topicsTotal={20}
                progress={60}
                reviewStatus="overdue"
            />,
        );

        expect(screen.getByText('Atrasada')).toBeInTheDocument();
    });

    it('omits the badge without a review status', () => {
        render(
            <SubjectCard
                subject="Álgebra Linear"
                topicsDone={12}
                topicsTotal={20}
                progress={60}
            />,
        );

        expect(screen.queryByText('Atrasada')).not.toBeInTheDocument();
        expect(screen.queryByText('Em dia')).not.toBeInTheDocument();
    });

    it('calls onClick when clicked', async () => {
        const onClick = vi.fn();
        render(
            <SubjectCard
                subject="Álgebra Linear"
                topicsDone={12}
                topicsTotal={20}
                progress={60}
                onClick={onClick}
            />,
        );

        await userEvent.click(screen.getByText('Álgebra Linear'));

        expect(onClick).toHaveBeenCalledOnce();
    });
});
