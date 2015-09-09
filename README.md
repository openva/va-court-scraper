# Virginia Court Scraper

Scrapes court decisions from [the Court of Appeals of Virginia Published Opinions webpage](http://www.courts.state.va.us/txtcap.htm) and inserts them into a database. This is in a VERY early state, and is all but guaranteed not to work upon the first attempt.

Requires HTML Purifier and MDB2, though the HTML Purifier requirement could be circumvented pretty easily.

## Instructions
* Create the database structure by loading in `create.sql`.
* Set the MySQL authentication information in the DSN string at the head of `scraper.php`.
* Run in a browser or at the command line (`php scraper.php`).

## To Do
* Add functionality to scrape decisions from the Supreme Court of Virginia website, which stores decisions as PDFs, rather than as plain text.
* Make sure this will work on a first run, with a fresh database. (If it doesn't, that should be pretty easy to hack around.)
* Fix the prepared statement problem that prevents the INSERT from working.
* Resolve the conceptual conflict about dealing with duplicates.

Released under the MIT license.
