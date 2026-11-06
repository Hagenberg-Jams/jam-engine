-- Each person can take part remotely. Seats on site count only the people on site.
ALTER TABLE registrations ADD COLUMN onsite_people INTEGER NOT NULL DEFAULT 0;
ALTER TABLE members ADD COLUMN remote INTEGER NOT NULL DEFAULT 0;
UPDATE registrations SET onsite_people = CASE WHEN wish = 'remote' THEN 0 ELSE people END;
UPDATE members SET remote = 1 WHERE registration_id IN (SELECT id FROM registrations WHERE wish = 'remote');
