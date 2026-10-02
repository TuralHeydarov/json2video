"""
JSON2Video Render Engine — Redis Queue Worker

Listens to the Redis 'render:jobs' queue for new render jobs,
processes them using the render engine, and updates job status in database.
"""
import json
import logging
import os
import signal
import sys
import time
import traceback

# Fix Pillow 10.x compatibility (ANTIALIAS removed, MoviePy still uses it)
import PIL.Image
if not hasattr(PIL.Image, 'ANTIALIAS'):
    PIL.Image.ANTIALIAS = PIL.Image.LANCZOS

import redis
from app.database import get_db_connection

from app.config import Config
from app.renderer.engine import RenderEngine
from app.services.transcriber import transcribe_to_srt, extract_audio, transcribe_from_audio
from app.utils.downloader import download_asset

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(name)s: %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S',
)
logger = logging.getLogger('worker')

# Graceful shutdown
running = True


def signal_handler(sig, frame):
    global running
    logger.info('Received shutdown signal. Finishing current job...')
    running = False


signal.signal(signal.SIGINT, signal_handler)
signal.signal(signal.SIGTERM, signal_handler)


def update_job_status(db, job_id: str, status: str, **kwargs):
    """Update a render job's status and additional fields in the database."""
    cursor = db.cursor()

    set_clauses = ['status = %s']
    values = [status]

    for key, value in kwargs.items():
        set_clauses.append(f'{key} = %s')
        # Convert numpy types to native Python types
        if hasattr(value, 'item'):
            value = value.item()
        elif isinstance(value, float) and not isinstance(value, (int, str)):
            value = float(value)
        values.append(value)

    values.append(job_id)

    query = f"UPDATE render_jobs SET {', '.join(set_clauses)} WHERE id = %s"
    cursor.execute(query, values)
    cursor.close()


def process_job(job_data: dict, db):
    """Process a single render job."""
    job_id = job_data['job_id']
    logger.info(f'Processing job: {job_id}')

    # Mark as processing
    update_job_status(
        db, job_id, 'processing',
        worker_id=Config.WORKER_ID,
        started_at=time.strftime('%Y-%m-%d %H:%M:%S'),
        progress=0,
    )

    try:
        # Initialize the render engine
        engine = RenderEngine(
            job_id=job_id,
            payload=job_data['payload'],
            resolution=job_data.get('resolution', 'full-hd'),
            quality=job_data.get('quality', 'high'),
            db=db,
        )

        # Run the render
        result = engine.render()

        # Mark as done
        update_job_status(
            db, job_id, 'done',
            progress=100,
            output_path=result['output_path'],
            output_url=result['output_url'],
            thumbnail_path=result.get('thumbnail_path'),
            duration_seconds=result['duration_seconds'],
            file_size_bytes=result['file_size_bytes'],
            completed_at=time.strftime('%Y-%m-%d %H:%M:%S'),
        )

        # Send webhook notification
        send_webhook(db, job_id, 'done', job_data, result=result)

        logger.info(f'Job {job_id} completed successfully. Output: {result["output_url"]}')

    except Exception as e:
        error_msg = str(e)
        error_trace = traceback.format_exc()
        logger.error(f'Job {job_id} failed: {error_msg}\n{error_trace}')

        update_job_status(
            db, job_id, 'failed',
            error_message=error_msg[:2000],
            error_code='RENDER_ERROR',
            completed_at=time.strftime('%Y-%m-%d %H:%M:%S'),
        )

        # Send webhook notification for failure
        send_webhook(db, job_id, 'failed', job_data, error=error_msg)


def process_transcribe_job(job_data: dict, db):
    """Process a single transcription job."""
    job_id = job_data['job_id']
    src_url = job_data['src_url']
    language = job_data.get('language')  # None = auto-detect
    logger.info(f'Processing transcribe job: {job_id} (language={language or "auto"})')

    # Mark as processing
    cursor = db.cursor()
    cursor.execute(
        'UPDATE transcribe_jobs SET status = %s, worker_id = %s, started_at = %s WHERE id = %s',
        ('processing', Config.WORKER_ID, time.strftime('%Y-%m-%d %H:%M:%S'), job_id)
    )
    cursor.close()

    temp_dir = os.path.join(Config.TEMP_DIR, f'transcribe_{job_id}')
    os.makedirs(temp_dir, exist_ok=True)

    try:
        # Download the source file
        logger.info(f'Downloading source: {src_url}')
        local_path = download_asset(src_url, temp_dir)

        # Detect type by extension
        ext = os.path.splitext(local_path)[1].lower()
        video_exts = {'.mp4', '.webm', '.mov', '.avi', '.mkv', '.flv', '.wmv'}

        if ext in video_exts:
            # Extract audio from video first
            logger.info('Source is video, extracting audio...')
            audio_path = os.path.join(temp_dir, 'extracted_audio.wav')
            extract_audio(local_path, audio_path)
        else:
            audio_path = local_path

        # Transcribe to SRT (with optional language)
        srt_temp_path = os.path.join(temp_dir, f'{job_id}.srt')
        transcribe_to_srt(audio_path, srt_temp_path, language=language)

        # Get language info (quick detect, model is cached)
        from app.services.transcriber import _get_model
        model = _get_model()
        detect_kwargs = {'beam_size': 1}
        if language:
            detect_kwargs['language'] = language
        _, info = model.transcribe(audio_path, **detect_kwargs)
        detected_language = info.language
        language_confidence = round(info.language_probability, 2)

        # Count segments
        with open(srt_temp_path, 'r', encoding='utf-8') as f:
            segment_count = sum(1 for line in f if line.strip() and line.strip().isdigit())

        # Copy SRT to permanent storage
        srt_storage_dir = os.path.join(Config.STORAGE_PATH, 'srt')
        os.makedirs(srt_storage_dir, exist_ok=True)
        srt_final_path = os.path.join(srt_storage_dir, f'{job_id}.srt')

        import shutil
        shutil.copy2(srt_temp_path, srt_final_path)

        # Build public URL
        srt_url = f'{Config.STORAGE_URL}/srt/{job_id}.srt'

        # Calculate expiry (1 hour from now)
        from datetime import datetime, timedelta
        expires_at = (datetime.now() + timedelta(hours=1)).strftime('%Y-%m-%d %H:%M:%S')

        # Update job as done
        cursor = db.cursor()
        cursor.execute(
            '''UPDATE transcribe_jobs
               SET status = %s, language = %s, language_confidence = %s,
                   segments = %s, srt_path = %s, srt_url = %s,
                   completed_at = %s, expires_at = %s
               WHERE id = %s''',
            ('done', detected_language, language_confidence, segment_count,
             srt_final_path, srt_url,
             time.strftime('%Y-%m-%d %H:%M:%S'), expires_at, job_id)
        )
        cursor.close()

        logger.info(f'Transcribe job {job_id} completed: {segment_count} segments, '
                    f'language={language} ({language_confidence}), URL: {srt_url}')

    except Exception as e:
        error_msg = str(e)
        logger.error(f'Transcribe job {job_id} failed: {error_msg}\n{traceback.format_exc()}')

        cursor = db.cursor()
        cursor.execute(
            'UPDATE transcribe_jobs SET status = %s, error_message = %s, completed_at = %s WHERE id = %s',
            ('failed', error_msg[:2000], time.strftime('%Y-%m-%d %H:%M:%S'), job_id)
        )
        cursor.close()

    finally:
        # Cleanup temp directory
        import shutil
        if os.path.exists(temp_dir):
            shutil.rmtree(temp_dir, ignore_errors=True)


