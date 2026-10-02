"""Round-trip an offline fixture through the actual PostgreSQL migration schema."""
import json
import os
from pathlib import Path
import sys
import unittest

import psycopg
from psycopg import sql
from psycopg.rows import dict_row

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / 'tools'))
from import_mysql_snapshot import TABLES, load_snapshot


class ImportRehearsal(unittest.TestCase):
    def test_roundtrip_and_refuses_overwrite(self):
        with psycopg.connect(host=os.environ['DB_HOST'], port=os.environ['DB_PORT'],
                             dbname=os.environ['DB_DATABASE'], user=os.environ['DB_USERNAME'],
                             password=os.environ['DB_PASSWORD'], autocommit=True) as db:
            snapshot = {}
            for table in TABLES:
                with db.cursor(row_factory=dict_row) as cursor:
                    cursor.execute(sql.SQL('SELECT * FROM {}').format(sql.Identifier('json2video', table)))
                    rows = cursor.fetchall()
                # Match the MySQL export contract: JSON columns are strings,
                # timestamps, decimals and UUIDs use their lossless text form.
                for row in rows:
                    for key, value in row.items():
                        if isinstance(value, (dict, list)):
                            row[key] = json.dumps(value)
                snapshot[table] = json.loads(json.dumps(rows, default=str))
            counts = load_snapshot(snapshot, db, 'json2video_import')
            self.assertEqual(counts, {table: len(rows) for table, rows in snapshot.items()})
            self.assertEqual(db.execute("SELECT nextval('json2video_import.users_id_seq')").fetchone()[0], 2)
            with self.assertRaisesRegex(ValueError, 'not empty'):
                load_snapshot(snapshot, db, 'json2video_import')
            self.assertEqual(db.execute('SELECT count(*) FROM json2video_import.users').fetchone()[0], 1)


if __name__ == '__main__':
    unittest.main()
