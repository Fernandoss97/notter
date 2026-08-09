import { Form } from '@inertiajs/react';
import { useRef } from 'react';
import StudyDataController from '@/actions/App/Http/Controllers/Settings/StudyDataController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

/**
 * O recomeço de quem terminou um concurso e vai para o próximo: apaga o
 * que foi estudado sem apagar quem estudou. Mora ao lado da exclusão de
 * conta porque é o mesmo tipo de gesto irreversível, e pede senha pela
 * mesma razão.
 */
export default function DeleteStudyData() {
    const passwordInput = useRef<HTMLInputElement>(null);

    return (
        <div className="flex items-center justify-between gap-4">
            <div>
                <div className="text-sm font-medium text-foreground">
                    Apagar dados de estudo
                </div>
                <div className="mt-0.5 text-xs text-muted-foreground">
                    Apaga planos, disciplinas, tarefas, revisões e sessões. Sua
                    conta e suas preferências continuam.
                </div>
            </div>

            <Dialog>
                <DialogTrigger asChild>
                    <Button
                        variant="outline"
                        data-test="delete-study-data-button"
                    >
                        Apagar dados
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogTitle>
                        Apagar todos os seus dados de estudo?
                    </DialogTitle>
                    <DialogDescription>
                        Todos os planos, com suas disciplinas, tarefas, revisões
                        e sessões registradas, são apagados de forma permanente.
                        Sua conta, sua senha, suas passkeys, a verificação em
                        duas etapas e as configurações do pomodoro não são
                        afetadas. Digite sua senha para confirmar.
                    </DialogDescription>

                    <Form
                        {...StudyDataController.destroy.form()}
                        options={{ preserveScroll: true }}
                        onError={() => passwordInput.current?.focus()}
                        resetOnSuccess
                        className="space-y-6"
                    >
                        {({ resetAndClearErrors, processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor="study_data_password"
                                        className="sr-only"
                                    >
                                        Senha
                                    </Label>

                                    <PasswordInput
                                        id="study_data_password"
                                        name="password"
                                        ref={passwordInput}
                                        placeholder="Senha"
                                        autoComplete="current-password"
                                    />

                                    <InputError message={errors.password} />
                                </div>

                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button
                                            variant="secondary"
                                            onClick={() =>
                                                resetAndClearErrors()
                                            }
                                        >
                                            Cancelar
                                        </Button>
                                    </DialogClose>

                                    <Button
                                        variant="destructive"
                                        disabled={processing}
                                        asChild
                                    >
                                        <button
                                            type="submit"
                                            data-test="confirm-delete-study-data-button"
                                        >
                                            Apagar dados de estudo
                                        </button>
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
