from pathlib import Path
from zipfile import ZipFile
from faster_whisper import WhisperModel
import json
import csv
import shutil

# ----------------------------
# CONFIG
# ----------------------------

ZIP_FILE = "WhatsApp Ptt 2026-06-02 at 2.00.21 AM.zip"

OUTPUT_DIR = Path("output")
EXTRACT_DIR = Path("extracted_whatsapp_audio")

# Model choices:
# tiny, base, small, medium, large-v3
# For Urdu, "medium" is decent. "large-v3" is better but slower.
MODEL_SIZE = "small"

# Use "cpu" if you do not have NVIDIA GPU.
# Use "cuda" if you have NVIDIA GPU and CUDA working.
DEVICE = "cpu"

# For CPU:
COMPUTE_TYPE = "int8"

# Urdu language code
LANGUAGE = "ur"

# Set this True if you also want English translation.
CREATE_ENGLISH_TRANSLATION = True


def clean_folder(folder: Path):
    if folder.exists():
        shutil.rmtree(folder)
    folder.mkdir(parents=True, exist_ok=True)


def extract_zip(zip_path: Path, extract_to: Path):
    print(f"Extracting: {zip_path}")
    with ZipFile(zip_path, "r") as zip_ref:
        zip_ref.extractall(extract_to)


def find_audio_files(folder: Path):
    audio_extensions = [".ogg", ".opus", ".mp3", ".wav", ".m4a", ".aac"]
    files = []

    for ext in audio_extensions:
        files.extend(folder.rglob(f"*{ext}"))

    return sorted(files)


def transcribe_file(model, audio_path: Path, task: str = "transcribe"):
    segments, info = model.transcribe(
        str(audio_path),
        language=LANGUAGE,
        task=task,
        beam_size=5,
        vad_filter=True,
        vad_parameters=dict(min_silence_duration_ms=500),
    )

    text_parts = []
    segment_rows = []

    for segment in segments:
        text = segment.text.strip()
        if not text:
            continue

        text_parts.append(text)
        segment_rows.append({
            "start": round(segment.start, 2),
            "end": round(segment.end, 2),
            "text": text,
        })

    return {
        "language": info.language,
        "language_probability": info.language_probability,
        "duration": getattr(info, "duration", None),
        "text": " ".join(text_parts).strip(),
        "segments": segment_rows,
    }


def save_outputs(results):
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

    # JSON output
    json_path = OUTPUT_DIR / "transcripts.json"
    with open(json_path, "w", encoding="utf-8") as f:
        json.dump(results, f, ensure_ascii=False, indent=2)

    # CSV output
    csv_path = OUTPUT_DIR / "transcripts.csv"
    with open(csv_path, "w", encoding="utf-8-sig", newline="") as f:
        writer = csv.writer(f)
        writer.writerow([
            "file_name",
            "urdu_transcript",
            "english_translation",
            "language",
            "language_probability",
        ])

        for item in results:
            writer.writerow([
                item["file_name"],
                item.get("urdu_transcript", ""),
                item.get("english_translation", ""),
                item.get("language", ""),
                item.get("language_probability", ""),
            ])

    # Markdown report
    md_path = OUTPUT_DIR / "combined_transcripts.md"
    with open(md_path, "w", encoding="utf-8") as f:
        f.write("# WhatsApp Voice Note Transcripts\n\n")

        for index, item in enumerate(results, start=1):
            f.write(f"## {index}. {item['file_name']}\n\n")

            f.write("### Urdu Transcript\n\n")
            f.write(item.get("urdu_transcript", "").strip() or "_No transcript generated._")
            f.write("\n\n")

            if CREATE_ENGLISH_TRANSLATION:
                f.write("### English Translation\n\n")
                f.write(item.get("english_translation", "").strip() or "_No translation generated._")
                f.write("\n\n")

            f.write("---\n\n")

    print("\nSaved outputs:")
    print(f"- {json_path}")
    print(f"- {csv_path}")
    print(f"- {md_path}")


def main():
    zip_path = Path(ZIP_FILE)

    if not zip_path.exists():
        raise FileNotFoundError(
            f"Zip file not found: {zip_path}\n"
            f"Put the zip file in the same folder as this script, or update ZIP_FILE."
        )

    clean_folder(EXTRACT_DIR)
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

    extract_zip(zip_path, EXTRACT_DIR)

    audio_files = find_audio_files(EXTRACT_DIR)

    if not audio_files:
        print("No audio files found.")
        return

    print(f"Found {len(audio_files)} audio files.")

    print(f"Loading Whisper model: {MODEL_SIZE}")
    model = WhisperModel(
        MODEL_SIZE,
        device=DEVICE,
        compute_type=COMPUTE_TYPE,
    )

    results = []

    for i, audio_path in enumerate(audio_files, start=1):
        print(f"\n[{i}/{len(audio_files)}] Transcribing: {audio_path.name}")

        try:
            urdu_result = transcribe_file(
                model=model,
                audio_path=audio_path,
                task="transcribe",
            )

            english_translation = ""

            if CREATE_ENGLISH_TRANSLATION:
                print(f"[{i}/{len(audio_files)}] Translating to English: {audio_path.name}")

                translation_result = transcribe_file(
                    model=model,
                    audio_path=audio_path,
                    task="translate",
                )

                english_translation = translation_result["text"]

            results.append({
                "file_name": audio_path.name,
                "file_path": str(audio_path),
                "language": urdu_result["language"],
                "language_probability": urdu_result["language_probability"],
                "duration": urdu_result["duration"],
                "urdu_transcript": urdu_result["text"],
                "english_translation": english_translation,
                "segments": urdu_result["segments"],
            })

        except Exception as e:
            print(f"Error processing {audio_path.name}: {e}")

            results.append({
                "file_name": audio_path.name,
                "file_path": str(audio_path),
                "error": str(e),
                "urdu_transcript": "",
                "english_translation": "",
            })

    save_outputs(results)

    print("\nDone.")


if __name__ == "__main__":
    main()
