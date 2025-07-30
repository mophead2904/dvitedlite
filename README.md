# ILL UPDAT3E THIS to be better sooon! :)


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

```ddev composer require mophead2904/dvitedlite```

```ddev composer speedster:init "THEME_NAME"```

```ddev restart```

```cd web/theme/custom/THEME_NAME```

```ddev npm i```

```ddev npm run build```

```ddev npm run dev```

```ddev drush cr```


-------------------------------
