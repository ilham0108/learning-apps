import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it } from 'vite-plus/test';
import {
    emptyItem,
    QuestionItemEditor,
} from '@/components/questions/question-item-editor';
import type { QuestionItemData } from '@/components/questions/question-item-editor';
import type { QuestionType } from '@/types';

function Harness({
    type,
    onState,
}: {
    type: QuestionType;
    onState: (item: QuestionItemData) => void;
}) {
    const [item, setItem] = useState(() => emptyItem(type));

    return (
        <QuestionItemEditor
            item={item}
            onChange={(next) => {
                setItem(next);
                onState(next);
            }}
            errors={{}}
            idPrefix="test"
        />
    );
}

describe('QuestionItemEditor', () => {
    it('keeps exactly one correct option for multiple choice', async () => {
        let state: QuestionItemData | null = null;
        render(
            <Harness
                type="multiple_choice"
                onState={(item) => (state = item)}
            />,
        );

        await userEvent.click(
            screen.getByRole('radio', {
                name: 'Jawaban benar untuk pilihan 3',
            }),
        );

        expect(state!.options.map((option) => option.is_correct)).toEqual([
            false,
            false,
            true,
            false,
        ]);
    });

    it('allows several correct options for multiple select', async () => {
        let state: QuestionItemData | null = null;
        render(
            <Harness
                type="multiple_select"
                onState={(item) => (state = item)}
            />,
        );

        await userEvent.click(
            screen.getByRole('checkbox', {
                name: 'Jawaban benar untuk pilihan 2',
            }),
        );

        expect(state!.options.map((option) => option.is_correct)).toEqual([
            true,
            true,
            false,
            false,
        ]);
    });

    it('does not allow fewer than two options', () => {
        render(<Harness type="multiple_choice" onState={() => undefined} />);

        expect(
            screen.getByRole('button', { name: 'Hapus pilihan 1' }),
        ).toBeEnabled();
    });

    it('shows accepted answers for short answers', async () => {
        let state: QuestionItemData | null = null;
        render(
            <Harness type="short_answer" onState={(item) => (state = item)} />,
        );

        await userEvent.type(
            screen.getByRole('textbox', { name: 'Jawaban 1' }),
            'Jakarta',
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Tambah jawaban' }),
        );

        expect(state!.accepted_answers).toEqual(['Jakarta', '']);
    });
});
