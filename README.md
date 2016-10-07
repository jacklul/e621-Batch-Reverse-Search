# e621 Batch Reverse Search [![Build Status](https://travis-ci.org/jacklul/e621-Batch-Reverse-Search.svg?branch=master)](https://travis-ci.org/jacklul/e621-Batch-Reverse-Search)

A script that iterates over a directory and performs a reverse search for every image using [iqdb.harry.lu](http://iqdb.harry.lu/) service.

## Requirements:

### Windows

Package comes with compiled PHP library (x86 Non Thread Safe) and [wfio](https://github.com/kenjiuno/php-wfio) extension, however you need **Visual C++ 2012 Redistributable (x86)** for it to run - https://www.microsoft.com/en-us/download/details.aspx?id=30679

### Linux

Install PHP library and cURL extension - for APT users: `sudo apt-get install php-cli php-curl`

## Usage:
- Put images into 'images' folder
- Run it with 'run.bat' ('run.sh' on linux)
- Wait, this can take a very long time, depending on how many images you got there...
- Matched images will be moved to 'found' folder, not matched images will be moved to 'not found' folder
- List file 'links.html' (in 'found' folder) will be created containing all the links, open it with a web browser

## Advanced
- You can pass any directory as an argument to the run script (on Windows you can move a directory over `run.bat`)
- Rename `config.cfg.example` to `config.cfg` to make the script use it, configure it how you want

## Contributing

See [CONTRIBUTING](https://github.com/jacklul/e621-Batch-Reverse-Search/blob/master/CONTRIBUTING.md) for more information.

## License

See [LICENSE](https://github.com/jacklul/e621-Batch-Reverse-Search/blob/master/LICENSE.md).
