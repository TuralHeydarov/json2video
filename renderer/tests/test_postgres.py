"""Database-only rehearsal: no render, external API, queue or webhook calls."""
import os
import unittest

from app.config import Config
from app.database import get_db_connection


class PostgresRehearsal(unittest.TestCase):
    def test_status_progress_and_webhook_lookup_in_selected_schema(self):
        self.assertEqual(Config.DB_CONNECTION, 'pgsql')
        db = get_db_connection()
        try:
            cursor = db.cursor()
            cursor.execute("INSERT INTO render_jobs (id, user_id, payload, status) VALUES (%s, %s, %s, %s)",
                           ('00000000-0000-4000-8000-000000000001', 1, '{}', 'queued'))
            cursor.execute('UPDATE render_jobs SET status=%s, progress=%s, file_size_bytes=%s WHERE id=%s',
                           ('done', 100, 5000000000, '00000000-0000-4000-8000-000000000001'))
            cursor.close()
            cursor = db.cursor(dictionary=True)
            cursor.execute('SELECT status,progress,file_size_bytes FROM render_jobs WHERE id=%s',
                           ('00000000-0000-4000-8000-000000000001',))
            self.assertEqual(cursor.fetchone(), dict(status='done', progress=100, file_size_bytes=5000000000))
            cursor.execute('SELECT url FROM webhook_configs WHERE user_id=%s AND is_active=TRUE', (1,))
            self.assertEqual(cursor.fetchone()['url'], 'https://invalid.test/webhook')
            cursor.close()
        finally:
            db.close()

    def test_rejects_public_or_injected_schema(self):
        original = Config.DB_SCHEMA
        try:
            for schema in ['public', 'json2video,brain', 'json2video;DROP SCHEMA brain']:
                Config.DB_SCHEMA = schema
                with self.assertRaises(ValueError):
                    get_db_connection()
        finally:
            Config.DB_SCHEMA = original


if __name__ == '__main__':
    unittest.main()
