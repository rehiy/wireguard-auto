@ECHO OFF

CD /D %~dp0

set "PATH=bin;%PATH%"

php5 bin\cli.php

IF "%1" == "" CMD /K
