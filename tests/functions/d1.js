import { readFileSync } from 'node:fs';
import { DatabaseSync } from 'node:sqlite';

/**
 * A small stand-in for Cloudflare D1 on top of SQLite, with the methods the functions use.
 */
export function createD1() {
    const sqlite = new DatabaseSync(':memory:');
    sqlite.exec(readFileSync(new URL('../../resources/registration/migrations/0001_registrations.sql', import.meta.url), 'utf8'));

    const statement = (sql, params = []) => ({
        bind: (...values) => statement(sql, values),
        run: async () => sqlite.prepare(sql).run(...params),
        first: async () => sqlite.prepare(sql).get(...params) ?? null,
        all: async () => ({ results: sqlite.prepare(sql).all(...params) }),
    });

    return {
        sqlite,
        prepare: (sql) => statement(sql),
        batch: async (statements) => {
            sqlite.exec('BEGIN');
            try {
                for (const item of statements) {
                    await item.run();
                }
                sqlite.exec('COMMIT');
            } catch (error) {
                sqlite.exec('ROLLBACK');
                throw error;
            }
        },
    };
}
