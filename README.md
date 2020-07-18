# version-release
a Simple package version manager for version release, support multiple project, and multiple user, and every user can post special project
# install
- copy version.php to your any directory of your http server
- need rwx for web user group, eg. www-data in apache2
- support php 7.x
- open this URL in your web browse,  http://host/to/path/version.php

any bugs to liuhuisong@hotmail.com

# configure
- the manager can generate configure automatic, the root configure name '.__version' maybe :
```
user=alice:md5-of-password, bob:md5-of-passwword, ...
```
- the project' configure name is same to package-bin, extend name '__version',maybe:
```
description='this is descirption for the xxx package'
user=alice,bob, ...
```