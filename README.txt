# University of Alberta Category Administration Plugin

## What does it do

This plugin adds a administrative layer at the category level. It allows non site level administrators to set Moodle site level settings at the category level.

## How does it work

There are two components to this plugin. A local module /local/contextadmin And a small number of Moodle core file changes
The following files are affected:
- /course/lib.php
- /lib/adminlib.php
- /lib/blocklib.php
- /lib/moodlelib.php
- /lib/navigationlib.php

All changes in the above files have been delineated by comments:
/*********** local_contextadmin Modification ************
code
/*********** End local_contextadmin Modification ********/

## Capabilities

This plugin adds the following 3 capabilities:
- contextadmin:editowncatsettings
    - Category administration menu access
    - Category administration menu activities/blocks tree access
    - Toggleing/Overriding/locking visibility of modules
    - Editing of any available settings for any available modules
- contextadmin:changevisibilty
    - Category administration menu access
    - Category administration menu activities/blocks tree access
    - Toggleing/Overriding/locking visibility of modules
- contextadmin:viewcategories
    - Category administration menu access

## Limitations

- Currently only blocks and activities are supported.
- Overriding/Locking is currently only supported for visibility of blocks and activities.
- Only settings which are fetched using the get_config method are supported. Any $CFG->setting style fetches will not be affected.

