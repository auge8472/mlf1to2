# Backup and upgrade script for My Little Forum 1.7 to My Little Forum 2.2 and higher

With this script you can backup the database content of an installation of My Little Forum 1.7.x. The backup files are ready for a reimport into a installation of My Little Forum, at least version 2.1.

## System requirements

- PHP up to version 5.6 (the script **will** currently **not work under PHP 7.x**!)

## Usage

1. Install a new My Little Forum of an actual version beside of the old installation of My Little Forum 1.7.x.
1. Unpack this script and load it up to your webspace into a new created directory.
1. Ensure, that the directory is writeable for the script.
1. Request the script with your browser.
1. Fill the form fields with …
    - … the database connection data,
    - … the table prefix of your installation of My Little Forum 1.7.x,
    - … the table prefix of your installation of My Little Forum 2.x and …
    - … a proper file name for the backup file, the script proposes an adequate file name itself.
1. When all necessary fields are filled, send the form data with the submit button. The script generated the backup file.
1. Import the data from the backup with a tool like phpMyAdmin or with the backup function of the installation of My Little Forum 2.x.

## Credits

- original author: @ilosuna
- reformatting, modernisation: @auge8472
- issue and bug findings: Irene König
