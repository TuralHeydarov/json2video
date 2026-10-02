"""Worker connections with a shared PostgreSQL schema or legacy MySQL."""
import re

from app.config import Config


class PostgresConnection:
    def __init__(self, connection):
        self.connection = connection

    def cursor(self, dictionary=False):
        if dictionary:
            from psycopg.rows import dict_row
            return self.connection.cursor(row_factory=dict_row)
        return self.connection.cursor()

    def is_connected(self):
        return not self.connection.closed

    def close(self):
        self.connection.close()


def get_db_connection():
    if Config.DB_CONNECTION == 'pgsql':
        import psycopg
        schema = Config.DB_SCHEMA
        if not re.fullmatch(r'[a-z_][a-z0-9_]*', schema) or schema == 'public':
            raise ValueError('PostgreSQL requires a dedicated DB_SCHEMA')
        return PostgresConnection(psycopg.connect(
            host=Config.DB_HOST, port=Config.DB_PORT,
            dbname=Config.DB_DATABASE, user=Config.DB_USERNAME,
            password=Config.DB_PASSWORD, autocommit=True,
            options=f'-c search_path={schema},pg_catalog',
            connect_timeout=10,
        ))
    if Config.DB_CONNECTION == 'mysql':
        import mysql.connector
        return mysql.connector.connect(
            host=Config.DB_HOST, port=Config.DB_PORT,
            database=Config.DB_DATABASE, user=Config.DB_USERNAME,
            password=Config.DB_PASSWORD, autocommit=True,
        )
    raise ValueError('Unsupported DB_CONNECTION')
