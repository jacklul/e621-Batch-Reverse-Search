# e621 Batch Reverse Search

A script that iterates over a directory and performs a reverse search for every image using remote services.

Used services: 
- [e621.net/iqdb_queries](https://e621.net/iqdb_queries)
- [saucenao.com](https://saucenao.com)

Removed services: 
- [~~iqdb.harry.lu~~](http://iqdb.harry.lu) - discontinued 

## Requirements:

#### Windows

Package comes with compiled **PHP 7.2 library** (x86 Non Thread Safe) and all required extensions.

You will need **Visual C++ 2015 Redistributable (x86)** for it to run - https://www.microsoft.com/en-us/download/details.aspx?id=52685

#### Linux

Install **PHP library** (>=7.2), cURL, GD and zip extensions - `sudo apt-get install php-cli php-curl php-gd php-zip`

#### Warning about PHP version

While the script will still work with PHP >=5.6 it won't be able to work with some special characters that can be in the file names, PHP 7.2+ is recommended.

## Usage:
- Put images into 'images' folder
- Run it with 'run.bat' ('run.sh' on linux)
- Wait, this can take a very long time, depending on how many images you got there...
- Matched images will be moved to 'found' folder, not matched images will be moved to 'not found' folder
- List file 'links.html' (in 'found' folder) will be created containing all the links, open it with a web browser

## Logging in (for e621 IQDB search):

- Rename `config.cfg.example` to `config.cfg`
- Fill your login details inside it:
    - `E621_LOGIN` - your e621 username
    - `E621_API_KEY` - obtained from `e621 -> Account -> Manage API Access`

## Advanced
- You can pass any directory as an argument to the run script (on Windows you can move a directory over `run.bat`)
- Rename `config.cfg.example` to `config.cfg` to make the script use it, configure it how you want

## Contributing

See [CONTRIBUTING](https://github.com/jacklul/e621-Batch-Reverse-Search/blob/master/CONTRIBUTING.md) for more information.

## License

See [LICENSE](https://github.com/jacklul/e621-Batch-Reverse-Search/blob/master/LICENSE).
