import { useHttp } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Panel } from '@/components/panel';
import { notes as saveNotes } from '@/routes/focus';

const AUTOSAVE_DELAY_MS = 800;

/**
 * Notas rápidas da sessão: onde a dúvida que apareceu no meio do estudo
 * é anotada sem sair da tela.
 *
 * Grava com `useHttp` em vez de uma visita Inertia — um `router.post` a
 * cada pausa na digitação devolveria todas as props da página e faria o
 * cronômetro re-renderizar do zero.
 */
export function SessionNotes({ initialNotes }: { initialNotes: string }) {
    const { data, setData, post, processing } = useHttp({
        notes: initialNotes,
    });
    const [saved, setSaved] = useState(false);
    const value = data.notes;

    useEffect(() => {
        if (value === initialNotes) {
            return;
        }

        const timeout = setTimeout(() => {
            void post(saveNotes().url, { onSuccess: () => setSaved(true) });
        }, AUTOSAVE_DELAY_MS);

        return () => clearTimeout(timeout);
        // Reagir a `post` reiniciaria o debounce a cada resposta do
        // servidor; `initialNotes` é a linha de base, não um gatilho.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value]);

    return (
        <Panel
            title="Notas da sessão"
            action={
                <span className="text-xs text-muted-foreground">
                    {processing ? 'Salvando…' : saved ? 'Salvo' : ''}
                </span>
            }
        >
            <textarea
                aria-label="Notas da sessão"
                value={value}
                onChange={(event) => {
                    setData('notes', event.target.value);
                    setSaved(false);
                }}
                placeholder="Dúvidas, fórmulas, o que revisar depois…"
                rows={4}
                className="w-full resize-none rounded-sm border border-border bg-transparent px-3 py-2 text-sm text-foreground transition-[border-color,box-shadow] duration-fast ease-out outline-none placeholder:text-muted-foreground focus-visible:border-focus-500 focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
        </Panel>
    );
}
