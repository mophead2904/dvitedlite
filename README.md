# ILL UPDAT3E THIS to be better sooon! :)

PLUG AND PLAY

![alt text](./zuckky.jpg "zuckky")

-------------------------------



### You will need ddev installed on your machine.
https://ddev.readthedocs.io/en/stable/users/install/ddev-installation/


-------------------------------



### You can run this in an existing project or create a new one with:
https://ddev.readthedocs.io/en/stable/users/quickstart/#drupal

```mkdir YOUR_PROJECT_NAME && cd YOUR_PROJECT_NAME```

```ddev config --project-type=drupal11 --docroot=web```

```ddev start```

```ddev composer create-project "drupal/recommended-project:^11"```

```ddev composer require drush/drush```

```ddev drush site:install --account-name=admin --account-pass=admin -y```

```ddev launch```

or automatically log in with

```ddev launch $(ddev drush uli)```


-------------------------------


# Starting in your drupal project root:

## Install dvitedlite
```ddev composer require mophead2904/dvitedlite```

## Initialize dvitedlite in your specified theme directory
```ddev composer dvitedlite:init "THEME_NAME"```

## Restart DDEV to apply configuration changes
```ddev restart```

## Clear Drupal cache
```ddev drush cr```

## Navigate to theme directory
```cd web/themes/custom/your_theme_name```

## Install dependencies
```ddev npm install```

## Build assets (first time)
```ddev npm run build```

## Start development server with HMR
```ddev npm run dev```

-------------------------------


## feel free to remove the package once you have initialized dvitedlite
