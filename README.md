# Installation

To get started, run the following commands:

```ddev composer require mophead2904/dvitedlite```

After requiring the package, the intallation process will update a few files in your project.
- settings.php
- settings.local.php
- local.settings.yml
These updates automatically apply development settings to your project.
They are used only to register if we are in development mode or not.

Next run the following command:

```ddev composer dvitedlite:init "THEME_NAME"```

The dvitedlite:init "THEME_NAME" command is will initialise the setup within the selected themes directory with a default structure and base files.
The base files include:
- vite.config.js
- includes/vite.php
- [THEME_NAME].theme (will generate if file does not exist, otherwise it will be updated)



  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/mophead2904/dvitedlite"
    }
  ],



## Usage
1. cd ./web/themes/custom/[THEME_NAME]
2. pnpm install
3. pnpm build to make initial manifest file
4. pnpm dev to start development server



The file structure is as follows:

```
web/themes/custom/[THEME_NAME]
├── includes
│   └── vite.php
├── src
│   ├── css
│   ├── js
│   ├── img
│   └── ect...
├── dist
│   ├── vite
│   │   └── mainifest.json
│   ├── css
│   │   └── ect...
│   └── js
│       └── ect...
├── [THEME_NAME].library.yml
├── [THEME_NAME].info.yml
└── [THEME_NAME].theme.yml
```
