# lang-pl_PL

### READ THIS WHEN YOU WANT TO MAKE TRANSLATIONS TO ANOTHER LANGUAGE

There are sixteen text files included in this repository, each with their own section of translations:

- translations.txt -- these are general translations and loaded each time
- helptext.txt     -- these are all help text sections and loaded each time
- dashboard.txt    -- these are translations for the dashboard section
- main.txt         -- these are translations for the main section
- shares.txt       -- these are translations for the shares section
- users.txt        -- these are translations for the users section
- settings.txt     -- these are translations for the settings section
- plugins.txt      -- these are translations for the plugins section
- docker.txt       -- these are translations for the docker section
- vms.txt          -- these are translations for the vms section
- tools.txt        -- these are translations for the tools section
- javascript.txt   -- these are translations for javascript scripts
- scripts.txt      -- these are translations for local scripts
- apps.txt         -- these are translations for the CA section
- ca_settings      -- these are translations for the CA settings
- javascript.ca.txt-- these are translations for the CA javascript

All file names are in lowercase and should be included in the repository to make the translations complete.

Removing a particular file, means no translations will be available for that section and the GUI will display text in original English.

### TRANSLATIONS

Each text file contains regular text strings stored in UTF-8 format with linux line-endings.
Use a text editor which supports UTF-8 and linux format, like [notepad++](https://notepad-plus-plus.org/downloads)

The content of each text file is separated into two parts

### PART 1

These are single line entries which are in the format:

`original English text=translated Foreign text`

Only modify the text after the equal sign(=) and leave the original English text at the left untouched.
Removing a line or omitting a translation after the equal sign, results in the GUI displaying this line with the original English text.

The translated text may have 'special characters', such as slashes, parenthesis or brackets which are not included in the key text,
but which are used to display text accordingly. E.g.

`Options see Help=Options (see Help)`

The characters \* and \*\* are used to display text in italics and bold respectively. E.g.

`Array must be Stopped to change=Array must be **Stopped** to change`

It is recommended to make translations per section, that is one file at the time, and verify the correctness of the translations in the GUI
before proceeding with the next section.

Keep in mind the length of the translations and try to make them similar length as the original text and avoid space issues in the GUI.

### PART 2

These are multi line entries used to translate multiple lines at once.
Multi line translations have a unique opening and closing tag:

**:unique_tag_name_plug:** - unique opening tag used for any multi line text section

**:end**    - closing tag

Do not remove or alter these tags and only translate the text between the opening and closing tags!

### HELP TEXT

All help text of the GUI is stored in a single file *helptext.txt*.

This file has multiple help text sections. Each section is enclosed by a unique opening tag and corresponding closing tag.

**:unique_tag_name_help:** - unique opening tag used for a help text section

**:end**    - corresponding closing tag

Do not remove or alter these tags and only translate the text between the opening and closing tags!

Be aware that Markdown styling syntax is used, this must be preserved.

### LOCAL TESTING

Once the translations are complete and you want to test locally the (intermediate) results, the text files need to be zipped into a single ZIP file.
Give the ZIP file the name of your language, e.g. French.zip.

In the GUI go to: Tools -> webGUI -> Language (switch to Developer view)

- By default only the English language is installed (built-in)
- Choose the ZIP file you have created earlier as the source file
- If the language name is recognized, it will be automatically selected, otherwise chose the name of the language from the dropdown menu to install.
- Click on "Upload" will add your translations to the GUI under the selected language name

NOTE: If your language is not available from the dropdown menu, please make a request on the [Unraid forum](https://forums.unraid.net/forum/75-multi-language-section/)

Now your language is available for local testing!

In the GUI go to: Settings -> Display Settings -> Language

- Select the preferred language from the dropdown menu. Note that only the available language choices are displayed here.

### GITHUB

A language repository will be made available at [Github](https://github.com/unraid), where translators can create Pull Requests (PR) and submit their work.

Once you are satisfied with your results, use Github (an account is required) to fork the respective language repository and create a PR with your modifications.

Limetech will review this and merge your work when approved.

### UPDATES

When updated source text files in English become available in the future, these updates will be made available through Github.

Translators can use the Github system to see which changes are made and update their translations accordingly.

### CREDITS

Your efforts are much welcomed and to show our appreciation, your name and language are credited on the Credits page under Tools in the GUI.
Please let us know which credentials to use.

Thank you very much!
