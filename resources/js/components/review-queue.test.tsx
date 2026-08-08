import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { DueReview } from '@/types/study';
import { ReviewQueue } from './review-queue';

const { post } = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
    router: { post },
}));

function review(overrides: Partial<DueReview> = {}): DueReview {
    return {
        id: 1,
        subjectId: 10,
        subjectName: 'Direito Constitucional',
        topicId: 100,
        topicName: 'Princípios fundamentais',
        sequence: 2,
        dueOn: '05/08',
        overdue: true,
        ...overrides,
    };
}

describe('ReviewQueue', () => {
    beforeEach(() => {
        post.mockReset();
    });

    it('lists each due review with its subject and sequence', () => {
        render(<ReviewQueue reviews={[review()]} />);

        expect(screen.getByText('Princípios fundamentais')).toBeInTheDocument();
        expect(
            screen.getByText(/Direito Constitucional · 2ª revisão · 05\/08/),
        ).toBeInTheDocument();
    });

    it('counts only the overdue reviews in the badge', () => {
        render(
            <ReviewQueue
                reviews={[
                    review({ id: 1, overdue: true }),
                    review({ id: 2, overdue: true }),
                    review({ id: 3, overdue: false }),
                ]}
            />,
        );

        expect(screen.getByText('2 atrasadas')).toBeInTheDocument();
    });

    it('drops the item from the list before the server answers', async () => {
        render(<ReviewQueue reviews={[review()]} />);

        await userEvent.click(screen.getByRole('button', { name: 'Feito' }));

        // `router.post` está mockado e nunca devolve props novas: se a
        // remoção dependesse do servidor, o item continuaria na lista.
        expect(
            screen.queryByText('Princípios fundamentais'),
        ).not.toBeInTheDocument();
        expect(post).toHaveBeenCalledOnce();
    });

    it('puts the item back when the request fails', async () => {
        post.mockImplementation((_url, _data, options) => options.onError());
        render(<ReviewQueue reviews={[review()]} />);

        await userEvent.click(screen.getByRole('button', { name: 'Feito' }));

        expect(screen.getByText('Princípios fundamentais')).toBeInTheDocument();
    });

    it('offers to study the topic when a handler is given', async () => {
        const onStudy = vi.fn();
        render(<ReviewQueue reviews={[review()]} onStudy={onStudy} />);

        await userEvent.click(screen.getByRole('button', { name: 'Estudar' }));

        expect(onStudy).toHaveBeenCalledWith(
            expect.objectContaining({ topicId: 100 }),
        );
    });

    it('hides the overdue badge in silent mode', () => {
        render(<ReviewQueue reviews={[review()]} muted />);

        expect(screen.queryByText('1 atrasada')).not.toBeInTheDocument();
        expect(screen.getByText('Princípios fundamentais')).toBeInTheDocument();
    });

    it('explains the empty queue instead of rendering nothing', () => {
        render(<ReviewQueue reviews={[]} />);

        expect(
            screen.getByText(/Nenhuma revisão para hoje/),
        ).toBeInTheDocument();
    });
});
