"""Local Russian ASR for supplied recordings; output is unedited and stays local.

Install tools/recordings-requirements.txt in an isolated environment. FFmpeg
must be available. This produces ASR drafts, not speaker-attributed minutes.
"""
from pathlib import Path
import argparse
import hashlib
import importlib.metadata
import json
import subprocess
import wave

import numpy as np
from faster_whisper import WhisperModel


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('recordings', nargs='+', type=Path)
    parser.add_argument('--output', required=True, type=Path)
    parser.add_argument('--model', default='small')
    parser.add_argument('--model-cache', type=Path)
    parser.add_argument('--cpu-threads', type=int, default=6)
    args = parser.parse_args()
    if any(not p.is_file() for p in args.recordings):
        parser.error('Every recording must be an existing file')
    args.output.mkdir(parents=True, exist_ok=True)
    model = WhisperModel(args.model, device='cpu', compute_type='int8',
                         cpu_threads=args.cpu_threads,
                         download_root=str(args.model_cache) if args.model_cache else None)
    settings = dict(language='ru', beam_size=3, vad_filter=False,
                    condition_on_previous_text=False, word_timestamps=False)
    for number, path in enumerate(args.recordings, 1):
        stem = f'recording-{number:02d}'
        wav_path = args.output / (stem + '.wav')
        json_path = args.output / (stem + '-asr.json')
        if wav_path.exists() or json_path.exists():
            raise FileExistsError(f'Refusing to overwrite outputs for {stem}')
        subprocess.run(['ffmpeg', '-v', 'error', '-nostdin', '-n', '-i', str(path),
                        '-vn', '-ac', '1', '-ar', '16000', '-c:a', 'pcm_s16le',
                        str(wav_path)], check=True)
        with wave.open(str(wav_path), 'rb') as wav:
            assert (wav.getnchannels(), wav.getframerate(), wav.getsampwidth()) == (1, 16000, 2)
            audio = np.frombuffer(wav.readframes(wav.getnframes()), dtype='<i2').astype(np.float32) / 32768
        segments, info = model.transcribe(audio, **settings)
        digest = hashlib.sha256()
        with path.open('rb') as stream:
            for chunk in iter(lambda: stream.read(1024 * 1024), b''):
                digest.update(chunk)
        draft = {'original_name': path.name, 'original_sha256': digest.hexdigest(),
                 'audio_duration_seconds': info.duration, 'model': args.model,
                 'device': 'cpu', 'compute_type': 'int8', 'cpu_threads': args.cpu_threads,
                 'settings': settings, 'speaker_diarization': False,
                 'versions': {p: importlib.metadata.version(p) for p in
                              ['faster-whisper', 'ctranslate2', 'numpy']},
                 'edition': 'unedited-local-asr', 'segments': []}
        for s in segments:
            draft['segments'].append({'id': s.id, 'start': s.start, 'end': s.end,
                                      'text': s.text.strip(), 'avg_logprob': s.avg_logprob,
                                      'no_speech_prob': s.no_speech_prob})
            print(f'{path.name}: {s.end:.1f}/{info.duration:.1f}s', flush=True)
        json_path.write_text(json.dumps(draft, ensure_ascii=False, indent=2) + '\n')
        print(f'Saved local ASR draft: {json_path}', flush=True)


if __name__ == '__main__':
    main()
