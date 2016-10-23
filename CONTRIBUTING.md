# How to contribute:

- [Fork](https://github.com/jacklul/e621-Batch-Reverse-Search/fork) this repository

- See [base requirements](https://github.com/jacklul/e621-Batch-Reverse-Search/blob/master/README.md), and install them if needed

- Install development dependencies with Composer - `composer install`

- Make your changes to source files in `src/` directory

- Run [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) - `vendor/bin/phpcs --standard=phpcs.xml.dist -sp --encoding=utf-8 src/ --report-width=150`

- If it finds any errors or warnings please correct them before moving on to the next step 

    (some can be fixed automatically with [PHP Code Beautifier and Fixer](https://github.com/squizlabs/PHP_CodeSniffer/wiki/Fixing-Errors-Automatically) - `vendor/bin/phpcbf --no-patch src/`

- Build `phar` file by running `php build.php`

- Run the run script from inside `build/` directory and test your changes

- If everything works fine push your changes to your fork then [create a pull request](https://github.com/jacklul/e621-Batch-Reverse-Search/compare)
