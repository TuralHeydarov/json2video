"""Read a consistent application snapshot without credentials in argv/output."""
import argparse
import json
import os

import mysql.connector

TABLES = ('plans', 'users', 'api_keys', 'render_jobs', 'templates', 'transcribe_jobs',
          'usage_logs', 'webhook_configs', 'plan_requests', 'password_reset_tokens',
          'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs')


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('output')
    args = parser.parse_args()
    database = mysql.connector.connect(
        host=os.environ['MYSQL_HOST'], port=int(os.getenv('MYSQL_PORT', '3306')),
        database=os.environ['MYSQL_DATABASE'], user=os.environ['MYSQL_USER'],
        password=os.environ['MYSQL_PASSWORD'],
    )
    database.start_transaction(consistent_snapshot=True, readonly=True)
    try:
        cursor = database.cursor(dictionary=True)
        snapshot = {}
        for table in TABLES:
            cursor.execute(f'SELECT * FROM `{table}`')
            snapshot[table] = cursor.fetchall()
        # Refuse to replace a previous backup. Restrict permissions at creation.
        descriptor = os.open(args.output, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
        with os.fdopen(descriptor, 'w') as output:
            json.dump(snapshot, output, default=str)
        print(json.dumps({table: len(rows) for table, rows in snapshot.items()}, sort_keys=True))
    finally:
        database.rollback()
        database.close()


if __name__ == '__main__':
    main()
