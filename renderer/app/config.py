"""
JSON2Video Render Engine — Configuration
"""
import os


class Config:
    # Redis
    REDIS_HOST = os.getenv('REDIS_HOST', 'localhost')
    REDIS_PORT = int(os.getenv('REDIS_PORT', 6379))
    REDIS_PREFIX = os.getenv('REDIS_PREFIX', 'laravel-database-')
    REDIS_QUEUE = REDIS_PREFIX + 'render:jobs'

    # Database
    DB_CONNECTION = os.getenv('DB_CONNECTION', 'mysql')
    DB_SCHEMA = os.getenv('DB_SCHEMA', 'json2video')
    DB_HOST = os.getenv('DB_HOST', 'localhost')
    DB_PORT = int(os.getenv('DB_PORT', 5432 if DB_CONNECTION == 'pgsql' else 3306))
    DB_DATABASE = os.getenv('DB_DATABASE', 'json2video')
    DB_USERNAME = os.getenv('DB_USERNAME', 'json2video')
    DB_PASSWORD = os.getenv('DB_PASSWORD', 'secret123')

    # Storage
    STORAGE_PATH = os.getenv('STORAGE_PATH', '/app/storage/renders')
    STORAGE_URL = os.getenv('STORAGE_URL', 'http://localhost:8000/renders')
    TEMP_DIR = os.getenv('RENDER_TEMP_DIR', '/tmp/renders')

    # Worker
    WORKER_CONCURRENCY = int(os.getenv('WORKER_CONCURRENCY', 2))
    WORKER_ID = os.getenv('HOSTNAME', 'worker-1')

    # Resolution presets
    RESOLUTIONS = {
        'sd': (854, 480),
        'hd': (1280, 720),
        'full-hd': (1920, 1080),
        '4k': (3840, 2160),
    }

    # Quality presets (CRF values for FFmpeg — lower = better quality)
    QUALITY_CRF = {
        'low': 28,
        'medium': 23,
        'high': 18,
    }
