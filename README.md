# e621 Batch Reverse Search

A script that iterates over a directory and performs a reverse search for every image using remote services.

Used services: 
- [e621.net/iqdb_queries](https://e621.net/iqdb_queries)
- [saucenao.com](https://saucenao.com)

Removed services: 
- [~~iqdb.harry.lu~~](http://iqdb.harry.lu) - discontinued 

## Requirements:

#### Windows

Package comes with compiled **PHP 5.6 library** (x86 Non Thread Safe) and all required extensions

You will need **Visual C++ 2012 Redistributable (x86)** for it to run - https://www.microsoft.com/en-us/download/details.aspx?id=30679

#### Linux

Install **PHP library** (>=5.6), cURL, GD and zip extensions - `sudo apt-get install php-cli php-curl php-gd php-zip`

## Usage:
- Put images into 'images' folder
- Run it with 'run.bat' ('run.sh' on linux)
- Wait, this can take a very long time, depending on how many images you got there...
- Matched images will be moved to 'found' folder, not matched images will be moved to 'not found' folder
- List file 'links.html' (in 'found' folder) will be created containing all the links, open it with a web browser

## Logging in (for e621 IQDB search):

- Rename `config.cfg.example` to `config.cfg`
- Fill your login details in `E621_LOGIN` and `E621_API_KEY` variables

## Advanced
- You can pass any directory as an argument to the run script (on Windows you can move a directory over `run.bat`)
- Rename `config.cfg.example` to `config.cfg` to make the script use it, configure it how you want

## Contributing

See [CONTRIBUTING](https://github.com/jacklul/e621-Batch-Reverse-Search/blob/master/CONTRIBUTING.md) for more information.

## License

See [LICENSE](https://github.com/jacklul/e621-Batch-Reverse-Search/blob/master/LICENSE).
