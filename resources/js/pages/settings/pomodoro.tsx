import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Panel } from '@/components/panel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SegmentedControl } from '@/components/ui/segmented-control';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { formatDurationMinutes } from '@/lib/format-duration';
import { edit, update } from '@/routes/pomodoro-settings';
import type { PomodoroSettingFull } from '@/types/study';

const CYCLE_OPTIONS = ['2', '3', '4', '5'].map((value) => ({
    value,
    label: value,
}));

/** Espelha `PomodoroSetting::MIN/MAX_DAILY_GOAL_MINUTES`. */
const MIN_DAILY_GOAL_MINUTES = 5;
const MAX_DAILY_GOAL_MINUTES = 960;

interface PomodoroEditProps {
    settings: PomodoroSettingFull;
}

export default function PomodoroEdit({ settings }: PomodoroEditProps) {
    const form = useForm({
        focus_minutes: settings.focusMinutes,
        short_break_minutes: settings.shortBreakMinutes,
        long_break_minutes: settings.longBreakMinutes,
        cycles_before_long_break: settings.cyclesBeforeLongBreak,
        daily_goal_minutes: settings.dailyGoalMinutes,
        auto_start_break: settings.autoStartBreak,
        auto_start_focus: settings.autoStartFocus,
        log_session_on_finish: settings.logSessionOnFinish,
        silent_mode_during_focus: settings.silentModeDuringFocus,
        exclude_breaks_from_study_time: settings.excludeBreaksFromStudyTime,
    });

    const weeklyEquivalent = Math.max(0, form.data.daily_goal_minutes) * 7;

    function submit(event: FormEvent) {
        event.preventDefault();
        form.put(update().url, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Pomodoro" />

            <form onSubmit={submit} className="flex flex-col gap-6">
                <Panel title="Duração dos ciclos">
                    <div className="grid grid-cols-3 gap-4">
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="focus_minutes">Foco</Label>
                            <Input
                                id="focus_minutes"
                                type="number"
                                min={1}
                                value={form.data.focus_minutes}
                                onChange={(event) =>
                                    form.setData(
                                        'focus_minutes',
                                        Number(event.target.value || 0),
                                    )
                                }
                            />
                            {form.errors.focus_minutes && (
                                <p className="text-sm text-destructive">
                                    {form.errors.focus_minutes}
                                </p>
                            )}
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="short_break_minutes">
                                Pausa curta
                            </Label>
                            <Input
                                id="short_break_minutes"
                                type="number"
                                min={1}
                                value={form.data.short_break_minutes}
                                onChange={(event) =>
                                    form.setData(
                                        'short_break_minutes',
                                        Number(event.target.value || 0),
                                    )
                                }
                            />
                            {form.errors.short_break_minutes && (
                                <p className="text-sm text-destructive">
                                    {form.errors.short_break_minutes}
                                </p>
                            )}
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="long_break_minutes">
                                Pausa longa
                            </Label>
                            <Input
                                id="long_break_minutes"
                                type="number"
                                min={1}
                                value={form.data.long_break_minutes}
                                onChange={(event) =>
                                    form.setData(
                                        'long_break_minutes',
                                        Number(event.target.value || 0),
                                    )
                                }
                            />
                            {form.errors.long_break_minutes && (
                                <p className="text-sm text-destructive">
                                    {form.errors.long_break_minutes}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="flex items-center gap-4">
                        <span className="text-sm text-fg-2">
                            Pausa longa a cada
                        </span>
                        <SegmentedControl
                            options={CYCLE_OPTIONS}
                            value={String(form.data.cycles_before_long_break)}
                            onValueChange={(value) =>
                                form.setData(
                                    'cycles_before_long_break',
                                    Number(value),
                                )
                            }
                        />
                        <span className="text-sm text-fg-2">pomodoros</span>
                    </div>
                </Panel>

                <Panel title="Meta do dia">
                    <div className="flex flex-wrap items-end gap-4">
                        <div className="flex w-40 flex-col gap-2">
                            <Label htmlFor="daily_goal_minutes">
                                Minutos por dia
                            </Label>
                            <Input
                                id="daily_goal_minutes"
                                type="number"
                                min={MIN_DAILY_GOAL_MINUTES}
                                max={MAX_DAILY_GOAL_MINUTES}
                                value={form.data.daily_goal_minutes}
                                onChange={(event) =>
                                    form.setData(
                                        'daily_goal_minutes',
                                        Number(event.target.value || 0),
                                    )
                                }
                            />
                            {form.errors.daily_goal_minutes && (
                                <p className="text-sm text-destructive">
                                    {form.errors.daily_goal_minutes}
                                </p>
                            )}
                        </div>

                        {/* O equivalente semanal fica à vista porque a meta
                            da semana continua sendo do plano: sem isso, as
                            duas poderiam discordar sem ninguém notar. */}
                        <p className="pb-2 text-sm text-fg-2">
                            É a meta da tela de foco e a linha das barras do
                            progresso.{' '}
                            <strong className="font-mono font-semibold text-foreground tabular-nums">
                                {formatDurationMinutes(weeklyEquivalent)}
                            </strong>{' '}
                            por semana, estudando todos os dias — a meta
                            semanal do plano é separada desta.
                        </p>
                    </div>
                </Panel>

                <Panel title="Comportamento">
                    <ToggleField
                        checked={form.data.auto_start_break}
                        onCheckedChange={(checked) =>
                            form.setData('auto_start_break', checked)
                        }
                        label="Iniciar pausa automaticamente"
                        hint="Ao terminar o foco, o timer segue sem confirmação"
                    />
                    <ToggleField
                        checked={form.data.auto_start_focus}
                        onCheckedChange={(checked) =>
                            form.setData('auto_start_focus', checked)
                        }
                        label="Iniciar próximo foco automaticamente"
                        hint="Encadeia o ciclo inteiro sem intervenção"
                    />
                    <ToggleField
                        checked={form.data.log_session_on_finish}
                        onCheckedChange={(checked) =>
                            form.setData('log_session_on_finish', checked)
                        }
                        label="Registrar sessão ao encerrar"
                        hint="Abre o formulário de tópico e atividade ao final"
                    />
                    <ToggleField
                        checked={form.data.silent_mode_during_focus}
                        onCheckedChange={(checked) =>
                            form.setData('silent_mode_during_focus', checked)
                        }
                        label="Modo silencioso durante o foco"
                        hint="Suprime notificações de revisão enquanto o timer roda"
                    />
                    <ToggleField
                        checked={form.data.exclude_breaks_from_study_time}
                        onCheckedChange={(checked) =>
                            form.setData(
                                'exclude_breaks_from_study_time',
                                checked,
                            )
                        }
                        label="Descontar pausas do tempo estudado"
                    />
                </Panel>

                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        Salvar alterações
                    </Button>
                </div>
            </form>
        </>
    );
}

PomodoroEdit.layout = {
    breadcrumbs: [{ title: 'Pomodoro', href: edit() }],
};

function ToggleField({
    checked,
    onCheckedChange,
    label,
    hint,
}: {
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    label: string;
    hint?: string;
}) {
    return (
        <div className="flex items-center justify-between gap-4">
            <div>
                <div className="text-sm font-medium text-foreground">
                    {label}
                </div>
                {hint && (
                    <div className="mt-0.5 text-xs text-muted-foreground">
                        {hint}
                    </div>
                )}
            </div>
            <Switch checked={checked} onCheckedChange={onCheckedChange} />
        </div>
    );
}
