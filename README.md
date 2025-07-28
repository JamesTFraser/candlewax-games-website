# Candlewax Games Website
The official website of Candlewax Games. Made with a bespoke PHP 8.3 framework implementing Twig 3 and Bootstrap 5.

The codebase is written to the PSR-12 standard and is both PHP Mess Detector and PHP Code Sniffer compliant (with a couple of exceptions which can be reviewed at /Config/Dev/PHPMD/ruleset.xml)

Implements the MVC design pattern along with a dependency injector for loose coupling and a Data Mapper database pattern.

Unit tests are stored in /Tests/Unit with more to come.


Installation

1. Clone the repository
2. Run 'composer install' in your CLI from the project root
3. Run 'npm install' in your CLI  from the project root
4. Copy the .env.example file and rename the copy .env (Make sure the .env file is saved in LF format to avoid extra invisible characters)
6. Update the the project root directory in the .env file (relative to your web server's root directory)
7. Create a database named 'candlewax_games'
8. Update the .env file with your local installations database details
9. Run the SQL in /database/sql targeting the newly created database
10. Ensure your web server has the correct folder permissions for the project