def send_webhook(db, job_id: str, status: str, job_data: dict, **kwargs):
    """Send webhook notification if configured."""
    import requests as http_requests

    try:
        user_id = job_data.get('user_id')
        webhook_url = job_data.get('webhook_url')

        # Check user's webhook config if no direct URL
        if not webhook_url and user_id:
            cursor = db.cursor(dictionary=True)
            cursor.execute(
                'SELECT url FROM webhook_configs WHERE user_id = %s AND is_active = TRUE',
                (user_id,)
            )
            row = cursor.fetchone()
            cursor.close()
            if row:
                webhook_url = row['url']

        if not webhook_url:
            return

        payload = {
            'event': f'render.{status}',
            'job_id': job_id,
            'status': status,
            'timestamp': time.strftime('%Y-%m-%dT%H:%M:%SZ'),
        }

        if status == 'done' and 'result' in kwargs:
            result = kwargs['result']
            payload['url'] = result.get('output_url')
            payload['duration'] = result.get('duration_seconds')
            payload['size_bytes'] = result.get('file_size_bytes')

        if status == 'failed' and 'error' in kwargs:
            payload['error'] = kwargs['error'][:500]

        http_requests.post(webhook_url, json=payload, timeout=10)
        logger.info(f'Webhook sent to {webhook_url} for job {job_id}')

    except Exception as e:
        logger.warning(f'Webhook failed for job {job_id}: {e}')


def main():
    """Main worker loop — listen to Redis queue and process jobs."""
    logger.info(f'🚀 JSON2Video Render Worker starting (ID: {Config.WORKER_ID})')
    logger.info(f'   Redis: {Config.REDIS_HOST}:{Config.REDIS_PORT}')
    logger.info(f'   database: {Config.DB_HOST}:{Config.DB_PORT}/{Config.DB_DATABASE}')
    logger.info(f'   Storage: {Config.STORAGE_PATH}')

    # Connect to Redis
    r = redis.Redis(
        host=Config.REDIS_HOST,
        port=Config.REDIS_PORT,
        decode_responses=True,
    )

    # Wait for Redis to be ready
    while running:
        try:
            r.ping()
            logger.info('Connected to Redis ✅')
            break
        except redis.ConnectionError:
            logger.warning('Waiting for Redis...')
            time.sleep(2)

    # Connect to database
    db = None
    while running:
        try:
            db = get_db_connection()
            logger.info('Connected to database ✅')
            break
        except Exception as e:
            logger.warning(f'Waiting for database... ({e})')
            time.sleep(3)

    if not running or not db:
        return

    render_queue = Config.REDIS_QUEUE
    transcribe_queue = Config.REDIS_PREFIX + 'transcribe:jobs'

    logger.info(f'Listening for jobs on queues: {render_queue}, {transcribe_queue}')

    while running:
        try:
            # BLPOP on both queues (render gets priority by being first)
            result = r.blpop([render_queue, transcribe_queue], timeout=5)

            if result is None:
                continue  # Timeout, loop again

            queue_name, raw_message = result
            job_data = json.loads(raw_message)

            # Ensure DB connection is alive
            if not db.is_connected():
                db = get_db_connection()

            # Route to correct processor based on which queue
            if 'transcribe' in queue_name:
                process_transcribe_job(job_data, db)
            else:
                process_job(job_data, db)

        except redis.ConnectionError:
            logger.error('Lost Redis connection. Reconnecting...')
            time.sleep(2)
            try:
                r = redis.Redis(
                    host=Config.REDIS_HOST,
                    port=Config.REDIS_PORT,
                    decode_responses=True,
                )
            except Exception:
                pass

        except Exception as e:
            logger.error(f'Unexpected error: {e}\n{traceback.format_exc()}')
            time.sleep(1)

    logger.info('Worker shutting down.')
    if db and db.is_connected():
        db.close()


if __name__ == '__main__':
    main()
