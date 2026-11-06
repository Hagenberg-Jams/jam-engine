-- The study programme (or another affiliation from the list in the config) is asked for each person.
ALTER TABLE members ADD COLUMN program TEXT NOT NULL DEFAULT '';
