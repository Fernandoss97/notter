import { Form } from '@inertiajs/react';
import { useRef } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteStudyData from '@/components/delete-study-data';
import InputError from '@/components/input-error';
import { Panel } from '@/components/panel';
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
import { Separator } from '@/components/ui/separator';

export default function DeleteUser() {
    const passwordInput = useRef<HTMLInputElement>(null);

    return (
        <Panel title="Zona de risco">
            {/* O recomeço vem antes da saída: quem quer zerar a trilha para
                o próximo concurso não deveria encontrar só o botão de
                excluir a conta. */}
            <DeleteStudyData />

            <Separator />

            <div className="flex items-center justify-between gap-4">
                <div>
                    <div className="text-sm font-medium text-foreground">
                        Excluir conta
                    </div>
                    <div className="mt-0.5 text-xs text-muted-foreground">
                        Apaga sua conta e todos os dados de estudo. Não dá para
                        desfazer.
                    </div>
                </div>

                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            variant="destructive"
                            data-test="delete-user-button"
                        >
                            Excluir conta
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>
                            Tem certeza de que quer excluir sua conta?
                        </DialogTitle>
                        <DialogDescription>
                            Ao excluir a conta, todos os planos, sessões e
                            revisões são apagados junto, de forma permanente.
                            Digite sua senha para confirmar.
                        </DialogDescription>

                        <Form
                            {...ProfileController.destroy.form()}
                            options={{
                                preserveScroll: true,
                            }}
                            onError={() => passwordInput.current?.focus()}
                            resetOnSuccess
                            className="space-y-6"
                        >
                            {({ resetAndClearErrors, processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor="password"
                                            className="sr-only"
                                        >
                                            Senha
                                        </Label>

                                        <PasswordInput
                                            id="password"
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
                                                data-test="confirm-delete-user-button"
                                            >
                                                Excluir conta
                                            </button>
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </Panel>
    );
}
