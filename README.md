Seed files for Locart

Files:
- seed_shoprequest.sql : SQL inserts for 5 sample shop request rows into `shoprequest` table.
- seed_shoprequest_more_default_status.sql : Additional 5 inserts that omit the `status` column so the table default applies.
- seed_shoprequest_user_rows.sql : Inserts the 4 user-provided rows (from your pasted content).
- ../scripts/seed_shoprequests.php : PHP script that runs the SQL file against `locart_db` using mysqli. To run a different SQL file edit the `$seedFile` variable in the script.

How to run:

From command line (Windows PowerShell), run:

php .\scripts\seed_shoprequests.php

To run the other seed file (`seed_shoprequest_more_default_status.sql`) either edit the `$seedFile` path at the top of `scripts/seed_shoprequests.php` or run the SQL directly from your database client/CLI.

Or place the script in a web-accessible folder and open `scripts/seed_shoprequests.php` in your browser (ensure your webserver user can access the DB).

Notes:
- The SQL assumes your `shoprequest` table has the columns: shopid, name, ownerid, type, contact_no1, contact_no2, verification_id, city, pincode, status, password, about, request_received_date.
- If your schema differs, edit `seed_shoprequest.sql` to match your column names/types before running.
- Always backup your DB before running seed scripts on production data.
