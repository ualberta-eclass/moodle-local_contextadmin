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
- Only settings which are fetched/set using the get_config/set_config method are supported. Any $CFG->setting style fetches will not be affected. You will have to modify system plugins still using the $CFG-> style. We have a number of plugins we've changed and are working toward sharing those changes through the github repository in the future.

## Compatible With

- 2.2.3+, may be compatible with previous but not tested.
- 2.4.0+

## Repository

https://github.com/ualberta-eclass/moodle-local_contextadmin

## Installation

### Method 1 - tarball

1. Download and untar from moodle plugin site
2. Copy local/contextadmin folder into moodleinstall/local/
3. Use your favorite patching application to apply core patches from core_patches directory
4. Standard moodle plugin install procedure.
5. Add new capabilities to any roles as needed.

### Method 2 - git integration

Use if your moodle installation is under git control.
1. Add github repository as new remote: git remote add contextadmin git://github.com/ualberta-eclass/moodle-local_contextadmin.git
2. git fetch
3. checkout your deployment branch.
4. merge from the moodle version branch matching your development branch base. eg. git merge contextadmin_22_STABLE

