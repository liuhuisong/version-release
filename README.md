# version-release
a Simple package version manager for version release, support multiple project, and multiple user, and every user can post special project
# install
- copy version.php to your any directory of your http server
- need rwx for web user group, eg. www-data in apache2, allow www-data has written a bitmask to this directory
- support php 7.x
- open this URL in your web browse,  http://host/to/path/version.php
# debug
any bugs to liuhuisong@hotmail.com

# configure
- the manager can generate configure automatic, the root configuration name '.__version', the content maybe :
```
user[]=alice:md5-of-password
user[]=bob:md5-of-passwword
...
```
- the project' configuration main name is same to package-bin, and  ext name  is '__version', the content maybe:
```
description='this is descirption for the xxx package'
user=alice,bob, ...
```
# update
when the client get http:/host/bin/update.php, it responds text/plain with:
```
return=OK
url=URL
version=VERSION
md5=MD5
update=UPDATE
size=SIZE
```
or
```
return=ERROR
error=error message
```
or
```
return=NONE
```