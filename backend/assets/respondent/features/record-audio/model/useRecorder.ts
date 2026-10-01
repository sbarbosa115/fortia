import {api, type Schema} from '@shared/api';
import {useCallback, useEffect, useRef, useState} from 'react';
import {
  RecorderError,
  type RecorderErrorKey,
  recorderErrorKey,
} from './recorder';

type TranscriptionToken = Schema<'TranscriptionTokenOutput'>;

type SpeechResult = {isFinal: boolean; 0: {transcript: string}};
type SpeechEvent = {resultIndex: number; results: ArrayLike<SpeechResult>};
type SpeechRecognitionLike = {
  lang: string;
  continuous: boolean;
  interimResults: boolean;
  onresult: ((event: SpeechEvent) => void) | null;
  onerror: ((event: {error: string}) => void) | null;
  onend: (() => void) | null;
  start: () => void;
  stop: () => void;
  abort: () => void;
};
type SpeechRecognitionCtor = new () => SpeechRecognitionLike;

declare global {
  interface Window {
    SpeechRecognition?: SpeechRecognitionCtor;
    webkitSpeechRecognition?: SpeechRecognitionCtor;
  }
}

export type RecorderStatus = 'idle' | 'connecting' | 'recording';

const SPEECH_ERRORS: Record<string, RecorderErrorKey> = {
  'not-allowed': 'denied',
  'service-not-allowed': 'denied',
  'audio-capture': 'noMicrophone',
  'network': 'connection',
};

function recognitionCtor(): SpeechRecognitionCtor | null {
  return window.SpeechRecognition ?? window.webkitSpeechRecognition ?? null;
}

/** Whether the microphone permission is already blocked (§9.7 "detected before recording"). */
async function permissionDenied(): Promise<boolean> {
  try {
    const status = await navigator.permissions?.query({
      name: 'microphone' as PermissionName,
    });
    return status?.state === 'denied';
  } catch {
    return false;
  }
}

type Live = {
  stream: MediaStream | null;
  context: AudioContext | null;
  recognition: SpeechRecognitionLike | null;
  frame: number | null;
  timer: number | null;
  finalText: string;
  interimText: string;
  recording: boolean;
  onEnded: (() => void) | null;
};

/**
 * Records one voice answer (PRD §9.7, §13.4): an ephemeral token per recording (GET /transcription/token), the
 * microphone level for the wave bars, and the live transcription in the UI language. The transcription runs on the
 * browser's speech recognition (the token's provider "browser"); stop() resolves with the recording's text.
 */
export function useRecorder(language: 'es' | 'en') {
  const [status, setStatus] = useState<RecorderStatus>('idle');
  const [error, setError] = useState<RecorderErrorKey | null>(null);
  const [level, setLevel] = useState(0);
  const [elapsed, setElapsed] = useState(0);
  const [transcript, setTranscript] = useState('');
  const live = useRef<Live>({
    stream: null,
    context: null,
    recognition: null,
    frame: null,
    timer: null,
    finalText: '',
    interimText: '',
    recording: false,
    onEnded: null,
  });

  const release = useCallback(() => {
    const current = live.current;
    current.recording = false;
    if (current.frame !== null) {
      cancelAnimationFrame(current.frame);
    }
    if (current.timer !== null) {
      window.clearInterval(current.timer);
    }
    current.stream?.getTracks().forEach((track) => track.stop());
    void current.context?.close().catch(() => undefined);
    current.stream = null;
    current.context = null;
    current.frame = null;
    current.timer = null;
  }, []);

  useEffect(
    () => () => {
      live.current.recognition?.abort();
      release();
    },
    [release],
  );

  const fail = useCallback(
    (cause: unknown) => {
      live.current.recognition?.abort();
      live.current.recognition = null;
      release();
      setStatus('idle');
      setLevel(0);
      setError(recorderErrorKey(cause));
    },
    [release],
  );

  const start = useCallback(async () => {
    setError(null);
    setTranscript('');
    setElapsed(0);
    try {
      if (!window.isSecureContext) {
        throw new RecorderError('insecure');
      }
      const Recognition = recognitionCtor();
      if (!navigator.mediaDevices?.getUserMedia || !Recognition) {
        throw new RecorderError('unsupported');
      }
      if (await permissionDenied()) {
        throw new RecorderError('denied');
      }
      setStatus('connecting');
      try {
        await api.get<TranscriptionToken>('/transcription/token');
      } catch {
        throw new RecorderError('connection');
      }
      const stream = await navigator.mediaDevices.getUserMedia({audio: true});
      const current = live.current;
      current.stream = stream;
      current.finalText = '';
      current.interimText = '';

      const context = new AudioContext();
      const analyser = context.createAnalyser();
      analyser.fftSize = 256;
      context.createMediaStreamSource(stream).connect(analyser);
      current.context = context;
      const samples = new Uint8Array(analyser.frequencyBinCount);
      const tick = () => {
        analyser.getByteFrequencyData(samples);
        const average = samples.reduce((sum, v) => sum + v, 0) / samples.length;
        setLevel(Math.min(1, average / 96));
        current.frame = requestAnimationFrame(tick);
      };
      current.frame = requestAnimationFrame(tick);

      const recognition = new Recognition();
      recognition.lang = language === 'en' ? 'en-US' : 'es-ES';
      recognition.continuous = true;
      recognition.interimResults = true;
      recognition.onresult = (event) => {
        let interim = '';
        for (let i = event.resultIndex; i < event.results.length; i += 1) {
          const result = event.results[i]!;
          if (result.isFinal) {
            current.finalText =
              `${current.finalText} ${result[0].transcript}`.trim();
          } else {
            interim += result[0].transcript;
          }
        }
        current.interimText = interim.trim();
        setTranscript(`${current.finalText} ${current.interimText}`.trim());
      };
      recognition.onerror = (event) => {
        const key = SPEECH_ERRORS[event.error];
        if (key) {
          fail(new RecorderError(key));
        }
      };
      recognition.onend = () => {
        if (current.recording) {
          // The browser stops listening after a silence: keep going until the respondent taps stop.
          try {
            recognition.start();
          } catch {
            // already restarting
          }
          return;
        }
        current.onEnded?.();
      };
      current.recognition = recognition;
      current.recording = true;
      recognition.start();

      const startedAt = Date.now();
      current.timer = window.setInterval(
        () => setElapsed(Date.now() - startedAt),
        250,
      );
      setStatus('recording');
    } catch (cause) {
      fail(cause);
    }
  }, [fail, language]);

  /** Stops recording and resolves with what was said (may be empty). */
  const stop = useCallback(async (): Promise<string> => {
    const current = live.current;
    const recognition = current.recognition;
    current.recording = false;
    if (recognition) {
      await new Promise<void>((resolve) => {
        const timeout = window.setTimeout(resolve, 1500);
        current.onEnded = () => {
          window.clearTimeout(timeout);
          resolve();
        };
        recognition.stop();
      });
    }
    current.recognition = null;
    current.onEnded = null;
    release();
    setStatus('idle');
    setLevel(0);
    return `${current.finalText} ${current.interimText}`.trim();
  }, [release]);

  return {
    status,
    error,
    level,
    elapsed,
    transcript,
    start,
    stop,
    clearError: () => setError(null),
  };
}
