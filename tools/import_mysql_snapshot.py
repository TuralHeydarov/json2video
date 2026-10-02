"""Load an allowlisted JSON snapshot into an EMPTY PostgreSQL app schema.

Run only against a rehearsed, isolated schema using that application's role.
Never executes SQL from a MySQL dump and never imports unknown tables.
"""
import argparse
import json
import os
import re
import hashlib
from datetime import datetime

import psycopg
from psycopg import sql

TABLES = (
    'plans', 'users', 'api_keys', 'render_jobs', 'templates', 'transcribe_jobs',
    'usage_logs', 'webhook_configs', 'plan_requests', 'password_reset_tokens',
    'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
)


def normalized(row, columns):
    result = {}
    for key, value in row.items():
        if value is not None:
            kind = columns[key]
            if kind == 'boolean':
                if value not in (0, 1, False, True):
                    raise ValueError('Invalid boolean')
                value = bool(value)
            elif kind in ('json', 'jsonb') and isinstance(value, str):
                value = json.loads(value)
            elif kind.startswith('timestamp'):
                value = (datetime.fromisoformat(value) if isinstance(value, str) else value).isoformat()
            elif kind in ('numeric', 'decimal'):
                value = str(value)
            elif kind == 'uuid':
                value = str(value)
        result[key] = value
    return result


def fingerprint(rows, columns):
    hashes = [hashlib.sha256(json.dumps(normalized(row, columns), sort_keys=True,
                                      separators=(',', ':'), default=str).encode()).hexdigest()
              for row in rows]
    return hashlib.sha256(''.join(sorted(hashes)).encode()).hexdigest()


def load_snapshot(snapshot, connection, schema):
    if not re.fullmatch(r'[a-z_][a-z0-9_]*', schema) or schema == 'public':
        raise ValueError('Dedicated schema required')
    if set(snapshot) != set(TABLES):
        raise ValueError('Snapshot table set does not match the application allowlist')
    counts = {}
    with connection.transaction():
        for table in TABLES:
            name = sql.Identifier(schema, table)
            if connection.execute(sql.SQL('SELECT count(*) FROM {}').format(name)).fetchone()[0]:
                raise ValueError(f'Target table is not empty: {table}')
            columns = dict(connection.execute(
                'SELECT column_name,data_type FROM information_schema.columns WHERE table_schema=%s AND table_name=%s',
                (schema, table)).fetchall())
            rows = snapshot[table]
            for row in rows:
                if set(row) != set(columns):
                    raise ValueError(f'Column mismatch in {table}')
                keys = list(row)
                values = []
                for key in keys:
                    value = row[key]
                    if value is not None and columns[key] == 'boolean':
                        if value not in (0, 1, False, True):
                            raise ValueError(f'Invalid boolean in {table}.{key}')
                        value = bool(value)
                    values.append(value)
                query = sql.SQL('INSERT INTO {} ({}) VALUES ({})').format(
                    name, sql.SQL(',').join(map(sql.Identifier, keys)),
                    sql.SQL(',').join(sql.Placeholder() for _ in keys))
                connection.execute(query, values)
            actual = connection.execute(sql.SQL('SELECT count(*) FROM {}').format(name)).fetchone()[0]
            if actual != len(rows):
                raise ValueError(f'Count mismatch in {table}')
            keys = list(columns)
            restored = connection.execute(sql.SQL('SELECT {} FROM {}').format(
                sql.SQL(',').join(map(sql.Identifier, keys)), name)).fetchall()
            if fingerprint(rows, columns) != fingerprint([dict(zip(keys, row)) for row in restored], columns):
                raise ValueError(f'Value fingerprint mismatch in {table}')
            counts[table] = actual
        # Restore SERIAL counters only after every row/foreign key was accepted.
        for table in TABLES:
            for column, sequence in connection.execute(
                "SELECT column_name,pg_get_serial_sequence(format('%%I.%%I',table_schema,table_name),column_name) "
                'FROM information_schema.columns WHERE table_schema=%s AND table_name=%s', (schema, table)).fetchall():
                if sequence:
                    maximum = connection.execute(sql.SQL('SELECT max({}) FROM {}').format(
                        sql.Identifier(column), sql.Identifier(schema, table))).fetchone()[0]
                    connection.execute('SELECT setval(%s,%s,%s)', (sequence, maximum or 1, maximum is not None))
    return counts


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('snapshot')
    parser.add_argument('--schema', required=True)
    args = parser.parse_args()
    with open(args.snapshot) as source:
        snapshot = json.load(source)
    # libpq environment / PGSERVICE file: no DSNs or passwords in argv/logs.
    with psycopg.connect('') as connection:
        print(json.dumps(load_snapshot(snapshot, connection, args.schema), sort_keys=True))


if __name__ == '__main__':
    main()
