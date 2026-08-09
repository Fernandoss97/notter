import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type * as FocusAlerts from './focus-alerts';

type Alerts = typeof FocusAlerts;

const contexts: FakeAudioContext[] = [];

class FakeAudioContext {
    state: 'running' | 'suspended' | 'closed' = 'running';

    currentTime = 0;

    destination = {};

    resume = vi.fn(async () => {
        this.state = 'running';
    });

    createOscillator = vi.fn(() => ({
        type: '',
        frequency: { value: 0 },
        connect: vi.fn(),
        start: vi.fn(),
        stop: vi.fn(),
    }));

    createGain = vi.fn(() => ({
        gain: {
            setValueAtTime: vi.fn(),
            exponentialRampToValueAtTime: vi.fn(),
        },
        connect: vi.fn(),
    }));

    constructor() {
        contexts.push(this);
    }
}

const notifications: { title: string; options?: NotificationOptions }[] = [];

class FakeNotification {
    static permission: NotificationPermission = 'granted';

    static requestPermission = vi.fn(async () => FakeNotification.permission);

    constructor(title: string, options?: NotificationOptions) {
        notifications.push({ title, options });
    }
}

/** O contexto de áudio é estado de módulo — cada teste recarrega. */
async function loadAlerts(): Promise<Alerts> {
    vi.resetModules();

    return import('./focus-alerts');
}

describe('focus alerts', () => {
    beforeEach(() => {
        contexts.length = 0;
        notifications.length = 0;
        FakeNotification.permission = 'granted';
        vi.stubGlobal('AudioContext', FakeAudioContext);
        vi.stubGlobal('Notification', FakeNotification);
        window.AudioContext =
            FakeAudioContext as unknown as typeof AudioContext;
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        Reflect.deleteProperty(window, 'AudioContext');
    });

    it('reuses one audio context instead of building a new one per cycle', async () => {
        const alerts = await loadAlerts();

        alerts.prepareAlerts();
        alerts.announcePhaseEnd('Ciclo concluído', 'Hora de descansar.');
        alerts.announcePhaseEnd('Intervalo terminado', 'Hora de voltar.');

        expect(contexts).toHaveLength(1);
    });

    it('unlocks the audio on the gesture, before there is anything to play', async () => {
        const alerts = await loadAlerts();

        alerts.prepareAlerts();

        // O contexto criado sem gesto nasce suspenso e não emite som
        // nenhum — sem exceção alguma para denunciar o silêncio.
        expect(contexts).toHaveLength(1);
        expect(FakeNotification.requestPermission).not.toHaveBeenCalled();
    });

    it('resumes a suspended context rather than playing into the void', async () => {
        const alerts = await loadAlerts();

        alerts.prepareAlerts();
        contexts[0].state = 'suspended';
        alerts.announcePhaseEnd('Ciclo concluído', 'Hora de descansar.');

        expect(contexts[0].resume).toHaveBeenCalled();
    });

    it('sounds two tones so the warning is not mistaken for any other beep', async () => {
        const alerts = await loadAlerts();

        alerts.announcePhaseEnd('Ciclo concluído', 'Hora de descansar.');

        expect(contexts[0].createOscillator).toHaveBeenCalledTimes(2);
    });

    it('replaces the previous notification instead of stacking them', async () => {
        const alerts = await loadAlerts();

        alerts.announcePhaseEnd('Ciclo concluído', 'Hora de descansar.');
        alerts.announcePhaseEnd('Intervalo terminado', 'Hora de voltar.');

        expect(notifications).toHaveLength(2);
        expect(notifications[0].options?.tag).toBe('notter-phase');
        expect(notifications[1].options?.tag).toBe('notter-phase');
    });

    it('stays quiet on the notification channel without permission', async () => {
        FakeNotification.permission = 'denied';
        const alerts = await loadAlerts();

        alerts.announcePhaseEnd('Ciclo concluído', 'Hora de descansar.');

        expect(notifications).toHaveLength(0);
        expect(alerts.notificationsBlocked()).toBe(true);
    });

    it('never asks again once the answer was no', async () => {
        FakeNotification.permission = 'denied';
        const alerts = await loadAlerts();

        alerts.requestNotificationPermission();

        expect(FakeNotification.requestPermission).not.toHaveBeenCalled();
    });

    it('asks for permission the first time, from the gesture', async () => {
        FakeNotification.permission = 'default';
        const alerts = await loadAlerts();

        alerts.prepareAlerts();

        expect(FakeNotification.requestPermission).toHaveBeenCalledOnce();
    });

    it('degrades quietly where there is no audio at all', async () => {
        vi.unstubAllGlobals();
        Reflect.deleteProperty(window, 'AudioContext');
        vi.stubGlobal('Notification', FakeNotification);
        const alerts = await loadAlerts();

        expect(() =>
            alerts.announcePhaseEnd('Ciclo concluído', 'Hora de descansar.'),
        ).not.toThrow();
        expect(notifications).toHaveLength(1);
    });
});
