# Project Description
a Simple package/version manager for release, support multiple project, and multiple user, and every user can post special project
# Install
- copy all files to your web directory, eg. '/bin',  in the ROOT of your http server. 
- require 'rwx' for web user group, eg. www-data of apache2, for the user 'www-data' require written to this directory
- support php 7.x.
- open this URL in your web browse:
```text
    http://host/to/path/version.php
```
# Debug
any bugs to liuhuisong@hotmail.com

# Configure
- the manager can generate configure automatic, the root configuration name '.__version', the content maybe :
```
user[]="alice:md5-of-password"
user[]="bob:md5-of-passwword"
...
```
- the project' configuration main name is same to package-bin, and  ext name  is '__version', the content maybe:
```
description="this is descirption for the xxx package"
user="alice,bob, ..."
```
# Update
when the client get update by URL
```
    http:/host/bin/update.php?project=PROJECT&ver=VERSION
```
- PROJECT the name of the project, every project has it's directory with this NAME
- VERSION the version of the client has. or not present if no any version.

The server responds text/plain with:
```
return=OK
url=URL
version=VERSION
md5=MD5
update=UPDATE
size=SIZE
```
if successed, or
```
return=ERROR
error=error message
```
if failed, or
```
return=NONE
```
if it was the newest already.