Welcome to HNWebCore
====================
This project is a fairly minimal framework designed to make creating a secure, fast custom PHP site with a very small amount of overhead work.

Authors and Contributors
------------------------
This codebase was primarily made by Hugh Nougher (@hnougher) with assistance from Daniel Scott (@dysfunctional16) who is also the main user of it.

Support or Contact
------------------
If you have a problem with using this framework or want to see a new feature you should try using the GitHub tracker for the repo. If you want pointers in how to use the framework, feel free to contact me through GitHub.

Summary of Installation
-----------------------
1. Clone this reposititory to a webserver location.
2. Modify config.php with server name and hostname.
3. Get URL rewriting working by modifying .htaccess, web.config or similar.
4. Install PEAR.
5. Add pear modules Mail, Net_SMTP and Mail_Mime for use through HNMail.
5. Add the MDB2 module at version 2.4.1.
6. Add a MDB2 DB module like mysqli at version 2.4.1.
  Note: Only certain DB modules have wrappers atm. You can see them in /classes/HNDB.hn*.class.php.
7. Setup the database with the table structure you want.
  Note: A very simple 'user' table is outlined in hnwc.sql. It can give clues on how the authentication is setup by default.
8. Try it out.
